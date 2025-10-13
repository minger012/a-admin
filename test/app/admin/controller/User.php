<?php

namespace app\admin\controller;

use app\admin\model\OrderModel;
use app\admin\validate\PlanOrderValidate;
use app\admin\validate\UserValidate;
use app\common\model\FlowModel;
use app\common\model\PlanOrderModel;
use app\common\validate\CommonValidate;
use app\home\model\UserModel;
use EncryptClass;
use think\facade\Db;

class User extends Base
{
    public function list()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        $where = [['is_del', '=', 0]];
        $planWhere = [];
        if (!empty($params['uid'])) {
            $where[] = ['a.id', '=', $params['uid']];
        }
        if (!empty($params['username'])) {
            $where[] = ['a.username', '=', $params['username']];
        }
        if (!empty($params['name'])) {
            $update['name'] = $params['name'];
        }
        if (!empty($params['short_name'])) {
            $update['short_name'] = $params['short_name'];
        }
        if (!empty($params['fb_id'])) {
            $where[] = ['a.fb_id', '=', $params['fb_id']];
        }
        if (!empty($params['lang'])) {
            $where[] = ['a.lang', '=', $params['lang']];
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
            } elseif (!empty($params['admin_username'])) {
                $admin_id = Db::name('admin')->where(['username' => $params['admin_username']])->value('id');
                if (!empty($admin_id)) {
                    $where[] = ['a.admin_id', '=', $admin_id];
                }
            }
            $paginator = Db::table('user')
                ->alias('a')
                ->leftJoin('admin b', 'a.admin_id=b.id')
                ->where($where)
                ->field('a.*, b.username as admin_name')
                ->order('a.id', 'desc')
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10,
                    'page' => $params['page'] ?? 1,
                ]);
            $list = $paginator->items();
            // 加密token
            $EncryptClass = new EncryptClass();
            foreach ($list as $k => $v) {
                unset($list[$k]['password']);
                unset($list[$k]['pay_password']);
                $bank = Db::table('bank_card')->where(['uid' => $v['id']])->find();
                $list[$k]['bank'] = $bank ?? [];
                $token = $EncryptClass->myEncrypt(json_encode([
                    'id' => $v['id'],
                    'isGm' => 1,
                    'entTime' => time() + 3600
                ]), UserModel::$_token_secretKey);
                $list[$k]['gmUrl'] = [
                    'market' => getDomain() . '/client#/market?token=' . $token,
                    'order' => getDomain() . '/client#/order?token=' . $token,
                ];
            }
            $res = [
                'list' => $list,       // 当前页数据
                'planList' => Db::table('plan')->where($planWhere)->field('id,name')->select()->toArray(),// 计划列表
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
        $validate = new UserValidate();
        if (!$validate->append('id', 'require|number')->check($params)) {
            return apiError($validate->getError());
        }
        $where = [['id', '=', $params['id']]];
        if (!$this->isSuperAdmin()) {
            $where[] = ['admin_id', '=', $this->adminInfo['id']];
        }
        unset($params['password']);
        try {
            $res = Db::name('user')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }
            Db::name('user')
                ->where($where)
                ->update($params);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    /**
     * 获取用户代理关系路径
     */
    public function getUserRelationPath()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            $userId = isset($params['uid']) ? intval($params['uid']) : 0;

            if (!$userId) {
                return json([
                    'code' => 0,
                    'msg' => '用户ID不能为空',
                    'data' => null
                ]);
            }

            // 获取用户层级关系路径
            $relationPath = $this->getUserHierarchy($userId);

            // 计算当前层级
            $currentLevel = count($relationPath);

            $responseData = [
                'current_level' => $currentLevel,
                'relation_path' => $relationPath,
                'current_uid' => $userId
            ];

            return json([
                'code' => 1,
                'msg' => '成功',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'msg' => '获取用户关系失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 递归获取用户层级关系
     */
    private function getUserHierarchy($userId, $path = [])
    {
        // 获取当前用户信息
        $user = Db::name('user')
            ->field('id, pid, username, short_name')
            ->where('id', $userId)
            ->find();

        if (!$user) {
            return $path;
        }

        // 将当前用户添加到路径开头
        array_unshift($path, [
            'uid' => $user['id'],
            'username' => $user['username'],
            'short_name' => $user['short_name'],
            'is_current' => empty($path) // 最后一个添加的是当前用户
        ]);

        // 如果有父级，继续递归
        if ($user['pid'] > 0) {
            return $this->getUserHierarchy($user['pid'], $path);
        }

        return $path;
    }

    // 修改登录密码
    public function pwdEdit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->eidt_pwd_rule)) {
            return apiError($validate->getError());
        }
        $where = [['id', '=', $params['id']]];
        if (!$this->isSuperAdmin()) {
            $where[] = ['admin_id', '=', $this->adminInfo['id']];
        }
        try {
            Db::name('user')
                ->where($where)
                ->update(['password' => getMd5Password($params['password'])]);
            return apiSuccess();
        } catch (\Exception $e) {
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
            $where = [['id', '=', $params['id']]];
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            Db::name('user')->where($where)->update(['is_del' => 1]);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 修改支付密码
    public function payPwdEdit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->eidt_pwd_rule)) {
            return apiError($validate->getError());
        }
        $where = [['id', '=', $params['id']]];
        if (!$this->isSuperAdmin()) {
            $where[] = ['admin_id', '=', $this->adminInfo['id']];
        }
        try {
            Db::name('user')
                ->where($where)
                ->update(['pay_password' => getMd5Password($params['pay_password'])]);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 修改提现方式
    public function bankEdit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)) {
            return apiError($validate->getError());
        }
        $where = [['id', '=', $params['id']]];
        if (!$this->isSuperAdmin()) {
            $where[] = ['admin_id', '=', $this->adminInfo['id']];
        }
        try {
            $update['methods'] = $params['methods'];
            $update['currency'] = $params['currency'];
            $update['address'] = $params['address'];
            $res = Db::name('bank_card')->where(['uid' => $params['id']])->find();
            if (!$res) {
                $update['uid'] = $params['id'];
                $update['create_time'] = time();
                unset($params['id']);
                Db::name('bank_card')->insert($update);
            } else {
                Db::name('bank_card')
                    ->where($where)
                    ->update($update);
            }
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 充值
    public function recharge()
    {
        try {
            $input = request()->getContent();
            $params = json_decode($input, true);
            if (empty($params['id']) ||
                floatval($params['money']) <= 0 ||
                !in_array($params['type'], [OrderModel::TYPE_1, OrderModel::TYPE_3])
            ) {
                return apiError('params_error');
            }
            $where = [['id', '=', $params['id']]];
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            // 开始事务
            Db::startTrans();
            //判断余额
            $userInfo = Db::name('user')
                ->where($where)
                ->lock(true)
                ->field('money,id,name,state,admin_id')
                ->find();
            if (empty($userInfo)) {
                throw new \Exception(lang('login_null'));
            }
            //插入订单表
            Db::name('order')->insert([
                'fb_id' => $this->fb_id,
                'uid' => $userInfo['id'],
                'admin_id' => $userInfo['admin_id'],
                'remarks' => $params['remarks'] ?? '',
                'user_remarks' => $params['user_remarks'] ?? '',
                'money' => $params['money'],
                'type' => $params['type'],
                'update_time' => time(),
                'create_time' => time(),
            ]);
            //添加资金
            Db::table('user')
                ->where('id', $userInfo['id'])
                ->inc('money', $params['money'])
                ->inc('pay_money', $params['money'])
                ->update();
            (new UserModel())->updateStar($userInfo['id']);
            //添加流水
            Db::table('flow')->insert([
                'fb_id' => $this->fb_id,
                'uid' => $userInfo['id'],
                'admin_id' => $userInfo['admin_id'],
                'type' => $params['type'],
                'before' => $userInfo['money'],
                'after' => $userInfo['money'] + $params['money'],
                'cha' => $params['money'],
                'update_time' => time(),
                'create_time' => time(),
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

    // 扣款
    public function userMoneySub()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        if (empty($params['id']) ||
            floatval($params['money']) <= 0 ||
            !in_array($params['type'], [OrderModel::TYPE_1, OrderModel::TYPE_3])
        ) {
            return apiError('params_error');
        }
        try {
            $where = [['id', '=', $params['id']]];
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            // 开始事务
            Db::startTrans();
            //判断余额
            $userInfo = Db::name('user')
                ->where($where)
                ->lock(true)
                ->field('money,id,pay_count')
                ->find();
            if (empty($userInfo)) {
                throw new \Exception(lang('login_null'));
            }
            if ($params['money'] > $userInfo['money']) {
                throw new \Exception(lang('money_error'));
            }
            //插入订单表
            Db::name('order')->insert([
                'fb_id' => $this->fb_id,
                'uid' => $userInfo['id'],
                'admin_id' => $this->adminInfo['id'],
                'remarks' => $params['remarks'] ?? '',
                'user_remarks' => $params['user_remarks'] ?? '',
                'money' => $params['money'],
                'type' => $params['type'],
                'update_time' => time(),
                'create_time' => time(),
            ]);
            //减资金
            Db::table('user')->
            where('username', $params['username'])
                ->dec('money', $params['money'])
                ->update();
            //添加流水
            Db::table('flow')->insert([
                'fb_id' => $this->fb_id,
                'uid' => $userInfo['id'],
                'type' => FlowModel::type_5,
                'admin_id' => $this->adminInfo['id'],
                'before' => $userInfo['money'],
                'after' => $userInfo['money'] + $params['money'],
                'cha' => $params['money'],
                'update_time' => time(),
                'create_time' => time(),
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

    // 发优惠券
    public function addCoupon()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->append('id', 'require|number')->check($params)) {
            return apiError($validate->getError());
        }
        try {
            $res = Db::name('coupon')->where(['id' => $params['cid']])->find();
            if (empty($res)) {
                return apiError('non_existent');
            }
            $update['uid'] = $params['id'];
            $update['cid'] = $params['cid'];
            $update['fb_id'] = $this->fb_id;
            if ($res['expir_type'] == 1) {
                $update['start_time'] = time();
                $update['end_time'] = time() + $res['expir_day'];
            } else {
                $update['start_time'] = $res['start_time'];
                $update['end_time'] = $res['end_time'];
            }
            $update['create_time'] = time();
            Db::name('user_coupon')->insert($update);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 派单
    public function addPlanOrder()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new PlanOrderValidate();
        if (!$validate->check($params)) {
            return apiError($validate->getError());
        }
        try {
            $admin_id = Db::table('user')->where('id', $params['uid'])->value('admin_id');
            if (empty($admin_id)) {
                return apiError('uid 有误');
            }
            $sqlArr = [];
            foreach ($params['plan'] as $v) {
                if ($v['min'] > $v['max']) {
                    return apiError($v['plan_id'] . 'min不能比max大');
                }
                $planData = Db::table('plan')
                    ->alias('a')
                    ->join('goods b', 'a.goods_id = b.id')
                    ->where('a.id', $v['plan_id'])
                    ->field('a.name as plan_name,a.goods_id,b.name as goods_name')
                    ->find();
                if (empty($planData)) {
                    return apiError($v['plan_id'] . '数据不存在');
                }
                $v = array_merge($planData, $v);
                $v['cd'] = $v['cd'] * 60;
                $v['uid'] = $params['uid'];
                $v['admin_id'] = $admin_id;
                $v['fb_id'] = $this->fb_id;
                $v['order_no'] = 'p_' . getFbId();
                $v['state'] = PlanOrderModel::state_0;
                $v['create_time'] = time();
                $v['update_time'] = time();
                $sqlArr[] = $v;
            }
            Db::name('plan_order')->insertAll($sqlArr);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
