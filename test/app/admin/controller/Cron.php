<?php

namespace app\admin\controller;

use app\common\model\PlanOrderModel;

class Cron
{
    // 定时任务结算
    public function accounts()
    {
        // 取消内存限制（设置为-1表示无限制）
        ini_set('memory_limit', '-1');
        // 设置脚本最大执行时间为无限制
        set_time_limit(0);
        // 记录开始时间和内存
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        echo date('Y-m-d h:i:s') . "：开始\n";

        /***************脚本内容 star ********************/

        try {
            (new PlanOrderModel())->settleOrders();
        } catch (\Exception $e) {
            echo $e;
            echo "\n";
        }

        /***************脚本内容 end********************/

        // 记录结束时间
        $end_time = microtime(true);
        // 计算总运行时间（秒）
        $execution_time = $end_time - $start_time;
        echo "脚本执行时间: " . $execution_time . " 秒\n";
        // 记录结束内存
        $end_memory = memory_get_usage();
        $memory_consumed = ($end_memory - $start_memory) / (1024 * 1024);
        echo "内存消耗: " . $memory_consumed . " MB\n";
        // 获取脚本峰值内存使用量
        $peak_memory = memory_get_peak_usage(true) / (1024 * 1024);
        echo "峰值内存使用: " . $peak_memory . " MB\n";
    }
}