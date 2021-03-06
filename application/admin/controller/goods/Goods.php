<?php

namespace app\admin\controller\goods;

use app\admin\logic\GoodsLogic;
use app\admin\model\SpecItem;
use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Goods extends Backend
{
    
    /**
     * Goods模型对象
     * @var \app\admin\model\Goods
     */
    protected $model = null;

    protected $noNeedRight = ['ajaxGetSpecInput', 'getKeyNameByKey'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Goods;
        $this->view->assign("isOnSaleList", $this->model->getIsOnSaleList());
        $this->view->assign("isFreeShippingList", $this->model->getIsFreeShippingList());
        $this->view->assign("isNewList", $this->model->getIsNewList());
        $this->view->assign("isHotList", $this->model->getIsHotList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $discount1 = model('level')->find(1);
                    $discount2 = model('level')->find(2);
                    $discount3 = model('level')->find(3);
                    $discount4 = model('level')->find(4);
                    $params['price1'] = round($params['price']*$discount1['discount'],2);
                    $params['price2'] = round($params['price']*$discount2['discount'],2);
                    $params['price3'] = round($params['price']*$discount3['discount'],2);
                    $params['price4'] = round($params['price']*$discount4['discount'],2);
                    $result = $this->model->allowField(true)->save($params);
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
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
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
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $discount1 = model('level')->find(1);
                    $discount2 = model('level')->find(2);
                    $discount3 = model('level')->find(3);
                    $discount4 = model('level')->find(4);
                    $params['price1'] = round($params['price']*$discount1['discount'],2);
                    $params['price2'] = round($params['price']*$discount2['discount'],2);
                    $params['price3'] = round($params['price']*$discount3['discount'],2);
                    $params['price4'] = round($params['price']*$discount4['discount'],2);
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
     * 规格详情
     */
    public function spec($ids = NULL)
    {
        $row = $this->model->get($ids);
        if (!$row)
            $this->error(__('No Results were found'));
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds))
        {
            if (!in_array($row[$this->dataLimitField], $adminIds))
            {
                $this->error(__('You have no permission'));
            }
        }
        $specList = model('Spec')->with(['specItem'])->order('id asc')->select();

        $items_id = model('SpecGoodsPrice')->where('goods_id',$ids)->column('key');
        $items_id = implode(',',$items_id);
        $items_ids = explode(',', $items_id);

        if ($this->request->isPost())
        {
            $params = $this->request->post("row/a");
            if ($params)
            {
                try
                {
                    $items = $params['item'];
                    $discount1 = model('level')->find(1);
                    $discount2 = model('level')->find(2);
                    $discount3 = model('level')->find(3);
                    $discount4 = model('level')->find(4);
                    $storeCount = 0;
                    foreach ($items as $key => $value) {
                        if ($value['price'] <= 0) {
                            unset($items[$key]);
                        }else{
                            $storeCount += $value['store_count'];
                            $items[$key]['key'] = $key;
                            $items[$key]['key_name'] = $this->getKeyNameByKey($key);
                            $items[$key]['price1'] = round($value['price']*$discount1['discount'],2);
                            $items[$key]['price2'] = round($value['price']*$discount2['discount'],2);
                            $items[$key]['price3'] = round($value['price']*$discount3['discount'],2);
                            $items[$key]['price4'] = round($value['price']*$discount4['discount'],2);
                            if (!$value['spec_image'])
                            {
                                unset($items[$key]['spec_image']);
                            }
                        }
                    }
                    $product = model('Goods')->find($ids);
                    model('Goods')->where('id', '=', $ids)->update(['store_count' => $storeCount]);
                    $product->specGoodsPrice()->delete();
                    $result =$product->specGoodsPrice()->saveAll($items);
                    if ($result !== false)
                    {
                        $this->success();
                    }
                    else
                    {
                        $this->error($row->getError());
                    }
                }
                catch (\think\exception\PDOException $e)
                {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        $this->view->assign('items_ids',$items_ids);
        $this->view->assign('specList',$specList);
        return $this->view->fetch();
    }
    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */
    public function ajaxGetSpecInput(){
        $GoodsLogic = new GoodsLogic();
        $goods_id = input('goods_id/d') ? input('goods_id/d') : 0;
        $str = $GoodsLogic->getSpecInput($goods_id ,input('post.spec_arr/a',[[]]));
        return $str;
    }

    private function getKeyNameByKey($key)
    {
        $keyArray = explode(',',$key);
        $data = SpecItem::all($keyArray,['spec']);
        $keyName = '';
        foreach ($data as $v) {
//            $keyName .= $v['spec']['item'];
            $keyName .= $v['spec']['name'];
            $keyName .= ':';
            $keyName .= $v['item'];
            $keyName .= ' ';
        }
        return $keyName;
    }

}
