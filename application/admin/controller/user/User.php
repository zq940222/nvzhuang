<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{
    
    /**
     * User模型对象
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
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
                ->with(['superiors','inviters'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 用户详情
     * @return mixed
     */
    public function detail($ids = null){
        $user = model('User')->find($ids);
        if(empty($user)){
            $this->error('用户不存在或已被删除');
        }
        $this->assign('user', $user);
        return $this->fetch();
    }

    /**
     * 退代理
     * @return mixed
     */
    public function refund($id = null){
        $user = model('User')->find($id);
        if(empty($user)){
            $this->error('用户不存在或已被删除');
        }
        $superior_id = 0;
        if($user['superior_id'] > 0){
            $superior_id = $user['superior_id'];
        }
        // 更换推荐人ID
        db('user')
        ->where('status="1" and inviter_id='.$id)
        ->setField('inviter_id', 0);
        // 更换上级ID
        db('user')
        ->where('status="1" and superior_id='.$id)
        ->setField('superior_id', $superior_id);
        // 删除等级关系树
        db('level_tree')
        ->where('user_id='.$id)
        ->delete();
        $level_tree = db('level_tree')->select();
        foreach ($level_tree as $key => $value) {
            if(!empty($level_tree[$key]['level_1'])){
                $level_1 = explode(',', $level_tree[$key]['level_1']);
                foreach ($level_1 as $k => $v) {
                    if($level_1[$k] == $id){
                        unset($level_1[$k]);
                    }
                }
                if(!empty($level_1)){
                    db('level_tree')
                    ->where('user_id='.$level_tree[$key]['user_id'])
                    ->setField('level_1', implode(',', $level_1));
                }
            }
            if(!empty($level_tree[$key]['level_2'])){
                $level_2 = explode(',', $level_tree[$key]['level_2']);
                foreach ($level_2 as $k => $v) {
                    if($level_2[$k] == $id){
                        unset($level_2[$k]);
                    }
                }
                if(!empty($level_2)){
                    db('level_tree')
                    ->where('user_id='.$level_tree[$key]['user_id'])
                    ->setField('level_2', implode(',', $level_2));
                }
            }
            if(!empty($level_tree[$key]['level_3'])){
                $level_3 = explode(',', $level_tree[$key]['level_3']);
                foreach ($level_3 as $k => $v) {
                    if($level_3[$k] == $id){
                        unset($level_3[$k]);
                    }
                }
                if(!empty($level_3)){
                    db('level_tree')
                    ->where('user_id='.$level_tree[$key]['user_id'])
                    ->setField('level_3', implode(',', $level_3));
                }
            }
            if(!empty($level_tree[$key]['level_4'])){
                $level_4 = explode(',', $level_tree[$key]['level_4']);
                foreach ($level_4 as $k => $v) {
                    if($level_4[$k] == $id){
                        unset($level_4[$k]);
                    }
                }
                if(!empty($level_4)){
                    db('level_tree')
                    ->where('user_id='.$level_tree[$key]['user_id'])
                    ->setField('level_4', implode(',', $level_4));
                }
            }
            
        }
        // 删除用户
        db('user')
        ->where('id='.$id)
        ->delete();
        $this->success();
    }

}
