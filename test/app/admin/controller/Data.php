<?php

namespace app\admin\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;

class Data extends Base
{
    public function register()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
//        $validate = new CommonValidate();
//        if (!$validate->check($params, $validate->page_rule)) {
//            return apiError($validate->getError());
//        }
        $where = [];
        if (!empty($params['sTime'])) {
            $where[] = ['create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['create_time', '<=', $params['eTime']];
        }
        try {
            if (!$this->isSuperAdmin()) {
                $where[] = $planWhere[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            $list = Db::table('user')
                ->where($where)
                ->field('create_time')
                ->order('id', 'desc')
                ->select()
                ->toArray();
            $arr = [];
            foreach ($list as $key => $value) {
                $date = date('Y-m-d', $value['create_time']);
                if (!isset($arr[$date])) {
                    $arr[$date] = 0;
                }
                $arr[$date]++;
            }
            $result = [];
            foreach ($arr as $key => $value) {
                $result[] = [
                    'date' => $key,
                    'count' => $value
                ];
            }
            return apiSuccess('success', $result);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function withdraw()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
//        $validate = new CommonValidate();
//        if (!$validate->check($params, $validate->page_rule)) {
//            return apiError($validate->getError());
//        }
        $where = [['state', '=', 1]];
        if (!empty($params['sTime'])) {
            $where[] = ['create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['create_time', '<=', $params['eTime']];
        }
        try {
            if (!$this->isSuperAdmin()) {
                $where[] = $planWhere[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            $list = Db::table('withdraw')
                ->where($where)
                ->field('money,create_time')
                ->order('id', 'desc')
                ->select()
                ->toArray();
            $arr = [];
            foreach ($list as $key => $value) {
                $date = date('Y-m-d', $value['create_time']);
                if (!isset($arr[$date])) {
                    $arr[$date] = 0;
                }
                $arr[$date] += $value['money'];
            }
            $result = [];
            foreach ($arr as $key => $value) {
                $result[] = [
                    'date' => $key,
                    'count' => $value
                ];
            }
            return apiSuccess('success', $result);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
