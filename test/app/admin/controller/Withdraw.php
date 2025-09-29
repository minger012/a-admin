<?php

namespace app\admin\controller;

use app\common\model\FlowModel;
use app\common\validate\CommonValidate;
use think\facade\Db;

class Withdraw extends Base
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
            $where[] = ['w.uid', '=', $params['uid']];
        }
        if (!empty($params['state'])) {
            $where[] = ['w.state', '=', $params['state']];
        }
        if (!empty($params['fb_id'])) {
            $where[] = ['w.fb_id', '<=', $params['fb_id']];
        }
        if (!empty($params['sTime'])) {
            $where[] = ['w.create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['w.create_time', '<=', $params['eTime']];
        }
        try {
            if (!$this->isSuperAdmin()) {
                $adminId = $this->adminInfo['id'];
                $where[] = ['u.admin_id', '=', $adminId];
            } elseif (!empty($params['admin_username'])) {
                $admin_id = Db::name('admin')->where(['username' => $params['admin_username']])->value('id');
                if (!empty($admin_id)) {
                    $where[] = ['w.admin_id', '=', $admin_id];
                }
            }
            $paginator = Db::table('withdraw')
                ->alias('w')
                ->join('user u', 'w.uid = u.id')
                ->where($where)
                ->field('w.*, u.username')
                ->order('w.id', 'desc')// 按ID倒序（可选）
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
            return apiSuccess('success', $res);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 编辑
    public function edit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)) {
            return apiError($validate->getError());
        }
        try {
            $res = Db::name('withdraw')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }
            $update['update_time'] = time();
            $update['currency'] = $params['currency'];
            $update['address'] = $params['address'];
            Db::name('plan')
                ->where(['id' => $params['id']])
                ->update($update);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 审核
    public function audit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)
            || !$validate->check($params, $validate->withdraw_state)
        ) {
            return apiError($validate->getError());
        }
        try {
            // 开始事务
            Db::startTrans();
            $where = [['w.id', '=', $params['id']]];

            $query = Db::table('withdraw')
                ->alias('w')
                ->join('user u', 'w.uid = u.id');

            if (!$this->isSuperAdmin()) {
                $where[] = ['u.admin_id', '=', $this->adminInfo['id']];
            }

            // Get withdraw info that matches our criteria
            $withdrawInfo = $query->where($where)->field('w.*')->find();
            if ($withdrawInfo['state'] != 0) {
                throw new \Exception(lang('params_error'));
            }
            // 审核通过
            if ($params['state'] == 1) {
                // 扣除冻结资金
                Db::table('user')->where('username', $withdrawInfo['username'])
                    ->dec('freeze_money', $withdrawInfo['money'])
                    ->update();
            } elseif ($params['state'] == 2) {
                // 审核失败
                $userInfo = Db::table('user')
                    ->where('username', $withdrawInfo['username'])
                    ->lock(true)
                    ->find();
                // 扣除冻结资金返回余额 跟 额度
                Db::table('user')->where('username', $withdrawInfo['username'])
                    ->dec('freeze_money', $withdrawInfo['money'])
                    ->inc('withdraw_limit', $withdrawInfo['money'])
                    ->inc('money', $withdrawInfo['money'])
                    ->update();
                // 插入流水
                Db::table('flow')->insert([
                    'uid' => $userInfo['id'],
                    'type' => FlowModel::type_7,
                    'before' => $userInfo['money'],
                    'after' => $userInfo['money'] + $withdrawInfo['money'],
                    'cha' => $withdrawInfo['money'],
                    'create_time' => time(),
                ]);
            }
            // 修改审核状态
            Db::name('withdraw')
                ->where(['id' => $params['id']])
                ->update([
                    'state' => $params['state'],
                    'user_remarks' => $params['user_remarks'],
                    'remarks' => $params['remarks'],
                    'update_time' => time(),
                    'admin_name' => $this->adminInfo['username']
                ]);
            // 提交事务
            Db::commit();
            return apiSuccess();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return apiError($e->getMessage());
        }
    }

    public function withdrawCount()
    {
        try {
            $where = [['w.state', '=', 0]];
            $query = Db::table('withdraw')
                ->alias('w')
                ->join('user u', 'w.uid = u.id');

            if (!$this->isSuperAdmin()) {
                $where[] = ['u.admin_id', '=', $this->adminInfo['id']];
            }
            $count = $query->where($where)->count();
            return apiSuccess('success', ['count' => $count]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
