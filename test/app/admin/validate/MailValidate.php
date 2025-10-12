<?php

namespace app\admin\validate;

use think\Validate;

class MailValidate extends Validate
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
        'sendType' => 'integer|in:1,2',
        'type' => 'integer|in:1,2,3',
        'title' => 'require|chsDash',
        'content' => 'require|chsDash',
        'uids' => 'array',
    ];
}