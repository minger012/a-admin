<?php

namespace app\admin\validate;

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
    }

    protected $rule = [
        'username' => 'max:20|regex:/^[a-zA-Z]+[a-zA-Z0-9]*$/',
        'name' => 'max:20|chsDash',
        'phone' => 'mobile',
        'lv' => 'integer|gt:0',
        'score' => 'integer|egt:0',
        'pledge_money' => 'float|egt:0',
        'remarks' => 'max:255',
        'withdraw_disabled' => 'max:255',
        'state' => 'integer|in:1,2',
        'pledge_refund' => 'integer|egt:0',
        'fb_id' => 'integer|egt:0',
    ];
}