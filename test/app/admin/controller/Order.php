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
            $where[] = ['a.uid', '=', $params['uid']];
        }
        if (!empty($params['username'])) {
            $where[] = ['b.username', '=', $params['username']];
        }
        if (!empty($params['fb_id'])) {
            $where[] = ['a.fb_id', '=', $params['fb_id']];
        }
        if (!empty($params['type'])) {
            $where[] = ['a.type', '=', $params['type']];
        }
        if (!empty($params['sTime'])) {
            $where[] = ['a.create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['a.create_time', '<=', $params['eTime']];
        }
        try {
            if (!$this->isSuperAdmin()) {
                $where[] = ['a.admin_id', '=', $this->adminInfo['id']];
            } elseif (!empty($params['admin_username'])) {
                $admin_id = Db::name('admin')->where(['username' => $params['admin_username']])->value('id');
                if (!empty($admin_id)) {
                    $where[] = ['a.admin_id', '=', $admin_id];
                }
            }
            $query = Db::table('order')
                ->alias('a')
                ->join('user b', 'a.uid = b.id')
                ->where($where)
                ->field('a.*, b.username,b.short_name');
            $paginator = $query->order('a.id', 'desc')// 按ID倒序（可选）
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
