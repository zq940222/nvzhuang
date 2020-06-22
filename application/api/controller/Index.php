<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->fetch();
    }

    // 轮播图
    public function ads_list()
    {
        $data = db('ads')->select();

        $this->success('请求成功',$data);
    }

    // 品牌推荐
    public function cate_brand()
    {
        $id = db('category')
        ->where('name="品牌"')
        ->value('id');
        $data = db('category')
        ->where('pid='.$id.' and flag like "%index%"')
        ->select();

        $this->success('请求成功',$data);
    }

    // 款式推荐
    public function cate_list()
    {
        $id = db('category')
        ->where('name="品牌"')
        ->value('id');
        $data = db('category')
        ->where('pid!='.$id.' and flag like "%index%"')
        ->select();

        $this->success('请求成功',$data);
    }

    // 新品推荐
    public function best_list()
    {
        $data = db('category')
        ->where('pid!=0 and flag like "%recommend%"')
        ->select();

        $this->success('请求成功',$data);
    }

    // 热卖推荐
    public function hot_list()
    {
        $user_id = $this->request->request('user_id');
        $level = db('user')->where('id='.$user_id)->value('level_id');

        $field = 'id as goods_id,goods_sn,name,cover_image,price,tag_price,price'.$level.' as lprice';
        $where = 'is_on_sale=1 and is_hot=1';

        $data = db('goods')
        ->field($field)
        ->where($where)
        ->limit(8)
        ->select();

        $this->success('请求成功',$data);
    }
}
