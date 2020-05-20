<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\api\model\KdSearch;
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
    protected $noNeedLogin = ['get_freight','get_freight_money','get_user_address','get_order_header','refund_order_list','parent_goods_payment'];
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
        $arr['goods_price'] = number_format($goods_price, 2);
        $arr['shipping_price'] = $shipping_price;
        $arr['goods_payment'] = $user['goods_payment'];
        $arr['total_num'] = $total_num;
        $arr['total_amount'] = number_format($goods_price + $shipping_price, 2);
        
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
        $data['goods_price'] = number_format($goods_price, 2);
        $data['shipping_price'] = $shipping_price;
        $data['goods_payment'] = $user['goods_payment'];
        $data['total_num'] = $total_num;
        $data['total_amount'] = number_format($goods_price + $shipping_price, 2);
        
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
     * @param int $cart_ids  购物车ID集，字符串逗号拼接
     * @param array $goods_data  商品数据集合:
     [{'goods_id':'1','group_id':'1','num':'2','price':'4000'},{'goods_id':'1','group_id':'2','num':'1','price':'4000'},{'goods_id':'1','group_id':'3','num':'1','price':'4000'}]
     */
    public function place_order()
    {
        // 加锁
        $Redislock = new Redislock;
        // 定义锁标识
        $lock_key = 'place_order';
        $order_lock = $Redislock->lock($lock_key,5,5);

        $data = $_REQUEST;
        //接收数据
        $_data['user_id'] = $user_id = $this->request->request('user_id');
        $_data['address_id'] = $address_id = $this->request->request('address_id');
        // $_data['goods_data'] = $this->request->request('goods_data');
        $cart_ids = $this->request->request('cart_ids');

        //判断参数不为空
        foreach ($_data as $key => $value) {
            if(empty($value)) $this->error('参数:'.$key.'不能为空', null, -1);
        }

        //获取用户地址
        $user_address = db('user_address')->where('id='.$address_id.' and user_id='.$user_id)->find();

        if(empty($user_address)) $this->error('地址信息错误', null, -2);

        if(!empty($cart_ids)){
            $gdata = db('cart')
            ->field('cart_id,goods_id,goods_spec_id as group_id,num,lprice as price')
            ->where('cart_id in ('.$cart_ids.')')
            ->select();
        }else{
            if(empty($data['goods_data'])){
                $this->error('参数:goods_data不能为空', null, -1);
            }
            $gdata = json_decode($data['goods_data'], true);
        }
        
        $user = db('user')->where('id='.$user_id)->find();
        //计算订单总价
        Db::startTrans();
        $Common = new Common;
        // dump($gdata);
        $goods_data = [];
        foreach ($gdata as $key => $value) {
            if($gdata[$key]['num'] > 1){
                for ($i=0; $i < $gdata[$key]['num']; $i++) { 
                    $goodsdata['goods_id'] = $gdata[$key]['goods_id'];
                    $goodsdata['group_id'] = $gdata[$key]['group_id'];
                    $goodsdata['num']      = 1;
                    $goodsdata['price']    = $gdata[$key]['price'];
                    $goods_data[] = $goodsdata;
                }
            }else{
                $goods_data[] = $gdata[$key];
            }
        }
        // dump($goods_data);die;
        //唯一订单号
        $order_sn_unique = time().$user_id.mt_rand(100000, 999999);
        foreach ($goods_data as $key => $value) {
            //商品总价
            $goods_price = 0;
            //邮费总价
            $shipping_price = 0;
            //订单总价
            $total_amount = 0;

            $goods_id = $goods_data[$key]['goods_id'];
            $group_id = $goods_data[$key]['group_id'];
            $num = $goods_data[$key]['num'];
            $price = $goods_data[$key]['price'];

            //查询商品
            $goods = db('goods')->where('id='.$goods_id)->find();
            //查询商品规格价格
            $spec_goods_price = db('spec_goods_price')->where('id='.$goods_data[$key]['group_id'])->find();
            //判断传入价格欲实际价格是否相同
            if($price != $spec_goods_price['price'.$user['level_id']]) {
                db('cart')
                ->where('cart_id='.$goods_data[$key]['cart_id'])
                ->setField('lprice',$spec_goods_price['price'.$user['level_id']]);
                Db::commit();
                $this->error('商品价格已更新,请返回购物车确认价格重新下单', null, -3);
            }
            $level = db('level')->where('id='.$user['level_id'])->find();
            //判断商品上架状态
            if($goods['is_on_sale'] == 0) $this->error(__('商品'.$goods['name'].'已下架'), null, -4);
            //商品价格=商品实际价格*购买数量
            $goods_price = $spec_goods_price['price'.$user['level_id']] * $num;
            // 当商品不包邮时计算运费
            $shipping_price = 0;
            if($goods['is_free_shipping'] == 0) {
                $shipping_price = $this->get_freight(['goods_num'=>$num,'address_id'=>$user_address['id']]);
            }

            //计算订单总价格和所有订单累计价格
            $total_amount = $goods_price + $shipping_price;
            if($user['goods_payment'] - $total_amount < 0) {
                $this->error('货款不足，请充值', null, -5);
            }

            // 如果用户有推荐人给推荐人返利
            // 查询用户是否使用了充值货款，如果使用了，递归预扣除其上级的货款，并生成记录.成功将会返回一个数组(所有记录的ID集)
            //判断用户货款剩余与充值货款剩余是否相等

            $parent_goods_payment = $this->parent_goods_payment($user_id,$total_amount,$Common);
            $parent_goods_payment_str = '';
            if(!empty($parent_goods_payment)){
                $parent_goods_payment_str = implode(',', $parent_goods_payment);
                $user_back_money = db('user_back_money')->where('status=0 and id in ('.$parent_goods_payment_str.') and user_id='.$user_id)->find();
            }

            //生成订单
            $order = array();
            $order['user_id'] = $user_id;
            $order['order_sn'] = time().$user_id.mt_rand(1000, 9999);
            $order['order_sn_unique'] = $order_sn_unique;
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
            $order['goods_num'] = $num;
            $order['createtime'] = time();
            $order['shipment'] = '平台';
            $order['shipment_id'] = 0;
            //判断用户货款剩余与充值货款剩余是否相等
            $gm_money = 0;
            if($user['goods_payment'] == $user['recharge_goods_money']){
                $gm_money = $total_amount;
            }else{
                if($user['goods_payment'] - $user['recharge_goods_money'] < $total_amount){
                    $gm_money = $total_amount - ($user['goods_payment'] - $user['recharge_goods_money']);
                }
            }
            if($gm_money > 0){
                $order['gm_type'] = 2;
                $order['gm_money'] = $gm_money;
            }
            if(isset($user_back_money) && !empty($user_back_money)){
                if(!empty($user_back_money['p_user_id'])){
                    $order['shipment'] = db('user')->where('id='.$user_back_money['p_user_id'])->value('real_name');
                    $order['shipment_id'] = $user_back_money['p_user_id'];
                }
                $order['gm_type'] = $user_back_money['money']>0?2:1;
                $order['gm_money'] = $user_back_money['money'];
                $order['profit'] = $user_back_money['profit'];
                $order['back_money'] = $user_back_money['back_money'];
            }
            // if($user['level_id'] != 1){
            //     if(!empty($user_back_money)){
            //         if(!empty($user_back_money['p_user_id'])){
            //             $order['shipment'] = db('user')->where('id='.$user_back_money['p_user_id'])->value('real_name');
            //             $order['shipment_id'] = $user_back_money['p_user_id'];
            //         }
            //         $order['gm_type'] = $user_back_money['money']>0?2:1;
            //         $order['gm_money'] = $user_back_money['money'];
            //         $order['profit'] = $user_back_money['profit'];
            //         $order['back_money'] = $user_back_money['back_money'];
            //     }
            // }else{
            //     if($superior_superior_user > 0){
            //         $order['back_money'] = $superior_superior_user_money;
            //     }
            //     db('user')->where('id='.$user_id)->setDec('recharge_goods_money', $gm_money);
            //     db('user')->where('id='.$user_id)->setDec('goods_payment',$total_amount);
            //     db('user')->where('id='.$user_id)->setInc('lock_goods_money',$total_amount);
            //     $Common->ins_money_log($user_id, 2, 2, $total_amount, '货款', '预扣除货款 '.$total_amount.' 元');
            // }
            
            //生成订单
            $order_id = db('order')->insertGetId($order);
            if (!empty($parent_goods_payment_str)) {
                db('user_back_money')->where('id in ('.$parent_goods_payment_str.')')->setField('order_id',$order_id);
            }

            //生成订单商品详情
            $order_goods = array();
            $order_goods['order_id'] = $order_id;
            $order_goods['goods_id'] = $goods_id;
            $order_goods['goods_name'] = $goods['name'];
            $order_goods['goods_sn'] = $goods['goods_sn'];
            $order_goods['goods_price'] = $price;
            $order_goods['goods_num'] = $num;
            $order_goods['item_id'] = $group_id;
            $spec_goods_price = db('spec_goods_price')->where('id='.$group_id)->find();
            $order_goods['spec_key'] = $spec_goods_price['key'];
            $order_goods['spec_key_name'] = $spec_goods_price['key_name'];
            $order_goods_res = db('order_goods')->insert($order_goods);
            if(!$order_goods_res) {
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
                // 剪掉商品库存和商品规格库存
                $store_count = db('spec_goods_price')->where('id='.$group_id.' and goods_id='.$goods_id)->value('store_count');
                if($store_count - $num < 0){
                    // 回滚事务
                    Db::rollback();
                    $this->error('订单创建失败，商品库存不足，请稍后再试', null, -6);
                }else{
                    db('goods')->where('id='.$goods_id)->setDec('store_count',$num);
                    db('spec_goods_price')->where('id='.$group_id.' and goods_id='.$goods_id)->setDec('store_count',$num);
                }
            }
        }
        if(!empty($cart_ids)){
            db('cart')->where('cart_id in ('.$cart_ids.')')->delete();
        }

        // 同步云仓库存
        $Store = new Store;
        foreach ($goods_data as $key => $value) {
            $group_id = $goods_data[$key]['group_id'];
            $num = $goods_data[$key]['num'];
            $spec_goods_price = db('spec_goods_price')->where('id='.$group_id)->find();
            // 获取云仓库存
            $Store_data = $Store->synchro($spec_goods_price['sku']);
            if($Store_data['code'] == 0){
                if(isset($Store_data['inventorys']) && !empty($Store_data['inventorys'])){
                    //同步云仓库存
                    $qty = $Store_data['inventorys'][0]['qty'];
                    $r = $Store->synchro_goods_num($spec_goods_price['sku'], $qty-$num);
                }
            }
        }
        // 提交事务
        Db::commit();
        $this->error('下单成功', $order_sn_unique, 1);
    }

    // 递归扣除一条线上每个用户的直属上级货款
    // user_id          下单用户ID
    // total_amount     用户使用的充值货款数量
    // $Common          $Common 对象
    // $array           方法返回的执行成功ID集
    public function parent_goods_payment($user_id,$total_amount,$Common,$array = [])
    {
        $user = db('user')->where('id='.$user_id)->find();
        $user_level = db('level')->where('id='.$user['level_id'])->find();
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
        if(!empty($array)){
            db('user')->where('id='.$user_id)->setInc('lock_goods_money',$total_amount);
            $Common->ins_money_log($user_id, 2, 2, $total_amount, '货款', '预扣除货款 '.$total_amount.' 元');
        }else{
            $Common->ins_money_log($user_id, 2, 2, $total_amount, '货款', '扣除货款 '.$total_amount.' 元');
        }
        

        // 如果用户是一级,记录给直属一级上级的返利，此为平台给的不扣除用户的利润，并且不区分充值货款和入代理货款
        if($user['level_id'] == 1){
            $gm_money = $total_amount;
            // 判断用户推荐人在不在
            if($user['inviter_id'] > 0){
                // 推荐人返利 = 提货用户使用的充值货款 * 提货用户等级的返利折扣
                $back_money = $gm_money * $user_level['rebate'];

                $user_back_money['user_id'] = $user_id;
                $user_back_money['inviter_id'] = $user['inviter_id'];
                $user_back_money['money'] = $total_amount;// 用户为一级时不区分充值货款和入代理货款
                $user_back_money['back_money'] = $back_money;
                $user_back_money['status'] = 0; //-1=失败｜0=等待返现｜1=成功
                $user_back_money['createtime'] = time();
                $id = db('user_back_money')->insertGetId($user_back_money);
                array_push($array, $id);
            }else{
                $data = array();
                $data['user_id'] = $user_id;    //提货用户ID
                // $data['p_user_id'] = $p_user['id']; //上级ID
                // $data['inviter_id'] = $user['inviter_id'];  //原上级ID
                $data['money'] = $gm_money; //提货金额(为提货用户使用的充值货款)
                // $data['shipment_money'] = $shipment_money;  //上级成本价
                // $data['profit'] = $profit;  //上级利润(已扣除给原上级的返利)
                // $data['back_money'] = $back_money;  //原上级得到的返利(如果原上级存在)
                $data['status'] = 0;
                $data['createtime'] = time();
                $id = db('user_back_money')->insertGetId($data);
                array_push($array, $id);
            }
        }else{
            // 如果用户使用充值货款
            if($gm_money > 0){
                // 判断用户推荐人在不在
                if($user['inviter_id'] > 0){
                    // 推荐人返利 = 提货用户使用的充值货款 * 提货用户等级的返利折扣
                    $back_money = $gm_money * $user_level['rebate'];
                }
                if($user['superior_id'] > 0){
                    $user_level = db('level')->where('id='.$user['level_id'])->find();
                    $p_user = db('user')->where('id='.$user['superior_id'])->find();
                    $p_user_level = db('level')->where('id='.$p_user['level_id'])->find();
                    // 上级成本价 == 用户所使用的充值货款 / 用户的拿货折扣 * 上级的拿货折扣
                    $shipment_money = $gm_money / $user_level['discount'] * $p_user_level['discount'];
                    // 上级利润 = $gm_money - $shipment_money
                    $profit = $gm_money - $shipment_money - $back_money;
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
                        // 预扣除上级用户货款
                        
                        // $Common->ins_money_log($p_user['id'], 2, 2, $shipment_money, '货款', '下级提货预扣货款');
                        // 站内信：上级 您的代理【nickname】将要提货【money】元，货款已扣除。
                        $message_template = db('message_template')->where('id=21')->find();
                        $content1 = str_replace('nick_name', $user['real_name'], $message_template['message_content']);
                        $content2 = str_replace('money', $shipment_money, $content1);
                        $Common->ins_message($p_user['id'], $message_template['message_title'], $content2);

                        $data = array();
                        $data['user_id'] = $user_id;    //提货用户ID
                        $data['p_user_id'] = $p_user['id']; //上级ID
                        $data['inviter_id'] = $user['inviter_id'];  //原上级ID
                        $data['money'] = $gm_money; //提货金额(为提货用户使用的充值货款)
                        $data['shipment_money'] = $shipment_money;  //上级成本价
                        $data['profit'] = $profit;  //上级利润(已扣除给原上级的返利)
                        $data['back_money'] = $back_money;  //原上级得到的返利(如果原上级存在)
                        $data['status'] = 0;
                        $data['createtime'] = time();
                        $id = db('user_back_money')->insertGetId($data);
                        array_push($array, $id);
                        $array = $this->parent_goods_payment($p_user['id'],$shipment_money,$Common,$array);
                    }
                }else{
                    // 如果当前用户没有上级，给推荐人返利
                    $data = array();
                    $data['user_id'] = $user_id;    //提货用户ID
                    // $data['p_user_id'] = $p_user['id']; //上级ID
                    $data['inviter_id'] = $user['inviter_id'];  //原上级ID
                    $data['money'] = $gm_money; //提货金额(为提货用户使用的充值货款)
                    // $data['shipment_money'] = $shipment_money;  //上级成本价
                    // $data['profit'] = $profit;  //上级利润(已扣除给原上级的返利)
                    $data['back_money'] = $back_money;  //原上级得到的返利(如果原上级存在)
                    $data['status'] = 0;
                    $data['createtime'] = time();
                    $id = db('user_back_money')->insertGetId($data);
                    array_push($array, $id);
                }
            }
        }
        return $array;
    }
    /**
     * 订单列表
     *
     * @param int $user_id  用户ID
     * @param int $status  订单状态:1=待发货,2=待收货,3=已完成
     * @param string $date  日期月份Y-m
     * @param int $page=1  数据页码
     * @param int $count=10  数据条数
     */
    public function order_list()
    {
        $user_id = $this->request->request('user_id');
        $status = $this->request->request('status');
        $page = $this->request->request('page');
        $count = $this->request->request('count');
        $date = $this->request->request('date');
        if(!$user_id) $this->error('参数user_id不能为空', null, -1);
        if(empty($status)) $status = 0;
        if(empty($page)) $page = 1;
        if(empty($count)) $count = 10;
        if(empty($date)) $date = date("Y-m");
        $firstday = date('Y-m-01', strtotime($date));
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));
        $firstday_time = strtotime($firstday);
        $lastday_time = strtotime($lastday);
        $time_where = 'createtime >='.$firstday_time.' and createtime <='.$lastday_time;

        $start = ($page - 1) * $count;

        $field = 'id as order_id,user_id,order_sn,is_refund,status,goods_num,total_amount,createtime';
        $where = 'is_refund=0 and user_id='.$user_id;
        if($status > 0) $where .= ' and status='.$status;

        $level_id = db('user')->where('id='.$user_id)->value('level_id');

        $order = db('order')
        ->field($field)
        ->where($where)
        ->where($time_where)
        ->order('createtime','desc')
        ->limit($start,$count)
        ->select();
        foreach ($order as $key => $value) {
            $order_goods = db('order_goods a')
            ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
            ->field('a.id as order_goods_id,a.goods_id,a.goods_name,a.goods_num,a.spec_key_name,b.spec_image as image,a.goods_price as price')
            ->where('a.order_id='.$value['order_id'])
            ->select();
            foreach ($order_goods as $key1 => $value1) {
                if(!empty($order_goods[$key1]['image'])) $order_goods[$key1]['image'] = get_http_host($order_goods[$key1]['image']);
            }
            $order[$key]['goods_data'] = $order_goods;
        }
        $data['order_header'] = $this->get_order_header($user_id,$date);
        $data['order_list'] = $order;

        $this->success('请求成功', $data);
        // $this->success('请求成功', $order);
    }

    public function get_order_header($user_id, $date)
    {
        $firstday = date('Y-m-01', strtotime($date));
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));
        $firstday_time = strtotime($firstday);
        $lastday_time = strtotime($lastday);
        $where = 'createtime >='.$firstday_time.' and createtime <='.$lastday_time;
        $agent_where = '1=1';
        $agent_upgrade = db('agent_upgrade')
        ->where('level=1 and status="1" and user_id='.$user_id)
        ->order('createtime','desc')
        ->find();
        if(!empty($agent_upgrade)){
            $where .= ' and createtime>'.$agent_upgrade['updatetime'];
            $agent_where .= ' and updatetime>'.$agent_upgrade['updatetime'];
        }
        //奖励金
        $money_info['bounty'] = db('user_bounty')
        ->where('user_id='.$user_id.' and status="0" and '.$where)
        ->sum('money');
        //利润
        $money_info['profit'] = db('user_money_log')
        ->where('`desc`="利润" and user_id='.$user_id.' and '.$where)
        ->sum('money');
        //销售折扣（团队收益）
        /*
        1）招代理算业绩（招顶级不算，顶级下单算业绩）
        2）自己一条线的订单算业绩（自己的订单算业绩）
        */
        //总销售额
        $total_sales = 0;
        //销售折扣
        $team_money = 0;
        //1）招代理算业绩（招顶级不算，顶级下单算业绩）
        $total_sales += db("agent_apply")
        ->where($where.' and status="1" and agency_id!=1 and superior_id='.$user_id)
        ->sum('goods_payment');
        // dump('邀请:'.$total_sales);
        // //2）下级升级代理算业绩
        $total_sales += db("agent_upgrade")
        ->where($where.' and status="1" and level!=1 and new_superior_id='.$user_id)
        ->sum('goods_payment');
        // dump('升级:'.$total_sales);
        //3）自己的订单算业绩（自己的订单算业绩）
        // $total_sales += db('order')->where($where.' and is_refund=0 and gm_type=2 and user_id='.$user_id.' and status="3"')->sum('gm_money');
        // 下级消费时产生的充值货款
        // $order = db('order')->where($where.' and is_refund=0 and user_id='.$user_id.' and status="3"')->column('id');
        // if(!empty($order)){
        //     $order_ids = implode(',', $order);
        //     $total_sales += db('user_back_money')->where($agent_where.' and p_user_id='.$user_id.' and status=1 and order_id in ('.$order_ids.')')->sum('money');
        // }
        $total_sales += db('user_back_money')->where($agent_where.' and p_user_id='.$user_id.' and status=1')->sum('shipment_money');
        // $total_sales += db('user_agent_back_money')->where('user_id='.$user_id.' and status=1')->sum('money');
        $total_sales += db('user_agent_back_money')->where($agent_where.' and p_user_id='.$user_id.' and status=1')->sum('shipment_money');
        $user = db('user')->where('id='.$user_id)->find();
        //4）非一级直属代理线销售额总和
        // $get_team_money = $this->get_team_money($user_id, $where);
        // $total_sales += $get_team_money['team_money'];
        $total_sales += db('order')->where($where.' and is_refund=0 and user_id='.$user_id.' and status="3"')->sum('total_amount');
        if($user['level_id'] == 1){
            
            $back_money = db('back_money')->order('id','asc')->select();
            $get_team1_money = $this->get_team1_money($user_id, $where);
            $total_sales += $get_team1_money['team_money'];
            // 计算当前总销售折扣
            if($total_sales > 0){
                $new_team_money = $total_sales / 10000;
                foreach ($back_money as $key => $value) {
                    if($new_team_money >= $back_money[$key]['sales']) {
                        $team_money = $total_sales * $back_money[$key]['discount'];
                    }
                }
            }
            $user_data = $get_team1_money['user_data'];
            if(!empty($user_data)){
                foreach ($user_data as $key => $value) {
                    if($user_data[$key]['team_money'] > 0){
                        $new_team_money = $user_data[$key]['team_money'] / 10000;
                        foreach ($back_money as $k => $v) {
                            if($new_team_money >= $back_money[$k]['sales']) {
                                $user_team_money = $user_data[$key]['team_money'] * $back_money[$k]['discount'];
                            }
                        }
                        if(isset($user_team_money)) {
                            $team_money -= $user_team_money;
                        }
                    }
                }
            }
            
        }
        //总销售额
        $money_info['total_sales'] = $total_sales;
        $money_info['team_money'] = $team_money;
        return $money_info;
    }
    // 非一级直属代理线销售额总和
    public function get_team_money($user_id, $where = '')
    {
        $team_money = 0;
        //先查自己有没有直属下级
        $users = db('user')
        ->field('id,real_name,level_id')
        ->where('status="1" and superior_id='.$user_id)
        ->select();
        //1）招代理算业绩（招顶级不算，顶级下单算业绩）
        $team_money += db("agent_apply")
        ->where('status="1" and agency_id!=1 and superior_id='.$user_id.' and '.$where)
        ->sum('goods_payment');
        //2）下级升级代理算业绩
        $team_money += db("agent_upgrade")
        ->where('status="1" and level!=1 and new_superior_id='.$user_id.' and '.$where)
        ->sum('goods_payment');
        $order = db('order')
        ->where('is_refund=0 and user_id='.$user_id.' and status="3" and '.$where)
        ->column('id');
        if(!empty($order)){
            $order_ids = implode(',', $order);
            $team_money += db('user_back_money')
            ->where('user_id='.$user_id.' and status=1 and order_id in ('.$order_ids.') and '.$where)
            ->sum('money');
        }
        $team_money += db('user_agent_back_money')
        ->where('p_user_id='.$user_id.' and status=1 and '.$where)
        ->sum('shipment_money');
        // dump($users[$key]['team_money']);
        // if(empty($where)) $where = '1=1';
        // if(!empty($users)){
        //     foreach ($users as $key => $value) {
        //         $users[$key]['team_money'] = 0;
        //         //1）招代理算业绩（招顶级不算，顶级下单算业绩）
        //         $users[$key]['team_money'] += db("agent_apply")
        //         ->where('status="1" and agency_id!=1 and superior_id='.$users[$key]['id'].' and '.$where)
        //         ->sum('goods_payment');
        //         //2）下级升级代理算业绩
        //         $users[$key]['team_money'] += db("agent_upgrade")
        //         ->where('status="1" and level!=1 and new_superior_id='.$users[$key]['id'].' and '.$where)
        //         ->sum('goods_payment');
        //         dump($users[$key]['team_money']);
        //         //3）自己的订单算业绩（自己的订单算业绩）
        //         // $users[$key]['team_money'] += db('order')->where('gm_type=2 and user_id='.$users[$key]['id'].' and status="3"'.$where)->sum('gm_money');
        //         $order = db('order')->where('is_refund=0 and user_id='.$users[$key]['id'].' and status="3" and '.$where)->column('id');
        //         if(!empty($order)){
        //             $order_ids = implode(',', $order);
        //             $users[$key]['team_money'] += db('user_back_money')->where('user_id='.$users[$key]['id'].' and status=1 and order_id in ('.$order_ids.')')->sum('money');
        //         }
        //         $users[$key]['team_money'] += db('user_agent_back_money')->where('user_id='.$users[$key]['id'].' and status=1')->sum('shipment_money');

        //         $team_money += $users[$key]['team_money'];

        //         $child_users = db('user')->where('superior_id='.$users[$key]['id'])->select();
        //         if(!empty($child_users)) {
        //             $child_data = $this->get_team_money($users[$key]['id'],$where);
        //             $team_money += $child_data['team_money'];
        //             $child_users_data = $child_data['user_data'];
        //             if(!empty($child_users_data)){
        //                 foreach ($child_users_data as $k => $v) {
        //                     $users[$key]['team_money'] += $child_users_data[$k]['team_money'];
        //                 }
        //             }
        //         }
        //     }
        // }
        $data['team_money'] = $team_money;
        // $data['user_data'] = $users;
        return $data;
    }
    // 一级直属代理团队销售额总和
    public function get_team1_money($user_id, $where = '')
    {
        $team_money = 0;
        $users = [];
        //先查自己有没有直属下级
        $level_tree = db('level_tree')
        ->field('user_id,level_id,level_1')
        ->where('user_id='.$user_id)
        ->find();
        if(empty($where)) $where = ' 1=1 ';
        if(!empty($level_tree['level_1'])){
            $level1_users = explode(',', $level_tree['level_1']);
            
            foreach ($level1_users as $key => $value) {
                $users[$key]['id'] = $value;
            }
            foreach ($users as $key => $value) {
                $agent_where = '1=1';
                $agent_upgrade = db('agent_upgrade')->where('level=1 and status="1" and user_id='.$users[$key]['id'])->find();
                if(!empty($agent_upgrade)){
                    $where .= ' and createtime>'.$agent_upgrade['updatetime'];
                    $agent_where .= ' and updatetime>'.$agent_upgrade['updatetime'];
                }
                $users[$key]['team_money'] = 0;
                //1）招代理算业绩（招顶级不算，顶级下单算业绩）
                $users[$key]['team_money'] += db("agent_apply")
                ->where('status="1" and agency_id!=1 and superior_id='.$users[$key]['id'].' and '.$where)
                ->sum('goods_payment');
                //2）下级升级代理算业绩
                $users[$key]['team_money'] += db("agent_upgrade")
                ->where('status="1" and level!=1 and new_superior_id='.$users[$key]['id'].' and '.$where)
                ->sum('goods_payment');
                //3）自己的订单算业绩（自己的订单算业绩）
                // $users[$key]['team_money'] += db('order')->where('gm_type=2 and user_id='.$users[$key]['id'].' and status="3"'.$where)->sum('gm_money');
                $users[$key]['team_money'] += db('order')->where($where.' and is_refund=0 and user_id='.$users[$key]['id'].' and status="3"')->sum('total_amount');

                // 下级消费时产生的充值货款
                $order = db('order')->where('is_refund=0 and user_id='.$users[$key]['id'].' and status="3"'.' and '.$where)->column('id');
                if(!empty($order)){
                    $order_ids = implode(',', $order);
                    // 下级消费时产生的充值货款
                    $users[$key]['team_money'] += db('user_back_money')->where($agent_where.' and p_user_id='.$users[$key]['id'].' and status=1 and order_id in ('.$order_ids.')')->sum('money');
                }
                // $users[$key]['team_money'] += db('user_agent_back_money')->where('user_id='.$users[$key]['id'].' and status=1')->sum('money');
                $users[$key]['team_money'] += db('user_agent_back_money')->where($agent_where.' and p_user_id='.$users[$key]['id'].' and status=1')->sum('shipment_money');
                //4）自己的非一级的直属下级业绩
                // $users[$key]['team_money'] += $this->get_team_money($users[$key]['id'],$where)['team_money'];

                $team_money += $users[$key]['team_money'];
                $child_level_tree = db('level_tree')
                ->field('user_id,level_id,level_1')
                ->where('user_id='.$users[$key]['id'])
                ->find();
                if(!empty($child_level_tree['level_1'])){
                    $child_level1_users = explode(',', $child_level_tree['level_1']);
                    $child_users = [];
                    foreach ($child_level1_users as $ck => $value) {
                        $child_users[$ck]['id'] = $value;
                    }
                    $child_data = $this->get_team1_money($users[$key]['id'],$where);
                    $team_money += $child_data['team_money'];
                    $child_users_data = $child_data['user_data'];
                    if(!empty($child_users_data)){
                        foreach ($child_users_data as $k => $v) {
                            $users[$key]['team_money'] += $child_users_data[$k]['team_money'];
                        }
                    }
                }
            }
        }
        $data['team_money'] = $team_money;
        $data['user_data'] = $users;
        return $data;
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

        $field = 'id as order_id,order_sn,is_refund,status,goods_num,total_amount,createtime';
        $where = 'user_id='.$user_id.' and id='.$order_id;

        $level_id = db('user')->where('id='.$user_id)->value('level_id');

        $order = db('order')
        ->field($field)
        ->where($where)
        ->find();
        $order['refund_type'] = 0;
        if($order['is_refund'] == 1){
            //售后类型:0=正常订单,1=仅退款,2=退货退款
            $order['refund_type'] = db('refund_order')
            ->where('user_id='.$user_id.' and order_id='.$order['order_id'])
            ->value('refund_type');
        }
        $order_goods = db('order_goods a')
        ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
        ->join('order c','a.order_id=c.id','INNER')
        ->field('a.id as order_goods_id,a.goods_id,a.goods_name,a.goods_num,a.spec_key_name,b.spec_image as image,b.price'.$level_id.' as price,c.profit')
        ->where('a.order_id='.$order['order_id'])
        ->select();
        foreach ($order_goods as $key1 => $value1) {
            if(!empty($order_goods[$key1]['image'])) $order_goods[$key1]['image'] = get_http_host($order_goods[$key1]['image']);
            // $refund_order = db('refund_order')->where('order_goods_id='.$order_goods[$key1]['order_goods_id'])->find();
            // if($order['status'] < 3){
            //     $order_goods[$key1]['refund_order_status'] = -2;
            //     $order_goods[$key1]['refund_order_id'] = '';
            //     if(!empty($refund_order) && $refund_order['status'] != -3) {
            //         $order_goods[$key1]['refund_order_status'] = $refund_order['status'];
            //         $order_goods[$key1]['refund_order_id'] = $refund_order['id'];
            //     }
            // }
        }
        $order['goods_data'] = $order_goods;

        $this->success('请求成功', $order);

    }

    /**
     * 查询物流
     * @param int $order_id  订单ID
     status     订单状态:1=待发货,2=待收货,3=已完成,4=退货
     State      物流状态：2-在途中,3-签收,4-问题件

     state      物流状态：0-未发货,1-已发货,2-运输中,3-派送中,4-已签收
     */
    public function logistics_url()
    {
        $order_id = $this->request->request('order_id');
        if(!$order_id) $this->error('参数不能为空', null, -1);

        // $express_no = db('order')->where('id='.$order_id)->value('express_no');
        // $url = 'https://m.kuaidi100.com/app/query/?nu='.$express_no;
        // if(empty($express_no)) $this->error('暂无物流信息', null, -2);
        // $this->success('请求成功', ['url'=>$url]);

        $order = db('order')->where('id='.$order_id)->find();
        if(empty($order)) $this->error('订单不存在', null, -2);
        $data = array();
        if($order['status'] >= 1) {
            $state = 0;
            $Accept['AcceptStation'] = '您的订单开始处理';
            $Accept['AcceptTime'] = date('Y-m-d H:i:s', $order['createtime']);
            $traces[] = $Accept;
            $data['courier_company'] = '';
            $data['express_no'] = '';
            if($order['status'] >= 2) {
                $state = 1;
                $Accept['AcceptStation'] = '卖家已发货';
                $Accept['AcceptTime'] = date('Y-m-d H:i:s', $order['shipping_time']);
                $traces[] = $Accept;

                if(!empty($order['courier_company']) && !empty($order['express_no'])) {
                    $data['courier_company'] = $order['courier_company'];
                    $data['express_no'] = $order['express_no'];
                    $courier = db('courier')->where('courier_company like "%'.$order['courier_company'].'%"')->find();
                    if(!empty($courier)) {
                        $KdSearch = new KdSearch;
                        $search_data = $KdSearch->getOrderTracesByJson($courier['courier_code'], $order['express_no']);
                        if(!empty($search_data['Traces'])){
                            if($search_data['State'] >= 2) {
                                $state = 2;
                                for ($i=0; $i<=count($search_data['Traces'])-1; $i++) { 
                                    $traces[] = $search_data['Traces'][$i];
                                    $paisong = substr_count($search_data['Traces'][$i]['AcceptStation'], '派送');
                                    $paijian = substr_count($search_data['Traces'][$i]['AcceptStation'], '派件');
                                    if($paisong > 0 || $paijian > 0) {
                                        $state = 3;
                                    }
                                }
                                if($search_data['State'] == 3) {
                                    $state = 4;
                                }
                            }
                        }
                    }
                }
            }
            $data['state'] = $state;
            // $data['traces'] = array_reverse($traces);
            $data['traces'] = $traces;
        }
        $this->success('请求成功', $data);
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
        $Common = new Common;
        if($order['status'] == 2){
            // 如果记录不是空的，证明有给上级、推荐人的返利
            $user_back_money = db('user_back_money')
            ->where('order_id='.$order_id)
            ->order('id','asc')
            ->select();
            if(!empty($user_back_money)){
                foreach ($user_back_money as $key => $value) {
                    
                    if($user_back_money[$key]['inviter_id'] > 0){
                        if($user_back_money[$key]['back_money'] > 0){
                            db('user')
                            ->where('id='.$user_back_money[$key]['inviter_id'])
                            ->setInc('money', $user_back_money[$key]['back_money']);
                            $Common->ins_money_log($user_back_money[$key]['inviter_id'], 1, 1, $user_back_money[$key]['back_money'], '余额', '返利');
                            //添加奖励金记录表
                            $user = db('user')->where('id='.$user_back_money[$key]['user_id'])->find();
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
                        //加到上级用户余额
                        db('user')
                        ->where('id='.$user_back_money[$key]['p_user_id'])
                        ->setInc('money', $user_back_money[$key]['shipment_money'] + $user_back_money[$key]['profit']);
                        //扣除上级玉扣款字段
                        db('user')
                        ->where('id='.$user_back_money[$key]['p_user_id'])
                        ->setDec('lock_goods_money', $user_back_money[$key]['shipment_money']);
                        $Common->ins_money_log($user_back_money[$key]['p_user_id'], 1, 1, $user_back_money[$key]['shipment_money'], '余额', '成本价');
                        if($user_back_money[$key]['profit'] > 0){
                            $Common->ins_money_log($user_back_money[$key]['p_user_id'], 1, 1, $user_back_money[$key]['profit'], '余额', '利润');
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
     * @param int $refund_type    售后类型:1=仅退款,2=退货退款(新增)
     * @param int $goods_num 退货数量
     * @param int $content   问题描述
     * @param int $images    图片
     */
    public function apply_refund_order()
    {
        $user_id = $this->request->request('user_id');
        $order_id = $this->request->request('order_id');
        $order_goods_id = $this->request->request('order_goods_id');
        $refund_type = $this->request->request('refund_type');
        $goods_num = $this->request->request('goods_num');
        $content = $this->request->request('content');
        $images = $this->request->request('images');
        if(!$user_id || !$order_id || !$order_goods_id || !$goods_num || !$refund_type) $this->error('参数不能为空', null, -1);

        $order = db('order')->where('id='.$order_id)->find();//shipping_time
        if($order['shipping_time'] > 0){
            if(time() - $order['shipping_time'] > 3600*24*20) $this->error('超出退单时间', null, -6);
        }
        
        if($user_id != $order['user_id']) $this->error('参数错误', null, -2);
        $order_goods = db('order_goods')->where('id='.$order_goods_id)->find();
        if($order_id != $order_goods['order_id']) $this->error('参数错误', null, -2);
        if($goods_num > $order_goods['goods_num']) $this->error('退货数量错误', null, -3);
        if($refund_type == 2){
            if($order['status'] < 2) $this->error('暂未发货，只能仅退款', null, -4);
        }

        $level_id = db('user')->where('id='.$user_id)->value('level_id');
        $price = db('order_goods a')
        ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
        ->join('order c','a.order_id=c.id','INNER')
        ->where('a.id='.$order_goods_id)
        ->value('b.price'.$level_id);

        $refund_order['user_id'] = $user_id;
        $refund_order['refund_type'] = $refund_type;
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
            db('order')->where('id='.$order_id)->setField('is_refund',1);
            $this->success('请求成功', null, 1);
        }else{
            $this->error('操作失败', null, -5);
        }


    }

    /**
     * 退货详情
     * @param int $user_id  用户ID
     * @param int $refund_order_id  退单商品ID
     //退货状态:-3=失效,-3=不可以退货,-2=可以退货,-1=审核未通过,0=等待审核,1=寄回商品,2=系统审核,3=退款完成
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
            //后台审核成功 买家发货时间为5天之内 超时退货订单失效 重新发起
            if($refund_order['updatetime'] + 3600*24*5 < time()){
                db('refund_order')->where('id='.$refund_order['id'])->setField('status',-3);
                $ro = db('refund_order')->where("status>='0' and status<'3' and order_id=".$refund_order['order_id'])->select();
                if(empty($ro)) {
                    db('order')->where('id='.$refund_order['order_id'])->setField('is_refund', 0);
                }
                $this->success('退货订单失效，超出发货时间，请重新发起', null, -2);
            }
            $order_goods['refund_order_state'] = '1.退货商品，2.发货单';
        }
        if($refund_order['status'] == 2)
        {
            $Accept['AcceptStation'] = '买家已确认发货';
            $Accept['AcceptTime'] = date('Y-m-d H:i:s',$refund_order['courier_time']);
            $traces[] = $Accept;
            $courier = db('courier')->where('courier_company like "%'.$refund_order['courier_company'].'%"')->find();
            if(!empty($courier)) {
                $KdSearch = new KdSearch;
                $search_data = $KdSearch->getOrderTracesByJson($courier['courier_code'], $refund_order['courier_no']);
                if(!empty($search_data)) {
                    if(!empty($search_data['Traces'])){
                        for ($i=0; $i<=count($search_data['Traces'])-1; $i++) { 
                            $traces[] = $search_data['Traces'][$i];
                        }
                    }else{
                        $Accept['AcceptStation'] = $search_data['Reason'];
                        $Accept['AcceptTime'] = '';
                        $traces[] = $Accept;
                    } 
                }else{
                    $Accept['AcceptStation'] = '暂时无法显示物流信息';
                    $Accept['AcceptTime'] = '';
                    $traces[] = $Accept;
                }
            }else{
                $Accept['AcceptStation'] = '暂时无法显示物流信息';
                $Accept['AcceptTime'] = '';
                $traces[] = $Accept;
            }
            $order_goods = array_reverse($traces);
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
        $data['courier_time'] = time();
        $res = db('refund_order')->where('id='.$refund_order_id)->update($data);
        if($res){
            $this->success('添加成功', null, 1);
        }else{
            $this->success('添加失败', null, -3);
        }
    }

    /**
     *  取消退货
     *
     * @param int $user_id  用户ID
     * @param int $refund_order_id  退货订单ID
     */
    public function cancel_refund()
    {
        $user_id = $this->request->request('user_id');
        $refund_order_id = $this->request->request('refund_order_id');
        if(!$user_id || !$refund_order_id) $this->error('参数不能为空', null, -1);

        //判断订单状态
        $refund_order = db('refund_order')->where('id='.$refund_order_id)->find();
        if($refund_order['status'] != "0") {
            $this->error('订单状态错误', null, -2);
        }
        //处理订单状态
        db('refund_order')->where('id='.$refund_order_id)->setField('status', -2);
        //判断改订单ID下是否还有退款订单，如果没有将订单的状态改为正常订单
        // $refund_orders = db('refund_order')->where('status >= "0" and status < "3" and order_id='.$refund_order['order_id'])->select();
        // if(empty($refund_orders)){
            db('order')->where('id='.$refund_order_id)->setField('is_refund', 0);
        // }
        $this->success('处理成功');
    }

    /**
     * 退货订单列表
     *
     * @param int $user_id  用户ID
     */
    public function refund_order_list()
    {
        $user_id = $this->request->request('user_id');
        $page = $this->request->request('page');
        $count = $this->request->request('count');
        if(empty($page)) $page = 1;
        if(empty($count)) $count = 10;
        $start = ($page - 1) * $count;
        if(!$user_id) $this->error('参数user_id不能为空', null, -1);

        $refund_order = db('refund_order')
        ->order('createtime','desc')
        ->limit($start,$count)
        ->select();
        $data = [];
        foreach ($refund_order as $key => $value) {
            $data[$key]['id'] = $refund_order[$key]['id'];
            $data[$key]['user_id'] = $refund_order[$key]['user_id'];
            $data[$key]['order_id'] = $refund_order[$key]['order_id'];
            $data[$key]['order_goods_id'] = $refund_order[$key]['order_goods_id'];
            $data[$key]['refund_type'] = $refund_order[$key]['refund_type'];
            $data[$key]['order_sn'] = $refund_order[$key]['order_sn'];
            $data[$key]['status'] = $refund_order[$key]['status'];
            $data[$key]['goods_num'] = $refund_order[$key]['goods_num'];
            $data[$key]['order_price'] = $refund_order[$key]['order_price'];

            $order_goods = db('order_goods a')
            ->join('spec_goods_price b','a.goods_id=b.goods_id and a.item_id=b.id','INNER')
            ->field('a.goods_id,a.goods_name,a.goods_num,a.spec_key_name,b.spec_image as image,a.goods_price as price')
            ->where('a.id='.$refund_order[$key]['order_goods_id'])
            ->find();
            // foreach ($order_goods as $key1 => $value1) {
            //     if(!empty($order_goods[$key1]['image'])) $order_goods[$key1]['image'] = get_http_host($order_goods[$key1]['image']);
            // }
            if(!empty($order_goods['image'])) $order_goods['image'] = get_http_host($order_goods['image']);
            $data[$key]['goods_data'] = $order_goods;
        }
        $this->success('请求成功', $data);
    }

    /******************退货******************/























}
