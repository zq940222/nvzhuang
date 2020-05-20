<?php
namespace jvshui;
  class RpcClient 
  {
      public function __construct($cfg,$ts=null) {
          $this->config = $cfg;
          $this->ts = $ts;
      }
      
      
      public function call($action,$parameters) {
        
        $system_params = $this->get_system_params($action, $parameters);
        
        $request_url = $this->get_request_url($system_params);
        
        $result = $this->post($request_url, $parameters, $system_params, $action);
        
        return $result;
      }

      public function get_request_url($params) {
        $url_map = $this->config->get_request_url();
        
        if(strstr($params['method'], 'jst')) {
            return $url_map['qm'];
        }
        else
        {
          return $url_map['jst'];
        }
        
      }

      public function get_system_params($action, $params,$sys_params=[])
      {
        # 默认系统参数
        $system_params = array(
            'partnerid' => $this->config->partner_id,
            'token' => $this->config->token,
            'method' => $action,
            'ts' => time()
        );
        //是否包含jst
        if(strstr($action, 'jst')) {
          $system_params['sign_method'] = 'md5';
          $system_params['format'] = 'json';
          $system_params['app_key'] = $this->config->taobao_appkey;
          $system_params['timestamp'] = date("Y-m-d H:i:s",$system_params['ts']);
          $system_params['target_app_key'] = $this->config->target_appkey;

          if($this->config->taobao_appkey == '' || $this->config->taobao_secret == '')  throw new Exception('请提供淘宝app_key和app_sercet');

        }
        
        return $this->generate_signature($system_params, $params);
      }

      //计算验签
      public function generate_signature($system_params, $params=null)
      {
         $sign_str = '';
         ksort($system_params);
         //奇门接口
         if(strstr($system_params['method'], 'jst')) 
         {
            $method = str_replace('jst.','',$system_params['method']);
            $jstsign = $method.$this->config->partner_id."token".$this->config->token."ts".$system_params['ts'].$this->config->partner_key;
            
            if($this->config->debug_mode) echo '计算jstsign源串->'.$jstsign;
            
            $system_params['jstsign'] = md5($jstsign);

            //如果有业务参数则合并
            if($params!=null) 
            {
                $system_params = array_merge($system_params,$params);
                ksort($system_params);

                foreach($system_params as $key=>$value) {
                   if(is_array($value)) 
                   {
                      $sign_str.= $key.join(',',$value);
                      continue;
                   }
                   $sign_str .=$key.strval($value); 
                }
            }
            if($this->config->debug_mode) echo '计算sign源串->'.$this->config->taobao_secret.$sign_str.$this->config->taobao_secret;
            $system_params['sign'] = strtoupper(md5($this->config->taobao_secret.$sign_str.$this->config->taobao_secret));
         }
         else  //普通接口
         {
            $no_exists_array = array('method','sign','partnerid','partnerkey');
            
            $sign_str = $system_params['method'].$system_params['partnerid'];
            foreach($system_params as $key=>$value) {
              
              if(in_array($key,$no_exists_array)) {
                continue;
              }
              $sign_str.=$key.strval($value); 
            }

            $sign_str.=$this->config->partner_key;
            if($this->config->debug_mode) echo '计算sign源串'.$sign_str;
            $system_params['sign'] = md5($sign_str);
         }

         return $system_params;

      }

      //发送请求
      public function post($url, $data, $url_params, $action)
      {
        $post_data = '';
        try
        {
          if(strstr($action,'jst')) {
            foreach($data as $key=>$value) {
                if(is_array($value)) {
                    $url_params[$key] = join(',',$value);
                    continue;
                }
                $url_params[$key]=$value;
            }
          }
          else
          {
            $post_data = json_encode($data);
          }

          $url .='?'.http_build_query($url_params);
          if($this->config->debug_mode) echo $url;
          $ch = curl_init($url);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
          curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type: application/x-www-form-urlencoded'
          ));

          $result = curl_exec($ch);
          if (curl_errno($ch)) {
              print curl_error($ch);
          }
          curl_close($ch);
          return json_decode($result,true);
          
        }
        catch(Exception $e)
        {
          return null;
        }
         
      }

  }
?>