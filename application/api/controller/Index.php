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
        $arr = [
            [
                'id'=>1,
                'val'=>'100'
            ],[
                'id'=>2,
                'val'=>'300'
            ],[
                'id'=>3,
                'val'=>'1000'
            ],[
                'id'=>4,
                'val'=>'100'
            ]
        ];
        $array = array_multisort(array_column($arr,'val'),SORT_DESC,$arr);
        dump($arr);die;
        $this->success('请求成功');
    }
}
