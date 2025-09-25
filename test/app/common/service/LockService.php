<?php

namespace app\common\service;

use think\facade\Cache;

class LockService
{
    public static  function apiLimit($key, int $cd = 2)
    {
        // 检查是否已锁定
        if (Cache::has($key)) {
            return false;
        }
        // 设置锁
        Cache::set($key, 1, $cd);
        return true;
    }
}