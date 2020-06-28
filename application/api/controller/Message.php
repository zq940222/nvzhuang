<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 消息接口
 */
class Message extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = [];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    public $message_category = 1;


    /**
     * 获取未读消息数
     *
     * @param int $user_id  用户id
     */
    public function message_num()
    {
        $user_id = $this->request->request('user_id');

        if(!$user_id) $this->error('参数:user_id不能为空', null, -1);

        $data['total_num'] = db('message')
        ->where('is_read=0 and user_id=0 and user_id='.$user_id)
        ->order('createtime','desc')
        ->count();

        $data['type_1_num'] = db('message')
        ->where('message_category=1 and is_read=0 and user_id=0 and user_id='.$user_id)
        ->order('createtime','desc')
        ->count();

        $data['type_2_num'] = db('message')
        ->where('message_category=2 and is_read=0 and user_id=0 and user_id='.$user_id)
        ->order('createtime','desc')
        ->count();

        $this->success('请求成功', $data);
    }
    /**
     * 消息列表
     *
     * @param int $user_id  用户id
     * @param int $message_category=1  消息类型:1=代理消息,2=公司消息
     */
    public function message_list()
    {
        $user_id = $this->request->request('user_id');
        $message_category = $this->request->request('message_category');

        if(!$user_id) $this->error('参数:user_id不能为空', null, -1);

        if(empty($message_category)) $message_category = $this->message_category;

        $data = db('message')
        ->where('message_category='.$message_category.' and user_id=0 and user_id='.$user_id)
        ->order('createtime','desc')
        ->select();



        $this->success('请求成功', $data);

    }

    /**
     * 消息详情
     *
     * @param int $user_id  用户id
     * @param int $id  消息ID
     */
    public function message_desc()
    {
        $user_id = $this->request->request('user_id');
        $id = $this->request->request('id');

        if(!$user_id || !$id) $this->error('参数:user_id不能为空', null, -1);

        $data = db('message')
        ->where('id',$id)
        ->find();
        if(!empty($data)) {
            db('message')->where('id',$id)->setField('is_read',1);
        }



        $this->success('请求成功', $data);

    }



























}
