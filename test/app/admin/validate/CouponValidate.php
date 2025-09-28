<?php

namespace app\admin\validate;

use think\Validate;

class CouponValidate extends Validate
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
        'name' => 'require|max:100',
        'type' => 'require|in:1,2,3,4,5',
        'is_new' => 'require|in:0,1',
        'state' => 'require|in:0,1',
        'expir_type' => 'require|in:1,2',
        'expir_day' => 'integer|gt:0',
        'discount' => 'require|gt:0',
        'min' => 'egt:0',
        'max' => 'egt:0',
        'start_time' => 'integer|egt:0',
        'end_time' => 'integer|egt:0',
    ];
}