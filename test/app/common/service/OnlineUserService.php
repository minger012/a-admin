<?php

namespace app\common\service;

use think\facade\Cache;

class OnlineUserService
{
    public static $key = 'online_user';
    public static $timeOut = 3600;

    /**
     * 用户上线
     */
    public static function online($uid, $admin_id)
    {
        $online = Cache::get(self::$key) ?? [];
        $uidKey = $uid . '_' . $admin_id;
        if (!empty($online[$uidKey]) && $online[$uidKey] + self::$timeOut > time()) {
            return;
        }
        foreach ($online as $k => $v) {
            if ($v + self::$timeOut < time()) {
                unset($online[$k]);
            }
        }
        $online[$uidKey] = time();
        Cache::set(self::$key, $online);
    }

    /**
     * 获取当前在线人数
     * @return int
     */
    public static function count($admin_id = 0): int
    {
        $count = 0;
        $online = Cache::get(self::$key) ?? [];
        foreach ($online as $k => $v) {
            if ($admin_id) {
                list($uidk, $admin_idk) = explode('_', $k);
                if ($admin_id != $admin_idk) {
                    continue;
                }
            }
            if ($v + self::$timeOut > time()) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * 获取在线用户列表
     * @param int $admin_id 管理员ID，若提供则只返回该管理员关联的用户
     * @return array
     */
    public static function getOnlineUserList($admin_id = 0): array
    {
        $result = [];
        $online = Cache::get(self::$key) ?? [];
        $now = time();
        
        foreach ($online as $k => $v) {
            // 检查是否已超时
            if ($v + self::$timeOut < $now) {
                continue;
            }
            
            // 解析用户ID和管理员ID
            list($uid, $adminId) = explode('_', $k);
            
            // 如果指定了管理员ID，则过滤
            if ($admin_id && $admin_id != $adminId) {
                continue;
            }
            
            // 添加到结果集
            $result[] = [
                'uid' => $uid,
                'admin_id' => $adminId,
                'online_time' => $v,
                'duration' => $now - $v, // 在线持续时间（秒）
            ];
        }
        
        return $result;
    }
    
    /**
     * 用户离线
     * @param int $uid 用户ID
     * @param int $admin_id 管理员ID
     * @return bool
     */
    public static function offline($uid, $admin_id): bool
    {
        $online = Cache::get(self::$key) ?? [];
        $uidKey = $uid . '_' . $admin_id;
        
        // 检查用户是否在线
        if (!isset($online[$uidKey])) {
            return false;
        }
        
        // 移除用户的在线状态
        unset($online[$uidKey]);
        
        // 更新缓存
        Cache::set(self::$key, $online);
        
        return true;
    }
}