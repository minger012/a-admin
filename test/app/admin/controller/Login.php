<?php

namespace app\admin\controller;

use app\admin\model\AdminModel;
use app\admin\validate\AdminValidate;

class Login
{
    public function login(AdminModel $model)
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new AdminValidate();
        if (!$validate->check($params, $validate->login_rule)) {
            return apiError($validate->getError());
        }
        // 登录
        return $model->login($params['username'], $params['password']);
    }
}
