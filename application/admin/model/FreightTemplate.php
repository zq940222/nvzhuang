<?php

namespace app\admin\model;

use think\Model;


class FreightTemplate extends Model
{

    

    

    // 表名
    protected $name = 'freight_template';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_enable_default_text'
    ];
    

    
    public function getIsEnableDefaultList()
    {
        return ['0' => __('Is_enable_default 0'), '1' => __('Is_enable_default 1')];
    }


    public function getIsEnableDefaultTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_enable_default']) ? $data['is_enable_default'] : '');
        $list = $this->getIsEnableDefaultList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function freightConfig()
    {
        return $this->hasMany('FreightConfig', 'template_id', 'template_id');
    }


}
