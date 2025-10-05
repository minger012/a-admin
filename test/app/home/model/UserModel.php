<?php

namespace app\home\model;

use EncryptClass;
use think\facade\Cache;
use think\facade\Db;
use think\Model;

class UserModel extends Model
{
    protected $table = 'user';
    //登录缓存key
    static protected $_login_cache_key = 'home_user_';
    //加密密钥
    static protected $_token_secretKey = 'ycadmin_user';
    //邀请密钥
    static protected $_invitation_secretKey = 'ycadmin_user_invitation';
    //登录有效期
    static protected $_login_ttl = 24 * 3600 * 30; //30天

    const STATE_1 = 1;// 正常
    const STATE_2 = 2;// 禁止登录
    const STATE_3 = 3;// 禁止投注
    const STATE_4 = 4;// 禁止提现
    const STATE_5 = 5;// 禁止充值

    //登录
    public function login($username, $password)
    {
        try {
            // 获取用户数据
            $user = $this->where(['username' => $username])->find();
            if (!empty($user) && $user['state'] == self::STATE_2) {
                throw new \Exception(lang('login_null'));
            }
            // 验证用户密码
            if (!checkPassword($password, $user['password'])) {
                throw new \Exception(lang('login_error'));
            }
            // 更新登录信息
            $user->login_count = $user->login_count + 1;
            $user->last_login_time = time();
            $user->last_login_ip = request()->ip();
            $user->save();
            // 加密token
            $token = (new EncryptClass())->myEncrypt(json_encode([
                'username' => $username,
                'password' => $password,
                'TimeClass' => time()
            ]), self::$_token_secretKey);
            // 是否有设置收款
            $cardId = Db::name('bank_card')->where('uid', $user['id'])->value('id');
            // 返回数据
            $data = [
                'id' => $user['id'],
                'last_login_time' => $user['last_login_time'],
                'last_login_ip' => $user['last_login_ip'],
                'lang' => $user['lang'] ?? config('lang.default_lang'),
                'set_pay_password' => $user['pay_password'] ? 1 : 0,// 是否有设置支付密码
                'set_card' => !empty($cardId) ? 1 : 0,
                'username' => $username,
                'name' => $user['name'],
                'admin_id' => $user['admin_id'],
                'token' => $token,
            ];
            // 缓存
            self::setUserInfo($username, $data);
            return apiSuccess('success', $data);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }

    }

    //退出登录
    public static function loginOut($username)
    {
        Cache::delete(self::$_login_cache_key . $username);
    }

    //保存用户缓存
    public static function setUserInfo($username, $data)
    {
        Cache::set(self::$_login_cache_key . $username, $data, self::$_login_ttl);
    }

    //获取用户缓存
    public static function getUserInfo()
    {
        // 从请求头中获取Token（默认字段为Authorization）
        $token = Request()->header('Authorization');
        if (empty($token)) {
            return [];
        }
        $data = (new EncryptClass())->decrypt(trim($token), self::$_token_secretKey);
        $data = json_decode($data, true);
        if (empty($data)) {
            return [];
        }
        $userInfo = Cache::get(self::$_login_cache_key . $data['username']);
        if (empty($userInfo) || $userInfo['token'] !== $token) {
            return [];
        }
        return $userInfo;
    }

    // 获取最上级邀请人
    public static function getPid($uid)
    {
        $user = Db::table('user')->where('id', $uid)->field('id,pid,admin_id')->find();
        if (!$user || $user['pid'] == 0) {
            return $user; // 如果没有上级或pid为0，返回自己
        }
        return self::getPid($user['pid']); // 递归查找
    }
}