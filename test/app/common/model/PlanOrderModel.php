<?php

namespace app\common\model;

use think\Model;

class PlanOrderModel extends Model
{
    // 订单状态
    const state_0 = 0;// 待投放
    const state_1 = 1;// 匹配中
    const state_2 = 2;// 投放中
    const state_3 = 3;// 投放失败
    const state_4 = 4;// 等待结算
    const state_5 = 5;// 结算成功
}