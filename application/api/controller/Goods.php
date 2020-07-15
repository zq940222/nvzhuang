<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Config;

/**
 * 商品接口
 */
class Goods extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['goods_list','cate_list','goods_desc'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    public $page = 1;
    public $count = 10;

    /**
     * 商品列表
     *
     * @param int $user_id  用户id
     * @param int $page=1  页码
     * @param int $count=10  数量
     * @param int $is_new  是否最新:0=否,1=是
     * @param string $sentiment  人气：asc=从小到大，desc=从大到小（click_count）
     * @param string $price  价格：asc=从小到大，desc=从大到小
     * @param int $brand_id  品牌ID
     * @param int $style_id  款式ID
     * @param int $activity_id  活动ID
     * @param int $cate_id  活动ID
     * @param string $price_interval  价格区间
     * @param string $search  搜索
     */
    public function goods_list()
    {
        $user_id = $this->request->request('user_id');
        $page = $this->request->request('page');
        $count = $this->request->request('count');
        $is_new = $this->request->request('is_new');
        $sentiment = $this->request->request('sentiment');
        $price = $this->request->request('price');
        // $brand_id = $this->request->request('brand_id');
        // $style_id = $this->request->request('style_id');
        // $activity_id = $this->request->request('activity_id');
        $cate_id = $this->request->request('cate_id');
        $price_interval = $this->request->request('price_interval');
        $search = $this->request->request('search');

        if (!$user_id) {
            $this->error(__('用户ID不能为空'), null, -1);
        }

        $level = db('user')->where('id='.$user_id)->value('level_id');
        if($level == 5) $level = '';

        $field = 'id as goods_id,goods_sn,name,cover_image,price,tag_price,price'.$level.' as lprice';
        $where = 'is_on_sale=1';
        // $order = ['weigh'=>'asc'];
        $order = [];

        if(empty($page)) $page = $this->page;

        if(empty($count)) $count = $this->count;

        $start = ( $page - 1 ) * $count;

        if(!empty($is_new)) {
            if($is_new == 1){
                // $where .= ' and is_new=1';
                $order['createtime'] = 'desc';
            }else{
                $order['createtime'] = 'asc';
            }
        }

        if(!empty($sentiment)) $order['click_count'] = $sentiment;

        if(!empty($price)) $order['price'.$level] = $price;

        if(!empty($cate_id)) {
            $category = db('category')->where('id',$cate_id)->find();
            if($category['pid'] == 0){
                $cates = db('category')->where('pid',$cate_id)->column('id');
                foreach ($cates as $key => $value) {
                    $where_arr[$key] = " (cate_id LIKE '".$value."' OR cate_id LIKE '".$value.",%' OR cate_id LIKE '%,".$value.",%' OR cate_id LIKE '%,".$value."') ";
                }
                $where .= ' and ('.implode(' or ', $where_arr).')';
            }else{
                $where .= " and (cate_id LIKE '".$cate_id."' OR cate_id LIKE '".$cate_id.",%' OR cate_id LIKE '%,".$cate_id.",%' OR cate_id LIKE '%,".$cate_id."') ";
            }
        }

        if(!empty($price_interval)) $where .= ' and price'.$level.' between '.explode('-', $price_interval)[0].' and '.explode('-', $price_interval)[1];

        if(!empty($search)) $where .= ' and keywords like "%'.$search.'%" or name like "%'.$search.'%"';

        $data = db('goods')
        ->field($field)
        ->where($where)
        ->order($order)
        ->limit($start, $count)
        ->select();
        
        $this->success('请求成功', $data);

    }

    /**
     * 商品详情
     *
     * @param int $user_id  用户id
     */
    public function goods_desc()
    {
        $user_id = $this->request->request('user_id');
        $goods_id = $this->request->request('goods_id');

        if (!$user_id) {
            $this->error(__('用户ID不能为空'), null, -1);
        }

        db('goods')->where('id='.$goods_id)->setInc('click_count', 1);

        $level = db('user')->where('id='.$user_id)->value('level_id');
        if($level == 5) $level = '';
        
        $field = 'id as goods_id,goods_sn,name,cover_image,goods_images,price,tag_price,price'.$level.' as lprice,goods_content,store_count';
        $where = 'is_on_sale=1 and id='.$goods_id;

        $goods = db('goods')
        ->field($field)
        ->where($where)
        ->find();
        $spec_goods_price = db('spec_goods_price')->where('goods_id='.$goods_id)->find();
        if(!empty($spec_goods_price)){
            $goods['price1'] = $spec_goods_price['price1'];
            $goods['price2'] = $spec_goods_price['price2'];
            $goods['price3'] = $spec_goods_price['price3'];
            $goods['price4'] = $spec_goods_price['price4'];
            $goods['tag_price'] = $spec_goods_price['tag_price'];
            $goods['price'] = $spec_goods_price['price'];
            $goods['lprice'] = $spec_goods_price['price'.$level];
        }
        
        if(empty($goods)) $this->error(__('商品不存在'), null, -2);
        if(!empty($goods['cover_image'])) $goods['cover_image'] = get_http_host($goods['cover_image']);
        if(!empty($goods['goods_images'])) {
            $goods_images = explode(',', $goods['goods_images']);
            foreach ($goods_images as $key => $value) {
                $goods_images[$key] = get_http_host($value);
            }
            $goods['goods_images'] = $goods_images;
        }
        $goods['goods_content'] = str_replace('/uploads/', 'http://'.$_SERVER['HTTP_HOST'].'/uploads/', $goods['goods_content']);
        $spec_field = 'id as group_id,goods_id,key,key_name,price,tag_price,price'.$level.' as lprice,store_count,spec_image';

        $spec_goods_price = db('spec_goods_price')
        ->field($spec_field)
        ->where('goods_id='.$goods['goods_id'])
        ->select();
        $spec_ids = array();
        foreach ($spec_goods_price as $key => $value) {
            if(!empty($spec_goods_price[$key]['spec_image'])){
                $spec_goods_price[$key]['spec_image'] = get_http_host($spec_goods_price[$key]['spec_image']);
            }else{
                $spec_goods_price[$key]['spec_image'] = $goods['cover_image'];
            }
            $spec_key = explode(',', $value['key']);
            foreach ($spec_key as $key1 => $value1) {
                $spec_key_arr[] = $value1;
            }
        }
        $spec_key_arr = array_unique($spec_key_arr);

        $spec_id_arr = db('spec_item')
        ->where('id in ('.implode(',', $spec_key_arr).')')
        ->column('spec_id');
        $spec = db("spec")
        ->where('id in ('.implode(',', array_unique($spec_id_arr)).')')
        ->select();
        $spec_item = db('spec_item')
        ->field('id,spec_id,item')
        ->where('id in ('.implode(',', $spec_key_arr).')')
        ->select();
        foreach ($spec as $key => $value) {
            $spec_data[$key]['spec_id'] = $spec[$key]['id'];
            $spec_data[$key]['spec_name'] = $spec[$key]['name'];
            foreach ($spec_item as $key1 => $value1) {
                if($spec_data[$key]['spec_id'] == $spec_item[$key1]['spec_id']) {
                    $spec_data[$key]['spec_data'][] = $spec_item[$key1];
                }
            }
        }

        $data['goods'] = $goods;
        $data['goods_spec_data']['spec'] = $spec_data;
        $data['goods_spec_data']['group'] = $spec_goods_price;

        $this->success('请求成功', $data);

    }

    /**
     * 分类树
     */
    public function cate_list()
    {
        $data = db('category')
        ->field('id,pid,name,nickname,image')
        ->where('status="normal" and pid=0')
        ->order('weigh','asc')
        ->select();
        foreach ($data as $key => $value) {
            $data[$key]['child'] = db('category')
            ->field('id,pid,name,nickname,image')
            ->where('status="normal" and pid='.$data[$key]['id'])
            ->order('weigh','asc')
            ->select();
        }
        // $data = array();
        // $site = Config::get('site.categorytype');
        // ksort($site);
        // foreach ($site as $key => $value) {
        //     $cate = array();
        //     $cat_name = $value;
        //     $cat_data = db('category')
        //     ->field('id as '.$key.'_id,name')
        //     ->where('pid=0 and type="'.$key.'"')
        //     ->order('weigh','asc')
        //     ->select();
        //     if($key == 'style') {
        //         foreach ($cat_data as $keys => $values) {
        //             $cat_data[$keys]['child'] = db('category')
        //             ->field('id as '.$key.'_id,name')
        //             ->where('pid='.$cat_data[$keys][$key.'_id'].' and type="'.$key.'"')
        //             ->order('weigh','asc')
        //             ->select();
        //         }
        //     }
        //     if(!empty($cat_data)) {
        //         $cate['cat_name'] = $cat_name;
        //         $cate['cat_data'] = $cat_data;
        //         $data[] = $cate;
        //     }
            
        // }
        $this->success('请求成功', $data);
    }





















}
