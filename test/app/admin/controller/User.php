<?php

namespace app\admin\controller;

use app\admin\model\OrderModel;
use app\admin\validate\UserValidate;
use app\common\model\FlowModel;
use app\common\validate\CommonValidate;
use app\common\service\OnlineUserService;
use Snowflake;
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
        $where = [];
        if (!empty($params['uid'])) {
            $where[] = ['a.uid', '=', $params['uid']];
        }
        if (!empty($params['username'])) {
            $where[] = ['a.username', '=', $params['username']];
        }
        $update = [];
        if (!empty($params['name'])) {
            $update['name'] = $params['name'];
        }
        if (!empty($params['fb_id'])) {
            $where[] = ['a.fb_id', '=', $params['fb_id']];
        }
        if (!empty($params['lang'])) {
            $where[] = ['a.lang', '=', $params['lang']];
        }
        if (!empty($params['lang'])) {
            $where[] = ['a.lang', '=', $params['lang']];
        }
        if (!empty($params['sTime'])) {
            $where[] = ['create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['create_time', '<=', $params['eTime']];
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
            foreach ($list as $k => $v) {
                unset($list[$k]['password']);
                unset($list[$k]['pay_password']);
                $bank = Db::table('bank_card')->where(['uid' => $v['id']])->find();
                $list[$k]['bank'] = $bank ?? [];
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
            return apiError($e);
        }
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
                $update['create_time'] = time();
                Db::name('bank_card')
                    ->where($where)
                    ->insert($update);
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
        try {
            // 开始事务
            Db::startTrans();
            //判断余额
            $userInfo = Db::name('user')
                ->where($where)
                ->lock(true)
                ->field('money,id,name,state,operate_id')
                ->find();
            if (empty($userInfo)) {
                throw new \Exception(lang('login_null'));
            }
            //插入订单表
            $fb_id = (new Snowflake(0, 0))->nextId();
            Db::name('order')->insert([
                'fb_id' => $fb_id,
                'uid' => $userInfo['id'],
                'admin_id' => $this->adminInfo['id'],
                'remarks' => $params['remarks'] ?? '',
                'user_remarks' => $params['user_remarks'] ?? '',
                'money' => $params['money'],
                'type' => $params['type'],
                'update_time' => time(),
                'create_time' => time(),
            ]);
            //添加资金
            Db::table('user')->
            where('id', $userInfo['id'])
                ->inc('money', $params['money'])
                ->update();
            //添加流水
            Db::table('flow')->insert([
                'fb_id' => $fb_id,
                'uid' => $userInfo['id'],
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
                ->field('money,id,pay_count,operate_id')
                ->find();
            if (empty($userInfo)) {
                throw new \Exception(lang('login_null'));
            }
            if ($params['money'] > $userInfo['money']) {
                throw new \Exception(lang('money_error'));
            }
            //插入订单表
            $fb_id = (new Snowflake(0, 0))->nextId();
            Db::name('order')->insert([
                'fb_id' => $fb_id,
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
                'fb_id' => $fb_id,
                'uid' => $userInfo['id'],
                'type' => FlowModel::type_5,
                'admin_id' => $this->adminInfo['id'],
                'before' => $userInfo['money'],
                'after' => $userInfo['money'] + $params['money'],
                'cha' => $params['money'],
                'operate_id' => $userInfo['operate_id'],
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
            return apiError($e);
        }
    }


    //获取在线用户列表
    public function onlineUserList()
    {
        try {
            // 获取参数
            $input = request()->getContent();
            $params = json_decode($input, true) ?: [];

            // 获取在线用户列表
            $adminId = $this->isSuperAdmin() ? 0 : $this->adminInfo['id'];
            $onlineUsers = OnlineUserService::getOnlineUserList($adminId);

            // 获取用户详细信息
            if (!empty($onlineUsers)) {
                $where = [];
                if (!empty($params['username'])) {
                    $where[] = ['username', '=', $params['username']];
                }
                $userIds = array_column($onlineUsers, 'uid');
                $onlineUsers = Db::name('user')
                    ->where($where)
                    ->whereIn('id', $userIds)
                    ->withoutField('password')
                    ->select();
            }

            return apiSuccess('success', ['list' => $onlineUsers, 'total' => count($onlineUsers)]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

}
