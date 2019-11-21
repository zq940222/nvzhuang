<?php

namespace addons\express\library;

class Application
{

    /**
     * 配置信息
     * @var array
     */
    private $config = [];

    /**
     * 服务提供者
     * @var array
     */
    private $providers = [
        'kdniao'    => 'Kdniao',
        'kuaidi100' => 'Kuaidi100',
    ];


    private $engine;    // 当前引擎类

    public function __construct($options = [])
    {
        $this->config = array_merge($this->config, is_array($options) ? $options : []);

        //注册服务提供者
        $this->engine = $this->registerProviders();
    }

    /**
     * 执行查询
     */
    public function query($express_id, $shipper_code = "")
    {
        return $this->engine->query($express_id, $shipper_code);
    }

    /**
     * 注册服务提供者
     */
    private function registerProviders()
    {
        $objname = __NAMESPACE__ . "\\engine\\" . $this->providers[$this->config['key']];
        return new $objname($this->config);
    }

    /**
     * 获取错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this->engine->getError();
    }
}
