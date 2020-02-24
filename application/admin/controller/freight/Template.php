<?php

namespace app\admin\controller\freight;

use app\admin\model\FreightTemplate;
use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Loader;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Template extends Backend
{
    
    /**
     * FreightTemplate模型对象
     * @var \app\admin\model\FreightTemplate
     */
    protected $model = null;

    protected $noNeedRight = ['area', 'getRegion'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\FreightTemplate;
        $this->view->assign("isEnableDefaultList", $this->model->getIsEnableDefaultList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    public function index()
    {
        $FreightTemplate = new FreightTemplate();
        $template_list = $FreightTemplate->with('freightConfig')->select();
        $this->assign('template_list', $template_list);
        return $this->fetch();
    }

    public function add()
    {
        return $this->fetch();
    }

    public function edit($ids = null)
    {
        if ($ids) {
            $FreightTemplate = new FreightTemplate();
            $freightTemplate = $FreightTemplate->with(['freightConfig','freightConfig.freightRegion','freightConfig.freightRegion.region'])->where(['template_id' => $ids])->find();
            if (empty($freightTemplate)) {
                $this->error('非法操作');
            }
        }else{
            $freightTemplate = [];
        }
//        dump($freightTemplate);
        $this->assign('row', $freightTemplate);
        return $this->fetch();
    }

    public function area()
    {
        $province_list = Db::name('area')->where(array('pid' => 0, 'level' => 1))->select();
        $this->assign('province_list', $province_list);
        return $this->fetch();
    }
    /**
     *  保存运费模板
     * @throws \think\Exception
     */
    public function save()
    {
        $template_id = input('template_id/d');
        $template_name = input('template_name/s');
        $is_enable_default = input('is_enable_default/d');
        $config_list = input('config_list/a', []);
        $data = input('post.');
        $freightTemplateValidate = Loader::validate('FreightTemplate');
        if (!$freightTemplateValidate->check($data)) {
            $this->error($freightTemplateValidate->getError());
        }
        if (empty($template_id)) {
            //添加模板
            $freightTemplate = new FreightTemplate();
        } else {
            //更新模板
            $freightTemplate = FreightTemplate::get(['template_id' => $template_id]);
        }
        $freightTemplate['template_name'] = $template_name;
        $freightTemplate['is_enable_default'] = $is_enable_default;
        $freightTemplate->save();
        $config_list_count = count($config_list);
        $config_id_arr = Db::name('freight_config')->where(['template_id' => $template_id])->field('config_id', true)->select();
        $update_config_id_arr = [];
        if ($config_list_count > 0) {
            for ($i = 0; $i < $config_list_count; $i++) {
                $freight_config_data = [
                    'first_unit' => $config_list[$i]['first_unit'],
                    'first_money' => $config_list[$i]['first_money'],
                    'continue_unit' => $config_list[$i]['continue_unit'],
                    'continue_money' => $config_list[$i]['continue_money'],
                    'template_id' => $freightTemplate['template_id'],
                    'is_default' => $config_list[$i]['is_default'],
                ];
                if (empty($config_list[$i]['config_id'])) {
                    //新增配送区域
                    $config_id = Db::name('freight_config')->insertGetId($freight_config_data);
                    if(!empty($config_list[$i]['area_ids'])){
                        $area_id_arr = explode(',', $config_list[$i]['area_ids']);
                        if ($config_id !== false) {
                            foreach ($area_id_arr as $areaKey => $areaVal) {
                                Db::name('freight_region')->insert(['template_id'=>$freightTemplate['template_id'],'config_id' => $config_id, 'region_id' => $areaVal]);
                            }
                        }
                    }
                } else {
                    //更新配送区域
                    array_push($update_config_id_arr, $config_list[$i]['config_id']);
                    $config_result = Db::name('freight_config')->where(['config_id' => $config_list[$i]['config_id']])->update($freight_config_data);
                    if ($config_result !== false) {
                        Db::name('freight_region')->where(['config_id' => $config_list[$i]['config_id']])->delete();
                        if(!empty($config_list[$i]['area_ids'])){
                            $area_id_arr = explode(',', $config_list[$i]['area_ids']);
                            foreach ($area_id_arr as $areaKey => $areaVal) {
                                Db::name('freight_region')->insert(['template_id'=>$freightTemplate['template_id'],'config_id' => $config_list[$i]['config_id'], 'region_id' => $areaVal]);
                            }
                        }
                    }
                }
            }
        }
        $delete_config_id_arr = array_diff($config_id_arr, $update_config_id_arr);
        if (count($delete_config_id_arr) > 0) {
            Db::name('freight_region')->where(['config_id' => ['IN', $delete_config_id_arr]])->delete();
            Db::name('freight_config')->where(['config_id' => ['IN', $delete_config_id_arr]])->delete();
        }
        $this->checkFreightTemplate($freightTemplate->template_id);
        $this->success();
    }


    /**
     * 删除
     */
    public function del($ids = "")
    {
        if ($ids) {
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }

            Db::name('goods')->where(['template_id' => $ids])->update(['template_id' => 0, 'is_free_shipping' => 1]);
            Db::name('freight_region')->where(['template_id' => $ids])->delete();
            Db::name('freight_config')->where(['template_id' => $ids])->delete();
            $delete = Db::name('freight_template')->where(['template_id' => $ids])->delete();
            if ($delete !== false) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /*
     * 获取地区
     */
    public function getRegion(){
        $parent_id = input('get.parent_id/d');
        $data = model('area')->where("pid", $parent_id)->select();
        $html = '';
        if($data){
            foreach($data as $h){
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }

    /**
     * 检查模板，如果模板下没有配送区域配置，就删除该模板
     * @param $template_id
     */
    private function checkFreightTemplate($template_id)
    {
        $freight_config = Db::name('freight_config')->where(['template_id' => $template_id])->find();
        if (empty($freight_config)) {
            Db::name('freight_template')->where('template_id', $template_id)->delete();
        }
    }
}
