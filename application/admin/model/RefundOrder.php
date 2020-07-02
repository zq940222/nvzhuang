<?php

namespace app\admin\model;

use think\Model;


class RefundOrder extends Model
{

    

    

    // 表名
    protected $name = 'refund_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'refund_type_text',
    ];
    

    
    public function getStatusList()
    {
        return ['-3' => __('失效'),'-1' => __('Status -1'), '0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getRefundTypeList()
    {
        return ['1' => __('仅退款'), '2' => __('退货退款')];
    }


    public function getRefundTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function users()
    {
        // return $this->hasone('user', 'id', 'user_id')->field('id,nickname');
        return $this->belongsTo('user', 'user_id', 'id',[],'LEFT')->setEagerlyType(0);
    }

    //获取所有订单商品
    public function OrderGoods()
    {
        return $this->hasMany('OrderGoods', 'order_id', 'order_goods_id');
    }


}
