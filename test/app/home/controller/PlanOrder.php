<?php

namespace app\home\controller;

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
            // 判断余额
            $userInfo = Db::name('user')
                ->where('id', $this->userInfo['id'])
                ->lock(true)
                ->field('money,state')
                ->find();
            if ($userInfo['state'] == 2) {
                throw new \Exception(lang('user_impose'));
            }
            if ($params['money'] > $userInfo['money']) {
                throw new \Exception(lang('money_error'));
            }
            // 优惠券判断
            if (!empty($params['cid'])) {
                $couponInfo = Db::name('user_coupon')
                    ->alias('a')
                    ->join('coupon b','a.cid=b.id')
                    ->field('a.*,b.type,b.state,b.discount,b.discount_amount')
                    ->where('a.id', $params['cid'])
                    ->where('a.uid', $this->userInfo['id'])
                    ->find();
                var_dump($couponInfo);die;
                if (empty($couponInfo) || $couponInfo['state'] == 2) {
                    throw new \Exception(lang('coupon_error'));
                }
                if ($couponInfo['start_time'] > time() || $couponInfo['end_time'] < time()) {
                    throw new \Exception(lang('coupon_time_error'));
                }
                if (!empty($couponInfo['use_time'])) {
                    throw new \Exception(lang('coupon_is_use'));
                }
                // 优惠券类型1增值2抵扣3团队4自定义5固定金额
//                if ($couponInfo['type'] == 1){
//                    $params['money'] *= $couponInfo
//                }
            }

            return apiSuccess('success', $res);
        } catch (\Exception $e) {
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