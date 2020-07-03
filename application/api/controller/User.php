<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Config;
use app\common\library\Sms;
// use app\api\model\User;
use fast\Random;
use think\Validate;
use think\Session;
use think\Db;
use think\Request;

/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login','register','resetpwd','changemobile','apply_info','apply_agent','get_http_host','upgrade','pay_info','level_info','get_qrcode','withdraw_apply_success','withdraw_apply_error','recharge_apply_success','recharge_apply_error','service','get_parent_user','get_address_info'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    public function get_user_info()
    {
        $user_id = $this->request->request('user_id');
        $user = db('user')
        ->field('id,username,nickname,mobile,avatar,level_id,superior_id,inviter_id,money,goods_payment,margin,logintime,prevtime')
        ->where('id='.$user_id)
        ->find();
        $user['level_name'] = db('level')->where('id='.$user['level_id'])->value('nickname');
        if($user['superior_id'] > 0){
            $superior = db('user')->where('id='.$user['superior_id'])->find();
            $user['superior_mobile'] = $superior['mobile'].'('.$superior['real_name'].')';
        }else{
            $user['superior_mobile'] = '';
        }
        if($user['inviter_id'] > 0){
            $inviter = db('user')->where('id='.$user['inviter_id'])->find();
            $user['inviter_mobile'] = $inviter['mobile'].'('.$inviter['real_name'].')';
        }else{
            $user['inviter_mobile'] = '';
        }
        
        if(!empty($user['avatar'])) $user['avatar'] = get_http_host($user['avatar']);
        $this->success('请求成功', $user);
    }

    public function get_http_host()
    {
        $this->success('请求成功', get_http_host(''));
    }
    /**
     * 获取代理申请基本信息
     *
     * @param int $user_id  用户id
     */
    public function apply_info()
    {
        $user_id = $this->request->request('user_id');
        if(!$user_id) {
            $this->error(__('无效的参数 : user_id'), null, -1);
        }
        $pay_config = db('config')
        ->field('name as "key",title,value')
        ->where('`group`="pay"')
        ->select();
        $pay_info = Config::getArrayData($pay_config);

        $parent_info = [];
        if($user_id == -1) {
            $parent_info['nickname'] = $pay_info['company_name'];
            $parent_info['mobile'] = $pay_info['company_phone'];
            $level_info = db('level')
            ->order('id','desc')
            ->select();
        }else{
            $parent_info = db('user')
            ->field('nickname,mobile')
            ->where('id='.$user_id)
            ->find();
            $user = db('user')->where('id='.$user_id)->find();
            $level_info = db('level')
            ->where('id=0 or id>='.$user['level_id'])
            ->order('id','desc')
            ->select();
        }
        unset($pay_info['company_address']);
        unset($pay_info['company_phone']);
        unset($pay_info['company_name']);
        
        
        $data['pay_info'] = $pay_info;
        $data['parent_info'] = $parent_info;
        $data['level_info'] = $level_info;

        $this->success('请求成功', $data);
    }

    /**
     * 申请代理
     *
     * @param int $superior_id  用户id
     * @param string $agency_id  代理等级
     * @param string $name  姓名
     * @param string $mobile  手机号
     * @param string $captcha  验证码
     * @param string $password  密码
     * @param string $wx  微信账号
     * @param string $id_card  身份证号
     * @param int $pay_type  打款方式:1=支付宝,2=银行卡
     * @param string $pay_money  打款金额
     * @param string $bank_account  打款帐号
     * @param string $pay_time  打款日期
     * @param string $avatar  头像
     * @param string $pay_certificate_images  打款凭证
     * @param string $remarks  备注
     */
    public function apply_agent()
    {
        $superior_id = $this->request->request('superior_id');
        if($superior_id == -1) $superior_id = 0;
        if(!empty($superior_id)){
            $data['inviter_id'] = $superior_id;
        }
        $data['agency_id'] = $this->request->request('agency_id');
        $data['name'] = $this->request->request('name');
        $data['mobile'] = $this->request->request('mobile');
        $captcha = $this->request->request('captcha');
        $data['password'] = $this->request->request('password');
        $data['wx'] = $this->request->request('wx');
        $data['id_card'] = $this->request->request('id_card');
        $data['pay_type'] = $this->request->request('pay_type');
        $data['pay_money'] = $this->request->request('pay_money');
        $data['bank_account'] = $this->request->request('bank_account');
        $data['pay_time'] = $this->request->request('pay_time');
        $data['avatar'] = $this->request->request('avatar');
        if($data['avatar'] == 'img/upfile.png') {
            $data['avatar'] = '/uploads/avatar.png';
        }
        $data['pay_certificate_images'] = $this->request->request('pay_certificate_images');
        $level = db('level')->where('id='.$data['agency_id'])->find();
        
        if($data['agency_id'] != 5){
            foreach ($data as $key => $value) {
                if(!$value) {
                    $this->error(__('无效的参数 : '.$key), null, -1);
                }
            }
        }else{
            foreach ($data as $key => $value) {
                if(empty($value)) unset($data[$key]);
            }
        }
        
        $data['goods_payment'] = 0;
        $data['remarks'] = $this->request->request('remarks');
        $mobile = db('user')->where('status="1" and mobile="'.$data['mobile'].'"')->find();
        if(!empty($mobile)) {
            $this->error('账号已存在', null, -3);
        }
        /****验证码验证****/
        $ret = Sms::check($data['mobile'], $captcha, 'register');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        /****验证码验证end****/
        /*--申请一个身份证一个账号--*/
        $user = db('user')->where('status="1" and id_card="'.$data['id_card'].'"')->find();
        if(!empty($user)) {
            $this->error('该身份证号已申请过账号', null, -3);
        }
        Db::startTrans();
        //先判断邀请人id是否存在
        if(!empty($data['inviter_id'])){
            //判断邀请人等级和注册人等级
            $inviter_user = db('user')->where('id='.$data['inviter_id'])->find();
            //如果邀请人等级和注册人等级相等
            if($inviter_user['level_id'] == $data['agency_id']){
                //判断邀请人等级是否是一级，如果是一级，那么注册人也是1级，那么注册人的走货上级为平台，注册成功后给推荐人
                if($inviter_user['level_id'] == 1){
                    $data['superior_id'] = 0;
                }else{
                    //如果不是一级 递归去查最终走货上级
                    //递归查询最终上级ID
                    $p_user_id = $this->get_parent_user($data['inviter_id'], $data['agency_id']);
                    $data['superior_id'] = $p_user_id;
                }
            }else{
                //如果不想等，那么邀请人和走货上级为同一人
                $data['superior_id'] = $superior_id;
            }

            $Common = new Common;
            if($data['agency_id'] != 5){
                if($data['superior_id'] > 0){
                    $superior_user = db('user')->where('id='.$data['superior_id'])->find();
                    //计算上级成本价,如果注册成功从上级所获利润中拿出（注册人需交的货款额*0.1）给推荐人作为推荐奖励（加到推荐人余额里）
                    $superior_level = db('level')->where('id='.$superior_user['level_id'])->find();
                    $superior_goods_payment = ($level['goods_payment'] / $level['discount']) * $superior_level['discount'];
                    $data['goods_payment'] = $superior_goods_payment;
                    //如果货款不足，提醒充值
                    if($superior_user['goods_payment'] < $superior_goods_payment){
                        //给走货上级发送的代理申请消息
                        $message_template = db('message_template')->where('id=1')->find();
                        $content1 = str_replace('nick_name', $data['name'], $message_template['message_content']);
                        $content2 = str_replace('level_name', $level['name'], $content1);
                        $Common->ins_message($data['superior_id'], $message_template['message_title'], $content2);
                        Db::commit();
                        $this->error('上级资金不足，请提醒补充', null, -4);
                    }else{
                        //判断走货上级是否有充值的货款，如果有优先走入代理的货款
                        // 递归判断上级是否使用了充值货款
                        $uabm_arr = $this->parent_goods_payment($superior_user['id'],$superior_goods_payment,$Common);
                    }
                }
            }
            //给推荐人发送的代理申请消息
            $message_template = db('message_template')->where('id=2')->find();
            $content1 = str_replace('nick_name', $data['name'], $message_template['message_content']);
            $content2 = str_replace('level_name', $level['name'], $content1);
            $Common->ins_message($data['inviter_id'], $message_template['message_title'], $content2);
            
        }
        
        $data['createtime'] = time();
        $res = db('agent_apply')->insertGetId($data);
        if($res) {
            if(isset($uabm_arr) && !empty($uabm_arr)){
                $uabm_ids = implode(',', $uabm_arr);
                db('user_agent_back_money')
                ->where('status=0 and id in ('.$uabm_ids.')')
                ->update(['type'=>1,'agent_id'=>$res]);
            }
            if($data['agency_id'] == 5){
                db('agent_apply')->where('id='.$res)->setField('status',1);
                $model = new \app\api\model\User();
                $model->register($res);
            }
            Db::commit();
            $this->success('提交成功');
        }else{
            Db::rollback();
            $this->success('创建数据失败', $false, -2);
        }

        
    }


    /**
     * 用户升级申请
     *
     * @param string $user_id 用户id
     * @param string $pre_level 之前代理等级
     * @param string $level 升级代理等级
     * @param string $pay_type 打款方式:1=支付宝,2=银行转账
     * @param string $money 付款金额
     * @param string $bank_account 银行卡号/支付宝账号
     * @param string $pay_time 付款日期
     * @param string $remark 备注
     * @param string $pay_certificate_images 打款凭证
     */
    public function user_upgrade()
    {
        $data['user_id'] = $this->request->request('user_id');
        $data['pre_level'] = $this->request->request('pre_level');
        $data['level'] = $this->request->request('level');
        $data['pay_type'] = $this->request->request('pay_type');
        $data['money'] = $this->request->request('money');
        $data['bank_account'] = $this->request->request('bank_account');
        $data['pay_time'] = $this->request->request('pay_time');
        $data['remark'] = $this->request->request('remark');
        $data['pay_certificate_images'] = $this->request->request('pay_certificate_images');
        foreach ($data as $key => $value) {
            if(!$value) {
                if($key == 'remark') continue;
                $this->error(__('无效的参数 : '.$key), null, -1);
            }
        }
        $agent_upgrade = db('agent_upgrade')->where('status="0" and user_id='.$data['user_id'])->find();
        if(!empty($agent_upgrade)) {
            $this->error(__('暂时无法提交，有未审核申请'), null, -3);
        }

        $level = db('level')->where('id='.$data['level'])->find();
        $data['goods_payment'] = 0;
        /*--当上级ID存在时判断上级货款是否充足--*/
        $user = db('user')->where('id='.$data['user_id'])->find();
        // 原上级ID
        $data['superior_id'] = $user['superior_id'];
        // 新上级ID
        $data['new_superior_id'] = 0;
        // 当原上级ID存在时
        Db::startTrans();
        $Common = new Common;
        if($data['superior_id'] > 0){
            // 如果用户要升级的等级不是一级，判断原上级代理等级是否高于用户要升级的等级，如果相等或高于，那么用户的新走火上级为原上级的上级;如果是一级那么走货方为平台，不进行任何操作
            if($data['level'] != 1) {
                $p_user = db('user')->where('id='.$data['superior_id'])->find();
                // 判断原上级代理等级是否高于等于用户要升级的等级
                if($p_user['level_id'] >= $data['level']){
                    // 如果成立，去查原上级的上级ID
                    $data['new_superior_id'] = $this->get_parent_user($data['superior_id'], $data['level']);
                }else{
                    // 如果不高于等于用户要升级的等级，则新上级ID=原上级ID
                    $data['new_superior_id'] = $user['superior_id'];
                }
                // 如果新上级ID大于0，证明不是平台，预扣除其货款
                if($data['new_superior_id'] > 0){
                    $new_p_user = db('user')->where('id='.$data['new_superior_id'])->find();
                    $new_p_level = db('level')->where('id='.$new_p_user['level_id'])->find();
                    // 计算新上级成本价
                    $new_p_goods_payment = ($level['goods_payment'] / $level['discount']) * $new_p_level['discount'];
                    $data['goods_payment'] = $new_p_goods_payment;
                    // 判断新上级货款是否充足
                    if($new_p_user['goods_payment'] < $new_p_goods_payment){
                        if($data['superior_id'] != $data['new_superior_id']){
                            // 变更走货上级时
                            // 站内信通知：1.新上级的通知
                            $message_template = db('message_template')->where('id=6')->find();
                            $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                            $content2 = str_replace('level_name', $level['name'], $content1);
                            $Common->ins_message($data['new_superior_id'], $message_template['message_title'], $content2);
                            // 站内信通知：1.原上级的通知
                            $message_template = db('message_template')->where('id=9')->find();
                            $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                            $content2 = str_replace('level_name', $level['name'], $content1);
                            $Common->ins_message($data['superior_id'], $message_template['message_title'], $content2);
                        }else{
                            // 不变更走货上级时
                            // 站内信通知：1.原上级的通知
                            $message_template = db('message_template')->where('id=7')->find();
                            $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                            $content2 = str_replace('level_name', $level['name'], $content1);
                            $Common->ins_message($data['superior_id'], $message_template['message_title'], $content2);
                        }
                        $this->error('上级资金不足，请提醒补充', null, -4);
                    }else{
                        // 如果充足，预扣新上级货款
                        // 递归判断上级是否使用了充值货款
                        $uabm_arr = $this->parent_goods_payment($new_p_user['id'],$new_p_goods_payment,$Common);

                        // 站内信通知：1.新上级的通知
                        $message_template = db('message_template')->where('id=8')->find();
                        $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                        $content2 = str_replace('level_name', $level['name'], $content1);
                        $Common->ins_message($new_p_user['id'], $message_template['message_title'], $content2);
                        // 流水记录
                        // $Common->ins_money_log($new_p_user['id'], 2, 2, $new_p_goods_payment, '货款', '下级代理升级预扣货款');

                        // 如果新上级和原上级ID不同，给原上级发送站内信通知
                        if($data['superior_id'] != $data['new_superior_id']){
                            // 站内信通知：1.原上级的通知
                            $message_template = db('message_template')->where('id=9')->find();
                            $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                            $content2 = str_replace('level_name', $level['name'], $content1);
                            $Common->ins_message($data['superior_id'], $message_template['message_title'], $content2);
                        }
                    }
                }else{
                    // 如果新上级ID是0，那么走货上级为平台，给原上级发送代理变更通知
                    // 站内信通知：1.原上级的通知
                    $message_template = db('message_template')->where('id=9')->find();
                    $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                    $content2 = str_replace('level_name', $level['name'], $content1);
                    $Common->ins_message($data['superior_id'], $message_template['message_title'], $content2);
                }
            }else{
                // 如果升级用户将要升级为一级，给原上级通知
                // 站内信通知：1.原上级的通知
                $message_template = db('message_template')->where('id=9')->find();
                $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                $content2 = str_replace('level_name', $level['name'], $content1);
                $Common->ins_message($data['superior_id'], $message_template['message_title'], $content2);
            }
        }
        if($user['inviter_id'] > 0){
            if($user['inviter_id'] != $data['superior_id'] && $user['inviter_id'] != $data['new_superior_id']){
                // 站内信通知：1.推荐人的通知
                $message_template = db('message_template')->where('id=10')->find();
                $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                $content2 = str_replace('level_name', $level['name'], $content1);
                $Common->ins_message($user['inviter_id'], $message_template['message_title'], $content2);
            }
        }
        // 站内信：给升级用户
        $message_template = db('message_template')->where('id=18')->find();
        $content1 = str_replace('level_name', $level['name'], $message_template['message_content']);
        $Common->ins_message($data['user_id'], $message_template['message_title'], $content1);

        $data['createtime'] = time();
        $res = db('agent_upgrade')->insertGetId($data);
        if($res) {
            if(isset($uabm_arr) && !empty($uabm_arr)){
                $uabm_ids = implode(',', $uabm_arr);
                db('user_agent_back_money')
                ->where('status=0 and id in ('.$uabm_ids.')')
                ->update(['type'=>2,'agent_id'=>$res]);
            }
            Db::commit();
            $this->success('提交成功');
        }else{
            Db::rollback();
            $this->success('创建数据是败', $res, -2);
        }

    }

    /**
     * 会员登录
     *
     * @param string $account  账号
     * @param string $password 密码
     */
    public function login()
    {
        $account = $this->request->request('account');
        $password = $this->request->request('password');
        if (!$account || !$password) {
            $this->error(__('无效的参数'), null, -1);
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $userinfo = $this->auth->getUserinfo();
            if(!empty($userinfo['avatar'])) $userinfo['avatar'] = get_http_host($userinfo['avatar']);
            $user = db('user')->where('mobile="'.$account.'"')->find();
            $userinfo['level'] = $user['level_id'];
            $data = ['userinfo' => $userinfo];
            Session::set($account, $userinfo);
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注销登录
     */
    public function logout()
    {
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 重置密码
     *
     * @param string $mobile      手机号
     * @param string $newpassword 新密码
     * @param string $captcha     验证码
     */
    public function resetpwd()
    {
        $mobile = $this->request->request("mobile");
        $newpassword = $this->request->request("newpassword");
        $captcha = $this->request->request("captcha");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }

        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if (!$user) {
            $this->error(__('User not found'));
        }
        $ret = Sms::check($mobile, $captcha, 'resetpwd');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        Sms::flush($mobile, 'resetpwd');

        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 修改会员个人信息
     *
     * @param string $avatar   头像地址
     * @param string $nickname 昵称
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $nickname = $this->request->request('nickname');
        $avatar = $this->request->request('avatar', '', 'trim,strip_tags,htmlspecialchars');
        if ($nickname) {
            $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('nickname already exists'));
            }
            $user->nickname = $nickname;
        }
        if(!empty($avatar)){
            $user->avatar = $avatar;
        }
        $user->save();
        $this->success('修改成功');
    }

    /**
     * 修改手机号
     *
     * @param string $email   手机号
     * @param string $captcha 验证码
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->request('mobile');
        $captcha = $this->request->request('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 用户反馈
     *
     * @param string $user_id 用户id
     * @param string $content 反馈内容
     */
    public function user_feedback()
    {
        $user_id = $this->request->request('user_id');
        $content = $this->request->request('content');
        if (!$user_id || !$content) {
            $this->error(__('Invalid parameters'));
        }
        $data['user_id'] = $user_id;
        $data['content'] = $content;
        $data['createtime'] = time();
        $res = db('feedback')->insert($data);
        if(!empty($res)){
            $this->success('提交成功', null, 1);
        }else{
            $this->error('提交失败', null, -1);
        }
    }

    public function get_parent_user($id, $level, $p_user_id = 0)
    {
        $user = db('user')->where('id='.$id)->find();
        if($user['superior_id'] == 0){
            $p_user_id = 0;
        }else{
            $p_user = db('user')->where('id='.$user['superior_id'])->find();
            if($p_user['level_id'] >= $level){
                $p_user_id = $this->get_parent_user($p_user['id'], $level);
            }else{
                $p_user_id = $p_user['id'];
            }
        }
        return $p_user_id;
    }

    /**
     * 获取代理升级基本信息
     *
     * @param int $user_id  用户id
     */
    public function upgrade_info()
    {
        $user_id = $this->request->request('user_id');
        if(!$user_id) {
            $this->error(__('无效的参数 : user_id'), null, -1);
        }
        $user = db('user')->where('id='.$user_id)->find();
        if(empty($user)) $this->error('用户不存在', null, -1);

        $level = $user['level_id'];

        $level_info = db('level')
        ->select();
        $now_level_info = array();
        foreach ($level_info as $key => $value) {
            if($level_info[$key]['id'] == $level) {
                $now_level_info = $value;
            }
            if($level_info[$key]['id'] >= $level) {
                unset($level_info[$key]);
            }
        }
        foreach ($level_info as $key => $value) {
            $level_info[$key]['total_money'] = $level_info[$key]['total_money'] - $now_level_info['margin'];
        }
        
        $data['now_level_info'] = $now_level_info;
        $data['pay_info'] = $this->pay_info(true);
        $data['level_info'] = $level_info;

        $this->success('请求成功', $data);
    }

    /**
     * 货款充值记录
     *
     * @param int $user_id  用户id
     * @param int $status  状态:-2=全部,0=待审核,1=成功,-1=未通过
     */
    public function recharge_list()
    {
        $user_id = $this->request->request('user_id');
        $status = $this->request->request('status');
        if(!$user_id) {
            $this->error(__('无效的参数'), null, -1);
        }

        $field = 'pay_type,bank_account,money,recharge_time,createtime,status';
        $where = 'user_id='.$user_id;

        if(strlen($status) == 0){
            $status = -2;
        }
        if($status > -2) $where .= ' and status="'.$status.'"';
        // $data['goods_payment'] = db('user')->where('id='.$user_id)->value('goods_payment');
        $data = db('user_recharge')
        ->field($field)
        ->where($where)
        ->order('createtime','desc')
        ->select();

        $this->success('请求成功', $data);
    }

    /**
     * 货款充值申请
     *
     * @param string $user_id 用户id
     * @param string $pay_type 打款方式:1=支付宝,2=银行转账
     * @param string $money 付款金额
     * @param string $bank_account 银行卡号/支付宝账号
     * @param string $recharge_time 付款日期
     * @param string $remake 备注
     * @param string $pay_certificate_images 打款凭证
     */
    public function recharge_apply()
    {
        $data['user_id'] = $this->request->request('user_id');
        $data['pay_type'] = $this->request->request('pay_type');
        $data['money'] = $this->request->request('money');
        $data['bank_account'] = $this->request->request('bank_account');
        $data['recharge_time'] = $this->request->request('recharge_time');
        $data['remake'] = $this->request->request('remake');
        $data['pay_certificate_images'] = $this->request->request('pay_certificate_images');
        foreach ($data as $key => $value) {
            if(!$value) {
                if($key == 'remake') continue;
                $this->error(__('无效的参数 : '.$key), null, -1);
            }
        }
        $data['createtime'] = time();

        $message['user_id'] = $data['user_id'];
        $message['message_category'] = 1;
        $message['message_title'] = '代理充值';
        $message['message_content'] = '充值成功，等待审核';
        $message['status'] = 1;
        $message['is_read'] = 0;
        $message['createtime'] = time();
        db('message')->insert($message);
        $res = db('user_recharge')->insert($data);
        if($res) {
            $this->success('提交成功,请等待审核');
        }else{
            $this->success('创建数据是败', $res, -2);
        }
    }


    /**
     * 提现记录
     *
     * @param int $user_id  用户id
     * @param int $status  状态:-2=全部,0=待审核,1=成功,-1=未通过
     */
    public function withdraw_list()
    {
        $user_id = $this->request->request('user_id');
        $status = $this->request->request('status');
        if(!$user_id) {
            $this->error(__('无效的参数'), null, -1);
        }
        
        $field = 'bank_account,money,createtime,status';
        $where = 'user_id='.$user_id;
        if(empty($status)) $status = -2;
        if($status > -2) $where .= ' and status='.$status;

        $data = db('user_withdraw')
        ->field($field)
        ->where($where)
        ->order('createtime','desc')
        ->select();

        $this->success('请求成功', $data);
    }

    /**
     * 提现申请
     *
     * @param int $user_id 用户id
     * @param string $bank_name 银行名称
     * @param string $branch_name 支行名称
     * @param string $name 开户姓名
     * @param string $bank_account 银行卡号
     * @param string $money 提现金额
     */
    public function withdraw_apply()
    {
        $data['user_id'] = $this->request->request('user_id');
        $data['bank_name'] = $this->request->request('bank_name');
        $data['branch_name'] = $this->request->request('branch_name');
        $data['name'] = $this->request->request('name');
        $data['bank_account'] = $this->request->request('bank_account');
        $data['money'] = $this->request->request('money');
        foreach ($data as $key => $value) {
            if(!$value) {
                $this->error(__('无效的参数 : '.$key), null, -1);
            }
        }
        if($data['money'] < 100) {
            $this->error('提现金额不可小于100', null, -4);
        }
        $money = db('user')->where('id='.$data['user_id'])->value('money');
        if($money - $data['money'] < 0) {
            $this->error('余额不足', null, -3);
        }
        db('user')->where('id='.$data['user_id'])->setDec('money',$data['money']);
        db('user')->where('id='.$data['user_id'])->setInc('lock_money',$data['money']);

        $data['createtime'] = time();
        $res = db('user_withdraw')->insert($data);
        if($res) {
            $this->success('提交成功,请等待审核');
        }else{
            $this->error('创建数据是败', $res, -2);
        }
    }

    /**
     * 获取打款信息
     */
    public function pay_info($func = false)
    {
        $pay_config = db('config')
        ->field('name as "key",title,value')
        ->where('`group`="pay"')
        ->select();
        $pay_info = Config::getArrayData($pay_config);

        unset($pay_info['company_address']);
        unset($pay_info['company_phone']);
        unset($pay_info['company_name']);

        if($func){
            return $pay_info;
        }else{
            $this->success('请求成功', $pay_info);
        }
    }

    /**
     * 我的钱包
     *
     * @param int $user_id  用户id
     * @param int $money_type  消费类型：1.余额｜2.货款
     * @param int $page  页码
     * @param int $count  数据量
     */
    public function wallet()
    {
        $user_id = $this->request->request('user_id');
        if(!$user_id) {
            $this->error(__('无效的参数'), null, -1);
        }
        $money_type = $this->request->request('money_type');
        $page = $this->request->request('page');
        $count = $this->request->request('count');
        if(empty($money_type)) $money_type = 1;
        if(empty($page)) $page = 1;
        if(empty($count)) $count = 10;
        $start = ($page-1)*$count;

        $user = db('user')->where('id='.$user_id)->find();
        //余额
        $money_info['money'] = $user['money'];
        //货款
        $money_info['goods_payment'] = $user['goods_payment'];
        //保证金
        $money_info['margin'] = $user['margin'];
        //奖励金
        $money_info['bounty'] = db('user_bounty')->where('user_id='.$user_id.' and status="0"')->sum('money');
        //利润
        $money_info['profit'] = db('user_money_log')
        ->where('memo="利润" and user_id='.$user_id)
        ->sum('money');
        //销售折扣（团队收益）
        /*
        1）招代理算业绩（招顶级不算，顶级下单算业绩）
        2）自己一条线的订单算业绩（自己的订单算业绩）
        */
        $money_info['team_money'] = 0;
        $team_money = 0;

        $firstday = date('Y-m-01', strtotime(date("Y-m-d")));
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));
        $firstday_time = strtotime($firstday);
        $lastday_time = strtotime($lastday);
        $where = 'createtime >='.$firstday_time.' and createtime <='.$lastday_time;
        //1）招代理算业绩（招顶级不算，顶级下单算业绩）
        $team_money += db("agent_apply")
        ->where($where.' and status="1" and agency_id!=1 and superior_id='.$user_id)
        ->sum('pay_money');
        //下级升级代理算业绩
        $team_money += db("agent_upgrade")
        ->where($where.' and status="1" and level!=1 and superior_id='.$user_id)
        ->sum('money');
        //2）自己一条线的订单算业绩（自己的订单算业绩）
        $team_money += $this->get_team_money($user_id);
        $total_sales = $team_money;
        if($user['level_id'] == 1){
            $team_money = $team_money / 10000;
            $back_money = db('back_money')->select();
            foreach ($back_money as $key => $value) {
                if($team_money > $back_money[$key]['sales']) {
                    $money_info['team_money'] = $back_money[$key]['discount_money'];
                }
            }
        }
        $money_log = db('user_money_log')
        ->field('type,money,memo,desc,createtime')
        ->where('user_id='.$user_id.' and money_type='.$money_type)
        ->order(['createtime'=>'desc','id'=>'desc'])
        ->limit($start,$count)
        ->select();

        $data['money_info'] = $money_info;
        $data['money_log'] = $money_log;

        $this->success('请求成功', $data);

    }

    /**
     * 获取代理等级信息
     */
    public function level_info()
    {
        $level = db('level')
        ->field('id,nickname')
        ->select();
        $this->success('请求成功', $level);
    }

    /**
     * 代理列表
     *
     * @param int $user_id  用户id
     * @param int $type  代理类型：1=直属代理，2=分销商
     * @param int $level_id  代理等级ID
     * @param string $date  日期月份Y-m
     */
    public function agency_list()
    {
        $user_id = $this->request->request('user_id');
        $type = $this->request->request('type');
        $level_id = $this->request->request('level_id');
        $date = $this->request->request('date');
        if(!$user_id) {
            $this->error(__('无效的参数'), null, -1);
        }
        if(empty($type)) $type = 1;
        if(empty($date)) $date = date("Y-m");

        $where1 = 'a.status="1" and a.superior_id='.$user_id;

        $where2 = 'status="1" and inviter_id='.$user_id;

        if($level_id > 0) {
            $where1 .= ' and a.level_id='.$level_id;
            $where2 .= ' and level_id='.$level_id;
        }
        
        $agency_1 = db('user a')
        ->join('level b','a.level_id=b.id')
        ->field('a.id as user_id,a.avatar,a.nickname,b.nickname as level_name,a.goods_payment')
        ->where($where1)
        ->order('a.createtime','desc')
        ->select();
        foreach ($agency_1 as $key => $value) {
            if(!empty($agency_1[$key]['avatar'])) {
                $agency_1[$key]['avatar'] = get_http_host($agency_1[$key]['avatar']);
            }
        }
        $agency_1_num = count($agency_1);

        $agency_ids = '';
        // 所有邀请的人的ID
        $inviter_id_arr = db('user')->where($where2)->column('id');
        if(!empty($inviter_id_arr)) {
            if(!empty($agency_ids)) $agency_ids .= ',';
            $agency_ids .= implode(',', $inviter_id_arr);
            foreach ($inviter_id_arr as $key => $value) {
                $childs_ids = $this->get_childs_id($value);
                if(!empty($childs_ids)){
                    if(!empty($agency_ids)) $agency_ids .= ',';
                    $agency_ids .= $childs_ids;
                }
            }
        } 

        
        //查所有下级的ID
        $superior_id_arr = db('user')->where(str_replace('a.', '', $where1))->column('id');
        if(!empty($superior_id_arr)){
            if(!empty($agency_ids)) $agency_ids .= ',';
            $agency_ids .= implode(',', $superior_id_arr);
            foreach ($superior_id_arr as $key => $value) {
                $childs_ids = $this->get_childs_id($value);
                if(!empty($childs_ids)){
                    if(!empty($agency_ids)) $agency_ids .= ',';
                    $agency_ids .= $childs_ids;
                }
            }
        }
        // dump($agency_ids);
        $agency_2 = array();
        $agency_2_where = 'a.status="1" and a.id in ('.$agency_ids.')';
        if(!empty($superior_id_arr)) {
            $agency_2_where .= ' and a.id not in ('.implode(',', $superior_id_arr).')';
        }
        $agency_2_num = 0;
        if(!empty($agency_ids)){
            $agency_2 = db('user a')
            ->join('level b','a.level_id=b.id')
            ->field('a.id as user_id,a.avatar,a.nickname,b.nickname as level_name,a.goods_payment')
            ->where($agency_2_where)
            ->order('a.createtime','desc')
            ->select();
            $agency_2_num = count($agency_2);
        }
        

        $data['agency_1_num'] = $agency_1_num;
        $data['agency_2_num'] = $agency_2_num;
        if($type == 1){
            $agency_data = $agency_1;
        }else if($type == 2){
            $agency_data = $agency_2;
        }
        $data['agency_data'] = array();

        $Order = new Order;
        // dump('当前用户:');
        $data['header'] = $Order->get_order_header($user_id, $date);
        $data['header']['bounty'] = round($data['header']['bounty'], 2);
        $data['header']['profit'] = round($data['header']['profit'], 2);
        $data['header']['team_money'] = round($data['header']['team_money'], 2);
        $data['header']['total_sales'] = round($data['header']['total_sales'], 2);
        if(!empty($agency_data)){
            $firstday = date('Y-m-01', strtotime($date));
            $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));
            $firstday_time = strtotime($firstday);
            $lastday_time = strtotime($lastday);
            $where = 'createtime >='.$firstday_time.' and createtime <='.$lastday_time;
            foreach ($agency_data as $key => $value) {
                // $agency_data[$key]['team_money'] = $this->get_team_money($agency_data[$key]['user_id']);
                // dump('直属下级:');
                $agency_data[$key]['team_money'] = $Order->get_team_money($agency_data[$key]['user_id'], $where)['team_money'];
            }
            // dump($agency_data);
            if($type == 1){
                array_multisort(array_column($agency_data,'team_money'),SORT_DESC,$agency_data);
                $agency = db('user a')
                ->join('level b','a.level_id=b.id')
                ->field('a.id as user_id,a.avatar,a.nickname,b.nickname as level_name,a.goods_payment')
                ->where('a.id='.$user_id)
                ->find();
                $agency['team_money'] = $data['header']['total_sales'];
                $data['agency_data'][] = $agency;
                foreach ($agency_data as $key => $value) {
                    $data['agency_data'][] = $agency_data[$key];
                }
            }else{
                $data['agency_data'] = $agency_data;
            }
        }
        

        $this->success('请求成功', $data);
    }

    public function get_childs_id($user_id, $ids = '')
    {
        $id_arr = db('user')
        ->where('status="1" and superior_id='.$user_id.' or inviter_id='.$user_id)
        ->column('id');
        if(!empty($id_arr)) {
            if(!empty($ids)){
                $ids .= ',';
            }
            $ids .= implode(',', $id_arr);
            foreach ($id_arr as $key => $value) {
                $ids = $this->get_childs_id($value, $ids);
            }
        }
        return $ids;
    }

    /**
     * 代理下级列表
     *
     * @param int $user_id  用户id
     * @param string $date  日期月份Y-m
     */
    public function agency_child_list()
    {
        $user_id = $this->request->request('user_id');
        $date = $this->request->request('date');
        if(!$user_id) {
            $this->error(__('无效的参数'), null, -1);
        }

        $user = db("user")->where('id='.$user_id)->find();
        //销售折扣（团队收益）
        /*
        1）招代理算业绩（招顶级不算，顶级下单算业绩）
        2）自己一条线的订单算业绩（自己的订单算业绩）
        */
        if(empty($date)) {
            $date = date('Y-m');
        }
        
        $data['team_money'] = 0;

        $firstday = date('Y-m-01', strtotime($date));
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));
        $firstday_time = strtotime($firstday);
        $lastday_time = strtotime($lastday);
        $where = 'createtime >='.$firstday_time.' and createtime <='.$lastday_time;

        // if($user['level_id'] == 1){
        //1）招代理算业绩（招顶级不算，顶级下单算业绩）
        $data['team_money'] += db("agent_apply")
        ->where($where.' and status="1" and agency_id!=1 and superior_id='.$user_id)
        ->sum('pay_money');
        //2）自己一条线的订单算业绩（自己的订单算业绩）
        $data['team_money'] += $this->get_team_money($user_id,' and '.$where);
        // }
        
        //销售折扣
        $data['discount'] = 0;
        if($user['level_id'] == 1){
            $team_money = $data['team_money'] / 10000;
            $back_money = db('back_money')->select();
            foreach ($back_money as $key => $value) {
                if($team_money > $back_money[$key]['sales']) {
                    $data['discount'] = $back_money[$key]['discount_money'];
                }
            }
        }

        $where1 = 'a.status="1" and a.superior_id='.$user_id;
        
        $agency_1 = db('user a')
        ->join('level b','a.level_id=b.id')
        ->field('a.id as user_id,a.avatar,a.nickname,b.nickname as level_name')
        ->where($where1)
        ->order('a.createtime','desc')
        ->select();
        foreach ($agency_1 as $key => $value) {
            if(!empty($agency_1[$key]['avatar'])) {
                $agency_1[$key]['avatar'] = get_http_host($agency_1[$key]['avatar']);
            }
        }

        $data['agency_data'] = $agency_1;

        foreach ($data['agency_data'] as $key => $value) {
            $data['agency_data'][$key]['team_money'] = $this->get_team_money($data['agency_data'][$key]['user_id'],' and '.$where);
        }
        

        $this->success('请求成功', $data);
    }

    // 自己的一条线下面所有人的业绩总和（包括自己）
    public function get_team_money($user_id, $where = '')
    {
        $team_money = 0;
        //先查自己有没有直属下级
        $users = db('user')->where('superior_id='.$user_id)->select();
        if(!empty($users)){
            foreach ($users as $key => $value) {
                $order_total_amount = db('order')->where('gm_type=2 and user_id='.$users[$key]['id'].' and status="3" '.$where)->sum('gm_money');
                $team_money += $order_total_amount;
                $child_users = db('user')->where('superior_id='.$users[$key]['id'])->select();
                if(!empty($child_users)) {
                    $team_money += $this->get_team_money($users[$key]['id'],$where);
                }
            }
        }
        //他自己的业绩
        $team_money += db('order')->where('gm_type=2 and user_id='.$user_id.' and status="3" '.$where)->sum('gm_money');
        
        return $team_money;
    }

    /**
     * 我的推广
     * @param int $user_id  用户id
     * @param int $url      用户注册申请页面链接
     */
    public function get_qrcode($user_id)
    {
        $user_id = $this->request->request('user_id');
        // $url = $this->request->request('url');
        if(!$user_id) {
            $this->error(__('无效的参数'), null, -1);
        }
        $url = 'http://'.$_SERVER['HTTP_HOST'].'/shop/Applyagent.html';

        $user = db('user')->where('id='.$user_id)->find();
        if(empty($user)){
            $this->error('用户不存在',null,-2);
        }

        $data = $url.'?user_id='.$user_id;//网址或者是文本内容

        $this->success('请求成功',$data);

        // vendor("phpqrcode.phpqrcode");//引入工具包
        // $qRcode = new \QRcode();
        // $data = $url.'?user_id='.$user_id;//网址或者是文本内容
        // // 纠错级别：L、M、Q、H
        // $level = 'L';
        // // 点的大小：1到10,用于手机端4就可以了
        // $size = 4;

        // // 生成的文件名
        // $fileName = 'user_id_'.$user_id.'.png';
        // $path = ROOT_PATH . 'public' . DS . 'uploads' . DS . 'qrcode';
        // if (!is_dir($path)) {
        //     mkdir($path, 0755, true);
        // }
        // $filepath = $path . DS . $fileName;
        
        // $qRcode->png($data, $filepath, $level, $size, 2);
        
        // if(is_file($filepath)) {
        //     $img_path = 'http://'.$_SERVER['HTTP_HOST'].'/uploads/qrcode/'.$fileName;
        //     $this->success('二维码生成成功',$img_path);
        // }else{
        //     $this->error('二维码生成失败');
        // }
    }

    public function service()
    {
        $data = db('config')->where('name="service"')->value('value');
        $this->success('请求成功',$data);
    }



    // 递归扣除一条线上每个用户的直属上级货款
    // user_id          下单用户ID
    // total_amount     用户使用的充值货款数量
    // $Common          $Common 对象
    // $array           方法返回的执行成功ID集
    public function parent_goods_payment($user_id,$total_amount,$Common,$array = [])
    {
        $user = db('user')->where('id='.$user_id)->find();
        $gm_money = 0;
        $back_money = 0;

        //判断用户货款剩余与充值货款剩余是否相等
        if($user['goods_payment'] == $user['recharge_goods_money']){
            $gm_money = $total_amount;
        }else{
            if($user['goods_payment'] - $user['recharge_goods_money'] < $total_amount){
                $gm_money = $total_amount - ($user['goods_payment'] - $user['recharge_goods_money']);
            }
        }
        db('user')->where('id='.$user_id)->setDec('recharge_goods_money', $gm_money);
        db('user')->where('id='.$user_id)->setDec('goods_payment',$total_amount);
        db('user')->where('id='.$user_id)->setInc('lock_goods_money',$total_amount);
        $Common->ins_money_log($user_id, 2, 2, $total_amount, '货款', '预扣除代理货款');

        // 如果用户使用充值货款
        if($gm_money > 0){
            $user_level = db('level')->where('id='.$user['level_id'])->find();
            // 推荐人返利 = 提货用户使用的充值货款 * 提货用户的返利折扣
            $back_money = $gm_money * $user_level['rebate'];
            if($user['superior_id'] > 0){
                $p_user = db('user')->where('id='.$user['superior_id'])->find();
                $p_user_level = db('level')->where('id='.$p_user['level_id'])->find();
                // 上级成本价 == 用户所使用的充值货款 / 用户的拿货折扣 * 上级的拿货折扣
                $shipment_money = $gm_money / $user_level['discount'] * $p_user_level['discount'];
                // 判断上级货款是否充足
                if($p_user['goods_payment'] - $shipment_money < 0) {
                    // 站内信：上级
                    $message_template = db('message_template')->where('id=20')->find();
                    $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                    $content2 = str_replace('money', $shipment_money, $content1);
                    $Common->ins_message($p_user['id'], $message_template['message_title'], $content2);
                    
                    Db::rollback();
                    $this->error('上级货款不足', null, -7);
                }else{
                    // 预扣除上级用户货款，当推荐人不是走货上级时，给推荐人返利
                    
                    // $Common->ins_money_log($p_user['id'], 2, 2, $shipment_money, '货款', '下级提货预扣货款');
                    // 站内信：上级 您的代理【nickname】将要提货【money】元，货款已扣除。
                    $message_template = db('message_template')->where('id=21')->find();
                    $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                    $content2 = str_replace('money', $shipment_money, $content1);
                    $Common->ins_message($p_user['id'], $message_template['message_title'], $content2);
                    if($user['inviter_id'] != $user['superior_id']){
                        // 上级利润 = 提货金额 - 上级成本价 - 给推荐人的返利
                        $profit = $gm_money - $shipment_money - $back_money;
                        $data = array();
                        $data['user_id'] = $user_id;    //提货用户ID
                        $data['p_user_id'] = $p_user['id']; //上级ID
                        $data['inviter_id'] = $user['inviter_id'];  //推荐人ID
                        $data['money'] = $gm_money; //提货金额(为提货用户使用的充值货款)
                        $data['shipment_money'] = $shipment_money;  //上级成本价
                        $data['profit'] = $profit;  //上级利润(已扣除给原上级的返利)
                        $data['back_money'] = $back_money;  //推荐人得到的返利(当推荐人不是走货上级的时候)
                        $data['status'] = 0;
                        $data['createtime'] = time();
                        $id = db('user_agent_back_money')->insertGetId($data);
                        array_push($array, $id);
                    }else{
                        // 上级利润 = 提货金额 - 上级成本价
                        $profit = $gm_money - $shipment_money;
                        // 如果是同一人 ，不用给推荐人返利
                        // 上级利润 = 提货金额 - 上级成本价
                        $data = array();
                        $data['user_id'] = $user_id;    //提货用户ID
                        $data['p_user_id'] = $p_user['id']; //上级ID
                        // $data['inviter_id'] = $user['inviter_id'];  //推荐人ID
                        $data['money'] = $gm_money; //提货金额(为提货用户使用的充值货款)
                        $data['shipment_money'] = $shipment_money;  //上级成本价
                        $data['profit'] = $profit;  //上级利润
                        // $data['back_money'] = $back_money;  //推荐人得到的返利(当推荐人不是走货上级的时候)
                        $data['status'] = 0;
                        $data['createtime'] = time();
                        $id = db('user_agent_back_money')->insertGetId($data);
                        array_push($array, $id);
                    }
                    $array = $this->parent_goods_payment($p_user['id'],$shipment_money,$Common,$array);
                }
            }else{
                // 如果当前用户没有上级，给推荐人返利
                $data = array();
                $data['user_id'] = $user_id;    //提货用户ID
                // $data['p_user_id'] = $p_user['id']; //上级ID
                $data['inviter_id'] = $user['inviter_id'];  //推荐人ID
                $data['money'] = $gm_money; //提货金额(为提货用户使用的充值货款)
                // $data['shipment_money'] = $shipment_money;  //上级成本价
                // $data['profit'] = $profit;  //上级利润(已扣除给原上级的返利)
                $data['back_money'] = $back_money;  //推荐人得到的返利(当推荐人不是走货上级的时候)
                $data['status'] = 0;
                $data['createtime'] = time();
                $id = db('user_agent_back_money')->insertGetId($data);
                array_push($array, $id);
            }
        }
        return $array;
    }

    // 浙江省杭州市江干区笕桥街道环站北路花园新宸府3幢3单元403
    // 阿里巴巴   18812345678   浙江省杭州市西湖区古荡街道西斗门路3号天堂软件园a幢
    public function get_address_info($address)
    {
        $DistinguishAddress = new \app\api\model\DistinguishAddress;

        $res = $DistinguishAddress->getAddressResult($address);

        if(!isset($res['code'])){
            $this->success('请求成功',$res);
        }else{
            $this->error($res['msg']);
        }
        
    }



}
