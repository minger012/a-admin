<?php

namespace app\admin\validate;

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
        'uid' => 'require|integer|gt:0',
        'plan' => 'require|array',
        'plan.*.plan_id' => 'require|gt:0',
        'plan.*.min' => 'require|float|gt:0',
        'plan.*.max' => 'require|float|gt:0',
        'plan.*.cd' => 'require|integer|gt:0',
        'plan.*.form' => 'require|in:1,2',
    ];

    protected $rule_edit = [
        'show_num' => 'require|integer|egt:0',
        'click_num' => 'require|integer|egt:0',
        'state' => 'require|in:1,2,3,4,5',
        'click_price' => 'require|float|egt:0',
        'cd' => 'require|integer|gt:0',
        'min' => 'require|float|gt:0',
        'max' => 'require|float|gt:0',
        'rate' => 'require|float|gt:0',
    ];
}