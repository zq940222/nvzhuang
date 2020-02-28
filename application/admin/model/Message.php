<?php

namespace app\admin\model;

use think\Model;


class Message extends Model
{

    

    

    // 表名
    protected $name = 'message';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'message_category_text',
        'status_text',
        'is_read_text'
    ];
    

    
    public function getMessageCategoryList()
    {
        return ['1' => __('Message_category 1'), '2' => __('Message_category 2')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getIsReadList()
    {
        return ['0' => __('Is_read 0'), '1' => __('Is_read 1')];
    }


    public function getMessageCategoryTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['message_category']) ? $data['message_category'] : '');
        $list = $this->getMessageCategoryList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsReadTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_read']) ? $data['is_read'] : '');
        $list = $this->getIsReadList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
