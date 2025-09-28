<?php

namespace app\admin\model;

use EncryptClass;
use think\facade\Cache;
use think\facade\Db;
use think\Model;

class OrderModel extends Model
{
    protected $table = 'order';

    // 订单类型
    const TYPE_1 = 1;// 人工
    const TYPE_2 = 2;// 虚拟
    const TYPE_3 = 3;// 赠送
}