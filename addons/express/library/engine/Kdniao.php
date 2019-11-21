<?php

namespace addons\express\library\engine;

use fast\Http;

/**
 * 快递鸟
 * @author amplam 343937632@qq.com
 * @Date   2019年5月13日 19:04:33
 */
class Kdniao extends Server
{

    /* @var array $config 配置 */
    private $config;

    //单号识别url
    private $reqURL = 'http://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx';

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
        $cacheIndex = 'express_kdniao_' . $express_id;

        if ($data = cache($cacheIndex)) {
            return $data;
        }

        if (!$shipper_code) {
            $data = $this->getOrderTracesByJson($express_id);

            $data = json_decode($data);
            if (!isset($data->Shippers) || !isset($data->Shippers[0]->ShipperCode)) {
                $this->error = "物流代码错误";
                return false;
            }
            $shipper_code = $data->Shippers[0]->ShipperCode;
        }


        $requestData = "{'OrderCode':'','ShipperCode':'{$shipper_code}','LogisticCode':'{$express_id}'}";

        $datas = array(
            'EBusinessID' => $this->config['ebusiness_id'],
            'RequestType' => '1002',
            'RequestData' => urlencode($requestData),
            'DataType'    => '2',
        );
        $datas['DataSign'] = $this->encrypt($requestData, $this->config['app_key']);
        $result = Http::post($this->reqURL, $datas);
        $result = json_decode($result, true);

        if (!$result['Success']) {
            $this->error = $result['Reason'];
            return false;
        }
        //格式转化和快递100一样
        $retrun_data = [
            'message' => 'ok',
            'nu'      => $express_id,
            'ischeck' => $result['State'] == 3 ? 1 : 0,
            'com'     => $shipper_code,
            'state'   => $result['State'],
        ];

        foreach ($result['Traces'] as $r) {
            $temp['time'] = $r['AcceptTime'];
            $temp['ftime'] = $r['AcceptTime'];
            $temp['context'] = $r['AcceptStation'];
            $retrun_data['data'][] = $temp;

        }
        //缓存5分钟
        cache($cacheIndex, json_encode($retrun_data), 300);
        return json_encode($retrun_data);
    }

    /**
     * Json方式 单号识别
     */
    public function getOrderTracesByJson($code)
    {
        $requestData = "{'LogisticCode':'{$code}'}";
        $datas = array(
            'EBusinessID' => $this->config['ebusiness_id'],
            'RequestType' => '2002',
            'RequestData' => urlencode($requestData),
            'DataType'    => '2',
        );
        $datas['DataSign'] = $this->encrypt($requestData, $this->config['app_key']);

        $result = Http::post($this->reqURL, $datas);


        return $result;
    }

    /**
     * 电商Sign签名生成
     * @param data 内容
     * @param appkey Appkey
     * @return DataSign签名
     */
    private function encrypt($data, $appkey)
    {
        return urlencode(base64_encode(md5($data . $appkey)));
    }

}