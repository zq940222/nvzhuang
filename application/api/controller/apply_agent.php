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
            $data['inviter_id'] = $superior_id;
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
        $level = db('level')->where('id='.$data['agency_id'])->find();
        
        foreach ($data as $key => $value) {
            if(!$value) {
                $this->error(__('无效的参数 : '.$key), null, -1);
            }
        }
        $data['goods_payment'] = 0;
        $data['remarks'] = $this->request->request('remarks');
        $mobile = db('user')->where('status="1" and mobile="'.$data['mobile'].'"')->find();
        if(!empty($mobile)) {
            $this->error('账号已存在', null, -3);
        }
        /****验证码验证****/
        // $ret = Sms::check($data['mobile'], $captcha, 'register');
        // if (!$ret) {
        //     $this->error(__('Captcha is incorrect'));
        // }
        /****验证码验证end****/
        /*--申请一个身份证一个账号--*/
        // $user = db('user')->where('status="1" and id_card="'.$data['id_card'].'"')->find();
        // if(!empty($user)) {
        //     $this->error('该身份证号已申请过账号', null, -3);
        // }
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
                    $this->error('上级资金不足，请提醒补充', null, -4);
                }else{
                    //判断走货上级是否有充值的货款，如果有优先走充值的货款
                    if($superior_user['recharge_goods_money'] < $superior_goods_payment){
                        db('user')->where('id='.$superior_user['id'])->setDec('recharge_goods_money', $superior_user['recharge_goods_money']);
                        $data['recharge_goods_money'] = $superior_user['recharge_goods_money'];
                    }else{
                        db('user')->where('id='.$superior_user['id'])->setDec('recharge_goods_money', $superior_goods_payment);
                        $data['recharge_goods_money'] = $superior_goods_payment;
                    }
                    db('user')->where('id='.$data['superior_id'])->setDec('goods_payment', $superior_goods_payment);
                    db('user')->where('id='.$data['superior_id'])->setInc('lock_goods_money', $superior_goods_payment);
                    /*添加流水记录*/
                    $Common->ins_money_log($data['superior_id'], 2, 2, $superior_goods_payment, '货款', '预扣除代理【'.$data['name'].'】注册货款');
                }
            }
            //给推荐人发送的代理申请消息
            $message_template = db('message_template')->where('id=2')->find();
            $content1 = str_replace('nick_name', $data['name'], $message_template['message_content']);
            $content2 = str_replace('level_name', $level['name'], $content1);
            $Common->ins_message($data['inviter_id'], $message_template['message_title'], $content2);
        }
        
        $data['createtime'] = time();
        $res = db('agent_apply')->insert($data);
        if($res) {
            Db::commit();
            $this->success('提交成功');
        }else{
            Db::rollback();
            $this->success('创建数据是败', $res, -2);
        }

        
    }