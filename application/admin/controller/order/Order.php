<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{
    
    /**
     * Order模型对象
     * @var \app\admin\model\Order
     */
    protected $model = null;
    protected $noNeedRight = ['detail','shipping'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Order;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isRefundList", $this->model->getIsRefundList());
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams(null,true);
            $total = $this->model
                ->with(['users','OrderGoods'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['users','OrderGoods'])
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
                
                // $list[$key]['users'] = db('user')
                // ->where('id='.$list[$key]['user_id'])
                // ->find();
                // $order_goods = db('order_goods a')
                // ->join('spec_goods_price b','a.goods_id=b.goods_id and a.spec_key=b.key')
                // ->where('a.order_id='.$list[$key]['id'])
                // ->find();
                // if(!empty($order_goods)){
                //     if(empty($order_goods['spec_image'])){
                //         $order_goods['spec_image'] = db('goods')->where('id='.$order_goods['goods_id'])->value('cover_image');
                //     }
                // }
                
                // $list[$key]['goods'] = $order_goods;
            }
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
        $order = model('Order')->find($ids);
        if(empty($order)){
            $this->error('订单不存在或已被删除');
        }
        if(!empty($order['order_goods'])){
            $order_goods = db('order_goods')
            ->where('order_id='.$order['id'])
            ->select();
            foreach ($order_goods as $key => $value) {
                $spec_image = db('spec_goods_price')
                ->where('goods_id='.$order_goods[$key]['goods_id'].' and `key`="'.$order_goods[$key]['spec_key'].'"')
                ->value('spec_image');
                $order_goods[$key]['spec_image'] = $spec_image;
            }
        }
        $order->orderGoods = $order_goods;
        $this->assign('order', $order);
        return $this->fetch();
    }

    /**
     * 订单发货
     * @return mixed
     */

    public function shipping($ids = null)
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
                $params['status'] = 2;
                $params['shipping_time'] = time();
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
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

    /**
     * 填写备注
     * @return mixed
     */
    public function remark($ids=null)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
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
