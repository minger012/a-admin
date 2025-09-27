<?php

namespace app\admin\validate;

use think\Validate;

class CodeValidate extends Validate
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
        'code' => 'require|integer|length:8|unique:code'
    ];
}