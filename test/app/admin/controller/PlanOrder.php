<?php

namespace app\admin\controller;

use app\admin\validate\PlanOrderValidate;
use app\common\model\PlanOrderModel;
use app\common\validate\CommonValidate;
use think\facade\Db;

class PlanOrder extends Base
{
    public function list()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        $where = [];
        $planWhere = [];
        if (!empty($params['uid'])) {
            $where[] = ['a.uid', '=', $params['uid']];
        }
        if (!empty($params['username'])) {
            $where[] = ['b.username', '=', $params['username']];
        }
        if (!empty($params['short_name'])) {
            $where[] = ['b.short_name', '=', $params['short_name']];
        }
        if (!empty($params['fb_id'])) {
            $where[] = ['a.fb_id', '=', $params['fb_id']];
        }
        if (!empty($params['order_no'])) {
            $where[] = ['a.order_no', '=', $params['order_no']];
        }
        if (isset($params['state']) && $params['state'] != '') {
            $where[] = ['a.state', '=', $params['state']];
        }
        if (!empty($params['sTime'])) {
            $where[] = ['a.create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['a.create_time', '<=', $params['eTime']];
        }
        try {
            if (!$this->isSuperAdmin()) {
                $where[] = $planWhere[] = ['a.admin_id', '=', $this->adminInfo['id']];
            }
            $paginator = Db::table('plan_order')
                ->alias('a')
                ->join('user b', 'a.uid = b.id')
                ->field('a.*,b.username,b.short_name')
                ->where($where)
                ->order('a.id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            $res = [
                'list' => $list,       // 当前页数据
                'total' => $paginator->total(),       // 总记录数
                'page' => $paginator->currentPage(), // 当前页码
                'page_size' => $paginator->listRows(),    // 每页记录数
                'total_page' => $paginator->lastPage(),    // 总页数
            ];
            return apiSuccess('success', $res);
        } catch (\Exception $e) {
            return apiError($e);
        }
    }

    public function edit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new PlanOrderValidate();
        if (!$validate->append('id', 'require|number')->check($params)) {
            return apiError($validate->getError());
        }
        try {
            $res = Db::name('plan')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }
            $params['update_time'] = time();
            Db::name('plan')
                ->where(['id' => $params['id']])
                ->update($params);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e);
        }
    }

    public function del()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        if (!(int)$params['id']) {
            return apiError('params_error');
        }
        try {
            $where = [
                ['id', '=', $params['id']],
                ['state', '=', PlanOrderModel::state_2],
            ];
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            Db::name('plan')->where($where)->delete();
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e);
        }
    }
}