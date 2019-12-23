<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Config;
use app\common\library\Sms;
use fast\Random;
use think\Validate;
use think\Session;

/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'register', 'resetpwd', 'changemobile', 'apply_info', 'apply_agent', 'get_http_host','upgrade'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    public function get_http_host()
    {
        $this->success('请求成功', ['HTTP_HOST'=>$this->request->server()['HTTP_HOST']]);
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
     */
    public function apply_agent()
    {
        $data['superior_id'] = $this->request->request('superior_id');
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
        $data['pay_certificate_images'] = $this->request->request('pay_certificate_images');
        foreach ($data as $key => $value) {
            if(!$value) {
                $this->error(__('无效的参数 : '.$key), null, -1);
            }
        }
        /****验证码验证****/
        $ret = Sms::check($data['mobile'], $captcha, 'register');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        /****验证码验证end****/
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
        $extend['superior_id'] = $data['superior_id']; //上级ID
        $extend['inviter_id'] = $data['superior_id']; //推荐人ID
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
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $user_bounty = array();
            $user_bounty['user_id'] = $data['superior_id'];
            $user_bounty['sub_id'] = $data['userinfo']['id'];
            $user_bounty['sub_level'] = $data['agency_id'];
            $user_bounty['money'] = db('level')->where('id='.$data['agency_id'])->value('bonus');
            $user_bounty['createtime'] = time();
            db('user_bounty')->insert($user_bounty);

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
        $user->avatar = $avatar;
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
        $agent_upgrade = db('agent_upgrade')->where('user_id='.$data['user_id'])->find();
        if(!empty($agent_upgrade) && $agent_upgrade['status'] == 0) {
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
    /**
     * 申请成功操作
     *
     * @param string $id 申请表主键id
     */
    public function upgrade()
    {
        $id = $this->request->request('id');
        $upgrade = db('agent_upgrade')->where('id='.$id)->find();
        $user = db('user')->where('id='.$upgrade['user_id'])->find();
        $level = db('level')->where('id='.$upgrade['level'])->find();
        // 1.将货款和保证金加到用户数据里
        db('user')->where('user_id='.$upgrade['user_id'])->setInc('goods_payment', $level['goods_payment']);
        db('user')->where('user_id='.$upgrade['user_id'])->setInc('margin', $level['margin']);
        // 2.修改用户等级 并判断当前代理等级是否大于上级用户代理等级 如果大于将上级id变为原上级的上级id
        $edit_user_data['level'] = $upgrade['level'];
        if($user['superior_id'] > 0) {
            $parent_user = db('user')->where('id='.$user['superior_id'])->find();
            if($parent_user['level_id'] >= $user['level_id']) {
                $edit_user_data['superior_id'] = $parent_user['superior_id'];
            } 
        }
        
        $res = db('user')->where('user_id='.$upgrade['user_id'])->update($edit_user_data);

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
        $level = db('user')->where('id='.$user_id)->value('level_id');

        $pay_config = db('config')
        ->field('name as "key",title,value')
        ->where('`group`="pay"')
        ->select();
        $pay_info = Config::getArrayData($pay_config);

        unset($pay_info['company_address']);
        unset($pay_info['company_phone']);
        unset($pay_info['company_name']);

        $level_info = db('level')
        ->select();
        foreach ($level_info as $key => $value) {
            if($level_info[$key]['id'] == $level) {
                $now_level_info = $value;
            }
            if($level_info[$key]['id'] >= $level) {
                unset($level_info[$key]);
            }
        }
        
        $data['now_level_info'] = $now_level_info;
        $data['pay_info'] = $pay_info;
        $data['level_info'] = $level_info;

        $this->success('请求成功', $data);
    }



}
