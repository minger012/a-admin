<?php

namespace app\admin\model;

use EncryptClass;
use think\facade\Cache;
use think\facade\Db;
use think\Model;

class AdminModel extends Model
{
    protected $table = 'admin';
    //登录缓存key
    static protected $_login_cache_key = 'admin_user_';
    //加密密钥
    static protected $_token_secretKey = 'ycadmin';
    //登录有效期
    protected $_login_ttl = 7 * 24 * 3600; // 7天
    // 管理员账号类型
    const TYPE_1 = 1;// 员工
    const TYPE_2 = 2;// 团队
    const TYPE_3 = 3;// 客服
    const TYPE_4 = 4;// 系统

    //登录
    public function login($username, $password)
    {
        try {
            $where['username'] = $username;
            // 获取用户数据
            $admin = $this->where($where)->find();
            if (empty($admin) && $admin['state'] != 1) {
                return apiError('login_null');
            }
            // 验证用户密码
            if (!checkPassword($password, $admin['password'])) {
                return apiError('login_error');
            }
            // 更新登录信息
            $admin->login_count = $admin->login_count + 1;
            $admin->last_login_time = time();
            $admin->last_login_ip = request()->ip();
            $admin->save();
            // 加密token
            $token = (new EncryptClass())->myEncrypt(json_encode([
                'username' => $username,
                'password' => $password,
                'time' => time()
            ]), self::$_token_secretKey);
            // 权限
            $string = Db::name('admin_auth')->where(['id' => $admin['auth_id']])->value('auth');
            // 返回数据
            $data = [
                'id' => $admin['id'],
                'username' => $username,
                'token' => $token,
                'auth_id' => $admin['auth_id'],
                'pid' => $admin['pid'],
                'auth' => !empty($string) ? explode(',', $string) : [],
            ];
            // 缓存
            Cache::set(self::$_login_cache_key . $username, $data, $this->_login_ttl);
            return apiSuccess('success', $data);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    //获取用户缓存
    public static function getAdminInfo()
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
        $adminInfo = Cache::get(self::$_login_cache_key . $data['username']);
        if (empty($adminInfo)
//            || $adminInfo['token'] !== $token
        ) {
            return [];
        }
        return $adminInfo;
    }
}