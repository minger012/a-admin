<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'lotteryOpen:cron' => 'app\admin\command\LotteryOpenCron',
        'lotteryPeriod:cron' => 'app\admin\command\LotteryPeriodCron',
    ],
];
