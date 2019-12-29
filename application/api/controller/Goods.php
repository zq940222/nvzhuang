<?php

namespace app\api\controller;

use app\common\controller\Api;

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
    protected $noNeedLogin = ['goods_list'];
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
        $brand_id = $this->request->request('brand_id');
        $style_id = $this->request->request('style_id');
        $activity_id = $this->request->request('activity_id');
        $price_interval = $this->request->request('price_interval');
        $search = $this->request->request('search');

        if (!$user_id) {
            $this->error(__('用户ID不能为空'), null, -1);
        }

        $level = db('user')->where('id='.$user_id)->value('level_id');

        $field = 'id,goods_sn,name,cover_image,price,price'.$level;
        $where = 'is_on_sale=1';
        $order = ['weigh'=>'asc'];

        if(empty($page)) $page = $this->page;

        if(empty($count)) $count = $this->count;

        $start = ( $page - 1 ) * $count;

        if(!empty($is_new) && $is_new == 1) $where .= ' and is_new=1';

        if(!empty($sentiment)) $order['click_count'] = $sentiment;

        if(!empty($price)) $order['price'.$level] = $price;

        if(!empty($brand_id)) $where .= ' and brand_id='.$brand_id;

        if(!empty($style_id)) $where .= ' and style_id='.$style_id;

        if(!empty($activity_id)) $where .= ' and activity_id='.$activity_id;

        if(!empty($price_interval)) $where .= ' and price'.$level.' between '.explode('-', $price_interval)[0].' and '.explode('-', $price_interval)[1];

        if(!empty($search)) $where .= ' and keywords like %'.$search.'%';

        $data = db('goods')
        ->field($field)
        ->where($where)
        ->order($order)
        ->limit($start, $count)
        ->select();

        $this->success('请求成功', $data);

    }
























}
