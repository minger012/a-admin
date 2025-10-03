<?php

namespace app\home\controller;

use app\common\model\FlowModel;
use app\common\model\PlanOrderModel;
use app\common\validate\CommonValidate;
use app\home\validate\PlanOrderValidate;
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
        try {
            $paginator = Db::table('plan')
                ->alias('a')
                ->join('goods b', 'a.goods_id = b.id')
                ->field('a.*,b.company')
                ->order('a.id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            // 字段转化
            foreach ($list as $key => $value) {

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
                'cd' => $params['cd'],
                'money' => $params['money'],
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

    public function detail()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)) {
            return apiError($validate->getError());
        }
        try {
            $planData = Db::table('plan')
                ->where('a.id', $params['id'])
                ->alias('a')
                ->join('goods b', 'a.goods_id = b.id')
                ->field('a.*,b.company,b.type_name,b.logo as goods_logo ,b.image as goods_image,b.google_play,b.app_store,b.app_info')
                ->find();
            $planData['money'] = Db::table('user')->where('id', $this->userInfo['id'])->value('money');
            $planData['goods_logo'] = getDomain() . $planData['goods_logo'];
            $planData['goods_image'] = getDomain() . $planData['goods_image'];
            $planData['app_info'] = !empty($planData['app_info']) ? json_decode(base64_decode($planData['app_info']), true) : [];
            return apiSuccess('success', $planData);
        } catch (\Exception $e) {
            return apiError($e);
        }
    }
}