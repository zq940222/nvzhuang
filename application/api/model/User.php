<?php
namespace app\api\model;
use app\common\library\Auth;
use app\api\controller\Common;
use think\Model;
use think\Db;

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
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }
        $data = db('agent_apply')->where('id='.$apply_id)->find();
        if($data['status'] != 1) {
            return ['msg'=>'用户未过审','code'=>-2,'success'=>false];
        }
        $username = $data['mobile'];
        $password = $data['password'];
        $mobile = $data['mobile'];
        $agency_id = $data['agency_id']; //代理等级
        $real_name = $data['name'];

        $extend['avatar'] = $data['avatar']; //头像
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
        Db::startTrans();
        $auth = new Auth;
        $ret = $auth->register($username, $password, $mobile, $agency_id, $real_name, $extend);
        if ($ret) {
            $Common = new Common;
            $userinfo = $auth->getUserinfo();
            //判断邀请人是否存在
            if($data['inviter_id'] > 0){
                //平台给的推荐奖(只要邀请人存在，平台都会给一笔推荐奖)
                $bonus = db('level')->where('id='.$data['agency_id'])->value('bonus');
                db('user')->where('id='.$data['inviter_id'])->setInc('money', $bonus);
                //奖励金
                //奖励金入账标记
                $Common->ins_money_log($data['inviter_id'], 1, 1, $bonus, '余额', '邀请人奖励金');
                //添加奖励金记录表
                $user_bounty = array();
                $user_bounty['user_id'] = $data['inviter_id'];
                $user_bounty['sub_id'] = $userinfo['id'];
                $user_bounty['sub_level'] = $data['agency_id'];
                $user_bounty['money'] = $bonus;
                $user_bounty['createtime'] = time();
                db('user_bounty')->insert($user_bounty);
                //判断走货上级是否存在
                if($data['superior_id'] > 0){
                    //当邀请人和走货上级都存在时，先判断其是否是同一人，
                    //如果不是，先用注册人所交等级货款*返利比例算出给推荐人的返利，在用注册人所交货款-返利，为走货上级拿到的钱(走货上级成本价+利润(扣掉给推荐人的返利的钱))
                    //如果是同一人，直接扣除走货上级(也是推荐人)的锁定货款，把成本价和利润直接加到用户余额里面
                    //这里要分成三个记录 余额+(成本价)、利润+、余额+(利润的入账标记)

                    //扣掉上级锁定货款（上级成本价）
                    db('user')->where('id='.$data['superior_id'])->setDec('lock_goods_money', $data['goods_payment']);
                    //如果邀请人和走货上级是同一人
                    if($data['inviter_id'] == $data['superior_id']){
                        /*上级得到的钱*/
                        //成本价 + 利润 + 平台给的推荐将(上面已添加进推荐人账户)
                        db('user')->where('id='.$data['superior_id'])->setInc('money', $level['goods_payment']);
                        //流水记录
                        //上级成本价
                        $Common->ins_money_log($data['superior_id'], 1, 1, $data['goods_payment'], '余额', '成本价');
                        //上级的利润
                        //利润入账标记
                        $Common->ins_money_log($data['superior_id'], 1, 1, $level['goods_payment'] - $data['goods_payment'], '余额', '利润');
                        /*上级得到的钱END*/
                    }else{
                        $superior_money = $level['goods_payment'];
                        $inviter_user = db('user')->where('id='.$data['inviter_id'])->find();
                        //如果邀请人和走货上级不是同一人时，并且注册人的注册等级==邀请人的等级，推荐人还会获得一笔上级给的返利
                        if($level['id'] == $inviter_user['level_id']){
                            //推荐人的返利 = 注册人所交等级货款 * 返利比例
                            $inviter_rebate = $level['goods_payment'] * $level['rebate'];
                            //上级拿到的钱 = 注册人所交货款 - 推荐人的返利
                            $superior_money -= $inviter_rebate;
                            /*推荐人的到的钱*/
                            //推荐人的返利 + 平台给的推荐将(上面已添加进推荐人账户)
                            db('user')->where('id='.$data['inviter_id'])->setInc('money', $inviter_rebate);
                            //流水记录
                            $Common->ins_money_log($data['inviter_id'], 1, 1, $inviter_rebate, '余额', '推荐人的返利');
                            //站内信通知：1.推荐人
                            //给推荐人发送的代理申请后台审核成功消息
                            $message_template = db('message_template')->where('id=3')->find();
                            $content1 = str_replace('nick_name', $real_name, $message_template['message_content']);
                            $content2 = str_replace('level_name', $level['name'], $content1);
                            $Common->ins_message($data['inviter_id'], $message_template['message_title'], $content2);
                            /*推荐人的到的钱END*/
                            //添加奖励金记录表
                            $user_bounty = array();
                            $user_bounty['user_id'] = $data['inviter_id'];
                            $user_bounty['sub_id'] = $userinfo['id'];
                            $user_bounty['sub_level'] = $data['agency_id'];
                            $user_bounty['money'] = $inviter_rebate;
                            $user_bounty['createtime'] = time();
                            db('user_bounty')->insert($user_bounty);
                        }
                        
                        /*上级得到的钱*/
                        //成本价 + 利润
                        db('user')->where('id='.$data['superior_id'])->setInc('money', $superior_money);
                        //流水记录
                        //上级成本价
                        $Common->ins_money_log($data['superior_id'], 1, 1, $data['goods_payment'], '余额', '成本价');
                        //上级的利润
                        //利润入账标记
                        $Common->ins_money_log($data['superior_id'], 1, 1, $superior_money - $data['goods_payment'], '余额', '利润');
                        /*上级得到的钱END*/
                        
                    }
                    //站内信通知：1.给走货上级
                    $message_template = db('message_template')->where('id=3')->find();
                    $content1 = str_replace('nick_name', $real_name, $message_template['message_content']);
                    $content2 = str_replace('level_name', $level['name'], $content1);
                    $Common->ins_message($data['superior_id'], $message_template['message_title'], $content2);
                }else{
                    $inviter_user = db('user')->where('id='.$data['inviter_id'])->find();
                    if($level['id'] == $inviter_user['level_id']){
                        //如果注册人的注册等级==邀请人的等级,并且走货上级不存在，证明走货方为平台，平台将直接给推荐人返利(推荐人的返利=注册人所交等级货款*返利比例)
                        //推荐人的返利 = 注册人所交等级货款 * 返利比例
                        $inviter_rebate = $level['goods_payment'] * $level['rebate'];
                        /*推荐人的到的钱*/
                        //推荐人的返利 + 平台给的推荐将(上面已添加进推荐人账户)
                        db('user')->where('id='.$data['inviter_id'])->setInc('money', $inviter_rebate);
                        //流水记录
                        $Common->ins_money_log($data['inviter_id'], 1, 1, $inviter_rebate, '余额', '推荐人的返利');
                        /*推荐人的到的钱END*/
                        //站内信通知：1.给推荐人
                        $message_template = db('message_template')->where('id=3')->find();
                        $content1 = str_replace('nick_name', $real_name, $message_template['message_content']);
                        $content2 = str_replace('level_name', $level['name'], $content1);
                        $Common->ins_message($data['inviter_id'], $message_template['message_title'], $content2);
                        //添加奖励金记录表
                        $user_bounty = array();
                        $user_bounty['user_id'] = $data['inviter_id'];
                        $user_bounty['sub_id'] = $userinfo['id'];
                        $user_bounty['sub_level'] = $data['agency_id'];
                        $user_bounty['money'] = $inviter_rebate;
                        $user_bounty['createtime'] = time();
                        db('user_bounty')->insert($user_bounty);
                    }
                }
            }
            
            //注册成功
            //流水记录：1.注册成功货款入账
            $Common->ins_money_log($userinfo['id'], 2, 1, $level['goods_payment'], '货款', '注册成功货款入账');
            //站内信通知：1.注册人注册成功通知
            $message_template = db('message_template')->where('id=4')->find();
            $content1 = str_replace('level_name', $level['name'], $message_template['message_content']);
            $Common->ins_message($userinfo['id'], $message_template['message_title'], $content1);

            //更新代理等级树
            $this->level_tree($apply_id, 1, $userinfo['id']);
            Db::commit();
            return ['msg'=>'注册成功','code'=>1,'success'=>true];
        } else {
            Db::rollback();
            return ['msg'=>'注册失败','code'=>0,'success'=>false];
        }
    }

    /**
     * 注册会员后台审核失败
     */
    public function register_error($apply_id)
    {
        if (!$apply_id) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }
        $data = db('agent_apply')->where('id='.$apply_id)->find();
        if($data['inviter_id'] > 0){
            $Common = new Common;
            //站内信通知：1.注册人注册成功通知
            $message_template = db('message_template')->where('id=5')->find();
            $content1 = str_replace('nick_name', $data['name'], $message_template['message_content']);
            $Common->ins_message($data['inviter_id'], $message_template['message_title'], $content1);
            if($data['superior_id'] > 0){
                if($data['inviter_id'] != $data['superior_id']){
                    $Common->ins_message($data['superior_id'], $message_template['message_title'], $content1);
                }
                $Common = new Common;
                $Common->ins_money_log($data['superior_id'], 2, 1, $data['goods_payment'], '货款', '代理【'.$data['name'].'】后台审核失败，货款退还');
                if($data['recharge_goods_money'] > 0){
                    db('user')->where('id','=', $data['superior_id'])->setInc('recharge_goods_money', $data['recharge_goods_money']);
                }
                db('user')->where('id='.$data['superior_id'])->setInc('goods_payment', $data['goods_payment']);
                db('user')->where('id='.$data['superior_id'])->setDec('lock_goods_money', $data['goods_payment']);
            }
            
        }
        return ['msg'=>'操作成功','code'=>1,'success'=>true];
    }
    /**
     * 申请成功操作
     *
     * @param string $id 申请表主键id
     */
    public function upgrade($id)
    {
        if (!$id) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }
        $upgrade = db('agent_upgrade')->where('id='.$id)->find();
        if($upgrade['status'] != 1) {
            return ['msg'=>'用户未过审','code'=>-2,'success'=>false];
        }
        // 升级用户的信息
        $user = db('user')->where('id='.$upgrade['user_id'])->find();
        // 将要升级为的等级
        $level = db('level')->where('id='.$upgrade['level'])->find();
        // 升级前的等级
        $pre_level = db('level')->where('id='.$upgrade['pre_level'])->find();

        Db::startTrans();
        $Common = new Common;
        //当用户升级时 之前等级所剩余的货款按照一定比例转换
        if($user['goods_payment'] > 0){
            db('user')
            ->where('id='.$upgrade['user_id'])
            ->setField('goods_payment', $user['goods_payment'] / $pre_level['discount'] * $level['discount']);
            if($user['recharge_goods_money'] > 0){
                db('user')
                ->where('id='.$upgrade['user_id'])
                ->setField('recharge_goods_money', $user['recharge_goods_money'] / $pre_level['discount'] * $level['discount']);
            }
        }
        // 给原上级的推荐奖，当升级用户等级==邀请人等级产生
        $rebate = 0;
        // 1.将货款和保证金加到用户数据里
        db('user')->where('id='.$upgrade['user_id'])->setInc('goods_payment', $level['goods_payment']);
        db('user')->where('id='.$upgrade['user_id'])->setInc('margin', $level['margin']-$pre_level['margin']);
        // 给推荐人奖励金
        if($user['inviter_id'] > 0){
            $bonus = $level['bonus'] - $pre_level['bonus'];
            db('user')->where('id='.$user['inviter_id'])->setInc('money', $bonus);
            $Common->ins_money_log($user['inviter_id'], 1, 1, $bonus, '余额', '推荐人的奖励金');
            //添加奖励金记录表
            $user_bounty = array();
            $user_bounty['user_id'] = $user['inviter_id'];
            $user_bounty['sub_id'] = $user['id'];
            $user_bounty['sub_level'] = $upgrade['level'];
            $user_bounty['money'] = $bonus;
            $user_bounty['createtime'] = time();
            db('user_bounty')->insert($user_bounty);
            // 站内信：推荐人
            $message_template = db('message_template')->where('id=11')->find();
            $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
            $content2 = str_replace('level_name', $level['name'], $content1);
            $Common->ins_message($user['inviter_id'], $message_template['message_title'], $content2);
            
        }
        // 如果原上级存在，给原上级代理变更通知
        if($upgrade['superior_id'] != $upgrade['new_superior_id']){
            // 如果原上级存在，并且和新上级不是同一人，原上级将会获得一笔推荐奖
            if($upgrade['superior_id'] > 0){
                $superior_user = db('user')->where('id='.$upgrade['superior_id'])->find();
                if($level['id'] == $superior_user['level_id']){
                    // 推荐奖
                    // 如果新上级也存在，推荐奖由新上级出，如果不存在，推荐奖由平台出
                    $rebate = $level['goods_payment'] * $level['rebate'];
                    db('user')->where('id='.$upgrade['superior_id'])->setInc('money', $rebate);
                    $Common->ins_money_log($upgrade['superior_id'], 1, 1, $rebate, '余额', '原上级的推荐奖励金');
                    //添加奖励金记录表
                    $user_bounty = array();
                    $user_bounty['user_id'] = $upgrade['superior_id'];
                    $user_bounty['sub_id'] = $user['id'];
                    $user_bounty['sub_level'] = $upgrade['level'];
                    $user_bounty['money'] = $rebate;
                    $user_bounty['createtime'] = time();
                    db('user_bounty')->insert($user_bounty);
                    // 站内信：原上级
                    $message_template = db('message_template')->where('id=13')->find();
                    $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                    $content2 = str_replace('level_name', $level['name'], $content1);
                    $Common->ins_message($upgrade['superior_id'], $message_template['message_title'], $content2);
                }
                
            }
            $edit_user_data['superior_id'] = $upgrade['new_superior_id'];
        }
        // 如果新上级存在，那么将升级用户走货上级为新上级，新上级将会获得[成本价+利润(如果升级用户原上级存在，新上级要扣除给原上级的返利)]
        if($upgrade['new_superior_id'] > 0){
            // 新上级的到的钱：成本价+利润(如果邀请人存在，扣掉给邀请人的返利) == 用户升级的等级的货款 - 推荐人获得的返利
            db('user')->where('id='.$upgrade['new_superior_id'])->setInc('money', $level['goods_payment'] - $rebate);
            // 站内信：新上级
            $message_template = db('message_template')->where('id=12')->find();
            $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
            $content2 = str_replace('level_name', $level['name'], $content1);
            $Common->ins_message($upgrade['new_superior_id'], $message_template['message_title'], $content2);
            //上级的利润 = 升级的用户升级时需要的货款 - 上级成本价 - 推荐人的返利
            $profit = $level['goods_payment'] - $upgrade['goods_payment'] - $rebate;
            // 流水记录：新上级
            $Common->ins_money_log($upgrade['new_superior_id'], 1, 1, $upgrade['goods_payment'], '余额', '成本价');
            $Common->ins_money_log($upgrade['new_superior_id'], 1, 1, $profit, '余额', '利润');
            // 扣掉新上级锁住的货款
            db('user')->where('id='.$upgrade['new_superior_id'])->setDec('lock_goods_money', $upgrade['goods_payment']);
        }
        // 站内信：给升级用户
        $message_template = db('message_template')->where('id=19')->find();
        $content1 = str_replace('level_name', $level['name'], $message_template['message_content']);
        $Common->ins_message($upgrade['user_id'], $message_template['message_title'], $content1);

        // 2.修改用户等级 并判断当前代理等级是否大于上级用户代理等级 如果大于将上级id变为原上级的上级id
        $edit_user_data['level_id'] = $upgrade['level'];
        $res = db('user')->where('id='.$upgrade['user_id'])->update($edit_user_data);

        if($res) {
            //更新代理等级树
            $this->level_tree($id, 2, $upgrade['user_id']);
            Db::commit();
            return ['msg'=>'修改成功','code'=>1,'success'=>true];
        }else{
            Db::rollback();
            return ['msg'=>'修改失败','code'=>0,'success'=>false];
        }

    }
    /**
     * 申请失败操作
     *
     * @param string $id 申请表主键id
     */
    public function upgrade_error($id)
    {
        if (!$id) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }
        $upgrade = db('agent_upgrade')->where('id='.$id)->find();
        Db::startTrans();
        $Common = new Common;
        $user = db('user')->where('id='.$upgrade['user_id'])->find();
        $level = db('level')->where('id='.$upgrade['level'])->find();
        if ($upgrade['new_superior_id'] > 0) {
            // 站内信：新上级
            $message_template = db('message_template')->where('id=14')->find();
            $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
            $content2 = str_replace('level_name', $level['name'], $content1);
            $Common->ins_message($upgrade['new_superior_id'], $message_template['message_title'], $content2);
            // 流水记录：新上级
            $Common->ins_money_log($upgrade['new_superior_id'], 2, 1, $upgrade['goods_payment'], '货款', '预扣货款退回');
            if($upgrade['recharge_goods_money'] > 0){
                db('user')->where('id','=', $upgrade['new_superior_id'])->setInc('recharge_goods_money', $upgrade['recharge_goods_money']);
            }
            db('user')->where('id','=', $upgrade['new_superior_id'])->setInc('goods_payment', $upgrade['goods_payment']);
            db('user')->where('id','=', $upgrade['new_superior_id'])->setDec('lock_goods_money', $upgrade['goods_payment']);
        }
        // 给原上级发送站内信
        if($user['superior_id'] != $upgrade['new_superior_id'] && $user['superior_id'] > 0) {
            // 站内信：原上级
            $message_template = db('message_template')->where('id=16')->find();
            $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
            $content2 = str_replace('level_name', $level['name'], $content1);
            $Common->ins_message($user['superior_id'], $message_template['message_title'], $content2);
        }
        // 给推荐人发送站内信
        if($user['inviter_id'] > 0){
            if($user['inviter_id'] != $upgrade['superior_id'] && $user['inviter_id'] != $upgrade['new_superior_id']){
                // 站内信：推荐人
                $message_template = db('message_template')->where('id=15')->find();
                $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                $content2 = str_replace('level_name', $level['name'], $content1);
                $Common->ins_message($user['inviter_id'], $message_template['message_title'], $content2);
            }
        }
        // 站内信：给升级用户
        $message_template = db('message_template')->where('id=17')->find();
        $content1 = str_replace('level_name', $level['name'], $message_template['message_content']);
        $Common->ins_message($upgrade['user_id'], $message_template['message_title'], $content1);

        Db::commit();
        return ['msg'=>'修改成功','code'=>1,'success'=>true];
    }

    /**
     * 货款充值申请审核成功操作
     *
     * @param string $id 数据id
     */
    public function recharge_apply_success($id)
    {
        if(!$id) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }

        $data = db('user_recharge')
        ->where('id='.$id)
        ->find();
        if(empty($data)) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }else{
            if($data['status'] != 0) {
                return ['msg'=>'申请已审核，请勿重复操作','code'=>-4,'success'=>false];
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
                $message['user_id'] = $data['user_id'];
                $message['message_category'] = 1;
                $message['message_title'] = '充值成功';
                $message['message_content'] = '货款充值成功，已入账，请注意查收';
                $message['status'] = 1;
                $message['is_read'] = 0;
                $message['createtime'] = time();
                db('message')->insert($message);
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
                return ['msg'=>'成功','code'=>1,'success'=>true];
            }else{
                return ['msg'=>'失败','code'=>0,'success'=>false];
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
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }

        $data = db('user_recharge')
        ->where('id='.$id)
        ->find();
        if(empty($data)) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }else{
            if($data['status'] != 0) {
                return ['msg'=>'申请已审核，请勿重复操作','code'=>-4,'success'=>false];
            }
            $message['user_id'] = $data['user_id'];
            $message['message_category'] = 1;
            $message['message_title'] = '货款充值';
            $message['message_content'] = '货款充值审核失败，请重新提交';
            $message['status'] = 1;
            $message['is_read'] = 0;
            $message['createtime'] = time();
            db('message')->insert($message);
            // db('user_recharge')->where('id='.$data['id'])->setField('status',-1);
            return ['msg'=>'成功','code'=>1,'success'=>true];
        }
    }


    /**
     * 提现申请审核成功操作
     * @param string $id 数据id
     */
    public function withdraw_apply_success($id)
    {
        if(!$id) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }

        $data = db('user_withdraw')
        ->where('id='.$id)
        ->find();
        if(empty($data)) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }else{
            if($data['status'] != 0) {
                return ['msg'=>'申请已审核，请勿重复操作','code'=>-4,'success'=>false];
            }
            Db::startTrans();
            $res = db('user')->where('id='.$data['user_id'])->setDec('lock_money', $data['money']);
            $lock_money = db('user')->where('id='.$data['user_id'])->value('lock_money');
            if($lock_money < 0) {
                Db::rollback();
                return ['msg'=>'数据错误','code'=>-3,'success'=>false];
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
                return ['msg'=>'成功','code'=>1,'success'=>true];
            }else{
                return ['msg'=>'失败','code'=>0,'success'=>false];
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
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }

        $data = db('user_withdraw')
        ->where('id='.$id)
        ->find();
        if(empty($data)) {
            return ['msg'=>'无效的参数','code'=>-1,'success'=>false];
        }else{
            if($data['status'] != 0) {
                return ['msg'=>'申请已审核，请勿重复操作','code'=>-4,'success'=>false];
            }
            Db::startTrans();
            $res = db('user')->where('id='.$data['user_id'])->setDec('lock_money', $data['money']);
            db('user')->where('id='.$data['user_id'])->setInc('money', $data['money']);
            $lock_money = db('user')->where('id='.$data['user_id'])->value('lock_money');
            if($lock_money < 0) {
                Db::rollback();
                return ['msg'=>'数据错误','code'=>-3,'success'=>false];
            }
            if($res) {
                // db('user_withdraw')->where('id='.$data['id'])->setField('status',-1);
                Db::commit();
                return ['msg'=>'成功','code'=>1,'success'=>true];
            }else{
                return ['msg'=>'失败','code'=>0,'success'=>false];
            }
        }
    }

    /**
     * 更新代理等级树
     * @param int $id 注册或升级表的ID
     * @param int $type 1.注册｜2.升级
     * @param int $user_id 用户ID
     */
    public function level_tree($id, $type, $user_id)
    {
        if($type == 1){
            $agent_apply = db('agent_apply')->where('id='.$id)->find();
            $superior_id = $agent_apply['superior_id'];
            $level = $agent_apply['agency_id'];
            // 当上级ID大于0时，证明有走货上级，直接归到其下面
            if($superior_id > 0) {
                $superior = db('level_tree')->where('user_id='.$superior_id)->find();
                if(!empty($superior['level_'.$level])){
                    $level_child = explode(',', $superior['level_'.$level]);
                    array_push($level_child, $user_id);
                    $level_child = array_unique($level_child);
                    sort($level_child);
                    db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$level, implode(',', $level_child));
                }else{
                    db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$level, $user_id);
                }
            }else{
                // 当上级ID==0时，判断用户等级，如果不是一级那归属为平台下面，没有变动；如果为一级，判断其推荐人是否存在，如果存在，将其归为推荐人的直属一级分销商(因为注册最多只能拉平级，所以当用户等级为一级时，推荐人一定为一级)
                if($level == 1){
                    $user = db('user')->where('id='.$user_id)->find();
                    if($user['inviter_id'] > 0){
                        $inviter = db('level_tree')->where('user_id='.$user['inviter_id'])->find();
                        if(!empty($inviter['level_'.$level])){
                            $level_child = explode(',', $inviter['level_'.$level]);
                            array_push($level_child, $user_id);
                            $level_child = array_unique($level_child);
                            sort($level_child);
                            db('level_tree')->where('user_id='.$user['inviter_id'])->setField('level_'.$level, implode(',', $level_child));
                        }else{
                            db('level_tree')->where('user_id='.$user['inviter_id'])->setField('level_'.$level, $user_id);
                        }
                    }
                }
            }
            $data['user_id'] = $user_id;
            $data['level_id'] = $level;
            db('level_tree')->insert($data);
        }
        if($type == 2){
            $agent_upgrade = db('agent_upgrade')->where('id='.$id)->find();
            $new_superior_id = $agent_upgrade['new_superior_id'];
            $superior_id = $agent_upgrade['superior_id'];
            $level = $agent_upgrade['level'];
            $pre_level = $agent_upgrade['pre_level'];
            // 当新上级ID大于0时，证明有走货上级，直接归到其下面
            if($new_superior_id > 0) {
                $superior = db('level_tree')->where('user_id='.$superior_id)->find();
                // 删除原来的
                $sup_level_child = explode(',', $superior['level_'.$pre_level]);
                foreach ($sup_level_child as $key => $value) {
                    if($sup_level_child[$key] == $user_id){
                        unset($sup_level_child[$key]);
                    }
                }
                sort( array_unique( $sup_level_child ) );
                db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$pre_level, implode(',', $sup_level_child));

                $new_superior = db('level_tree')->where('user_id='.$new_superior_id)->find();
                if(!empty($new_superior['level_'.$level])){
                    $level_child = explode(',', $new_superior['level_'.$level]);
                    array_push($level_child, $user_id);
                    $level_child = array_unique($level_child);
                    sort($level_child);
                    db('level_tree')->where('user_id='.$new_superior_id)->setField('level_'.$level, implode(',', $level_child));
                }else{
                    db('level_tree')->where('user_id='.$new_superior_id)->setField('level_'.$level, $user_id);
                }
            }else{
                // 当新上级ID==0时
                // 1.如果原上级ID==0，那么用户为平台下的代理，直接修改其代理等级
                // 2.判断原上级ID > 0
                //  1）判断如果原上级为一级，那么用户归为原上级的直属一级代理
                //  2）如果原上级不是一级，
                //   a.判断如果用户等级是一级
                //      a）判断如果原上级一条线上面有一级代理，那么这个用户为这个一级代理的直属一级代理
                //      b）如果原上级一条线上面没有一级代理，那么这个人为单独一条线，不是其他一级代理的直属一级代理
                //   b.如果用户不是一级(证明用户和原上级是平级或者高于原上级)，那么这个人为单独一条线，不是其他一级代理的直属一级代理
                if($superior_id > 0){
                    $superior_user = db('user')->where('id='.$superior_id)->find();
                    if($superior_user['level_id'] == 1){
                        $superior = db('level_tree')->where('user_id='.$superior_id)->find();

                        // 删除原来的
                        $sup_level_child = explode(',', $superior['level_'.$pre_level]);
                        foreach ($sup_level_child as $key => $value) {
                            if($sup_level_child[$key] == $user_id){
                                unset($sup_level_child[$key]);
                            }
                        }
                        if(!empty($sup_level_child)){
                            $sup_level_child = array_unique( $sup_level_child );
                            sort($sup_level_child);
                            db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$pre_level, implode(',', $sup_level_child));
                        }else{
                            db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$pre_level, '');
                        }

                        if(!empty($superior['level_'.$level])){
                            $level_child = explode(',', $superior['level_'.$level]);
                            array_push($level_child, $user_id);
                            $level_child = array_unique($level_child);
                            sort($level_child);
                            db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$level, implode(',', $level_child));
                        }else{
                            db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$level, $user_id);
                        }
                    }else{
                        if($level == 1){
                            $p_user_id = $this->get_parent_level1_user($superior_id);
                            if($p_user_id > 0){
                                // 删除原来的
                                $superior_old = db('level_tree')->where('user_id='.$superior_id)->find();
                                $sup_level_child = explode(',', $superior_old['level_'.$pre_level]);
                                foreach ($sup_level_child as $key => $value) {
                                    if($sup_level_child[$key] == $user_id){
                                        unset($sup_level_child[$key]);
                                    }
                                }
                                if(!empty($sup_level_child)){
                                    $sup_level_child = array_unique( $sup_level_child );
                                    sort($sup_level_child);
                                    db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$pre_level, implode(',', $sup_level_child));
                                }else{
                                    db('level_tree')->where('user_id='.$superior_id)->setField('level_'.$pre_level, '');
                                }

                                $superior = db('level_tree')->where('user_id='.$p_user_id)->find();
                                if(!empty($superior['level_'.$level])){
                                    $level_child = explode(',', $superior['level_'.$level]);
                                    array_push($level_child, $user_id);
                                    $level_child = array_unique($level_child);
                                    sort($level_child);
                                    db('level_tree')->where('user_id='.$p_user_id)->setField('level_'.$level, implode(',', $level_child));
                                }else{
                                    db('level_tree')->where('user_id='.$p_user_id)->setField('level_'.$level, $user_id);
                                }
                            }
                        }
                    }
                }
            }
            // 只要用户升级了，
            // 1）判断如果不是一级，去查其邀请过的所有，小于他升级过后的等级的用户，归到其下面
            // 2）如果是一级，去查其邀请过的所有，小于等于他升级过后的等级的用户，归到其下面
            // 并将这些用户从别的代理处那删除
            if($level == 1){
                $where = 'status="1" and inviter_id='.$user_id.' and level_id>='.$level;
            }else{
                $where = 'status="1" and inviter_id='.$user_id.' and level_id>'.$level;
            }
            db('level_tree')->where('user_id='.$user_id)->setField('level_id',$level);
            $inviter_user = db('user')
            ->field('id,level_id')
            ->where($where)
            ->select();
            $update_data = [];
            $user_level_tree = db('level_tree')->where('user_id='.$user_id)->find();
            foreach ($inviter_user as $key => $value) {
                // 邀请用户的等级
                $i_u_level_id = $inviter_user[$key]['level_id']; //2
                // 有可能为邀请用户的上级的其他代理
                $inviter_level_tree = db('level_tree')
                ->where('level_id<='.$i_u_level_id)
                ->select();
                foreach ($inviter_level_tree as $k => $v) {
                    if(!empty($inviter_level_tree[$k]['level_'.$i_u_level_id])){
                        $inviter_level_tree_level = $inviter_level_tree[$k]['level_'.$i_u_level_id];
                        if(!empty($inviter_level_tree_level)){
                            $inviter_level_tree_level_arr = explode(',', $inviter_level_tree_level);
                            $search_key = array_search($inviter_user[$key]['id'], $inviter_level_tree_level_arr);
                            if($search_key !== false && $search_key >= 0){
                                $user_level_tree_level = $user_level_tree['level_'.$i_u_level_id];
                                $i_u_id = $inviter_level_tree_level_arr[$search_key];
                                if(!empty($user_level_tree_level)){
                                    $user_level_tree_level_arr = explode(',', $user_level_tree_level);
                                    array_push($user_level_tree_level_arr, $i_u_id);
                                    $user_level_tree_level_arr = array_unique($user_level_tree_level_arr);
                                    sort($user_level_tree_level_arr);
                                    $user_level_tree_level_str = implode(',', $user_level_tree_level_arr);
                                    db('level_tree')->where('user_id='.$user_id)->setField('level_'.$i_u_level_id, $user_level_tree_level_str);
                                }else{
                                    db('level_tree')->where('user_id='.$user_id)->setField('level_'.$i_u_level_id, $i_u_id);
                                }
                                unset($inviter_level_tree_level_arr[$search_key]);
                                if($i_u_level_id > 1){
                                    db('user')->where('id='.$i_u_id)->setField('superior_id', $user_id);
                                }
                            }
                            if(!empty($inviter_level_tree_level_arr)){
                                $inviter_level_tree_level_str = implode(',', $inviter_level_tree_level_arr);
                                db('level_tree')->where('user_id='.$inviter_level_tree[$k]['user_id'])->setField('level_'.$i_u_level_id, $inviter_level_tree_level_str);
                            }else{
                                db('level_tree')->where('user_id='.$inviter_level_tree[$k]['user_id'])->setField('level_'.$i_u_level_id, '');
                            }
                        }
                    }
                }
            }
        }
    }

    public function get_parent_level1_user($id)
    {
        $user = db('user')->where('id='.$id)->find();
        if($user['superior_id'] == 0){
            return 0;
        }else{
            $p_user = db('user')->where('id='.$user['superior_id'])->find();
            if($p_user['level_id'] == 1){
                return $p_user['id'];
            }else{
                $this->get_parent_user($p_user['id']);
            }
        }
    }

}
