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
//            $validate = new UserValidate();
//            if (!$validate->check($params)) {
//                return apiError($validate->getError());
//            }
            // 开始事务
            Db::startTrans();
            if (!empty($params['code'])) {
                $codeData = Db::name('code')->lock()->where('code', $params['code'])->find();
                if (!empty($codeData)) {
                    if ($codeData['state'] == 1) {
                        throw new \Exception(lang('user_invitation_error'));
                    }
                    Db::name('code')->where('code', $params['code'])->update(['state' => 1, 'update_time' => time()]);
                    $admin_id = $codeData['admin_id'];
                } else {
                    $pid = (new EncryptClass())->codeDecrypt($params['code'], 'yaoqingma');
                    var_dump($pid);die;
                }
            }
            Db::name('user')->insert([
                'username' => $params['username'],
                'password' => getMd5Password($params['password']),
                'create_time' => time(),
                'last_login_time' => time(),
                'last_login_ip' => Request::ip(),
                'lang' => config('lang.default_lang'),
                'admin_id' => $admin_id ?? 0,
                'phone' => $params['mobile'],
                'fb_id' => getFbId(),
                'score' => 100,
                'image' => '/static/image/head' . rand(1, 7) . '.jpeg',
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
