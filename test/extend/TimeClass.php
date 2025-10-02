<?php

class TimeClass
{
    /**
     * 获取今天0点的时间戳
     * @return int
     */
    public static function getTodayStart()
    {
        return strtotime('today');
    }

    /**
     * 获取今天23:59:59的时间戳
     * @return int
     */
    public static function getTodayEnd()
    {
        return strtotime('tomorrow') - 1;
    }

    /**
     * 获取本周一0点的时间戳
     * @return int
     */
    public static function getWeekStart()
    {
        return strtotime('this week Monday');
    }

    /**
     * 获取本周日23:59:59的时间戳
     * @return int
     */
    public static function getWeekEnd()
    {
        return strtotime('next week Monday') - 1;
    }

    /**
     * 获取本月1号0点的时间戳
     * @return int
     */
    public static function getMonthStart()
    {
        return strtotime(date('Y-m-01'));
    }

    /**
     * 获取本月最后一天23:59:59的时间戳
     * @return int
     */
    public static function getMonthEnd()
    {
        return strtotime(date('Y-m-t 23:59:59'));
    }
}
