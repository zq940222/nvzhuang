<?php 
namespace jvshui;
include 'Rpc_client.php';
class Service {
    private $__client = null;
    public function __construct($cfg,$ts=null) {
        $this->__client = new RpcClient($cfg);
        
    }

    //店铺查询  普通接口
    public function shops_query($action, $params=null) {
        if($params == null) $params = (object)array();
        return $this->__client->call($action,$params);
    }
}

?>