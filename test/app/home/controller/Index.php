<?php

namespace app\home\controller;

use app\common\model\ConfigModel;
use app\common\validate\CommonValidate;
use think\facade\Db;
use TimeClass;

class Index extends Base
{
    public function index(ConfigModel $model)
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->time_id)) {
            return apiError($validate->getError());
        }
        $where = [
            ['uid', '=', $this->userInfo['id']],
            ['state', 'in', '2,4,5'],
        ];
        if ($params['id'] == 0) {
            $where[] = ['create_time', '>=', TimeClass::getTodayStart()];
        } elseif ($params['id'] == 1) {
            $where[] = ['create_time', '>=', TimeClass::getWeekStart()];
        } elseif ($params['id'] == 2) {
            $where[] = ['create_time', '>=', TimeClass::getMonthStart()];
        }
        try {
            $planOrderData = Db::table('plan_order')
                ->where($where)
                ->field('money,putIn,wait_putIn,show_num,click_num,click_money,profit')
                ->select()->toArray();
            $money = 0; // 总额
            $putIn = 0; // 已投放
            $wait_putIn = 0; // 待投放
            $show_num = 0; // 待投放
            $click_num = 0; // 点击数
            $click_money = 0; // 广告收入
            $profit = 0; // 利润
            foreach ($planOrderData as $v) {
                $money += $v['money'];
                $putIn += $v['putIn'];
                $wait_putIn += $v['wait_putIn'];
                $show_num += $v['show_num'];
                $click_num += $v['click_num'];
                $click_money += $v['click_money'];
                $profit += $v['profit'];
            }
            $userData = Db::table('user')
                ->where('id', $this->userInfo['id'])
                ->field('username,score,lv,sign,sign_time,image')
                ->find();
            $userData['isSign'] = isToday($userData['sign_time']) ? 1 : 0;
            $userData['image'] = fileDomain($userData['image']);
            return apiSuccess('success', [
                'userData' => $userData,
                'planOrderData' => [
                    'money' => round($money, 2),
                    'count' => count($planOrderData),
                    'putIn' => round($putIn, 2),
                    'wait_putIn' => round($wait_putIn, 2),
                    'show_num' => $show_num,
                    'click_num' => $click_num,
                    'click_money' => round($click_money, 2),
                    'profit' => round($profit, 2),
                ],
            ]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

}
