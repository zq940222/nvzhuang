<?php

namespace app\admin\controller\money;

use app\api\model\User;
use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 提现申请管理
 *
 * @icon fa fa-circle-o
 */
class Userwithdraw extends Backend
{
    
    /**
     * UserWithdraw模型对象
     * @var \app\admin\model\UserWithdraw
     */
    protected $model = null;

    protected $noNeedRight = ['audit'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\UserWithdraw;
        $this->view->assign("statusList", $this->model->getStatusList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function audit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    // $result = $row->allowField(true)->save($params);
                    $model = new User();
                    if ($params['status'] == 1){
                        $res = $model->withdraw_apply_success($ids);
                        if(json_decode($res,true)['code'] == 1){
                            $result = true;
                        }else{
                            $result = false;
                        }
                    }
                    if ($params['status'] == -1){
                        $res = $model->withdraw_apply_error($ids);
                        if(json_decode($res,true)['code'] == 1){
                            $result = true;
                        }else{
                            $result = false;
                        }
                    }
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
