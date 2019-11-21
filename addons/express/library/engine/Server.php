<?php

namespace addons\express\library\engine;


/**
 * 快递查询抽象类
 * Class server
 * @package addons\express\library\engine
 */
abstract class Server
{
    /**
     * 错误信息
     * @var
     */
    protected $error;

    /**
     * 查询
     * @return mixed
     */
    abstract public function query($express_id, $shipper_code = "");

    /**
     * 返回错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }


}
