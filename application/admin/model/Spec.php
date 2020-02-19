<?php

namespace app\admin\model;

use think\Model;


class Spec extends Model
{

    

    

    // 表名
    protected $name = 'spec';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }



    public function specItem()
    {
        return $this->hasMany('SpecItem','spec_id','id');
    }





}