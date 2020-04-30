<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 购物车接口
 */
class Cart extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = [];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    /**
     * 添加购物车
     *
     * @param int $user_id  用户id
     * @param int $goods_id  商品ID
     * @param int $group_id  商品规格组合ID
     * @param int $num  购买数量
     * @param string $price  商品原价
     * @param string $lprice  商品折扣价
     */
    public function join_cart()
    {
        $data['user_id']       = $user_id = $this->request->request('user_id');
        $data['goods_id']      = $goods_id = $this->request->request('goods_id');
        $data['goods_spec_id'] = $goods_spec_id = $this->request->request('group_id');
        $data['num']           = $num = (int)$this->request->request('num');
        $data['lprice']        = $lprice = $this->request->request('lprice');
        $data['price']         = $price = $this->request->request('price');

        foreach ($data as $key => $value) {
            if(empty($value)) $this->error(__('参数：'.$key.' 不能为空'), null, -1);
        }

        if($num <= 0) $this->error('购买数量不能小于0', null, -2);

        $store_count = db('spec_goods_price')->where('id',$goods_spec_id)->value('store_count');

        if($store_count - $num < 0) $this->error('商品库存不足', null, -3);

        $user = db('user')->where('id='.$user_id)->find();

        $spec_goods_price = db('spec_goods_price')->where('id',$goods_spec_id)->value('price'.$user['level_id']);

        if($lprice != $spec_goods_price) $this->error('传入价格与实际不符', null, -5);

        // db('spec_goods_price')->where('id',$goods_spec_id)->setDec('store_count', $num);

        $data['createtime'] = time();

        $res = db('cart')->insert($data);

        if($res) {
            $this->success('添加成功', null, 1);
        } else {
            $this->error('添加失败', null, -4);
        }

    }

    /**
     * 购物车列表
     *
     * @param int $user_id  用户id
     */
    public function cart_list()
    {
        $user_id = $this->request->request('user_id');

        if(empty($user_id)) $this->error(__('user_id 不能为空'), null, -1);

        $data = db('cart')
        ->field('cart_id,goods_id,goods_spec_id,lprice,price,num')
        ->where('is_delete=0 and user_id='.$user_id)
        ->select();
        if(!empty($data)){
            foreach ($data as $key => $value) {
                $goods = db('goods')->field('name,cover_image')->where('id',$data[$key]['goods_id'])->find();
                $data[$key] = array_merge($value,$goods);
                $data[$key]['spec_name'] = db('spec_goods_price')->where('id='.$data[$key]['goods_spec_id'])->value('key_name');
                $data[$key]['cover_image'] = get_http_host($data[$key]['cover_image']);
            }
        }
        

        $this->success('请求成功', $data);

    }

    /**
      *删除商品
      * @param string cart_ids  购物车商品ID串（用英文都好拼接）
      * @param int $user_id  用户id
    */
    public function del_cart_goods()
    {
        $cart_ids = $this->request->request('cart_ids');
        $user_id = $this->request->request('user_id');

        if(empty($cart_ids) || empty($user_id)){
            $this->error('参数不能为空', null, -1);
        }
        $cart = db('cart')->where('user_id='.$user_id.' and cart_id in ('.$cart_ids.')')->select();
        if(empty($cart)) {
            $this->error('购物车商品不存在', null, -2);
        }
        $res = db('cart')
        ->where('cart_id in ('.$cart_ids.')')
        ->delete();

        if($res){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    /**
      *修改购物车商品数量
      * @param string cart_id  购物车商品ID
      * @param int $user_id  用户id
      * @param int $num  购买数量
    */
    public function edit_cart_goods_num()
    {
        $cart_id = $this->request->request('cart_id');
        $user_id = $this->request->request('user_id');
        $num = $this->request->request('num');

        if(empty($cart_id) || empty($user_id) || empty($num)){
            $this->error('参数不能为空', null, -1);
        }
        $cart = db('cart')->where('user_id='.$user_id.' and cart_id='.$cart_id)->find();
        if(empty($cart)) {
            $this->error('购物车商品不存在', null, -2);
        }
        $res = db('cart')
        ->where('cart_id='.$cart_id)
        ->setField('num',$num);

        $this->success('操作成功');
    }























}
