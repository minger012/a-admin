<?php

namespace app\home\controller;

use app\BaseController;
use app\common\model\ConfigModel;
use app\common\validate\CommonValidate;
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
            if (!empty($params['code'])) {
                $fuid = Db::name('admin')->where('id', $params['code'])->value('id');
                if (!$fuid) {
                    throw new \Exception(lang('user_invitation_error'));
                }
            }
            Db::name('user')->insert([
                'username' => $params['username'],
                'password' => getMd5Password($params['password']),
                'create_time' => time(),
                'last_login_time' => time(),
                'last_login_ip' => Request::ip(),
                'lang' => config('lang.default_lang'),
                'admin_id' => $fuid ?? 0,
                'score' => 100,
            ]);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
