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