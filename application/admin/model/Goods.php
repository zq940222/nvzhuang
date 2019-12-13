<?php

namespace app\admin\model;

use think\Model;


class Goods extends Model
{

    

    

    // 表名
    protected $name = 'goods';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_on_sale_text',
        'is_free_shipping_text',
        'is_new_text',
        'is_hot_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    
    public function getIsOnSaleList()
    {
        return ['0' => __('Is_on_sale 0'), '1' => __('Is_on_sale 1')];
    }

    public function getIsFreeShippingList()
    {
        return ['0' => __('Is_free_shipping 0'), '1' => __('Is_free_shipping 1')];
    }

    public function getIsNewList()
    {
        return ['0' => __('Is_new 0'), '1' => __('Is_new 1')];
    }

    public function getIsHotList()
    {
        return ['0' => __('Is_hot 0'), '1' => __('Is_hot 1')];
    }


    public function getIsOnSaleTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_on_sale']) ? $data['is_on_sale'] : '');
        $list = $this->getIsOnSaleList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsFreeShippingTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_free_shipping']) ? $data['is_free_shipping'] : '');
        $list = $this->getIsFreeShippingList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsNewTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_new']) ? $data['is_new'] : '');
        $list = $this->getIsNewList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsHotTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_hot']) ? $data['is_hot'] : '');
        $list = $this->getIsHotList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
