<?php

namespace addons\express;

use think\Addons;
use addons\express\library\Application;

/**
 * 快递查询接口插件
 */
class Express extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {
        return true;
    }

    /**
     * $data['express_id'] 快递单号
     * $data['shipper_ode'] 物流商代码【都不相同的，可以为空】
     * @param unknown $data
     */
    public function expressQuery($data)
    {
        if (!isset($data['express_id'])) {
            exception("快递单号不能为空");
        }
        $config = array();

        foreach ($this->getConfig() as $key => $r) {
            if ($r['is_open'] == 1) {
                $r['key'] = $key;
                $config = $r;
                break;
            }
        }
        if (!$config) {
            exception("没有启动查询接口");
        }
        $app = new Application($config);
        $rs = $app->query($data['express_id'], '');
        if ($rs) {
            return $rs;
        } else {
            exception($app->getError());
        }
    }
}
