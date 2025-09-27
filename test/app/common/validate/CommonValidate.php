<?php

namespace app\common\validate;

use think\Validate;

class CommonValidate extends Validate
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
    ];
    public $period_no_rule = [
        'period_no' => 'require|integer|gt:0',
    ];
    public $id_rule = [
        'id' => 'require|integer|gt:0',
    ];

    public $eidt_pwd_rule = [
        'id' => 'require|integer|gt:0',
        'password' => 'require|length:6,32',
    ];

    public $code_rule = [
        'code' => 'require|regex:/^[a-zA-Z,]+$/',
    ];
    public $page_rule = [
        'pageSize' => 'integer|gt:0',
        'page' => 'integer|gt:0',
    ];
    public $withdraw_state = [
        'state' => 'in:1,2',
    ];
    public $config_id = [
        'id' => 'in:1,2,3,9',
    ];
}