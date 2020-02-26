<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 地址接口
 */
class Address extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = [];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    /**
     * 测试方法
     *
     * @ApiTitle    (测试名称)
     * @ApiSummary  (测试描述信息)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/test/id/{id}/name/{name})
     * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
     * @ApiParams   (name="id", type="integer", required=true, description="会员ID")
     * @ApiParams   (name="name", type="string", required=true, description="用户名")
     * @ApiParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
         'code':'1',
         'msg':'返回成功'
        })
     */

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取用户地址列表
     *
     * @param int $user_id  用户id
     */
    public function user_address()
    {
        $user_id = $this->request->request('user_id');
        if(!$user_id) {
            $this->error(__('无效的参数 : user_id'), null, -1);
        }

        $user_address = db('user_address')
        ->where('user_id='.$user_id)
        ->select();
        $data = [];
        foreach ($user_address as $key => $value) {
            if(!empty($user_address[$key]['area_id'])) {
                $mergename = db('area')->where('id='.$user_address[$key]['area_id'])->value('mergename');
                $user_address[$key]['areaName'] = implode('', explode(',', $mergename)).$user_address[$key]['address'];                
            }else{
                if(!empty($user_address[$key]['city_id'])) {
                    $mergename = db('area')->where('id='.$user_address[$key]['city_id'])->value('mergename');
                    $user_address[$key]['areaName'] = implode('', explode(',', $mergename)).$user_address[$key]['address'];
                }else{
                    if(!empty($user_address[$key]['province_id'])) {
                        $mergename = db('area')->where('id='.$user_address[$key]['province_id'])->value('mergename');
                        $user_address[$key]['areaName'] = implode('', explode(',', $mergename)).$user_address[$key]['address'];                        
                    }
                }
            }
            if(!empty($user_address[$key]['mobile'])) {
                $user_address[$key]['mobile'] = $this->yc_phone($user_address[$key]['mobile']);
            }
            $data[$key]['address_id'] = $user_address[$key]['id'];
            $data[$key]['user_id'] = $user_address[$key]['user_id'];
            $data[$key]['consignee'] = $user_address[$key]['consignee'];
            $data[$key]['mobile'] = $user_address[$key]['mobile'];
            $data[$key]['areaName'] = $user_address[$key]['areaName'];
            $data[$key]['is_default'] = $user_address[$key]['is_default'];
        }

        $this->success('请求成功', $data, 1);
    }

    /**
     * 添加或修改地址
     */
    public function save_address()
    {
        $data['user_id'] = $this->request->request("user_id");
        $data['consignee'] = $this->request->request("consignee");
        $data['mobile'] = $this->request->request("mobile");
        $data['province_id'] = $this->request->request("province_id");
        $data['city_id'] = $this->request->request("city_id");
        $data['area_id'] = $this->request->request("area_id");
        $data['address'] = $this->request->request("address");
        $data['is_default'] = $this->request->request("is_default");
        $id = $this->request->request("address_id");

        if($data['is_default'] == 1){
            db('user_address')
            ->where('user_id='.$data['user_id'])
            ->setField('is_default',0);
        }else{
            $user_address = db('user_address')->where('is_default=1 and user_id='.$data['user_id'])->find();
            if(empty($user_address)) {
                $data['is_default'] = 1;
            }
        }
        if(!empty($id)){
            $data['updatetime'] = time();
            $res = db('user_address')->where('id='.$id)->update($data);
        }else{
            $data['createtime'] = time();
            $res = db('user_address')->insert($data);
        }
        
        if(!empty($res)){
            $this->success('请求成功', null, 1);
        }else{
            $this->error('操作失败', null, -1);
        }
    }

    /**
     * 删除地址
     */
    public function del_address()
    {
        $id = $this->request->request("address_id");
        $user_id = $this->request->request("user_id");

        if(!$user_id || !$id) {
            $this->error(__('无效的参数'), null, -1);
        }
        
        $res = db('user_address')->where('id='.$id.' and user_id='.$user_id)->delete();
        
        if(!empty($res)){
            $this->success('操作成功', null, 1);
        }else{
            $this->error('操作失败', null, -1);
        }
    }

    /**
     * 获取部分城市信息
     */
    public function get_area($pid = 0)
    {
        $data = db('area')
        ->field('id,pid,name,mergename,level')
        ->where('pid='.$pid)
        ->select();
        
        $this->success('请求成功', $data, 1);
    }
    /**
     * 获取全部城市信息
     */
    public function get_area_list()
    {
        $area = db('area')
        ->field('id,pid,name,mergename,level')
        ->select();
        $data = $this->get_data($area);
        
        $this->success('请求成功', $data, 1);
    }
    /*
     * 递归遍历
     * @param $data array
     * @param $id int
     * return array
     * */
    //四级分类查询
    public function get_data($data, $id=0){
        $list = array();
        foreach($data as $v) {
            if($v['pid'] == $id) {
                $v['child'] = $this->get_data($data, $v['id']);
                if(empty($v['child'])) {
                    unset($v['child']);
                }
                array_push($list, $v);
            }
        }
        return $list;     
    }
    /* */
    //自定义函数手机号隐藏中间四位
    public function yc_phone($str){
        $str=$str;
        $resstr=substr_replace($str,'****',3,4);
        return $resstr;
    }






































}
