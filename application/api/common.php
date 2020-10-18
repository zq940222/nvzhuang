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
	if(!empty($url)){
		$url = '/uploads'.explode('/uploads', $url)[1];
	}
	$return_url = 'http://'.$_SERVER['HTTP_HOST'].$url;
    
    return $return_url;
}

    /**
     * 添加money_log记录
     * @param $user_id 会员ID
     * @param $money_type 消费类型：1.余额｜2.货款
     * @param $type 类型:1=收入,2=支出
     * @param $money 资金
     * @param $memo 备注
     * @param $desc 描述
     */
    function ins_money_log($user_id, $money_type, $type, $money, $memo, $desc = '', $before=0, $after=0)
    {
        $money_log['user_id'] = $user_id;
        $money_log['money_type'] = $money_type;
        $money_log['type'] = $type;
        $money_log['money'] = $money;
        $money_log['memo'] = $memo;
        $money_log['desc'] = $desc;
        $money_log['before'] = $before;
        $money_log['after'] = $after;
        $money_log['createtime'] = time();
        db('user_money_log')->insert($money_log);

        return true;
    }

    /**
     * 添加message记录
     * @param $user_id 会员ID
     * @param $money_type 消费类型：1.余额｜2.货款
     * @param $type 类型:1=收入,2=支出
     * @param $money 资金
     * @param $memo 备注
     * @param $desc 描述
     */
    function ins_message($user_id, $message_title, $message_content)
    {
        $message['user_id'] = $user_id;
        $message['message_category'] = 1;
        $message['message_title'] = $message_title;
        $message['message_content'] = $message_content;
        $message['status'] = 1;
        $message['is_read'] = 0;
        $message['createtime'] = time();
        db('message')->insert($message);

        return true;
    }