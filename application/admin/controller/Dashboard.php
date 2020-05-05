<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Config;

/**
 * 控制台
 *
 * @icon fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        $seventtime = \fast\Date::unixtime('day', -30);
        $paylist = $createlist = [];
        for ($i = 0; $i < 30; $i++)
        {
            $day = date("Y-m-d", $seventtime + ($i * 86400));
            // $createlist[$day] = mt_rand(20, 200);
            // $paylist[$day] = mt_rand(1, mt_rand(1, $createlist[$day]));
            $lastday = date('Y-m-d', strtotime("$day +1 day"));
            $firstday_time = strtotime($day);
            $lastday_time = strtotime($lastday);
            $createlist[$day] = db('order')
            ->where('deleted=0 and createtime>='.$firstday_time.' and createtime<='.$lastday_time)
            ->count();
            $paylist[$day] = db('order')
            ->where('deleted=0 and createtime>='.$firstday_time.' and createtime<='.$lastday_time)
            ->count();
        }
        $hooks = config('addons.hooks');
        $uploadmode = isset($hooks['upload_config_init']) && $hooks['upload_config_init'] ? implode(',', $hooks['upload_config_init']) : 'local';
        $addonComposerCfg = ROOT_PATH . '/vendor/karsonzhang/fastadmin-addons/composer.json';
        Config::parse($addonComposerCfg, "json", "composer");
        $config = Config::get("composer");
        $addonVersion = isset($config['version']) ? $config['version'] : __('Unknown');

        //总会员数
        $user_total = db('user')->where('status="1"')->count();
        //总订单数
        $order_total = db('order')->count();
        //总退单数
        $refund_order_total = db('refund_order')->count();
        $today_time = strtotime(date('Y-m-d',time()));
        //今日注册
        $today_agent_apply = db('agent_apply')
        ->where('status="1" and createtime>'.$today_time)
        ->count();
        //今日升级
        $today_agent_upgrade = db('agent_upgrade')
        ->where('status="1" and createtime>'.$today_time)
        ->count();
        //今日订单
        $today_order = db('order')
        ->where('deleted=0 and createtime>'.$today_time)
        ->count();
        //今日待处理订单
        $wait_today_order = db('order')
        ->where('deleted=0 and status="1" and createtime>'.$today_time)
        ->count();
        //今日退单
        $today_refund_order = db('refund_order')
        ->where('createtime>'.$today_time)
        ->count();
        //今日待处理退单
        $wait_today_refund_order = db('refund_order')
        ->where('status="0" and createtime>'.$today_time)
        ->count();
        // dump($paylist);
        // dump($createlist);
        $this->view->assign([
            'totaluser'        => $user_total,
            'totalviews'       => 219390,
            'totalorder'       => $order_total,
            'totalorderamount' => 174800,
            'totalrefundorder' => $refund_order_total,
            'todayagentapply'  => $today_agent_apply,
            'todayagentupgrade' => $today_agent_upgrade,
            'todayuserlogin'   => 321,
            'todayusersignup'  => 430,
            'todayorder'       => $today_order,
            'todayrefundorder' => $today_refund_order,
            'unsettleorder'    => $wait_today_order,
            'waittodayrefundorder'    => $wait_today_refund_order,
            'sevendnu'         => '80%',
            'sevendau'         => '32%',
            'paylist'          => $paylist,
            'createlist'       => $createlist,
            'addonversion'       => $addonVersion,
            'uploadmode'       => $uploadmode
        ]);

        return $this->view->fetch();
    }

    public function get_order_data($date)
    {
        $seventtime = strtotime($date);
        $paylist = $createlist = [];
        for ($i = 0; $i < 7; $i++)
        {
            $day = date("Y-m-d", $seventtime + ($i * 86400));
            // $createlist[$day] = mt_rand(20, 200);
            // $paylist[$day] = mt_rand(1, mt_rand(1, $createlist[$day]));
            $lastday = date('Y-m-d', strtotime("$day +1 day"));
            $firstday_time = strtotime($day);
            $lastday_time = strtotime($lastday);
            $createlist[$day] = db('order')
            ->where('deleted=0 and createtime>='.$firstday_time.' and createtime<='.$lastday_time)
            ->count();
            $paylist[$day] = db('order')
            ->where('deleted=0 and createtime>='.$firstday_time.' and createtime<='.$lastday_time)
            ->count();
        }
        $data['createlist'] = $createlist;
        $data['paylist'] = $paylist;

        // dump($data);
        return $data;
    }

}
