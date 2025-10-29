<?php

namespace app\home\controller;

use app\common\model\ConfigModel;
use app\common\service\OnlineUserService;
use app\common\validate\CommonValidate;
use EncryptClass;
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
                ->field('username,score,lv,sign,sign_time,image,code')
                ->find();
            $userData['isSign'] = isToday($userData['sign_time']) ? 1 : 0;
            $userData['image'] = fileDomain($userData['image']);
            $withdraw = Db::table('withdraw')
                ->alias('a')
                ->join('user b', 'a.uid=b.id')
                ->where('a.state', 1)
                ->order('a.id desc')
                ->limit(50)
                ->field('a.money,b.username')
                ->select()->toArray();
            // 如果数据不足50条，补充假数据
            $currentCount = count($withdraw);
            if ($currentCount < 50) {
                $fakeCount = 50 - $currentCount;
                $fakeNames = [
                    'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles',
                    'Christopher', 'Daniel', 'Matthew', 'Anthony', 'Donald', 'Mark', 'Paul', 'Steven', 'Andrew', 'Kenneth',
                    'Joshua', 'Kevin', 'Brian', 'George', 'Edward', 'Ronald', 'Timothy', 'Jason', 'Jeffrey', 'Ryan',
                    'Jacob', 'Gary', 'Nicholas', 'Eric', 'Jonathan', 'Stephen', 'Larry', 'Justin', 'Scott', 'Brandon',
                    'Benjamin', 'Samuel', 'Gregory', 'Frank', 'Alexander', 'Raymond', 'Patrick', 'Jack', 'Dennis', 'Jerry',
                    'Tyler', 'Aaron', 'Jose', 'Adam', 'Nathan', 'Henry', 'Zachary', 'Douglas', 'Peter', 'Kyle'
                ];

                for ($i = 0; $i < $fakeCount; $i++) {
                    $randomName = $fakeNames[array_rand($fakeNames)] . ' ' . $fakeNames[array_rand($fakeNames)];
                    $randomMoney = round(mt_rand(10, 500) + mt_rand(0, 99) / 100, 1);

                    $withdraw[] = [
                        'money' => $randomMoney,
                        'username' => $randomName
                    ];
                }
            }
            $planOrderCount = Db::table('plan_order')->count();
            return apiSuccess('success', [
                'userData' => $userData,
                'onlineCount' => 80212 + OnlineUserService::count(),
                'adCount' => 43988 + $planOrderCount,
                'planOrderCount' => 53460 + $planOrderCount,
                'withdrawList' => $withdraw,
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
