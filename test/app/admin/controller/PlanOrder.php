<?php

namespace app\admin\controller;

use app\admin\validate\PlanOrderValidate;
use app\common\model\FlowModel;
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
                ->order('a.id', 'desc')
                ->field('a.fb_id, COUNT(*) as count')
                ->group('a.fb_id')
                ->where($where)
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            $fbIds = $paginator->column('fb_id');
            $details = Db::table('plan_order')
                ->alias('a')
                ->join('user b', 'a.uid = b.id')
                ->order('a.id', 'desc')
                ->field('a.*,b.username,b.short_name')
                ->where('a.fb_id', 'in', $fbIds)
                ->select()
                ->toArray();
            // 组织数据
            $groupedDetails = [];
            foreach ($details as $item) {
                $groupedDetails[$item['fb_id']][] = $item;
            }
            // 合并数据
            foreach ($list as &$item) {
                $item['items'] = $groupedDetails[$item['fb_id']] ?? [];
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

    public function edit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new PlanOrderValidate();
        if (!$validate->append('id', 'require|number')->check($params, $validate->rule_edit)) {
            return apiError($validate->getError());
        }
        try {
            // 开始事务
            Db::startTrans();
            $where = [['id', '=', $params['id']]];
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            $planOrder = Db::name('plan_order')->where($where)->find();

            if (!$planOrder) {
                throw new \Exception(lang('non_existent'));
            }
            // 判断余额
            $userInfo = Db::name('user')
                ->where('id', $planOrder['uid'])
                ->lock(true)
                ->field('money')
                ->find();
            if ($planOrder['state'] == PlanOrderModel::state_1) {// 匹配中
                if (!in_array($params['state'], [PlanOrderModel::state_2, PlanOrderModel::state_3])) {
                    throw new \Exception(lang('params_error'));
                }
                if ($params['state'] == PlanOrderModel::state_3) {// 失败退回
                    // 添加资金
                    Db::table('user')->where('id', $planOrder['uid'])->inc('money', $planOrder['actual_money'])->update();
                    // 添加流水
                    Db::table('flow')->insert([
                        'uid' => $planOrder['uid'],
                        'type' => FlowModel::type_6,
                        'admin_id' => $planOrder['admin_id'],
                        'before' => $userInfo['money'],
                        'after' => $userInfo['money'] + $planOrder['actual_money'],
                        'cha' => $planOrder['actual_money'],
                        'fb_id' => $this->fb_id,
                        'update_time' => time(),
                        'create_time' => time(),
                    ]);
                    // 优惠券退回
                    if (!empty($planOrder['cid'])) {
                        Db::table('user_coupon')->where('id', $planOrder['cid'])->update(['use_time' => 0]);
                    }
                } elseif ($params['state'] == PlanOrderModel::state_2) {
                    $params['start_time'] = time();
                }
            } elseif ($planOrder['state'] == PlanOrderModel::state_4) {// 结算中
                if (!in_array($params['state'], [PlanOrderModel::state_5])) {
                    throw new \Exception(lang('params_error'));
                }
                // 添加资金
                Db::table('user')->where('id', $planOrder['uid'])->inc('money', $planOrder['profit'] + $planOrder['money'])->update();
                // 添加流水
                Db::table('flow')->insert([
                    'uid' => $planOrder['uid'],
                    'type' => FlowModel::type_3,
                    'admin_id' => $planOrder['admin_id'],
                    'before' => $userInfo['money'],
                    'after' => $userInfo['money'] + $planOrder['profit'] + $planOrder['money'],
                    'cha' => $planOrder['profit'] + $planOrder['money'],
                    'fb_id' => $this->fb_id,
                    'update_time' => time(),
                    'create_time' => time(),
                ]);
            } else {
                throw new \Exception(lang('params_error'));
            }
            $params['update_time'] = time();
            Db::name('plan_order')
                ->where(['id' => $params['id']])
                ->update($params);
            // 提交事务
            Db::commit();
            return apiSuccess();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return apiError($e->getMessage());
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