<?php
namespace app\api\model;
use think\Model;

/**
 * 用户接口
 */
class User extends Model
{

    /**
     * 注册会员
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email    邮箱
     * @param string $mobile   手机号
     * @param string $code   验证码
     */
    public function register($apply_id)
    {
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
     * 申请成功操作
     *
     * @param string $id 申请表主键id
     */
    public function upgrade($id)
    {
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
     * 货款充值申请审核成功操作
     *
     * @param string $id 数据id
     */
    public function recharge_apply_success($id)
    {
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
    public function recharge_apply_error($id)
    {
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
     * 提现申请审核成功操作
     * @param string $id 数据id
     */
    public function withdraw_apply_success($id)
    {
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
    public function withdraw_apply_error($id)
    {
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



}
