<?php

namespace app\admin\controller;

use think\facade\Db;

class AdminAuth extends Base
{
    public function list()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        try {
            $paginator = Db::table('admin_auth')
                ->order('id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $res = [
                'list' => $paginator->items(),       // 当前页数据
                'total' => $paginator->total(),       // 总记录数
                'page' => $paginator->currentPage(), // 当前页码
                'page_size' => $paginator->listRows(),    // 每页记录数
                'total_page' => $paginator->lastPage(),    // 总页数
            ];
            return apiSuccess('', $res);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function add()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        if (!$this->isSuperAdmin()) {
            return apiError('no_auth');
        }
        try {
            $res = Db::name('admin_auth')->where(['name' => $params['name']])->find();
            if ($res) {
                return apiError('已存在');
            }
            Db::name('admin_auth')->insert($params);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }

    }

    public function edit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        if (!$this->isSuperAdmin()) {
            return apiError('no_auth');
        }
        try {
            $res = Db::name('admin_auth')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }
            Db::name('admin_auth')
                ->where(['id' => $params['id']])
                ->update($params);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function del()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        if (!$this->isSuperAdmin() || $params['id'] == 1) {
            return apiError('no_auth');
        }
        try {
            $res = Db::name('admin_auth')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }
            Db::name('admin_auth')
                ->where('id', $params['id'])
                ->delete();
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
