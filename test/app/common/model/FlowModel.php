<?php
/*
 * 投注-期号
 */

namespace app\common\model;

use think\facade\Db;
use think\Model;

class FlowModel extends Model
{
    protected $table = 'flow';

    // 操作类型
    const type_1 = 1;//后台充值
    const type_2 = 2;//人工提现
    const type_3 = 3;//广告结算
    const type_4 = 4;//广告投放
    const type_5 = 5;//后台扣款
    const type_6 = 6;//投资失败
    const type_7 = 7;//提现失败
}