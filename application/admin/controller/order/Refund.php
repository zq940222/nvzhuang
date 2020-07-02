<?php

namespace app\admin\controller\order;

use app\api\model\Order;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\exception\PDOException;

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
     * 查看
     */
    public function index()
    {
        //当前方法是否存在关联模型
        $this->relationSearch = true;  
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['users', 'OrderGoods'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['users', 'OrderGoods'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            foreach ($list as $key => $value) {
                $order_goods = $list[$key]['order_goods'];
                if(!empty($order_goods)){
                    foreach ($order_goods as $k => $v) {
                        if(empty($order_goods[$k]['spec_image'])){
                            $order_goods[$k]['spec_image'] = db('goods')->where('id='.$order_goods[$k]['goods_id'])->value('cover_image');
                        }
                    }
                }
                $list[$key]['order_goods'] = $order_goods;
            }
            // foreach ($list as $key => $value) {
            //     $list[$key]['goods'] = db('order_goods a')
            //     ->join('spec_goods_price b','a.goods_id=b.goods_id and a.spec_key=b.key')
            //     ->where('a.id='.$list[$key]['order_goods_id'])
            //     ->find();
            // }
            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }
        return $this->view->fetch();
    }

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
            if ($params['status'] == 1 && $order['status'] != 1){
                $order->save(['updatetime' => time()]);
            }
            if($params['status'] == 3 && $order['status'] != 3){
                //返回金额给用户
                Db::startTrans();
                try{
                    $Order = new Order;
                    $res = $Order->confirm_return($ids);
                    if($res){
                        Db::commit();
                    }
                }catch (Exception $exception){
                    Db::rollback();
                    $this->error($exception->getMessage());
                }
            }else{
                $order->save(['status' => $params['status']]);
                if($params['status'] == -1){
                    $refund_order = db('refund_order')->where('status>="0" and order_id='.$order['order_id'])->select();
                    if(empty($refund_order)){
                        db('order')->where('order')->setField('is_refund',0);
                    }
                }
            }
            $this->success();
        }else{
            $order->goods = db('order_goods a')
            ->join('spec_goods_price b','a.goods_id=b.goods_id and a.spec_key=b.key')
            ->where('a.id='.$order->order_goods_id)
            ->find();
        }
        $this->assign('order', $order);
        return $this->fetch();
    }

    /**
     * 退款
     * @param null $ids
     */
    public function agree($ids = null)
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $list = $this->model->where($pk, 'in', $ids)->select();
            $count = 0;
            Db::startTrans();
            try {

                foreach ($list as $k => $v) {
                    if ($v['refund_type'] == 2 && $v['status'] == 2){
                        $count +=1;
                        $user = model('User')->find($v['user_id']);
                        $user->setInc('money',$v->order_price);
                        $v->save(['status' => 3]);
                    }else if ($v['refund_type'] == 1 && $v['status'] == 0){
                        $count +=1;
                        $user = model('User')->find($v['user_id']);
                        $user->setInc('money',$v->order_price);
                        $v->save(['status' => 3]);
                    }
                }
                Db::commit();
                $this->success();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }
}
