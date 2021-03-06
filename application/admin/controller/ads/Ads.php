<?php

namespace app\admin\controller\ads;

use app\common\controller\Backend;

/**
 * 广告管理
 *
 * @icon fa fa-circle-o
 */
class Ads extends Backend
{
    
    /**
     * Ads模型对象
     * @var \app\admin\model\ads\Ads
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ads\Ads;

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
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            foreach ($list as $key => $value) {
                $list[$key]['cate_id'] = db('category')->where('id='.$list[$key]['cate_id'])->value('name');
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function catelist()
    {
        $keyValue = $this->request->request("keyValue");
        if($keyValue){
            $list = db('category')
            ->where('id='.$keyValue)
            ->find();
            $total = 1;
        }else{
            $total = db('category')->where('pid>0')->count();

            $pageNumber = $this->request->request('pageNumber');
            $pageSize = $this->request->request('pageSize');

            $start = ($pageNumber - 1) * $pageSize;
            $list = db('category')
            ->where('pid>0')
            ->limit($start,$pageSize)
            ->select();
        }
        
        $result = array("total" => $total, "list" => $list);
        return json($result);
    }

}
