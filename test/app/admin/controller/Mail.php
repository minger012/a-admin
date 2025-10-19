<?php

namespace app\admin\controller;

use app\admin\validate\MailValidate;
use app\common\validate\CommonValidate;
use think\facade\Db;

class Mail extends Base
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
        $userWhere = [];
        if (!empty($params['uid'])) {
            $where[] = ['uid', '=', $params['uid']];
        }
        if (!empty($params['type'])) {
            $where[] = ['type', '=', $params['type']];
        }
        if (!empty($params['sTime'])) {
            $where[] = ['create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['create_time', '<=', $params['eTime']];
        }
        try {
            if (!$this->isSuperAdmin()) {
                $where[] = $userWhere[] = ['admin_id', '=', $this->adminInfo['id']];
            } elseif (!empty($params['admin_username'])) {
                $admin_id = Db::name('admin')->where(['username' => $params['admin_username']])->value('id');
                if (!empty($admin_id)) {
                    $where[] = ['admin_id', '=', $admin_id];
                }
            }
            $paginator = Db::table('user_mail')
                ->where($where)
                ->order('id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            $res = [
                'list' => $list,       // 当前页数据
                'userList' => Db::table('user')->where($userWhere)->field('id,username')->select()->toArray(),
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

    // 发邮件
    public function send()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new MailValidate();
        if (!$validate->check($params)) {
            return apiError($validate->getError());
        }
        try {
            $where = [];
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            if ($params['sendType'] == 2) {// 发指定
                if (empty($params['uids'])) {
                    return apiError('uid 有误');
                }
                $where[] = ['id', 'in', implode(',', $params['uids'])];
            }
            $uidArr = Db::table('user')->where($where)->field('id,admin_id')->select()->toArray();
            if (empty($uidArr)) {
                return apiError('用户不存在');
            }
            $sqlArr = [];
            foreach ($uidArr as $value) {
                $sqlArr[] = [
                    'uid' => $value['id'],
                    'title' => $params['title'],
                    'content' => $params['content'],
                    'type' => $params['type'],
                    'admin_id' => $value['admin_id'],
                    'update_time' => time(),
                    'create_time' => time(),
                ];
            }
            Db::name('user_mail')->insertAll($sqlArr);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function edit()
    {
        try {
            $input = request()->getContent();
            $params = json_decode($input, true);
            $validate = new MailValidate();
            if (!$validate->append('id', 'require|number')->check($params, $validate->rule_edit)) {
                return apiError($validate->getError());
            }
            $params['update_time'] = time();
            Db::name('user_mail')
                ->where(['id' => $params['id']])
                ->update($params);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function del()
    {
        try {
            $input = request()->getContent();
            $params = json_decode($input, true);
            if (!(int)$params['id']) {
                return apiError('params_error');
            }
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            Db::name('user_mail')->where(['id' => $params['id']])->delete();
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}