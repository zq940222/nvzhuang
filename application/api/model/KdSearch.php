<?php
namespace app\api\model;
use think\Model;

/**
 * 快递鸟查询物流接口
 */
class KdSearch extends Model
{

    //电商ID
    public $EBusinessID = '1620788';
    // public $EBusinessID = 'test1620788';
    //电商加密私钥，快递鸟提供，注意保管，不要泄漏
    public $AppKey = '3a6670d3-9753-42d4-9344-a3c42d4bab1c';
    // public $AppKey = '49bd4f0e-f8ed-4af6-861a-daeeb11a5bca';
    //请求url
    public $ReqURL = 'http://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx';
    // public $ReqURL = 'http://sandboxapi.kdniao.com:8080/kdniaosandbox/gateway/exterfaceInvoke.json';

    /**
     * @param  string $courier_code 快递公司编码
     * @param  string $courier_no 快递号
     * Json方式 查询订单物流轨迹
     */
    public function getOrderTracesByJson($courier_code, $courier_no){
        // $courier_code = 'ZTO';
        // $courier_no = '545106836035';
        // $courier_code = 'ZTO';
        // $courier_no = '75329726090623';
        // $courier_code = 'STO';
        // $courier_no = '773024059626882';
        // $courier_code = 'YD';
        // $courier_no = '4602171601790';
        $requestData= "{'OrderCode':'','ShipperCode':'".$courier_code."','LogisticCode':'".$courier_no."'}";
        
        $datas = array(
            'EBusinessID' => $this->EBusinessID,
            'RequestType' => '1002',
            'RequestData' => urlencode($requestData) ,
            'DataType' => '2',
        );
        $datas['DataSign'] = $this->encrypt($requestData, $this->AppKey);
        $result = json_decode( $this->sendPost($this->ReqURL, $datas) , true);   
        
        //根据公司业务处理返回的信息......
        
        return $result;
    }

    /**
     *  post提交数据 
     * @param  string $url 请求Url
     * @param  array $datas 提交的数据 
     * @return url响应返回的html
     */
    public function sendPost($url, $datas) {
        $temps = array();   
        foreach ($datas as $key => $value) {
            $temps[] = sprintf('%s=%s', $key, $value);      
        }   
        $post_data = implode('&', $temps);
        $url_info = parse_url($url);
        if(empty($url_info['port']))
        {
            $url_info['port']=80;   
        }
        $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
        $httpheader.= "Host:" . $url_info['host'] . "\r\n";
        $httpheader.= "Content-Type:application/x-www-form-urlencoded\r\n";
        $httpheader.= "Content-Length:" . strlen($post_data) . "\r\n";
        $httpheader.= "Connection:close\r\n\r\n";
        $httpheader.= $post_data;
        $fd = fsockopen($url_info['host'], $url_info['port']);
        fwrite($fd, $httpheader);
        $gets = "";
        $headerFlag = true;
        while (!feof($fd)) {
            if (($header = @fgets($fd)) && ($header == "\r\n" || $header == "\n")) {
                break;
            }
        }
        while (!feof($fd)) {
            $gets.= fread($fd, 128);
        }
        fclose($fd);  
        
        return $gets;
    }

    /**
     * 电商Sign签名生成
     * @param data 内容   
     * @param appkey Appkey
     * @return DataSign签名
     */
    public function encrypt($data, $appkey) {
        return urlencode(base64_encode(md5($data.$appkey)));
    }








}
