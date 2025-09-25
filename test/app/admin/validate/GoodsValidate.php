<?php

namespace app\admin\validate;

use think\Validate;

class GoodsValidate extends Validate
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
        'a_name' => 'require|max:10',
        'intro' => 'require|max:200',
        'period_time' => 'require|integer|gt:0',
        'seal_time' => 'require|integer|gt:0',
        'multiple' => 'require|float|gt:0',
    ];
}