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
        'logo' => 'require|max:100',
        'image' => 'require|max:100',
        'company' => 'require|max:100',
        'type_name' => 'require|max:100',
        'intro' => 'require',
        'sort' => 'integer',
        'category' => 'integer|gt:0',
        'is_home' => 'integer|in:0,1',
        'is_hot' => 'integer|in:0,1',
        'state' => 'integer|in:0,1,2,3',
        'app_info.*.title' => 'require|max:100',
        'app_info.*.content' => 'require',
    ];
}