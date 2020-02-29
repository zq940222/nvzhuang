<?php

namespace app\admin\model;

use think\Model;


class OrderGoods extends Model
{





    // 表名
    protected $name = 'orderGoods';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    //自定义初始化
    protected function initialize()
    {
        parent::initialize();
    }

    public function goods()
    {
        return $this->hasOne('goods','id','goods_id');
    }

}
