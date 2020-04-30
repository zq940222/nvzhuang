<?php
namespace app\api\model;
use app\common\library\Auth;
use app\api\controller\Common;
use think\Model;
use think\Db;

/**
 * 订单
 */
class Order extends Model
{
    // 退货审核成功处理
    public function confirm_return($id)
    {
        $refund_order = db('refund_order')
        ->where('id='.$id)
        ->find();
        $order = db('order')
        ->where('id='.$refund_order['order_id'])
        ->find();
        $user_back_money = db('user_back_money')
        ->where('order_id='.$refund_order['order_id'])
        ->select();
        $user = db('user')
        ->where('id='.$refund_order['user_id'])
        ->find();
        // 下单用户货款返回钱包
        if($refund_order['order_price'] > $order['gm_money']){
            $recharge_goods_money = $order['gm_money'];
        }else{
            $recharge_goods_money = $refund_order['order_price'];
        }
        db('user')
        ->where('id='.$refund_order['user_id'])
        ->setInc('goods_payment', $refund_order['order_price']);
        db('user')
        ->where('id='.$refund_order['user_id'])
        ->setInc('recharge_goods_money', $recharge_goods_money);
        $Common = new Common;
        $Common->ins_money_log($refund_order['user_id'], 2, 1, $refund_order['order_price'], '货款', '退货成功，预扣货款退回');
        // 站内信：下单用户。
        $message_template = db('message_template')->where('id=22')->find();
        $content = str_replace('money', $refund_order['order_price'], $message_template['message_content']);
        $Common->ins_message($refund_order['user_id'], $message_template['message_title'], $content);

        if(!empty($user_back_money)){
            foreach ($user_back_money as $key => $value) {
                if($user_back_money[$key]['p_user_id'] > 0){
                    // key+1是当前用户的上级所使用的充值货款记录
                    db('user')
                    ->where('id='.$user_back_money[$key]['p_user_id'])
                    ->setInc('goods_payment', $user_back_money[$key]['shipment_money']);
                    if(isset($user_back_money[$key+1])){
                        db('user')
                        ->where('id='.$user_back_money[$key]['p_user_id'])
                        ->setInc('recharge_goods_money', $user_back_money[$key+1]['money']);
                    }
                    db('user')
                    ->where('id='.$user_back_money[$key]['p_user_id'])
                    ->setDec('lock_goods_money', $user_back_money[$key]['shipment_money']);
                    $Common->ins_money_log($user_back_money[$key]['p_user_id'], 2, 1, $refund_order['order_price'], '货款', '代理退货成功，预扣货款退回');
                    // 站内信：下单用户。
                    $message_template = db('message_template')->where('id=23')->find();
                    $content1 = str_replace('money', $user_back_money[$key]['money'], $message_template['message_content']);
                    $content = str_replace('nick_name', $user['real_name'], $content1);
                    $Common->ins_message($user_back_money[$key]['p_user_id'], $message_template['message_title'], $content);
                }
                db('user_back_money')->where('id='.$value['id'])->setField("status",-1);
                db('user_back_money')->where('id='.$value['id'])->setField("updatetime",time());
            }
        }
        // 改变订单状态
        // db('order')->where('id='.$refund_order['order_id'])->setField('status','3');
        db('refund_order')->where('id='.$id)->setField('status','3');

        return true;
    }

}
