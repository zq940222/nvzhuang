<?php
namespace jvshui;
class Config {

    private $__url_map = array(
        "jst"=>null,
        "qm"=>null
    );
    function __construct($sandbox, $partner_id, $partner_key,$token,$taobao_appkey, $taobao_secret,$debug_mode,$target_appkey='23060081') 
    {
        $this->sandbox = $sandbox;
        $this->partner_id = $partner_id;
        $this->partner_key = $partner_key;
        $this->token = $token;
        $this->taobao_appkey = $taobao_appkey;
        $this->taobao_secret = $taobao_secret;
        $this->target_appkey = $target_appkey;
        $this->debug_mode = $debug_mode;
    }

    public function get_request_url(){
        if($this->sandbox) {
            $this->__url_map["jst"] = "https://c.jushuitan.com/api/open/query.aspx";
            $this->__url_map["qm"] = "";
        }
        else {
             $this->__url_map["jst"] = "https://open.erp321.com/api/open/query.aspx";
             $this->__url_map["qm"] = "";
        }

        return $this->__url_map;
    }
}
?>