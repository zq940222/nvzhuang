<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 首页接口
 */
class Timetask extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function edit_order_status()
    {
        $file_name = RUNTIME_PATH.'/log/time_task.txt';
        file_put_contents($file_name, '/*****开始处理'.date('Y-m-d H:i:s').'*****/'.PHP_EOL, FILE_APPEND);//写入缓存 
 
        $order = db('order')
        ->where('status="2" and is_refund=0')
        ->select();
        foreach ($order as $key => $value) {
            $level_id = db('user')->where('id='.$order[$key]['user_id'])->value('level_id');
            $level = db('level')->where(['id'=>$level_id])->find();
            $out_time = $level['time_task'] * 60 * 60 * 24;

            if(time() - $order[$key]['shipping_time'] >= $out_time){
                $result = $this->confirm_receipt($order[$key]['user_id'], $order[$key]['id']);
                $content = 'ID:'.$order[$key]['id'].'|NO:'.$order[$key]['order_sn'].'|';
                if($result['code'] == 1){
                    $content .= '处理成功|';
                }else{
                    $content .= '处理失败|msg:'.$result['msg'].'|';
                }
                $content .= 'Time:'.date('Y-m-d H:i:s');
                file_put_contents($file_name, $content.PHP_EOL, FILE_APPEND);//写入缓存 
            }
        }
        file_put_contents($file_name, '/*****处理结束'.date('Y-m-d H:i:s').'*****/'.PHP_EOL, FILE_APPEND);//写入缓存 
    }

    /**
     * 确认收货
     * @param int $status  订单状态:1=待发货,2=待收货,3=已完成,4=退货
     */
    public function confirm_receipt($user_id, $order_id)
    {
        // $user_id = $this->request->request('user_id');
        // $order_id = $this->request->request('order_id');
        if(empty($order_id) || empty($user_id)){
            return ['code'=>-1,'msg'=>'参数不能为空'];
            // $this->error('参数不能为空', null, -1);
        }
        $order = db('order')->where('id='.$order_id.' and user_id='.$user_id)->find();
        if(empty($order)){
            return ['code'=>-2,'msg'=>'订单不存在'];
            // $this->error('订单不存在', null, -2);
        }
        $Common = new Common;
        if($order['status'] == 2){
            // 如果记录不是空的，证明有给上级、推荐人的返利
            $user_back_money = db('user_back_money')
            ->where('order_id='.$order_id)
            ->order('id','asc')
            ->select();
            if(!empty($user_back_money)){
                foreach ($user_back_money as $key => $value) {
                    
                    if($user_back_money[$key]['inviter_id'] > 0 && $user_back_money[$key]['inviter_id'] != $user_back_money[$key]['p_user_id']){
                        if($user_back_money[$key]['back_money'] > 0){
                            $user = db('user')->where('id='.$user_back_money[$key]['user_id'])->find();

                            db('user')
                            ->where('id='.$user_back_money[$key]['inviter_id'])
                            ->setInc('money', $user_back_money[$key]['back_money']);
                            $Common->ins_money_log($user_back_money[$key]['inviter_id'], 1, 1, $user_back_money[$key]['back_money'], '余额', '返利', $user['money'], $user['money']+$user_back_money[$key]['back_money']);
                            //添加奖励金记录表
                            
                            $user_bounty = array();
                            $user_bounty['user_id'] = $user_back_money[$key]['inviter_id'];
                            $user_bounty['sub_id'] = $user_back_money[$key]['user_id'];
                            $user_bounty['sub_level'] = $user['level_id'];
                            $user_bounty['money'] = $user_back_money[$key]['back_money'];
                            $user_bounty['createtime'] = time();
                            db('user_bounty')->insert($user_bounty);
                        }
                    }
                    // 出货方ID
                    if($user_back_money[$key]['p_user_id'] > 0){
                        $p_user = db('user')->where('id='.$user_back_money[$key]['p_user_id'])->find();
                        //加到上级用户余额
                        db('user')
                        ->where('id='.$user_back_money[$key]['p_user_id'])
                        ->setInc('money', $user_back_money[$key]['shipment_money'] + $user_back_money[$key]['profit']);
                        //扣除上级玉扣款字段
                        db('user')
                        ->where('id='.$user_back_money[$key]['p_user_id'])
                        ->setDec('lock_goods_money', $user_back_money[$key]['shipment_money']);
                        $Common->ins_money_log($user_back_money[$key]['p_user_id'], 1, 1, $user_back_money[$key]['shipment_money'], '余额', '成本价', $p_user['money'], $p_user['money']+$user_back_money[$key]['shipment_money']);
                        if($user_back_money[$key]['profit'] > 0){
                            $Common->ins_money_log($user_back_money[$key]['p_user_id'], 1, 1, $user_back_money[$key]['profit'], '余额', '利润', $p_user['money']+$user_back_money[$key]['shipment_money'], $p_user['money']+$user_back_money[$key]['shipment_money']+$user_back_money[$key]['profit']);
                        }
                    }
                    db('user_back_money')->where('id='.$value['id'])->setField("status",1);
                    db('user_back_money')->where('id='.$value['id'])->setField("updatetime",time());
                }
            }

            $arr['status'] = 3;
            $arr['confirm_time'] = time();//确认收货时间
            $res = db('order')->where('id='.$order_id)->update($arr);
            
            if($res){
                return ['code'=>1,'msg'=>'操作成功'];
                // $this->success('操作成功');
            }else{
                return ['code'=>0,'msg'=>'操作失败'];
                // $this->error('操作失败');
            }
        }else{
            return ['code'=>-3,'msg'=>'订单错误'];
            // $this->error('订单错误', null, -3);
        }
    }

}
