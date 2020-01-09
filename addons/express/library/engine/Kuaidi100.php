<?php

namespace addons\express\library\engine;

use fast\Http;

/**
 * 快递100
 * @author amplam 343937632@qq.com
 * @Date   2019年5月13日 19:04:33
 */
class Kuaidi100 extends Server
{

    /* @var array $config 配置 */
    private $config;

    //url
    private $reqURL = 'http://poll.kuaidi100.com/poll/query.do';
    private $codeURL = 'http://m.kuaidi100.com/autonumber/auto?num=';

    /**
     * 构造方法
     * WxPay constructor.
     * @param $config
     */
    public function __construct($config = array())
    {
        $this->config = $config;

    }

    /**
     * 查询
     *
     * @return boolean
     */
    public function query($express_id, $shipper_code = "")
    {

        // 缓存索引
        $cacheIndex = 'express_kuaidi100_' . $express_id;

        if ($data = cache($cacheIndex)) {
            return $data;
        }

        if (!$shipper_code) {

            $data = Http::get($this->codeURL . $express_id);
            $data = json_decode($data,true);
            if (!isset($data[0]) || !isset($data[0]->comCode)) {
                $this->error = "获取快递100物流代码错误";
                return false;
            }
            $shipper_code = $data[0]->comCode;
        }


        // 参数设置
        $postData = [
            'customer' => $this->config['customer'],
            'param'    => json_encode([
                'resultv2' => '1',
                'com'      => $shipper_code,
                'num'      => $express_id
            ])
        ];
        $postData['sign'] = strtoupper(md5($postData['param'] . $this->config['app_key'] . $postData['customer']));
        // 请求快递100 api
        $result = http::post($this->reqURL, $postData);
        $express = json_decode($result, true);
        // 记录错误信息
        if (isset($express['returnCode']) || !isset($express['data'])) {
            $this->error = isset($express['message']) ? $express['message'] : '查询失败';
            return false;
        }
        // 记录缓存, 时效5分钟
        Cache::set($cacheIndex, $express, 300);
        return $express;
    }


}