<?php

namespace app\home\validate;

use think\Validate;

class UserValidate extends Validate
{
    public function __construct()
    {
        parent::__construct();
        $this->setFieldsLang();
    }

    // 设置字段翻译
    protected function setFieldsLang()
    {
        foreach ($this->rule as $field => $name) {
            $this->field[$field] = lang($field);
        }
        $this->field['cardno'] = lang('cardno');
        $this->field['money'] = lang('money');
        $this->field['card_id'] = lang('card_id');
        $this->field['orpassword'] = lang('orpassword');
    }

    protected $rule = [
        'username' => 'require|alphaNum|unique:user|length:2,32',
        'password' => 'require|length:6,32',
        'repassword' => 'require|confirm:password',
        'code' => 'require|alphaNum|gt:0',
    ];

    public $login_rule = [
        'username' => 'require|alphaNum',
        'password' => 'require|length:6,32',
    ];

    public $bank_car_rule = [
        'address' => 'require|alphaNum',
        'methods' => 'require|alphaNum',
        'currency' => 'require|alphaNum',
    ];
    public $bank_car_edit_rule = [
        'cardno' => 'require|alphaNum|unique:bank_card,cardno^uid',
        'password' => 'length:6,32',
        'name' => 'require|regex:/^[\w\x{4e00}-\x{9fa5}\s]+$/u',
        'bank' => 'require|regex:/^[\w\x{4e00}-\x{9fa5}\s()]+$/u',
    ];
    public $set_password_rule = [
        'orpassword' => 'length:6,32',
        'password' => 'require|length:6,32',
        'repassword' => 'require|confirm:password',
    ];
    //提现
    public $withdraw_rule = [
        'money' => 'require|integer|gt:0',
        'password' => 'require',
    ];

    public $user_add_rule = [
        'username' => 'require|alphaNum|unique:user|length:2,32',
        'password' => 'require|length:6,32',
        'isP' => 'in:0,1',
    ];
}