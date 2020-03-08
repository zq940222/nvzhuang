<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Db;
use think\Exception;

/**
 * 退货管理
 *
 * @icon fa fa-circle-o
 */
class Refund extends Backend
{
    
    /**
     * RefundOrder模型对象
     * @var \app\admin\model\RefundOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\RefundOrder;
        $this->view->assign("statusList", $this->model->getStatusList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 订单详情
     * @return mixed
     */
    public function detail($ids = null){
        $order = model('RefundOrder')->find($ids);
        if(empty($order)){
            $this->error('订单不存在或已被删除');
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if($params['status'] == 3 && $order['status'] != 3){
                //返回金额给用户
                Db::startTrans();
                try{
                    $user = model('User')->find($order['user_id']);
                    $user->setInc('money',$order->order_price);
                    $order->save(['status' => 3]);
                    Db::commit();
                    $this->success();
                }catch (Exception $exception){
                    Db::rollback();
                    $this->error($exception->getMessage());
                }
            }
        }
        $this->assign('order', $order);
        return $this->fetch();
    }
}
