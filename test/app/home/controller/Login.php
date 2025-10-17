<?php

namespace app\home\controller;

use app\BaseController;
use app\common\model\ConfigModel;
use app\common\validate\CommonValidate;
use app\home\model\UserModel;
use app\home\validate\UserValidate;
use EncryptClass;
use think\facade\Db;
use think\facade\Request;

class Login extends BaseController
{
    // 登录
    public function login(UserModel $model)
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new UserValidate();
        if (!$validate->check($params, $validate->login_rule)) {
            return apiError($validate->getError());
        }
        return $model->login($params['username'], $params['password']);
    }

    //注册
    public function register()
    {
        try {
            $input = request()->getContent();
            $params = json_decode($input, true);
            $validate = new UserValidate();
            if (!$validate->check($params)) {
                return apiError($validate->getError());
            }
            // 开始事务
            Db::startTrans();
            $codeData = Db::name('code')->lock()->where('code', $params['code'])->find();
            if (!empty($codeData)) {
                if ($codeData['state'] == 1) {
                    throw new \Exception(lang('user_invitation_error'));
                }
                Db::name('code')->where('code', $params['code'])->update(['state' => 1, 'update_time' => time()]);
            } else {
                $codeData = (new EncryptClass())->getByInviteCode($params['code']);
                if ($codeData['state'] != 1) {
                    throw new \Exception(lang('user_invitation_state_error'));
                }
                if (empty($codeData) ||  $codeData['is_del'] == 1) {
                    throw new \Exception(lang('user_invitation_error'));
                }
            }

            // 生成fb_id
            $fbId = getFbId();

            // 插入用户数据
            $userId = Db::name('user')->insertGetId([
                'username' => $params['username'],
                'password' => getMd5Password($params['password']),
                'create_time' => time(),
                'last_login_time' => time(),
                'last_login_ip' => Request::ip(),
                'lang' => config('lang.default_lang'),
                'admin_id' => $codeData['admin_id'] ?? 0,
                'pid' => $codeData['id'] ?? 0,
                'code' => (new EncryptClass())->generateInviteCode(),
                'phone' => $params['mobile'],
                'fb_id' => $fbId,
                'score' => 100,
                'image' => '/static/image/head' . rand(1, 7) . '.jpeg',
            ]);

            // 注册送新人券
            $this->giveNewUserCoupon($userId, $fbId);

            // 提交事务
            Db::commit();
            return apiSuccess();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return apiError($e->getMessage());
        }
    }

    /**
     * 给新用户发放新人券
     * @param int $userId 用户ID
     * @param string $fbId 用户fb_id
     */
    private function giveNewUserCoupon($userId, $fbId)
    {
        // 查询所有可用的新人券
        $newCoupons = Db::name('coupon')
            ->where('is_new', 1)
            ->where('state', 1) // 状态正常
            ->select();

        if (empty($newCoupons)) {
            return;
        }

        $currentTime = time();
        $userCouponData = [];

        foreach ($newCoupons as $coupon) {
            // 计算优惠券的有效期
            if ($coupon['expir_type'] == 1) {
                // 按天数计算
                $startTime = $currentTime;
                $endTime = $currentTime + ($coupon['expir_day'] * 24 * 3600);
            } else {
                // 按时间段计算
                $startTime = $coupon['start_time'];
                $endTime = $coupon['end_time'];
            }

            // 确保结束时间大于当前时间
            if ($endTime <= $currentTime) {
                continue;
            }

            $userCouponData[] = [
                'fb_id' => $fbId,
                'uid' => $userId,
                'cid' => $coupon['id'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'use_time' => 0,
                'update_time' => $currentTime,
                'create_time' => $currentTime,
            ];
        }

        // 批量插入用户优惠券
        if (!empty($userCouponData)) {
            Db::name('user_coupon')->insertAll($userCouponData);
        }
    }

    public function getConfig(ConfigModel $model)
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        try {
            $value = $model->getConfigValue($params['ids']);
            $res = apiSuccess('success', $value);
            return $res;
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }

    }
}
