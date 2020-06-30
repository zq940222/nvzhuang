<?php

namespace app\api\controller;

use app\common\controller\Api;
// use think\Config;
use \jvshui\Service;
use \jvshui\Config;

/**
 * 库存同步接口
 */
class Store extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = '*';
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    protected $env;

    protected $cfg;

    function __construct()
    {
        $env = [
            // 'sandbox' => true, //测试环境还是正式环境
            // 'debug_mode'=>false, //是否输出日志
            // 'partner_id' => 'ywv5jGT8ge6Pvlq3FZSPol345asd',
            // 'partner_key' => 'ywv5jGT8ge6Pvlq3FZSPol2323',
            // 'token' => '181ee8952a88f5a57db52587472c3798'
            'sandbox' => false, //正式环境
            'debug_mode'=>false, //是否输出日志
            'partner_id' => '396ff00a775fbf8e47979f254570313d',
            'partner_key' => '99a29c12f88ba0725e905d507ac70f17',
            'token' => '3a76a21c0a2e163a3f77ba30bda73ef5'
        ];
        $this->env = $env;
        $this->cfg = new Config($env['sandbox'],$env['partner_id'],$env['partner_key'],$env['token'],'','',$env['debug_mode']);
    }

    // 刷新token
    public function refresh_token()
    {
        $cfg = $this->cfg;

        $service = new Service($cfg);

        $action = 'refresh.token';

        $service->shops_query($action);
    }

    // 查询库存
    public function synchro($sku_id='66660109')
    {
        $cfg = $this->cfg;

        $service = new Service($cfg);

        $action = 'inventory.query';

        $params = [
            'wms_co_id' => 10557038,
            'page_index' => 1,
            'page_size' => 30,
            // 'modified_begin' => , //datetime          修改起始时间，和结束时间必须同时存在，时间间隔不能超过七天
            // 'modified_end' => , //datetime          修改起始时间，和结束时间必须同时存在，时间间隔不能超过七天
            'sku_ids' => $sku_id,
        ];
        // $params = [
        //     'page_index' => 1,
        //     'page_size' => 30,
        //     'modified_begin' => date('Y-m-d H:i:s', strtotime('2019-12-01')), //datetime          修改起始时间，和结束时间必须同时存在，时间间隔不能超过七天
        //     'modified_end' => date('Y-m-d H:i:s', strtotime('2019-12-07')), //datetime          修改起始时间，和结束时间必须同时存在，时间间隔不能超过七天
        // ];
        //普通接口调用方式,查询全部店铺信息
        $response = $service->shops_query($action,$params);
        return $response;
    }

    // 同步库存
    public function synchro_goods_num($sku_id='66660109', $qty=1)
    {
        $cfg = $this->cfg;

        $service = new Service($cfg);

        $action = 'inventory.wms.upload';

        $params = [
            [
                'sku_id' => $sku_id,
                'qty' => $qty
            ]
        ];
        //普通接口调用方式,查询全部店铺信息
        $response = $service->shops_query($action,$params);
        // dump($response);
        return $response;
    }

    // 新建盘点单
    public function inventory_upload($order_sn='sn66660109',$sku_id='66660109', $qty=1)
    {
        $cfg = $this->cfg;

        $service = new Service($cfg);

        $action = 'jushuitan.inventory.upload';

        $params = [
            'wms_co_id' => 10557038,    //分仓公司ID
            'type' => 'check',    //盘点类型 :全量:check ;增量:adjust(默认adjust)
            'is_confirm' => true,   //是否自动确认，默认false，增量同步时只能传true
            'data' => [
                [
                    'so_id' => $order_sn,    //外部单号，会在ERP判断重复，仅可用一次
                    'warehouse' => 1,    //仓库;主仓=1，销退仓=2， 进货仓=3，次品仓 = 4
                    // 'remark' => '',     //备注
                    'items' => [
                        [
                            "qty" => $qty,
                            "sku_id" => (string)$sku_id
                        ]
                    ]
                ]
            ]
        ];
        //普通接口调用方式,查询全部店铺信息
        $response = $service->shops_query($action,$params);
        dump($response);
        return $response;
    }

    // 查询仓位
    /*
array(9) {
    ["page_size"] => int(0)
    ["page_index"] => int(0)
    ["data_count"] => int(0)
    ["page_count"] => int(0)
    ["has_next"] => bool(false)
    ["datas"] => array(2) {
        [0] => array(4) {
            ["name"] => string(15) "杭州近江店"
            ["co_id"] => int(10367626)
            ["wms_co_id"] => int(10391408)
            ["status"] => string(6) "生效"
        }
        [1] => array(4) {
            ["name"] => string(12) "自建商城"
            ["co_id"] => int(10367626)
            ["wms_co_id"] => int(10557038)
            ["status"] => string(6) "生效"
        }
    }
    ["code"] => int(0)
    ["issuccess"] => bool(true)
    ["msg"] => NULL
}
    */
    public function partner()
    {
        $cfg = $this->cfg;

        $service = new Service($cfg);

        $action = 'wms.partner.query';

        //普通接口调用方式,查询全部店铺信息
        $response = $service->shops_query($action,[]);
        dump($response);
        // return $response;
    }
















}
