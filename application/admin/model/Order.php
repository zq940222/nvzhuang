<?php

namespace app\admin\model;

use think\Model;


class Order extends Model
{

    

    

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'shipping_time_text',
        'confirm_time_text',
        'deleted_text'
    ];
    

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4')];
    }

    public function getDeletedList()
    {
        return ['1' => __('Deleted 1'), '0' => __('Deleted 0')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getShippingTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['shipping_time']) ? $data['shipping_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getConfirmTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['confirm_time']) ? $data['confirm_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getDeletedTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['deleted']) ? $data['deleted'] : '');
        $list = $this->getDeletedList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setShippingTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setConfirmTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
