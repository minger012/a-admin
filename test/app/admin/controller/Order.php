<?php

namespace app\admin\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;

class Order extends Base
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
        if (!empty($params['uid'])) {
            $where[] = ['o.uid', '=', $params['uid']];
        }
        if (!empty($params['username'])) {
            $where[] = ['u.username', '=', $params['username']];
        }
        if (!empty($params['fb_id'])) {
            $where[] = ['o.fb_id', '=', $params['fb_id']];
        }
        if (!empty($params['type'])) {
            $where[] = ['o.type', '=', $params['type']];
        }
        if (!empty($params['sTime'])) {
            $where[] = ['o.create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['o.create_time', '<=', $params['eTime']];
        }
        try {
            if (!$this->isSuperAdmin()) {
                $where[] = ['o.admin_id', '=', $this->adminInfo['id']];
            } elseif (!empty($params['admin_username'])) {
                $admin_id = Db::name('admin')->where(['username' => $params['admin_username']])->value('id');
                if (!empty($admin_id)) {
                    $where[] = ['o.admin_id', '=', $admin_id];
                }
            }
            $query = Db::table('order')
                ->alias('o')
                ->join('user u', 'o.uid = u.id')
                ->where($where)
                ->field('o.*, u.username');
            $paginator = $query->order('o.id', 'desc')// 按ID倒序（可选）
            ->paginate([
                'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                'page' => $params['page'] ?? 1,     // 当前页码
            ]);
            $res = [
                'list' => $paginator->items(),       // 当前页数据
                'money' => round(Db::table('order')
                    ->alias('o')
                    ->join('user u', 'o.uid = u.id')
                    ->where($where)
                    ->sum('o.money'), 2),
                'total' => $paginator->total(),       // 总记录数
                'page' => $paginator->currentPage(), // 当前页码
                'page_size' => $paginator->listRows(),    // 每页记录数
                'total_page' => $paginator->lastPage(),    // 总页数
            ];
            return apiSuccess('success', $res);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }

    }

}
