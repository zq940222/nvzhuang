<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 订单接口
 */
class Order extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['get_freight','get_freight_money','get_user_address'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    /*
    *获取运费
    *goods_num           商品件数
    *address_id          买家地址ID
    */
    public function get_freight($extend)
    {
        $freight = 0;

        $freight_template = db('freight_template')
        ->where('is_enable_default=1')
        ->find();

        if(empty($freight_template)) {
            return $freight;
        }

        $freight_region = db('freight_region')
        ->where('template_id='.$freight_template['template_id'])
        ->select();
        // 如果大于0 证明有指定区域
        $freight_config = [];
        if(count($freight_region) > 0){
            $user_address = db('user_address')->where('id='.$extend['address_id'])->find();
            foreach ($freight_region as $key => $value) {
                // 判断如果用户的 省 在指定区域中 用此条模版
                if($freight_region[$key]['region_id'] == $user_address['province_id']) {
                    $freight_config = db('freight_config')->where('config_id='.$freight_region[$key]['config_id'])->find();
                }else{
                    // 默认全国
                    $freight_config = db('freight_config')->where('is_default=1 and template_id='.$freight_template['template_id'])->find();
                }
            }
        }else{
            // 默认全国
            $freight_config = db('freight_config')->where('is_default=1 and template_id='.$freight_template['template_id'])->find();
        }

        if(empty($freight_config)) {
            return $freight;
        }

        if($freight_config['first_unit'] != 0 && $freight_config['continue_unit'] != 0) {
            $freight += $freight_config['first_money'];
            if($freight_config['first_unit'] < $extend['goods_num']){
                $freight += $this->get_freight_money($freight_config['continue_unit'],$extend['goods_num']-$freight_config['first_unit'],$freight_config['continue_money']);
            }
        }
        return $freight;
    }

    public function get_freight_money($old_num, $new_num, $continu, $freight = 0)
    {
        $freight += $continu;
        if($new_num - $old_num > 0) {
            $freight += $this->get_freight_money($old_num, $new_num - $old_num, $continu);
        }
        return $freight;
    } 

    /**
     * 普通确认订单
     *
     * @param int $user_id  用户id
     * @param int $goods_id  商品ID
     * @param int $group_id  商品规格组合ID
     * @param int $num  购买数量
     * @param string $lprice  商品折扣价
     */
    public function confirm_order()
    {
        $data['user_id'] = $user_id = $this->request->request('user_id');
        $data['goods_id'] = $goods_id = $this->request->request('goods_id');
        $data['group_id'] = $group_id = $this->request->request('group_id');
        $data['num'] = $num = $this->request->request('num');
        $data['lprice'] = $lprice = $this->request->request('lprice');

        foreach ($data as $key => $value) {
            if(empty($value)) $this->error('参数:'.$key.'不能为空', null, -1);
        }
        $user = db('user')->where('id='.$user_id)->find();
        $user_address = db('user_address')->where('is_default=1 and user_id='.$user_id)->find();
        $arr['address_data'] = [];
        if(!empty($user_address)) {
            $arr['address_data'] = $this->get_user_address($user_address);
        }
        //商品总价
        $goods_price = 0;
        //邮费总价
        $shipping_price = 0;
        //商品总数量
        $total_num = 0;

        $goods = db('goods')
        ->field('id as goods_id,name,cover_image,is_on_sale,is_free_shipping')
        ->where('id='.$goods_id)
        ->find();
        if($goods['is_on_sale'] == 0) $this->error(__('商品'.$goods['name'].'已下架'), null, -3);
        $goods['group_id'] = $group_id;
        $goods['cover_image'] = get_http_host($goods['cover_image']);
        $goods['price'] = $lprice;
        $goods_price += $goods['price'] * $num;
        $goods['num'] = $num;
        $total_num += $goods['num'];
        $goods['spec_name'] = db('spec_goods_price')->where('id='.$group_id)->value('key_name');

        // 当商品不包邮时计算运费
        if($goods['is_free_shipping'] == 0) {
            if(!empty($user_address['id'])) {
                $shipping_price = $this->get_freight(['goods_num'=>$num,'address_id'=>$user_address['id']]);
            }
        }
        //订单总额
        $arr['goods_data'] = $goods;
        $arr['goods_price'] = $goods_price;
        $arr['shipping_price'] = $shipping_price;
        $arr['goods_payment'] = $user['goods_payment'];
        $arr['total_num'] = $total_num;
        $arr['total_amount'] = $goods_price + $shipping_price;
        
        $this->success('请求成功', $arr);
        
    }


    /**
     * 购物车确认订单
     *
     * @param int $user_id  用户id
     * @param string $cart_id  商品ID
     */
    public function cart_confirm_order()
    {
        $user_id = $this->request->request('user_id');
        $cart_id = $this->request->request('cart_id');

        if(!$user_id || !$cart_id) {
            $this->error(__('参数不能为空'), null, -1);
        }

        $cart = db('cart')
        ->where('is_delete=0 and cart_id in ('.$cart_id.') and user_id='.$user_id)
        ->select();

        if(empty($cart)) $this->error(__('购物车商品不存在'), null, -2);

        $user = db('user')->where('id='.$user_id)->find();

        $user_address = db('user_address')->where('is_default=1 and user_id='.$user_id)->find();

        $data['address_data'] = [];
        if(!empty($user_address)) {
            $data['address_data'] = $this->get_user_address($user_address);
        }

        //商品总价
        $goods_price = 0;
        //邮费总价
        $shipping_price = 0;
        //商品总数量
        $total_num = 0;
        //不包邮商品总数量
        $freight_total_num = 0;

        foreach ($cart as $key => $value) {
            $goods[$key] = db('goods')
            ->field('id as goods_id,name,cover_image,is_on_sale,is_free_shipping')
            ->where('id='.$cart[$key]['goods_id'])
            ->find();
            if($goods[$key]['is_on_sale'] == 0) $this->error(__('商品'.$goods[$key]['name'].'已下架'), null, -3);
            $goods[$key]['group_id'] = $cart[$key]['goods_spec_id'];
            $goods[$key]['cover_image'] = get_http_host($goods[$key]['cover_image']);
            $goods[$key]['price'] = $cart[$key]['lprice'];
            $goods_price += $goods[$key]['price'] * $cart[$key]['num'];
            $goods[$key]['num'] = $cart[$key]['num'];
            $total_num += $goods[$key]['num'];
            $goods[$key]['spec_name'] = db('spec_goods_price')->where('id='.$cart[$key]['goods_spec_id'])->value('key_name');

            // 当商品不包邮时计算运费
            if($goods[$key]['is_free_shipping'] == 0) {
                $freight_total_num += $cart[$key]['num'];
            }
        }

        //当不包邮购买数量大于0 并且用户地址信息不为空时
        if($freight_total_num > 0){
            if(!empty($user_address['id'])) {
                $shipping_price = $this->get_freight(['goods_num'=>$freight_total_num,'address_id'=>$user_address['id']]);
            }
        }

        //订单总额
        $data['goods_data'] = $goods;
        $data['goods_price'] = $goods_price;
        $data['shipping_price'] = $shipping_price;
        $data['goods_payment'] = $user['goods_payment'];
        $data['total_num'] = $total_num;
        $data['total_amount'] = $goods_price + $shipping_price;
        
        $this->success('请求成功', $data);

    }

    /**
     * 组合地址信息
     *
     * @param array $user_address  用户地址数据
     */
    public function get_user_address($user_address)
    {
        if(!empty($user_address['area_id'])) {
            $mergename = db('area')->where('id='.$user_address['area_id'])->value('mergename');
            $user_address['areaName'] = implode('', explode(',', $mergename)).$user_address['address'];                
        }else{
            if(!empty($user_address['city_id'])) {
                $mergename = db('area')->where('id='.$user_address['city_id'])->value('mergename');
                $user_address['areaName'] = implode('', explode(',', $mergename)).$user_address['address'];
            }else{
                if(!empty($user_address['province_id'])) {
                    $mergename = db('area')->where('id='.$user_address['province_id'])->value('mergename');
                    $user_address['areaName'] = implode('', explode(',', $mergename)).$user_address['address'];                        
                }
            }
        }
        if(!empty($user_address['mobile'])) {
            $Address = new Address;
            $user_address['mobile'] = $Address->yc_phone($user_address['mobile']);
        }
        $data['address_id'] = $user_address['id'];
        $data['consignee'] = $user_address['consignee'];
        $data['mobile'] = $user_address['mobile'];
        $data['areaName'] = $user_address['areaName'];

        return $data;
    }


    /**
     * 下单
     *
     * @param int $user_id  用户id
     * @param int $address_id  地址ID
     * @param array $goods_data  商品数据集合:
     [{'goods_id':'1','group_id':'1','num':'2','price':'4000'},{'goods_id':'1','group_id':'2','num':'1','price':'4000'},{'goods_id':'1','group_id':'3','num':'1','price':'4000'}]
     */
    public function place_order()
    {
        $data = $_REQUEST;
        //接收数据
        $_data['user_id'] = $user_id = $this->request->request('user_id');
        $_data['address_id'] = $address_id = $this->request->request('address_id');
        $_data['goods_data'] = $this->request->request('goods_data');

        //判断参数不为空
        foreach ($_data as $key => $value) {
            if(empty($value)) $this->error('参数:'.$key.'不能为空', null, -1);
        }

        //获取用户地址
        $user_address = db('user_address')->where('id='.$address_id.' and user_id='.$user_id)->find();

        if(empty($user_address)) $this->error('地址信息错误', null, -2);

        //商品总价
        $goods_price = 0;
        //邮费总价
        $shipping_price = 0;
        //商品总数量
        $total_num = 0;
        //订单总价
        $total_amount = 0;
        //给上级产生的利润 做查询记录用
        $profit = 0;
        //给推荐人的返利
        $back_money = 0;
        //不包邮商品总数量
        $freight_total_num = 0;

        $goods_data = json_decode($data['goods_data'], true);

        $user = db('user')->where('id='.$user_id)->find();
        //计算订单总价
        foreach ($goods_data as $key => $value) {
            $goods_id = $goods_data[$key]['goods_id'];
            $group_id = $goods_data[$key]['group_id'];
            $num = $goods_data[$key]['num'];
            $total_num += $num;
            $price = $goods_data[$key]['price'];

            $goods = db('goods')->where('id='.$goods_id)->find();
            $spec_goods_price = db('spec_goods_price')->where('id='.$goods_data[$key]['group_id'])->find();
            if($price != $spec_goods_price['price'.$user['level_id']]) $this->error('传入价格错误', null, -3);
            
            if($goods['is_on_sale'] == 0) $this->error(__('商品'.$goods['name'].'已下架'), null, -4);
            $goods_price += $spec_goods_price['price'.$user['level_id']] * $num;
            // 当商品不包邮时计算运费
            if($goods['is_free_shipping'] == 0) {
                $freight_total_num += $goods_data[$key]['num'];
            }
            /*累计利润和返利金额*/
            //判断邀请人和上级是否一致
            if($user['superior_id'] != $user['inviter_id']) {
                //当上级不是平台时
                if($user['superior_id'] > 0) {
                    $p_user_level = db('user')->where('id='.$user['superior_id'])->value('level_id');
                    //上级拿到的利润
                    $profit += ($spec_goods_price['price'.$user['level_id']] - $spec_goods_price['price'.$p_user_level]) / 2;
                    //推荐人拿到的返利
                    $back_money += ($spec_goods_price['price'.$user['level_id']] - $spec_goods_price['price'.$p_user_level]) / 2;
                }
                //当上级是平台时 推荐人的返利将由平台提供
                if($user['superior_id'] == 0) {
                    //推荐人返利
                    $p_user_level = db('user')->where('id='.$user['inviter_id'])->value('level_id');
                    //查询等级返利比例
                    $rebate = db('level')->where('id='.$p_user_level)->value('rebate');
                    $back_money += $spec_goods_price['price'.$user['level_id']] * $rebate;
                }
            }else{
                $p_user_level = db('user')->where('id='.$user['superior_id'])->value('level_id');
                //上级拿到的利润
                $profit += $spec_goods_price['price'.$user['level_id']] - $spec_goods_price['price'.$p_user_level];
            }
            /*累计利润和返利金额*/
            
        }
        //当不包邮购买数量大于0 并且用户地址信息不为空时
        if($freight_total_num > 0){
            if(!empty($user_address['id'])) {
                $shipping_price = $this->get_freight(['goods_num'=>$freight_total_num,'address_id'=>$user_address['id']]);
            }
        }
        $total_amount = $goods_price + $shipping_price;

        if($user['goods_payment'] - $total_amount < 0) {
            $this->error('货款不足，请充值', null, -5);
        } else {
            db('user')->where('id='.$user_id)->setDec('goods_payment', $total_amount);
        }

        //生成订单
        Db::startTrans();
        $order = array();
        $order['user_id'] = $user_id;
        $order['order_sn'] = time().mt_rand(1000, 9999).$user_id;
        $order['consignee'] = $user_address['consignee'];
        $order['province_id'] = $user_address['province_id'];
        $order['city_id'] = $user_address['city_id'];
        $order['area_id'] = $user_address['area_id'];
        $order['address'] = $user_address['address'];
        $order['mobile'] = $user_address['mobile'];
        $order['goods_price'] = $goods_price;
        $order['shipping_price'] = $shipping_price;
        $order['order_amount'] = $total_amount;
        $order['total_amount'] = $total_amount;

        //推荐人返利  
        if($back_money > 0){
            db('user')->where('id='.$user['inviter_id'])->setInc('money', $back_money);
            /*为推荐人添加流水记录*/
            $money_log_4['user_id'] = $user['inviter_id'];
            $money_log_4['type'] = 1;
            $money_log_4['money'] = $back_money;
            $money_log_4['memo'] = '返利';
            $money_log_4['createtime'] = time();
            $money_log[] = $money_log_4;
            /*为推荐人添加流水记录*/
        }
        //当上级是平台时
        if($user['superior_id'] == 0) {
            $order['shipment'] = '平台';
        }else{
            //查看上级代理货款是否充足
            $p_user = db('user')->where('id='.$user['superior_id'])->find();
            //上级拿货价
            //判断邀请人和上级是否一致
            if($user['superior_id'] == $user['inviter_id']) {
                $p_user_goods_price = $goods_price - $profit;
            }else{
                $p_user_goods_price = $goods_price - $profit - $back_money;
            }
            
            if($p_user['goods_payment'] - $p_user_goods_price < 0) {
                $p_user_message['user_id'] = $p_user['id'];
                $p_user_message['message_category'] = 1;
                $p_user_message['message_title'] = '代理订货通知';
                $p_user_message['message_content']='您的代理'.$user['nickname'].'将要提货'.$p_user_goods_price.'元，您的货款剩余不足，请及时补货。';
                $p_user_message['status'] = 1;
                $p_user_message['is_read'] = 0;
                $p_user_message['createtime'] = time();
                db('message')->insert($p_user_message);
                $this->error('上级货品不足，请联系其补货', null, -7);
            }else{
                //当推荐人与上级是同一人时
                if($user['superior_id'] == $user['inviter_id']) {
                    //加到上级用户余额
                    db('user')->where('id='.$user['superior_id'])->setInc('money', $goods_price);
                    /*为推荐人添加流水记录*/
                    $money_log_2['user_id'] = $p_user['id'];
                    $money_log_2['type'] = 1;
                    $money_log_2['money'] = $goods_price;
                    $money_log_2['memo'] = '余额';
                    $money_log_2['createtime'] = time();
                    $money_log[] = $money_log_2;
                    /*为推荐人添加流水记录*/

                    $message_content = '您的代理'.$user['nickname'].'将要提货'.$goods_price.'元，款项已转入您的余额，请注意查收。';
                }else{//当推荐人与上级不是同一人时 上级用户给推荐人返利
                    //加到上级用户余额
                    db('user')->where('id='.$user['superior_id'])->setInc('money', $goods_price - $back_money);
                    /*为推荐人添加流水记录*/
                    $money_log_2['user_id'] = $p_user['id'];
                    $money_log_2['type'] = 1;
                    $money_log_2['money'] = $goods_price - $back_money;
                    $money_log_2['memo'] = '余额';
                    $money_log_2['createtime'] = time();
                    $money_log[] = $money_log_2;
                    /*为推荐人添加流水记录*/

                    $message_content = '您的代理'.$user['nickname'].'将要提货'.$goods_price.'元，其中'.$back_money.'元是给其推荐人的返利，款项已转入您的余额，请注意查收。';
                }
                //扣除上级货款金额
                db('user')->where('id='.$user['superior_id'])->setDec('goods_payment', $p_user_goods_price);
                //消息通知
                $puser_message['user_id'] = $p_user['id'];
                $puser_message['message_category'] = 1;
                $puser_message['message_title'] = '代理订货通知';
                $puser_message['message_content'] = $message_content;
                $puser_message['status'] = 1;
                $puser_message['is_read'] = 0;
                $puser_message['createtime'] = time();
                db('message')->insert($puser_message);
                /*添加流水记录*/
                $money_log_1['user_id'] = $p_user['id'];
                $money_log_1['type'] = 2;
                $money_log_1['money'] = $p_user_goods_price;
                $money_log_1['memo'] = '下级订货货款';
                $money_log_1['createtime'] = time();
                $money_log[] = $money_log_1;
                /*添加流水记录*/
            }
            if($profit > 0) {
                $order['profit'] = $profit;

                $money_log_5['user_id'] = $p_user['id'];
                $money_log_5['type'] = 1;
                $money_log_5['money'] = $profit;
                $money_log_5['memo'] = '利润';
                $money_log_5['createtime'] = time();
                $money_log[] = $money_log_5;
            }
            $money_log_3['user_id'] = $user['id'];
            $money_log_3['type'] = 2;
            $money_log_3['money'] = $total_amount;
            $money_log_3['memo'] = '货款';
            $money_log_3['createtime'] = time();
            $money_log[] = $money_log_3;
            db('user_money_log')->insertAll($money_log);

            $order['shipment'] = $p_user['nickname'];
        }
        
        $order['goods_num'] = $total_num;
        $order['createtime'] = time();
        $order_id = db('order')->insertGetId($order);
        //生成订单商品详情
        $order_goods = array();
        foreach ($goods_data as $key => $value) {
            $goods = db('goods')->where('id='.$goods_data[$key]['goods_id'])->find();
            $order_goods[$key]['order_id'] = $order_id;
            $order_goods[$key]['goods_id'] = $goods_data[$key]['goods_id'];
            $order_goods[$key]['goods_name'] = $goods['name'];
            $order_goods[$key]['goods_sn'] = $goods['goods_sn'];
            $order_goods[$key]['goods_num'] = $goods_data[$key]['num'];
            $order_goods[$key]['item_id'] = $goods_data[$key]['group_id'];
            $spec_goods_price = db('spec_goods_price')->where('id='.$goods_data[$key]['group_id'])->find();
            $order_goods[$key]['spec_key'] = $spec_goods_price['key'];
            $order_goods[$key]['spec_key_name'] = $spec_goods_price['key_name'];
        }
        $order_goods_res = db('order_goods')->insertAll($order_goods);
        //判断处理条数与实际数据条数是否相等
        if($order_goods_res != count($order_goods)) {
            // 回滚事务
            Db::rollback();
            $this->error('订单创建失败，请稍后再试', null, -6);
        }else{
            //建立订单日志记录
            $log_order = array();
            $log_order['user_id'] = $user_id;
            $log_order['order_id'] = $order_id;
            $log_order['log_desc'] = '下单成功';
            $log_order['createtime'] = time();
            db('log_order')->insert($log_order);

            $goods_payment = db('user')->where('id='.$user_id)->value('goods_payment');
            if($goods_payment < 0) {
                // 回滚事务
                Db::rollback();
                $this->error('货款不足，请充值', null, -5);
            }else{
                // 提交事务
                Db::commit();
                $this->error('下单成功', $order['order_sn'], 1);
            }
        }
    }

    /**
     * 订单列表
     *
     * @param int $user_id  用户ID
     * @param int $status  订单状态:1=待发货,2=待收货,3=已完成
     * @param int $page=1  数据页码
     * @param int $count=10  数据条数
     */
    public function order_list()
    {
        $user_id = $this->request->request('user_id');
        $status = $this->request->request('status');
        $page = $this->request->request('page');
        $count = $this->request->request('count');
        if(!$user_id) $this->error('参数user_id不能为空', null, -1);
        if(empty($status)) $status = 0;
        if(empty($page)) $page = 1;
        if(empty($count)) $count = 10;
        $start = ($page - 1) * $count;

        $field = 'id as order_id,order_sn,status,goods_num,total_amount,createtime';
        $where = 'user_id='.$user_id;
        if($status > 0) $where .= ' and status='.$status;

        $level_id = db('user')->where('id='.$user_id)->value('level_id');

        $order = db('order')
        ->field($field)
        ->where($where)
        ->order('createtime','desc')
        ->limit($start,$count)
        ->select();
        foreach ($order as $key => $value) {
            $order_goods = db('order_goods a')
            ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
            ->field('a.id as order_goods_id,a.goods_id,a.goods_name,a.goods_num,a.spec_key_name,b.spec_image as image,b.price'.$level_id.' as price')
            ->where('a.order_id='.$value['order_id'])
            ->select();
            foreach ($order_goods as $key1 => $value1) {
                if(!empty($order_goods[$key1]['image'])) $order_goods[$key1]['image'] = get_http_host($order_goods[$key1]['image']);
            }
            $order[$key]['goods_data'] = $order_goods;
        }

        $this->success('请求成功', $order);
    }

    /**
     * 订单详情
     *
     * @param int $user_id  用户ID
     * @param int $order_id  订单ID
     */
    public function order_desc()
    {
        $user_id = $this->request->request('user_id');
        $order_id = $this->request->request('order_id');
        if(!$user_id || !$order_id) $this->error('参数不能为空', null, -1);

        $field = 'id as order_id,order_sn,status,goods_num,total_amount,createtime';
        $where = 'user_id='.$user_id.' and id='.$order_id;

        $level_id = db('user')->where('id='.$user_id)->value('level_id');

        $order = db('order')
        ->field($field)
        ->where($where)
        ->find();
        $order_goods = db('order_goods a')
        ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
        ->join('order c','a.order_id=c.id','INNER')
        ->field('a.id as order_goods_id,a.goods_id,a.goods_name,a.goods_num,a.spec_key_name,b.spec_image as image,b.price'.$level_id.' as price,c.profit')
        ->where('a.order_id='.$order['order_id'])
        ->select();
        foreach ($order_goods as $key1 => $value1) {
            if(!empty($order_goods[$key1]['image'])) $order_goods[$key1]['image'] = get_http_host($order_goods[$key1]['image']);
            $refund_order = db('refund_order')->where('order_goods_id='.$order_goods[$key1]['order_goods_id'])->find();
            $order_goods[$key1]['refund_order_status'] = -2;
            $order_goods[$key1]['refund_order_id'] = '';
            if(!empty($refund_order)) {
                $order_goods[$key1]['refund_order_status'] = $refund_order['status'];
                $order_goods[$key1]['refund_order_id'] = $refund_order['id'];
            }
        }
        $order['goods_data'] = $order_goods;

        $this->success('请求成功', $order);

    }

    /**
     * 查询物流
     * @param int $order_id  订单ID
     */
    public function logistics_url()
    {
        $order_id = $this->request->request('order_id');
        if(!$order_id) $this->error('参数不能为空', null, -1);

        $express_no = db('order')->where('id='.$order_id)->value('express_no');
        $url = 'https://m.kuaidi100.com/app/query/?nu='.$express_no;

        if(empty($express_no)) $this->error('暂无物流信息', null, -2);
        $this->success('请求成功', ['url'=>$url]);
        
    }

    /**
     * 确认收货
     * @param int $status  订单状态:1=待发货,2=待收货,3=已完成,4=退货
     */
    public function confirm_receipt()
    {
        $user_id = $this->request->request('user_id');
        $order_id = $this->request->request('order_id');
        if(empty($order_id) || empty($user_id)){
            $this->error('参数不能为空', null, -1);
        }
        $order = db('order')->where('id='.$order_id.' and user_id='.$user_id)->find();
        if(empty($order)){
            $this->error('订单不存在', null, -2);
        }
        if($order['status'] == 2){
            $arr['status'] = 3;
            $arr['confirm_time'] = time();//确认收货时间
            $res = db('order')->where('id='.$order_id)->update($arr);
            
            if($res){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->error('订单错误', null, -3);
        }
    }

    /******************退货******************/
    /**
     * 获取退货商品信息
     * @param int $user_id  用户ID
     * @param int $order_goods_id  订单商品ID
     */
    public function get_refund_order()
    {
        $user_id = $this->request->request('user_id');
        $order_goods_id = $this->request->request('order_goods_id');
        if(!$user_id || !$order_goods_id) $this->error('参数不能为空', null, -1);

        $level_id = db('user')->where('id='.$user_id)->value('level_id');

        $order_goods = db('order_goods a')
        ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
        ->join('order c','a.order_id=c.id','INNER')
        ->field('a.goods_id,a.id as order_goods_id,c.order_sn,a.goods_name,a.goods_num,a.spec_key_name,b.spec_image as image,b.price'.$level_id.' as price')
        ->where('a.id='.$order_goods_id)
        ->find();
        $order_goods['total_amount'] = $order_goods['goods_num'] * $order_goods['price'];
        $order_goods['user_id'] = $user_id;
        if(!empty($order_goods['image'])) $order_goods['image'] = get_http_host($order_goods['image']);

        $this->success('请求成功', $order_goods);
    }

    /**
     * 申请退货
     * @param int $user_id   用户ID
     * @param int $order_id  订单ID
     * @param int $order_goods_id  订单商品ID
     * @param int $goods_num 退货数量
     * @param int $content   问题描述
     * @param int $images    图片
     */
    public function apply_refund_order()
    {
        $user_id = $this->request->request('user_id');
        $order_id = $this->request->request('order_id');
        $order_goods_id = $this->request->request('order_goods_id');
        $goods_num = $this->request->request('goods_num');
        $content = $this->request->request('content');
        $images = $this->request->request('images');
        if(!$user_id || !$order_id || !$order_goods_id || !$goods_num) $this->error('参数不能为空', null, -1);

        $order = db('order')->where('id='.$order_id)->find();//shipping_time
        if($order['shipping_time'] > 3600*24*15) $this->error('超出退单时间', null, -6);
        if($user_id != $order['user_id']) $this->error('参数错误', null, -2);
        $order_goods = db('order_goods')->where('id='.$order_goods_id)->find();
        if($order_id != $order_goods['order_id']) $this->error('参数错误', null, -2);
        if($goods_num > $order_goods['goods_num']) $this->error('退货数量错误', null, -3);
        if($order['status'] != 3) $this->error('订单状态错误', null, -4);

        $level_id = db('user')->where('id='.$user_id)->value('level_id');
        $price = db('order_goods a')
        ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
        ->join('order c','a.order_id=c.id','INNER')
        ->where('a.id='.$order_goods_id)
        ->value('b.price'.$level_id);

        $refund_order['user_id'] = $user_id;
        $refund_order['order_sn'] = $order['order_sn'];
        $refund_order['order_id'] = $order_id;
        $refund_order['order_goods_id'] = $order_goods_id;
        $refund_order['goods_num'] = $goods_num;
        $refund_order['order_price'] = $goods_num * $price;
        $refund_order['createtime'] = time();
        if(!empty($content)) $refund_order['content'] = $content;
        if(!empty($images)) $refund_order['images'] = $images;
        $id = db('refund_order')->insertGetId($refund_order);
        if($id){
            db('order')->where('id='.$order_id)->setField('status',4);
            $this->success('请求成功', null, 1);
        }else{
            $this->error('操作失败', null, -5);
        }


    }

    /**
     * 退货详情
     * @param int $user_id  用户ID
     * @param int $refund_order_id  退单商品ID
     //退货状态:-3=不可以退货,-2=可以退货,-1=审核未通过,0=等待审核,1=寄回商品,2=系统审核,3=退款完成
     */
    public function refund_order_desc()
    {
        $user_id = $this->request->request('user_id');
        $refund_order_id = $this->request->request('refund_order_id');
        if(!$user_id || !$refund_order_id) $this->error('参数不能为空', null, -1);

        $level_id = db('user')->where('id='.$user_id)->value('level_id');
        $refund_order = db('refund_order')->where('id='.$refund_order_id)->find();

        $order_goods = [];
        if($refund_order['status'] == 0 || $refund_order['status'] == 3)
        {
            $order_goods = db('order_goods a')
            ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
            ->join('order c','a.order_id=c.id','INNER')
            ->field('a.goods_id,a.id as order_goods_id,c.order_sn,a.goods_name,a.goods_num,a.spec_key_name,b.spec_image as image,b.price'.$level_id.' as price')
            ->where('a.id='.$refund_order['order_goods_id'])
            ->find();
            $order_goods['goods_num'] = $refund_order['goods_num'];
            $order_goods['total_amount'] = $order_goods['goods_num'] * $order_goods['price'];
            if(!empty($order_goods['image'])) $order_goods['image'] = get_http_host($order_goods['image']);
            $order_goods['refund_order_status'] = $refund_order['status'];
        }
        if($refund_order['status'] != 2)
        {
            $Config = Config('site');
            $order_goods["refund_mode"] = '退回账户钱包';
            $order_goods["refund_address"] = $Config['refund_address'];
            $order_goods["refund_consignee"] = $Config['refund_consignee'];
            $order_goods["refund_mobile"] = $Config['refund_mobile'];
            $order_goods["content"] = $refund_order['content'];   
        }
        if($refund_order['status'] == 1)
        {
            $order_goods['refund_order_state'] = '1.退货商品，2.发货单';
        }
        if($refund_order['status'] == 2)
        {
            if(empty($refund_order['courier_no'])) $this->error('暂无物流信息', null, -2);
            $order_goods['url'] = 'https://m.kuaidi100.com/app/query/?nu='.$refund_order['courier_no'];
        }
        

        $this->success('请求成功', $order_goods);
    }

    /**
     * 填写快递信息
     * @param int $user_id  用户ID
     * @param int $refund_order_id  退单商品ID
     * @param int $courier_company   快递公司
     * @param int $courier_no    快递单号
     */
    public function write_courier()
    {
        $data['user_id'] = $user_id = $this->request->request('user_id');
        $data['refund_order_id'] = $refund_order_id = $this->request->request('refund_order_id');
        $data['courier_company'] = $courier_company = $this->request->request('courier_company');
        $data['courier_no'] = $courier_no = $this->request->request('courier_no');
        foreach ($data as $key => $value) {
            if(empty($value)) $this->error('参数:'.$key.'不能为空', null, -1);
        }

        $refund_order = db('refund_order')->where('id='.$refund_order_id)->find();
        if($refund_order['status'] != 1){
            $this->error('退货订单状态错误', null, -2);
        }

        unset($data['user_id']);
        unset($data['refund_order_id']);
        $data['status'] = 2;
        $res = db('refund_order')->where('id='.$refund_order_id)->update($data);
        if($res){
            $this->success('添加成功', null, 1);
        }else{
            $this->success('添加失败', null, -3);
        }
    }

    /******************退货******************/























}
