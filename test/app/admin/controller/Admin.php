<?php

namespace app\admin\controller;

use app\admin\validate\AdminValidate;
use app\common\validate\CommonValidate;
use think\facade\Db;

class Admin extends Base
{
    public function list()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        try {
            $where = [];
            $paginator = Db::table('admin')
                ->alias('a')
                ->join('admin_auth b', 'a.auth_id=b.id')
                ->where($where)
                ->field('a.id,a.username,a.auth_id,a.state,a.create_time,a.pid,b.name as auth_name')
                ->order('a.id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            foreach ($list as $k => $v) {
                if (!empty($v['pid'])) {
                    $list[$k]['pusername'] = Db::table('admin')->where('id', $v['pid'])->value('username');
                } else {
                    $list[$k]['pusername'] = '';
                }
            }
            $res = [
                'list' => $list,       // 当前页数据
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

    public function add()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new AdminValidate();
        if (!$validate->check($params)) {
            return apiError($validate->getError());
        }
        try {
            if (!empty($params['pusername'])) {
                $pid = Db::name('admin')->where(['username' => $params['pusername']])->value('id');
                if (!$pid) {
                    return apiError('non_existent');
                }
                unset($params['pusername']);
            }
            $params['id'] = rand(1000000, 9999999);
            $params['create_time'] = time();
            $params['last_login_time'] = time();
            $params['pid'] = $pid ?? $this->adminInfo['id'];
            $params['password'] = getMd5Password($params['password']);
            Db::name('admin')->insert($params);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function edit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new AdminValidate();
        if (!$validate->check($params, $validate->save_rule)) {
            return apiError($validate->getError());
        }
        if (!$this->isSuperAdmin()) {
            return apiError('no_auth');
        }
        try {
            $res = Db::name('admin')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }
            $update = [];
            if (!empty($params['pusername'])) {
                $pid = Db::name('admin')->where(['username' => $params['pusername']])->value('id');
                if (!$pid) {
                    return apiError('non_existent');
                }
                $update['pid'] = $pid;
            }
            if (!empty($params['password'])) {
                $update['password'] = getMd5Password($params['password']);
            } else if (isset($params['password'])) {
                unset($params['password']);
            }
            if (!empty($params['auth_id'])) {
                $update['auth_id'] = $params['auth_id'];
            }
            Db::name('admin')
                ->where(['id' => $params['id']])
                ->update($update);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function del()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        if (!$this->isSuperAdmin()) {
            return apiError('no_auth');
        }
        try {
            $res = Db::name('admin')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }
            Db::name('admin')->where(['id' => $params['id']])->delete();
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
