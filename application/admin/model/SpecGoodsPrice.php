<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/19
 * Time: 17:40
 */

namespace app\admin\model;


use think\Model;

class SpecGoodsPrice extends Model
{
    // 表名
    protected $name = 'spec_goods_price';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

}