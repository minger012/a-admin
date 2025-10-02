<?php

namespace app\home\controller;

use app\BaseController;
use app\home\model\UserModel;
use app\home\validate\UserValidate;
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
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new UserValidate();
        if (!$validate->check($params)) {
            return apiError($validate->getError());
        }
        try {
            // 开始事务
            Db::startTrans();
            if (!empty($params['code'])) {
                $codeData = Db::name('code')->lock()->where('code', $params['code'])->find();
                if (empty($codeData)) {
                    throw new \Exception(lang('user_invitation_error'));
                }
                if ($codeData['state'] == 1) {
                    throw new \Exception('授权码已被使用');
                }
                Db::name('code')->where('code', $params['code'])->update(['state' => 1]);
            }
            Db::name('user')->insert([
                'username' => $params['username'],
                'password' => getMd5Password($params['password']),
                'create_time' => time(),
                'last_login_time' => time(),
                'last_login_ip' => Request::ip(),
                'lang' => config('lang.default_lang'),
                'admin_id' => $codeData['admin_id'],
                'phone' => $params['mobile'],
                'fb_id' => getFbId(),
                'code' => $params['code'],
                'score' => 100,
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
}
