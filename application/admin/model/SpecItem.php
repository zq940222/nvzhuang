<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/19
 * Time: 15:48
 */

namespace app\admin\model;


use think\Model;

class SpecItem extends Model
{
    // 表名
    protected $name = 'spec_item';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function spec()
    {
        return $this->belongsTo('Spec','spec_id','id');
    }
}