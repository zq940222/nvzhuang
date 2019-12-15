<?php
/**
 * 生成数据返回值
 */
function jsonReturn($msg,$status = -1,$data = []){
	if(isset($data['status']))return json_encode($data);
	$rs = ['status'=>$status,'msg'=>$msg];
	if(!empty($data))$rs['data'] = $data;
	return json_encode($rs);
}