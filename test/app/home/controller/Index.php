<?php

namespace app\home\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;
use TimeClass;

class Index extends Base
{
    public function index()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->time_id)) {
            return apiError($validate->getError());
        }
        $where = [['uid', '=', $this->userInfo['id']]];
        if ($params['id'] == 1) {
            $where[] = ['create_time', '>=', TimeClass::getTodayStart()];
        } elseif ($params['id'] == 2) {
            $where[] = ['create_time', '>=', TimeClass::getWeekStart()];
        } elseif ($params['id'] == 4) {
            $where[] = ['create_time', '>=', TimeClass::getMonthStart()];
        }
        try {
            $planOrderData = Db::table('plan_order')
                ->where($where)
                ->field('money,score,lv,sign,sign_time,image')
                ->find();

            $userData = Db::table('user')
                ->where('id', $this->userInfo['id'])
                ->field('username,score,lv,sign,sign_time,image')
                ->find();
            $userData['isSign'] = isToday($userData['sign_time']) ? 1 : 0;

            return apiSuccess('success', [
                'userData' => $userData
            ]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

}
