<?php
namespace app\api\model;
use think\Model;
use think\Db;

class DistinguishAddress extends Model
{

    /**
     * 类的入口方法
     * 传入地址信息自动识别，并返回最高匹配结果
     * 如果地址新增，则需要删除缓存文件重新缓存
     * @param $address
     */
    public function getAddressResult($address){
        // 优先第一种方法
        $result = $this->getAddressArrar($address);
        if(!$result) return ['code'=>-1,'msg'=>'请检查并正确填写省份(市辖区)'];
        // 如果结果不理想，再模糊去匹配
        if($result['level'] != 3){
            $result_sub = $this->addressVague($address);
            // 只有全匹配对才替换，否则不做任何改变
            if($result_sub['level'] == 3){
                $result = $result_sub;
            }
        }

        $end_address = $result['province']['name'] . $result['city']['name'] . $result['district']['name'] . $result['info'];
        $address = str_replace($end_address, '', $address);
        // 联系方式-优先匹配电话
        if(preg_match('/1\d{10}/', $address, $mobiles)){ // 手机
            $result['mobile'] = $mobiles[0];
        } else if(preg_match('/(\d{3,4}-)?\d{7,8}/', $address, $mobiles)){ // 固定电话
            $result['mobile'] = $mobiles[0];
        }
        $address = str_replace($result['mobile'], '', $address);

        $name = str_replace(' ', '', $address);

        // 去掉详细里面的姓名和电话
        // $result['info'] = str_replace($result['mobile'], '', $result['info']);
        // $result['info'] = str_replace($result['name'], '', $result['info']);
        $result['name'] = $name;
        $result['address'] = $end_address;

        return $this->getCityLevelList($result);
    }

    /**
     * 获取对应城市等级列表
     */
    public function getCityLevelList($result){
        // 获取所有地址递归列表
        $regions = $this->getRegionTreeList();
        // 获取省份列表- 只有存在值才返回对应列表
        $province_id = $result['province']['id'];
        if ($province_id) {
            foreach ($regions as $region){
                unset($region['childs']);
                $result['province_list'][] = $region;
            }
        }
        // 获取城市列表- 只有存在值才返回对应列表
        $city_id = $result['city']['id'];
        if ($city_id) {
            foreach ($regions[$province_id]['childs'] as $region){
                unset($region['childs']);
                $result['city_list'][] = $region;
            }
        }
        // 获取地区列表- 只有存在值才返回对应列表

        $district_id = $result['district']['id'];
        if ($district_id) {
            foreach ($regions[$province_id]['childs'][$city_id]['childs'] as $region){
                unset($region['childs']);
                $result['district_list'][] = $region;
            }
        }

        return $result;
    }

    /**
     * 获取所有地址递归列表
     */
    public function getRegionTreeList(){
        // IO
        $file_name = 'regions.json';
        if(is_file($file_name)){
            $regions = file_get_contents($file_name);
            $regions = json_decode($regions, true);
        } else {
            // $region_sql = "select region_id,region_name,parent_id from region";
            $regions = db('area')
            ->field('id,name,pid')
            ->select();
            $regions = $this->arrayKey($regions);
            file_put_contents($file_name, json_encode($regions));
        }
        return $regions;
    }

    /**
     * 第一种方法
     * 根据地址列表递归查找准确地址
     * @param $address
     * @return array
     */
    public function getAddressArrar($address){
        // 获取所有地址递归列表
        $regions = $this->getRegionTreeList();
        // 初始化数据
        $province = $city = $district = array();

        // 先查找省份-第一级地区
        $province = $this->checkAddress($address, $regions);
        if($province){
            // 查找城市-第二级地区
            $city = $this->checkAddress($address, $province['list']);
            if($city){
                // 查找地区-第三级地区
                // 西藏自治区那曲市色尼区辽宁南路西藏公路  第三个参数因为这个地址冲突取消强制
                $district = $this->checkAddress($address, $city['list']);
            }
        }
        return $this->getAddressInfo($address, $province, $city, $district);
    }

        /**
         * 第二种方法
         * 地址模糊查找
         */
    public function addressVague($address){
        $res = preg_match_all('/\S{2}[自市区镇县乡岛州]/iu', $address,$arr);
        if(!$res) return false;

        $where = ' where ';
        foreach ($arr[0] as $value){
            if(strpos($value, '小区') === false && strpos($value, '开发区') === false){
                $where .= "name like '%{$value}' or ";
            }
        }
        $where = substr($where,0,-3);

        // $region_sql = "select region_id,region_name,parent_id,region_type from region " . $where;
        // $citys = $GLOBALS['db']->getAll($region_sql);
        $citys = db('area')
        ->where('1=1'.$where)
        ->field('id,name,pid,level')
        ->select();

        // 匹配所有地址
        $result = array();
        foreach ($citys as &$city){
            // 所有相关联的地区id
            $city_ids = array();

            if($city['region_type'] == 2) {
                $city_ids = array($city['pid'], $city['id']);

                // 尝试能不能匹配第三级
                // $region_sql = "select region_id,region_name,parent_id,region_type,left(region_name,2) as ab_name from region where parent_id='{$city['region_id']}'" ;
                // $areas = $GLOBALS['db']->getAll($region_sql);
                $areas = db('area')
                ->where("pid=".$city['id'])
                ->field('id,name,pid,level,left(name,2) as ab_name')
                ->select();
                foreach ($areas as $row){
                    if(mb_strpos($address,$row['ab_name'])){
                        $city_ids[] = $row['id'];
                    }
                }
            } else if($city['region_type'] == 3){
                // $region_sql = "select parent_id from region where region_id='{$city['parent_id']}'" ;
                // $city['province_id'] = $GLOBALS['db']->getOne($region_sql);
                $city['province_id'] = db('area')
                ->where("pid=".$city['pid'])
                ->value('pid');
                $city_ids = array($city['pid'], $city['id'], $city['province_id']);
            }

            // 查找该单词所有相关的地区记录
            // $where = " where region_id in(" . join(',', $city_ids) . ")";
            // $region_sql = "select region_id,region_name,parent_id,region_type,left(region_name,2) as ab_name from region " . $where . ' order by region_id asc';
            // $city_list = $GLOBALS['db']->getAll($region_sql);
            $city_list = db('area')
                ->where("pid in(".join(',', $city_ids).")")
                ->field('id,name,pid,level,left(name,2) as ab_name')
                ->order('id','asc')
                ->select();

            sort($city_ids);
            $key = array_pop($city_ids);
            $result[$key] = $city_list;
            sort($result);

        }

        if($result){
            list($province, $city, $area) = $result[0];
            return $this->getAddressInfo($address, $province, $city, $area);
        }

        return false;
    }

    /**
     * 匹配正确的城市地址
     * @param $address
     * @param $city_list
     * @param int $force
     * @param int $str_len
     * @return array
     */
    public function checkAddress($address, $city_list, $force=false, $str_len=2){
        $num = 0;
        $list = array();
        $result = array();

        // 遍历所有可能存在的城市
        foreach ($city_list as $city_key=>$city){
            $city_name = mb_substr($city['name'], 0, $str_len,'utf-8');

            // 判断是否存包含当前地址字符
            $city_arr = explode($city_name, $address);

            // 如果存在相关字眼，保存该地址的所有子地址
            if(count($city_arr) >= 2){

                // 必须名称长度同时达到当前比对长度
                if(strlen($city['name']) < $str_len){
                    continue;
                }

                $num ++;
                $list = $list + $city['childs'];

                $result[] =  array(
                    'id' =>  $city['id'],
                    'name' =>  $city['name'],
                    'list'  =>$list,
                );
            }
        }



        // 如果有多个存在，则加大字符匹配长度
        if($num > 1 || $force){
            $region_name1 = $result[0]['name'];
            $region_name2 = $result[1]['name'];

            if(strlen($region_name1) == strlen($region_name2) && strlen($region_name1) == $str_len){
                $region_id1 =  $result[0]['id'];
                $region_id2 =  $result[1]['id'];
                $index = $region_id1 > $region_id2 ? 1 : 0;
                $result = $result[$index];
                return $result;
            }
            return $this->checkAddress($address, $city_list, $force, $str_len+1);
        } else {
            $result[0]['list'] = $list;
            return $result[0];
        }
    }

    /**
     * 根据原地址返回详细信息
     * @param $address
     * @param $province
     * @param $city
     * @param $area
     * @return array
     */
    public function getAddressInfo($address, $province, $city, $district){
        // 查找最后出现的地址 - 截取详细信息
        $find_str = '';
        if(!isset($province['name'])) return false;
        if(!isset($city['name'])) return false;
        if(!isset($district['name'])) return false;
        if($province['name']){
            $find_str = $province['name'];
            if($city['name']){
                $find_str = $city['name'];
                if($district['name']){
                    $find_str = $district['name'];
                }
            }
        }

        // 截取详细的信息
        $find_str_len = mb_strlen($find_str,'utf-8');
        for($i=0; $i<$find_str_len-1; $i++){
            $substr = mb_substr($find_str,0,$find_str_len - $i, 'utf-8');
            $end_index = mb_strpos($address, $substr);
            if ($end_index){
                $address = mb_substr($address, $end_index + mb_strlen($substr) , mb_strlen($address) - $end_index);
            }
        }
        !empty($find_str) && $find_str = '|\S*' . $find_str;
        $area['info'] = preg_replace("/\s*|,|，|:|：{$find_str}/i", '', $address);

        $level = 0;
        if($district['name']){
            $level = 3;
        } else if($city['name']){
            $level = 2;
        } else if ($province['name']) {
            $level = 1;
        }

        return array(
            'province'  => array('id'=>$province['id'], 'name'=>$province['name']),
            'city'      =>  array('id'=>$city['id'], 'name'=>$city['name']),
            'district'      => array('id'=>$district['id'], 'name'=>$district['name']),
            'info'      => $area['info'],
            'level'     => $level,
        );
    }

    /**
     * 递归所有地址成无限分类数组
     * @param $data
     * @param int $region_id
     * @return array
     */
    public function arrayKey($data, $region_id=0){
        $result = array();
        foreach ($data as $row){
            if($region_id == $row['pid']){
                $key = $row['id'];
                $row['childs'] = $this->arrayKey($data, $row['id']);
                $result[$key] = $row;
            }
        }
        return $result;
    }
}
?>