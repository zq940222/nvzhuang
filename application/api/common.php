<?php
/**
 * 生成数据返回值
 */
function jsonReturn($msg,$code = -1,$data = []){
	if(isset($data['code']))return json_encode($data);
	$rs = ['code'=>$code,'msg'=>$msg,'time'=>time()];
	if(!empty($data))$rs['data'] = $data;
	return json_encode($rs);
}

/**
 * 给图片添加域名信息
 */
function get_http_host($url)
{
	$return_url = 'http://'.$_SERVER['HTTP_HOST'].$url;
    
    return $return_url;
}