<?php
namespace app\admin\validate;

use think\Validate;

class AdminValidate extends Validate
{
    //自动时间戳
    protected $autoWriteTimestamp = true;

    //添加时验证
    public $rule = [
        'username' => 'require|unique:admin',
        'password' => 'require|length:6,32',
//        'repassword' => 'require|confirm:password',
//        'phone'     =>  'regex:/^1\d{10}$/',
//        'email'     =>  'email',
        'auth_id' => 'require',
    ];
    public $login_rule = [
        'username' => 'require',
        'password' => 'require|length:6,32',
    ];
    //修改时验证
    public $save_rule = [
        'username' => 'require',
        'password' => 'length:6,32',
//        'repassword' => 'confirm:password',
//        'phone'     =>  'regex:/^1\d{10}$/',
//        'email'     =>  'email',
        'auth_id' => 'require',
    ];
    protected $message = [
        'username.require' => '请输入账号',
        'username.unique' => '账号已存在',
        'password.require' => '请输入密码',
        'password.length' => '请输入6到32位密码',
        'repassword.require' => '请输入确认密码',
        'repassword.confirm' => '两次密码不一致',
        'phone.regex' => '请输入正确的手机号',
        'email.email' => '请输入正确的邮箱',
        'auth_id.require' => '请输入选择角色',
    ];


}