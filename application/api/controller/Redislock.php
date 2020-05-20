<?php
namespace app\api\controller;

use app\common\controller\Api;
/**
 * 
 */
class Redislock extends Api
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = '*';
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = '*';

    public $_redis;
    
    public function __construct(){
        $this->_redis = new \Redis();
        $this->_redis->connect('127.0.0.1',6379);
    }    

    /**
     * 获取锁
     * @param  String  $key    锁标识
     * @param  Int     $expire 锁过期时间
     * @return Boolean
     */
    public function lock($key, $expire=5, $num=5){
        $is_lock = $this->_redis->setnx($key, time()+$expire);

        if(!$is_lock) {
            //获取锁失败则重试{$num}次
            for($i = 0; $i < $num; $i++){
 
                $is_lock = $this->_redis->setnx($key, time()+$expire);
 
                if($is_lock){
                    break;
                }
                sleep(1);
            }
        }

        // 不能获取锁
        if(!$is_lock){

            // 判断锁是否过期
            $lock_time = $this->_redis->get($key);

            // 锁已过期，删除锁，重新获取
            if(time()>$lock_time){
                $this->unlock($key);
                $is_lock = $this->_redis->setnx($key, time()+$expire);
            }
        }

        return $is_lock? true : false;
    }

    /**
     * 释放锁
     * @param  String  $key 锁标识
     * @return Boolean
     */
    public function unlock($key){
        return $this->_redis->del($key);
    }

    


}


?>