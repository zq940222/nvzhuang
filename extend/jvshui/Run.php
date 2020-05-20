<?php
namespace jvshui;
   include 'Service.php';
   include 'Config.php';

   $env =array(
       'sandbox' => true, //测试环境还是正式环境
       'debug_mode'=>false, //是否输出日志
       'partner_id' => 'ywv5jGT8ge6Pvlq3FZSPol345asd',
       'partner_key' => 'ywv5jGT8ge6Pvlq3FZSPol2323',
       'token' => '181ee8952a88f5a57db52587472c3798'
   );

   $cfg = new Config($env['sandbox'],$env['partner_id'],$env['partner_key'],$env['token'],'','',$env['debug_mode']);

   $service = new Service($cfg);
   
   //普通接口调用方式,查询全部店铺信息
   $response = $service->shops_query(); 
 
?>