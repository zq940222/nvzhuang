<?php

namespace app\admin\model;

use think\Model;


class AgentApply extends Model
{

    

    

    // 表名
    protected $name = 'agent_apply';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'pay_type_text',
        'status_text'
    ];
    

    
    public function getPayTypeList()
    {
        return ['1' => __('Pay_type 1'), '2' => __('Pay_type 2')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '-1' => __('Status -1')];
    }


    public function getPayTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_type']) ? $data['pay_type'] : '');
        $list = $this->getPayTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
