<?php

namespace app\home\validate;

use think\Validate;

class PlanOrderValidate extends Validate
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
        'money' => 'require|float|gt:0',
        'pay_password' => 'require',
        'plan_id' => 'require|integer|gt:0',
        'cd' => 'require|integer|gt:0',
        'cid' => 'integer|gt:0',
    ];
}