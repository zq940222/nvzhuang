<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 微信验证接口
 */
class Wechat extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['checkSignature','callbackAction'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce     = $_GET["nonce"];
        $token     = 'qscong921026';
        $tmpArr    = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr    = implode( $tmpArr );
        $tmpStr    = sha1( $tmpStr );
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }

    //使用
    public function callbackAction()
    {
        if ($this->checkSignature()) {
            echo $_GET['echostr'];
            exit;
        }else{
            //等会进行补全
        }
    }

}
