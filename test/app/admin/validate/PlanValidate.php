<?php

namespace app\admin\validate;

use think\Validate;

class PlanValidate extends Validate
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
        'goods_id' => 'require|integer|gt:0',
        'intro' => 'require',
        'image' => 'require|max:100',
        'state' => 'integer|in:0,1',
        'content' => 'require|array',
        'content.*.title' => 'require|max:100',
        'content.*.content' => 'require',
        'orienteering.*.title' => 'require|max:100',
        'orienteering.*.content' => 'require',
        'rule.*.title' => 'require|max:100',
        'rule.*.content' => 'require',
    ];
}