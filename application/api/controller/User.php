<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Config;
use app\common\library\Sms;
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
    protected $noNeedLogin = ['login','register','resetpwd','changemobile','apply_info','apply_agent','get_http_host','upgrade','pay_info','level_info','get_qrcode','withdraw_apply_success','withdraw_apply_error','recharge_apply_success','recharge_apply_error'];
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
        }else{
            $parent_info = db('user')
            ->field('nickname,mobile')
            ->where('id='.$user_id)
            ->find();
        }
        unset($pay_info['company_address']);
        unset($pay_info['company_phone']);
        unset($pay_info['company_name']);

        $level_info = db('level')
        ->select();
        
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
        if(!empty($superior_id)){
            $data['superior_id'] = $superior_id;
        }
        $data['agency_id'] = $this->request->request('agency_id');
        $data['name'] = $this->request->request('name');
        $data['mobile'] = $this->request->request('mobile');
        // $captcha = $this->request->request('captcha');
        $data['password'] = $this->request->request('password');
        $data['wx'] = $this->request->request('wx');
        $data['id_card'] = $this->request->request('id_card');
        $data['pay_type'] = $this->request->request('pay_type');
        $data['pay_money'] = $this->request->request('pay_money');
        $data['bank_account'] = $this->request->request('bank_account');
        $data['pay_time'] = $this->request->request('pay_time');
        $data['avatar'] = $this->request->request('avatar');
        $data['pay_certificate_images'] = $this->request->request('pay_certificate_images');
        foreach ($data as $key => $value) {
            if(!$value) {
                $this->error(__('无效的参数 : '.$key), null, -1);
            }
        }
        $data['remarks'] = $this->request->request('remarks');
        /****验证码验证****/
        // $ret = Sms::check($data['mobile'], $captcha, 'register');
        // if (!$ret) {
        //     $this->error(__('Captcha is incorrect'));
        // }
        /****验证码验证end****/
        /*--申请一个身份证一个账号--*/
        $user = db('user')->where('status=1 and real_name="'.$data['name'].'" and id_card="'.$data['id_card'].'"')->find();
        if(!empty($user)) {
            $this->error('该身份证号已申请过账号', null, -3);
        }
        /*--当上级ID存在时判断上级货款是否充足--*/
        if(!empty($data['superior_id'])){
            $level = db('level')->where('id='.$data['agency_id'])->find();
            $p_user = db('user')->where('id='.$data['superior_id'])->find();
            // 如果上级ID存在 判断上级等级是否高于将要注册的等级。如果不高 去查其符合条件的上级
            $data['inviter_id'] = $data['superior_id'];
            if($p_user['level_id'] >= $data['agency_id']){
                $p_user_id = $this->get_parent_user($data['superior_id'], $data['agency_id']);
                $data['superior_id'] = $p_user_id;
            }
            if($data['superior_id'] > 0){
                $p_user = db('user')->where('id='.$data['superior_id'])->find();
                if($p_user['goods_payment'] < $level['goods_payment']){
                    $message['user_id'] = $data['superior_id'];
                    $message['message_category'] = 1;
                    $message['message_title'] = '代理招募';
                    $message['message_content'] = '您的货款资金不足，代理【'.$data['name'].'】无法招募，请及时补充！';
                    $message['status'] = 1;
                    $message['is_read'] = 0;
                    $message['createtime'] = time();
                    db('message')->insert($message);
                    $this->error('上级资金不足，请提醒补充', null, -4);
                }
                db('user')->where('id='.$data['superior_id'])->setDec('goods_payment', $level['goods_payment']);
                db('user')->where('id='.$data['superior_id'])->setInc('lock_goods_money', $level['goods_payment']);
            }
        }
        
        $data['createtime'] = time();
        $res = db('agent_apply')->insert($data);
        if($res) {
            $this->success('提交成功');
        }else{
            $this->success('创建数据是败', $res, -2);
        }

        
    }

    /**
     * 注册会员
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email    邮箱
     * @param string $mobile   手机号
     * @param string $code   验证码
     */
    public function register()
    {
        $apply_id = $this->request->request('apply_id');

        if (!$apply_id) {
            $this->error(__('无效的参数'), null, -1);
        }
        $data = db('agent_apply')->where('id='.$apply_id)->find();
        if($data['status'] != 1) {
            $this->error(__('用户未过审'), null, -2);
        }
        $username = $data['mobile'];
        $password = $data['password'];
        $mobile = $data['mobile'];
        $agency_id = $data['agency_id']; //代理等级
        $real_name = $data['name'];

        $extend['avatar'] = $data['avatar']; //头像
        //如果邀请人的等级没有用户等级高 那么将用户挂在邀请人的上级的下面
        $p_user = db('user')->where('id='.$data['superior_id'])->find();
        $extend['superior_id'] = $data['superior_id']; //上级ID
        $extend['inviter_id'] = $data['inviter_id']; //推荐人ID
        $level = db('level')->where('id='.$agency_id)->find();
        $extend['goods_payment'] = $level['goods_payment']; //货款
        $extend['margin'] = $level['margin']; //保证金
        $extend['wx'] = $data['wx']; //微信账号
        $extend['id_card'] = $data['id_card']; //身份证号
        if($data['pay_type'] == 1){
            $extend['ali_account'] = $data['bank_account'];
        }else{
            $extend['bank_account'] = $data['bank_account'];
        }

        $ret = $this->auth->register($username, $password, $mobile, $agency_id, $real_name, $extend);
        if ($ret) {
            /*--如果上级存在 将上级的货款转换到余额里面--*/
            if(!empty($p_user) && $extend['superior_id'] > 0){
                db('user')->where('id='.$extend['superior_id'])->setDec('lock_goods_money', $level['goods_payment']);
                db('user')->where('id='.$extend['superior_id'])->setInc('money', $level['goods_payment']);
                /*添加流水记录*/
                $money_log_4['user_id'] = $extend['superior_id'];
                $money_log_4['money_type'] = 2;
                $money_log_4['type'] = 2;
                $money_log_4['money'] = $level['goods_payment'];
                $money_log_4['memo'] = '货款';
                $money_log_4['createtime'] = time();
                $money_log[] = $money_log_4;

                $money_log_5['user_id'] = $extend['superior_id'];
                $money_log_5['money_type'] = 1;
                $money_log_5['type'] = 1;
                $money_log_5['money'] = $level['goods_payment'];
                $money_log_5['memo'] = '余额';
                $money_log_5['createtime'] = time();
                $money_log[] = $money_log_5;

                $message['user_id'] = $extend['superior_id'];
                $message['message_category'] = 1;
                $message['message_title'] = '代理招募';
                $message['message_content'] = '代理【'.$real_name.'】招募成功！';
                $message['status'] = 1;
                $message['is_read'] = 0;
                $message['createtime'] = time();
                db('message')->insert($message);

                //下级货款 - （下级货款/下级折扣 * 上级折扣）= 利润
                $p_level = db('level')->where('id='.$p_user['level_id'])->find();
                $profit = $level['goods_payment'] - ($level['goods_payment'] / $level['discount'] * $p_level['discount']);
                if($data['superior_id'] != $data['inviter_id'] && $data['inviter_id'] > 0){
                    $old_p_user_profit = $level['goods_payment'] * 0.1;
                    $profit -= $old_p_user_profit;
                    $money_log_7['user_id'] = $data['inviter_id'];
                    $money_log_7['money_type'] = 1;
                    $money_log_7['type'] = 1;
                    $money_log_7['money'] = $old_p_user_profit;
                    $money_log_7['memo'] = '利润';
                    $money_log_7['createtime'] = time();
                    $money_log[] = $money_log_7;

                    db('user')->where('id='.$data['superior_id'])->setDec('money', $old_p_user_profit);
                    $money_log_8['user_id'] = $data['superior_id'];
                    $money_log_8['money_type'] = 1;
                    $money_log_8['type'] = 2;
                    $money_log_8['money'] = $old_p_user_profit;
                    $money_log_8['memo'] = '推荐人利润分成';
                    $money_log_8['createtime'] = time();
                    $money_log[] = $money_log_8;

                    db('user')->where('id='.$data['inviter_id'])->setInc('money', $old_p_user_profit);
                    $money_log_9['user_id'] = $data['inviter_id'];
                    $money_log_9['money_type'] = 1;
                    $money_log_9['type'] = 1;
                    $money_log_9['money'] = $old_p_user_profit;
                    $money_log_9['memo'] = '推荐人利润分成';
                    $money_log_9['createtime'] = time();
                    $money_log[] = $money_log_9;
                }
                $money_log_6['user_id'] = $data['superior_id'];
                $money_log_6['money_type'] = 1;
                $money_log_6['type'] = 1;
                $money_log_6['money'] = $profit;
                $money_log_6['memo'] = '利润';
                $money_log_6['createtime'] = time();
                $money_log[] = $money_log_6;
            }
            /*--如果上级存在 将上级的货款转换到余额里面END--*/

            $data['userinfo'] = $this->auth->getUserinfo();
            $user_bounty = array();
            $user_bounty['user_id'] = $data['superior_id'];
            $user_bounty['sub_id'] = $data['userinfo']['id'];
            $user_bounty['sub_level'] = $data['agency_id'];
            $user_bounty['money'] = db('level')->where('id='.$data['agency_id'])->value('bonus');
            $user_bounty['createtime'] = time();
            db('user_bounty')->insert($user_bounty);
            db('user')->where('id='.$data['superior_id'])->setInc('money', $user_bounty['money']);

            /*添加流水记录*/
            $money_log_1['user_id'] = $data['userinfo']['id'];
            $money_log_1['money_type'] = 2;
            $money_log_1['type'] = 1;
            $money_log_1['money'] = $extend['goods_payment'];
            $money_log_1['memo'] = '注册货款';
            $money_log_1['createtime'] = time();
            $money_log[] = $money_log_1;

            // $money_log_2['user_id'] = $data['userinfo']['id'];
            // $money_log_2['type'] = 1;
            // $money_log_2['money'] = $extend['margin'];
            // $money_log_2['memo'] = '保证金';
            // $money_log_2['createtime'] = time();
            // $money_log[] = $money_log_2;

            $money_log_3['user_id'] = $data['superior_id'];
            $money_log_3['money_type'] = 1;
            $money_log_3['type'] = 1;
            $money_log_3['money'] = $user_bounty['money'];
            $money_log_3['memo'] = '奖励金';
            $money_log_3['createtime'] = time();
            $money_log[] = $money_log_3;

            db('user_money_log')->insertAll($money_log);
            /*添加流水记录*/

            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
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
        /*--当上级ID存在时判断上级货款是否充足--*/
        $user = db('user')->where('id='.$data['user_id'])->find();
        // 当上级ID
        if($user['superior_id'] > 0){
            $data['superior_id'] = $user['superior_id'];
            if($data['level'] != 1) {
                $data['new_superior_id'] = $user['superior_id'];

                //判断当前代理等级是否大于上级用户代理等级 如果大于将上级id变为原上级的上级id 并扣除其货款
                // 先判断上级的上级是否是0 如果不是再递归判断上级的上级的等级是否没有当前用户要升级的等级高或为0
                // 注册也是要判断
                $p_user_id = $this->get_parent_user($user['id'], $data['level']);
                if($p_user_id != 0){
                    $p_user = db('user')->where('id='.$p_user_id)->find();
                    $data['new_superior_id'] = $p_user_id;

                    $level = db('level')->where('id='.$data['level'])->find();
                    $data['goods_payment'] = $level['goods_payment'];
                    if($p_user['goods_payment'] < $level['goods_payment']){
                        $message['user_id'] = $p_user['id'];
                        $message['message_category'] = 1;
                        $message['message_title'] = '代理升级';
                        $message['message_content'] = '下级代理升级，上级变更。您的货款资金不足，代理【'.$user['nickname'].'】无法升级，请及时补充！';
                        $message['status'] = 1;
                        $message['is_read'] = 0;
                        $message['createtime'] = time();
                        db('message')->insert($message);
                        $this->error('上级资金不足，请提醒补充', null, -4);
                    }
                    db('user')->where('id='.$p_user['id'])->setDec('goods_payment', $level['goods_payment']);
                    db('user')->where('id='.$p_user['id'])->setInc('lock_goods_money', $level['goods_payment']);
                }
                
            }else{
                $data['superior_id'] = 0;
            }
        }

        $agent_upgrade = db('agent_upgrade')->where('status="0" and user_id='.$data['user_id'])->find();
        if(!empty($agent_upgrade)) {
            $this->error(__('暂时无法提交，有未审核申请'), null, -3);
        }
        $data['createtime'] = time();
        $res = db('agent_upgrade')->insert($data);
        if($res) {
            $this->success('提交成功');
        }else{
            $this->success('创建数据是败', $res, -2);
        }

    }
    public function get_parent_user($id, $level)
    {
        $user = db('user')->where('id='.$id)->find();
        if($user['superior_id'] == 0){
            return 0;
        }else{
            $p_user = db('user')->where('id='.$user['superior_id'])->find();
            if($p_user['level_id'] >= $level){
                $this->get_parent_user($p_user['id'], $level);
            }else{
                return $p_user['id'];
            }
        }
    }
    /**
     * 申请成功操作
     *
     * @param string $id 申请表主键id
     */
    public function upgrade()
    {
        $id = $this->request->request('id');
        if (!$id) {
            $this->error(__('无效的参数'), null, -1);
        }
        $upgrade = db('agent_upgrade')->where('id='.$id)->find();
        if($upgrade['status'] != 1) {
            $this->error(__('用户未过审'), null, -2);
        }
        $user = db('user')->where('id='.$upgrade['user_id'])->find();
        $level = db('level')->where('id='.$upgrade['level'])->find();
        //当用户升级时 之前等级所剩余的货款按照一定比例转换
        if($user['goods_payment'] > 0){
            db('user')->where('id='.$upgrade['user_id'])->setField('goods_payment', $user['goods_payment']*$level['discount']);
        }
        // 1.将货款和保证金加到用户数据里
        db('user')->where('id='.$upgrade['user_id'])->setInc('goods_payment', $level['goods_payment']);
        db('user')->where('id='.$upgrade['user_id'])->setInc('margin', $level['margin']);
        /*--如果上级存在 将上级的货款转换到余额里面--*/
        if($upgrade['new_superior_id'] > 0){
            db('user')->where('id='.$upgrade['new_superior_id'])->setDec('lock_goods_money', $level['goods_payment']);
            db('user')->where('id='.$upgrade['new_superior_id'])->setInc('money', $level['goods_payment']);
            /*添加流水记录*/
            $money_log_4['user_id'] = $upgrade['new_superior_id'];
            $money_log_4['money_type'] = 2;
            $money_log_4['type'] = 2;
            $money_log_4['money'] = $level['goods_payment'];
            $money_log_4['memo'] = '货款';
            $money_log_4['createtime'] = time();
            $money_log[] = $money_log_4;

            $money_log_5['user_id'] = $upgrade['new_superior_id'];
            $money_log_5['money_type'] = 1;
            $money_log_5['type'] = 1;
            $money_log_5['money'] = $level['goods_payment'];
            $money_log_5['memo'] = '余额';
            $money_log_5['createtime'] = time();
            $money_log[] = $money_log_5;

            $message[]['user_id'] = $upgrade['user_id'];
            $message[]['message_category'] = 1;
            $message[]['message_title'] = '代理升级';
            $message[]['message_content'] = '升级成功';
            $message[]['status'] = 1;
            $message[]['is_read'] = 0;
            $message[]['createtime'] = time();

            $message[]['user_id'] = $upgrade['new_superior_id'];
            $message[]['message_category'] = 1;
            $message[]['message_title'] = '代理升级';
            $message[]['message_content'] = '代理上级变更成功，代理【'.$real_name.'】已升级！';
            $message[]['status'] = 1;
            $message[]['is_read'] = 0;
            $message[]['createtime'] = time();
            db('message')->insertAll($message);

            //下级货款 - （下级货款/下级折扣 * 上级折扣）= 利润
            $p_user = db('user')->where('id='.$upgrade['new_superior_id'])->find();
            $p_level = db('level')->where('id='.$p_user['level_id'])->find();
            $profit = $level['goods_payment'] - ($level['goods_payment'] / $level['discount'] * $p_level['discount']);
            if($upgrade['superior_id'] != $upgrade['new_superior_id']){
                $old_p_user_profit = $level['goods_payment'] * 0.1;
                $profit -= $old_p_user_profit;
                $money_log_7['user_id'] = $upgrade['superior_id'];
                $money_log_7['money_type'] = 1;
                $money_log_7['type'] = 1;
                $money_log_7['money'] = $old_p_user_profit;
                $money_log_7['memo'] = '利润';
                $money_log_7['createtime'] = time();
                $money_log[] = $money_log_7;

                db('user')->where('id='.$upgrade['new_superior_id'])->setDec('money', $old_p_user_profit);
                $money_log_8['user_id'] = $upgrade['new_superior_id'];
                $money_log_8['money_type'] = 1;
                $money_log_8['type'] = 2;
                $money_log_8['money'] = $old_p_user_profit;
                $money_log_8['memo'] = '代理上级变更利润分成';
                $money_log_8['createtime'] = time();
                $money_log[] = $money_log_8;

                db('user')->where('id='.$upgrade['superior_id'])->setInc('money', $old_p_user_profit);
                $money_log_9['user_id'] = $upgrade['superior_id'];
                $money_log_9['money_type'] = 1;
                $money_log_9['type'] = 1;
                $money_log_9['money'] = $old_p_user_profit;
                $money_log_9['memo'] = '原上级利润分成';
                $money_log_9['createtime'] = time();
                $money_log[] = $money_log_9;
            }
            $money_log_6['user_id'] = $upgrade['new_superior_id'];
            $money_log_6['money_type'] = 1;
            $money_log_6['type'] = 1;
            $money_log_6['money'] = $profit;
            $money_log_6['memo'] = '利润';
            $money_log_6['createtime'] = time();
            $money_log[] = $money_log_6;
        }
        /*--如果上级存在 将上级的货款转换到余额里面END--*/
        /*添加流水记录*/
        $money_log_1['user_id'] = $upgrade['user_id'];
        $money_log_1['money_type'] = 2;
        $money_log_1['type'] = 1;
        $money_log_1['money'] = $level['goods_payment'];
        $money_log_1['memo'] = '货款';
        $money_log_1['createtime'] = time();
        $money_log[] = $money_log_1;

        // $money_log_2['user_id'] = $upgrade['user_id'];
        // $money_log_2['type'] = 1;
        // $money_log_2['money'] = $level['margin'];
        // $money_log_2['memo'] = '保证金';
        // $money_log_2['createtime'] = time();
        // $money_log[] = $money_log_2;

        db('user_money_log')->insertAll($money_log);
        /*添加流水记录*/        

        // 2.修改用户等级 并判断当前代理等级是否大于上级用户代理等级 如果大于将上级id变为原上级的上级id
        $edit_user_data['level_id'] = $upgrade['level'];
        $edit_user_data['superior_id'] = $upgrade['new_superior_id'];
        
        $res = db('user')->where('id='.$upgrade['user_id'])->update($edit_user_data);

        if($res) {
            $this->success('修改成功');
        }else{
            $this->error('修改失败');
        }

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
        $res = db('user_recharge')->insert($data);
        if($res) {
            $this->success('提交成功,请等待审核');
        }else{
            $this->success('创建数据是败', $res, -2);
        }
    }

    /**
     * 货款充值申请审核成功操作
     *
     * @param string $id 数据id
     */
    public function recharge_apply_success()
    {
        $id = $this->request->request('id');
        if(!$id) {
            $this->error(__('无效的参数'), null, -1);
        }

        $data = db('user_recharge')
        ->where('id='.$id)
        ->find();
        if(empty($data)) {
            $this->error(__('无效的参数'), null, -1);
        }else{
            if($data['status'] != 0) {
                $this->error(__('申请已审核，请勿重复操作'), null, -4);
            }
            $res = db('user')->where('id='.$data['user_id'])->setInc('goods_payment', $data['money']);
            //添加到充值货款金额字段
            db('user')->where('id='.$data['user_id'])->setInc('recharge_goods_money', $data['money']);
            $level_id = db('user')->where('id='.$data['user_id'])->value('level_id');
            $experience = db('level')->where('id='.$level_id)->value('experience');
            if($experience > 0){
                $money_log_3['user_id'] = $data['user_id'];
                $money_log_3['money_type'] = 2;
                $money_log_3['type'] = 1;
                $money_log_3['money'] = $experience;
                $money_log_3['memo'] = '体验金';
                $money_log_3['createtime'] = time();
                $money_log[] = $money_log_3;
                db('user')->where('id='.$data['user_id'])->setInc('goods_payment', $experience);
            }
            // if($data['money'] > 9800) {
            //     $money_log_2['user_id'] = $data['user_id'];
            //     $money_log_2['type'] = 1;
            //     $money_log_2['money'] = 200;
            //     $money_log_2['memo'] = '体验金';
            //     $money_log_2['createtime'] = time();
            //     $money_log[] = $money_log_2;
            //     db('user')->where('id='.$data['user_id'])->setInc('goods_payment', 200);
            // }
            if($res) {
                db('user_recharge')->where('id='.$data['id'])->setField('status',1);
                /*添加流水记录*/
                $money_log_1['user_id'] = $data['user_id'];
                $money_log_1['money_type'] = 2;
                $money_log_1['type'] = 1;
                $money_log_1['money'] = $data['money'];
                $money_log_1['memo'] = '货款';
                $money_log_1['createtime'] = time();
                $money_log[] = $money_log_1;
                db('user_money_log')->insertAll($money_log);
                /*添加流水记录*/
                $this->success('成功',null,1);
            }else{
                $this->error('失败',null,-2);
            }
        }
    }

    /**
     * 货款充值申请审核失败操作
     *
     * @param string $id 数据id
     */
    public function recharge_apply_error()
    {
        $id = $this->request->request('id');
        if(!$id) {
            $this->error(__('无效的参数'), null, -1);
        }

        $data = db('user_recharge')
        ->where('id='.$id)
        ->find();
        if(empty($data)) {
            $this->error(__('无效的参数'), null, -1);
        }else{
            if($data['status'] != 0) {
                $this->error(__('申请已审核，请勿重复操作'), null, -4);
            }
            db('user_recharge')->where('id='.$data['id'])->setField('status',-1);
            $this->success('成功',null,1);
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
     * 提现申请审核成功操作
     * @param string $id 数据id
     */
    public function withdraw_apply_success()
    {
        $id = $this->request->request('id');
        if(!$id) {
            $this->error(__('无效的参数'), null, -1);
        }

        $data = db('user_withdraw')
        ->where('id='.$id)
        ->find();
        if(empty($data)) {
            $this->error(__('无效的参数'), null, -1);
        }else{
            if($data['status'] != 0) {
                $this->error(__('申请已审核，请勿重复操作'), null, -4);
            }
            Db::startTrans();
            $res = db('user')->where('id='.$data['user_id'])->setDec('lock_money', $data['money']);
            $lock_money = db('user')->where('id='.$data['user_id'])->value('lock_money');
            if($lock_money < 0) {
                Db::rollback();
                $this->error('数据错误',null,-3);
            }
            if($res) {
                db('user_withdraw')->where('id='.$data['id'])->setField('status',1);
                /*添加流水记录*/
                $money_log_1['user_id'] = $data['user_id'];
                $money_log_1['money_type'] = 1;
                $money_log_1['type'] = 2;
                $money_log_1['money'] = $data['money'];
                $money_log_1['memo'] = '余额提现';
                $money_log_1['createtime'] = time();
                $money_log[] = $money_log_1;
                db('user_money_log')->insertAll($money_log);
                /*添加流水记录*/
                Db::commit();
                $this->success('成功',null,1);
            }else{
                $this->error('失败',null,-2);
            }
        }
    }

    /**
     * 提现申请审核失败操作
     * @param string $id 数据id
     */
    public function withdraw_apply_error()
    {
        $id = $this->request->request('id');
        if(!$id) {
            $this->error(__('无效的参数'), null, -1);
        }

        $data = db('user_withdraw')
        ->where('id='.$id)
        ->find();
        if(empty($data)) {
            $this->error(__('无效的参数'), null, -1);
        }else{
            if($data['status'] != 0) {
                $this->error(__('申请已审核，请勿重复操作'), null, -4);
            }
            Db::startTrans();
            $res = db('user')->where('id='.$data['user_id'])->setDec('lock_money', $data['money']);
            db('user')->where('id='.$data['user_id'])->setInc('money', $data['money']);
            $lock_money = db('user')->where('id='.$data['user_id'])->value('lock_money');
            if($lock_money < 0) {
                Db::rollback();
                $this->error('数据错误',null,-3);
            }
            if($res) {
                db('user_withdraw')->where('id='.$data['id'])->setField('status',-1);
                Db::commit();
                $this->success('成功',null,1);
            }else{
                $this->error('失败',null,-2);
            }
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
        ->where($where.' and status=1 and agency_id!=1 and superior_id='.$user_id)
        ->sum('pay_money');
        //下级升级代理算业绩
        $team_money += db("agent_upgrade")
        ->where($where.' and status=1 and level!=1 and superior_id='.$user_id)
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
        ->field('type,money,memo,createtime')
        ->where('user_id='.$user_id.' and money_type='.$money_type)
        ->order('createtime','desc')
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
     */
    public function agency_list()
    {
        $user_id = $this->request->request('user_id');
        $type = $this->request->request('type');
        $level_id = $this->request->request('level_id');
        if(!$user_id) {
            $this->error(__('无效的参数'), null, -1);
        }
        if(empty($type)) $type = 1;

        $where1 = 'a.status="1" and a.superior_id='.$user_id;

        $where2 = 'a.status="1" and a.inviter_id='.$user_id.' and a.superior_id!='.$user_id;

        if($level_id > 0) {
            $where1 .= ' and level_id='.$level_id;
            $where2 .= ' and level_id='.$level_id;
        }
        
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
        $agency_1_num = count($agency_1);

        $agency_2 = db('user a')
        ->join('level b','a.level_id=b.id')
        ->field('a.id as user_id,a.avatar,a.nickname,b.nickname as level_name')
        ->where($where2)
        ->order('a.createtime','desc')
        ->select();
        $agency_2_num = count($agency_2);

        $data['agency_1_num'] = $agency_1_num;
        $data['agency_2_num'] = $agency_2_num;
        if($type == 1){
            $data['agency_data'] = $agency_1;
        }else if($type == 2){
            $data['agency_data'] = $agency_2;
        }
        foreach ($data['agency_data'] as $key => $value) {
            $data['agency_data'][$key]['team_money'] = $this->get_team_money($data['agency_data'][$key]['user_id']);
        }
        

        $this->success('请求成功', $data);
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
        ->where($where.' and status=1 and agency_id!=1 and superior_id='.$user_id)
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
            $data['agency_data'][$key]['team_money'] = $this->get_team_money($data['agency_data'][$key]['user_id'].' and '.$where);
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
                $order_total_amount = db('order')->where('gm_type=2 and user_id='.$users[$key]['id'].' and status=3'.$where)->sum('gm_money');
                $team_money += $order_total_amount;
                $child_users = db('user')->where('superior_id='.$users[$key]['id'])->select();
                if(!empty($child_users)) {
                    $team_money += $this->get_team_money($users[$key]['id'].$where);
                }
            }
        }
        $team_money += db('order')->where('gm_type=2 and user_id='.$user_id.' and status=3'.$where)->sum('gm_money');
        
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
        $url = 'http://demo02.asdeee.com/Applyagent.html';

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










}
