<?php

namespace app\home\controller;

use app\common\model\FlowModel;
use app\common\model\PlanOrderModel;
use app\common\validate\CommonValidate;
use app\home\validate\PlanOrderValidate;
use think\facade\Db;

class PlanOrder extends Base
{
    // json字段
    protected $jsonField = ['content', 'orienteering', 'rule'];

    public function list()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        $where = [['a.uid', '=', $this->userInfo['id']]];
        if (in_array($params['type'], [1, 2, 3, 4])) {
            $where[] = ['a.state', '=', $params['type'] - 1];
        } elseif ($params['type'] == 5) {
            $where[] = ['a.state', 'in', '4,5'];
        }
        try {
            $paginator = Db::table('plan_order')
                ->alias('a')
                ->join('plan b', 'a.plan_id = b.id')
                ->join('goods c', 'a.goods_id = c.id')
                ->where($where)
                ->field('a.*,b.image,c.logo as goods_logo,c.type_name,c.intro as goods_intro')
                ->order('a.id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            foreach ($list as $key => $value) {
                $list[$key]['image'] = getDomain() . $value['image'];
                $list[$key]['goods_logo'] = getDomain() . $value['goods_logo'];
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
            return apiError($e);
        }
    }

    // 下单
    public function add()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new PlanOrderValidate();
        if (!$validate->check($params)) {
            return apiError($validate->getError());
        }
        try {
            // 开始事务
            Db::startTrans();
            // 实付金额
            $actual_money = $params['money'];
            // 优惠券判断
            if (!empty($params['cid'])) {
                $couponInfo = Db::name('user_coupon')
                    ->alias('a')
                    ->join('coupon b', 'a.cid=b.id')
                    ->field('a.*,b.min,b.max,b.type,b.state,b.discount,b.discount_amount')
                    ->where('a.id', $params['cid'])
                    ->where('a.uid', $this->userInfo['id'])
                    ->find();
                if (empty($couponInfo) || $couponInfo['state'] == 2) {
                    throw new \Exception(lang('coupon_error'));
                }
                // 时间范围
                if ($couponInfo['start_time'] > time() || $couponInfo['end_time'] < time()) {
                    throw new \Exception(lang('coupon_time_error'));
                }
                if (!empty($couponInfo['use_time'])) {
                    throw new \Exception(lang('coupon_is_use'));
                }
                // 金额范围
                if (($couponInfo['min'] && $params['money'] < $couponInfo['min'])
                    || ($couponInfo['max'] && $params['money'] > $couponInfo['min'])
                ) {
                    throw new \Exception(sprintf(lang('coupon_money_error'), $couponInfo['min'], $couponInfo['max']));
                }
                // 优惠券类型1增值2抵扣3团队4自定义5固定金额
                if ($couponInfo['type'] == 1) {
                    $params['money'] = round($params['money'] * (1 + $couponInfo['discount'] / 100), 2);
                } else if ($couponInfo['type'] == 2 || $couponInfo['type'] == 3 || $couponInfo['type'] == 4) {
                    $actual_money = round($params['money'] * (1 + $couponInfo['discount'] / 100), 2);
                } else if ($couponInfo['type'] == 5) {
                    $actual_money = round($params['money'] - $couponInfo['discount_amount']);
                }
                if ($actual_money <= 0) {
                    throw new \Exception(lang('coupon_error'));
                }
                Db::name('user_coupon')->where('id', $params['cid'])->update(['use_time' => time()]);
            }
            // 判断余额
            $userInfo = Db::name('user')
                ->where('id', $this->userInfo['id'])
                ->lock(true)
                ->field('money,state,pay_password')
                ->find();
            if ($userInfo['state'] == 2) {
                throw new \Exception(lang('user_impose'));
            }
            if ($actual_money > $userInfo['money']) {
                throw new \Exception(lang('money_error'));
            }
            if ($userInfo['pay_password'] !== getMd5Password($params['pay_password'])) {
                throw new \Exception(lang('password_error'));
            }
            // 判断计划
            $planInfo = Db::name('plan')
                ->alias('a')
                ->join('goods b', 'a.goods_id=b.id')
                ->where('a.id', $params['plan_id'])
                ->field('a.id,a.name,a.goods_id,b.name as goods_name')
                ->find();
            if (empty($planInfo)) {
                throw new \Exception(lang('order_error'));
            }
            // 插入订单表
            $order_no = 'p_' . getFbId();
            Db::name('plan_order')->insert([
                'uid' => $this->userInfo['id'],
                'admin_id' => $this->userInfo['admin_id'],
                'plan_id' => $params['plan_id'],
                'plan_name' => $planInfo['name'],
                'goods_name' => $planInfo['goods_name'],
                'goods_id' => $planInfo['goods_id'],
                'order_no' => $order_no,
                'fb_id' => $this->fb_id,
                'form' => 1,
                'cd' => round($params['cd'] / 60),
                'money' => $params['money'],
                'wait_putIn' => $params['money'],
                'min' => $params['money'],
                'max' => $params['money'],
                'cid' => $params['cid'],
                'actual_money' => $actual_money,
                'state' => PlanOrderModel::state_1,
                'update_time' => time(),
                'create_time' => time(),
            ]);
            // 扣除资金
            Db::table('user')->where('id', $this->userInfo['id'])->dec('money', $actual_money)->update();
            // 添加流水
            Db::table('flow')->insert([
                'uid' => $this->userInfo['id'],
                'type' => FlowModel::type_4,
                'cid' => $params['cid'],
                'admin_id' => $this->userInfo['admin_id'],
                'coupon_money' => $params['money'] - $actual_money,
                'before' => $userInfo['money'],
                'after' => $userInfo['money'] - $actual_money,
                'cha' => -$actual_money,
                'fb_id' => $this->fb_id,
                'order_no' => $order_no,
                'update_time' => time(),
                'create_time' => time(),
            ]);
            // 提交事务
            Db::commit();
            return apiSuccess('order_success');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return apiError($e->getMessage());
        }
    }

    // 下单 (后台派单的)
    public function sendAdd()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new PlanOrderValidate();
        if (!$validate->check($params, $validate->rule_send)) {
            return apiError($validate->getError());
        }
        try {
            // 开始事务
            Db::startTrans();
            // 实付金额
            $actual_money = $params['money'];
            // 优惠券判断
            if (!empty($params['cid'])) {
                $couponInfo = Db::name('user_coupon')
                    ->alias('a')
                    ->join('coupon b', 'a.cid=b.id')
                    ->field('a.*,b.min,b.max,b.type,b.state,b.discount,b.discount_amount')
                    ->where('a.id', $params['cid'])
                    ->where('a.uid', $this->userInfo['id'])
                    ->find();
                if (empty($couponInfo) || $couponInfo['state'] == 2) {
                    throw new \Exception(lang('coupon_error'));
                }
                // 时间范围
                if ($couponInfo['start_time'] > time() || $couponInfo['end_time'] < time()) {
                    throw new \Exception(lang('coupon_time_error'));
                }
                if (!empty($couponInfo['use_time'])) {
                    throw new \Exception(lang('coupon_is_use'));
                }
                // 金额范围
                if (($couponInfo['min'] && $params['money'] < $couponInfo['min'])
                    || ($couponInfo['max'] && $params['money'] > $couponInfo['min'])
                ) {
                    throw new \Exception(sprintf(lang('coupon_money_error'), $couponInfo['min'], $couponInfo['max']));
                }
                // 优惠券类型1增值2抵扣3团队4自定义5固定金额
                if ($couponInfo['type'] == 1) {
                    $params['money'] = round($params['money'] * (1 + $couponInfo['discount'] / 100), 2);
                } else if ($couponInfo['type'] == 2 || $couponInfo['type'] == 3 || $couponInfo['type'] == 4) {
                    $actual_money = round($params['money'] * (1 + $couponInfo['discount'] / 100), 2);
                } else if ($couponInfo['type'] == 5) {
                    $actual_money = round($params['money'] - $couponInfo['discount_amount']);
                }
                if ($actual_money <= 0) {
                    throw new \Exception(lang('coupon_error'));
                }
                Db::name('user_coupon')->where('id', $params['cid'])->update(['use_time' => time()]);
            }
            // 判断余额
            $userInfo = Db::name('user')
                ->where('id', $this->userInfo['id'])
                ->lock(true)
                ->field('money,state,pay_password')
                ->find();
            if ($userInfo['state'] == 2) {
                throw new \Exception(lang('user_impose'));
            }
            if ($actual_money > $userInfo['money']) {
                throw new \Exception(lang('money_error'));
            }
            if ($userInfo['pay_password'] !== getMd5Password($params['pay_password'])) {
                throw new \Exception(lang('password_error'));
            }
            // 判断订单
            $planOrderInfo = Db::name('plan_order')
                ->lock()
                ->where('id', $params['id'])
                ->find();
            if (empty($planOrderInfo) || $planOrderInfo['uid'] != $this->userInfo['id'] || $planOrderInfo['state'] != PlanOrderModel::state_0) {
                throw new \Exception(lang('order_error'));
            }
            if ($params['money'] < $planOrderInfo['min'] || $params['money'] > $planOrderInfo['max']) {
                throw new \Exception(sprintf(lang('order_money_error'), $planOrderInfo['min'], $planOrderInfo['max']));
            }
            // 更新订单表
            Db::name('plan_order')
                ->where('id', $params['id'])
                ->update([
                    'money' => $params['money'],
                    'wait_putIn' => $params['money'],
                    'cid' => $params['cid'],
                    'actual_money' => $actual_money,
                    'state' => PlanOrderModel::state_2,
                    'update_time' => time(),
                    'start_time' => time(),
                ]);
            // 扣除资金
            Db::table('user')->where('id', $this->userInfo['id'])->dec('money', $actual_money)->update();
            // 添加流水
            Db::table('flow')->insert([
                'uid' => $this->userInfo['id'],
                'type' => FlowModel::type_4,
                'cid' => $params['cid'],
                'admin_id' => $this->userInfo['admin_id'],
                'coupon_money' => $params['money'] - $actual_money,
                'before' => $userInfo['money'],
                'after' => $userInfo['money'] - $actual_money,
                'cha' => -$actual_money,
                'fb_id' => $this->fb_id,
                'order_no' => $planOrderInfo['order_no'],
                'update_time' => time(),
                'create_time' => time(),
            ]);
            // 提交事务
            Db::commit();
            return apiSuccess('order_success');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return apiError($e->getMessage());
        }
    }

    // 详情
    public function detail()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)) {
            return apiError($validate->getError());
        }
        try {
            $planOrderDetail = Db::table('plan_order')
                ->alias('a')
                ->join('plan b', 'a.plan_id = b.id')
                ->join('goods c', 'a.goods_id = b.id')
                ->where('a.id', $params['id'])
                ->where('a.uid', $this->userInfo['id'])
                ->field('a.*,b.image,b.intro,b.content,b.orienteering,b.rule,c.logo as goods_logo,c.type_name,c.company as goods_company')
                ->find();
            if (empty($planOrderDetail)) {
                return apiError('non_existent');
            }
            $planOrderDetail['image'] = getDomain() . $planOrderDetail['image'];
            $planOrderDetail['goods_logo'] = getDomain() . $planOrderDetail['goods_logo'];
            foreach ($this->jsonField as $field) {
                $planOrderDetail[$field] = jsonDecode($planOrderDetail[$field]);
            }
            return apiSuccess('success', $planOrderDetail);
        } catch (\Exception $e) {
            return apiError($e);
        }
    }
}