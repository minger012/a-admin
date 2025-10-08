<?php

namespace app\common\model;

use think\facade\Db;
use think\Model;

class PlanOrderModel extends Model
{
    protected $table = 'plan_order';

    // 订单状态
    const state_0 = 0;// 待投放
    const state_1 = 1;// 匹配中
    const state_2 = 2;// 投放中
    const state_3 = 3;// 投放失败
    const state_4 = 4;// 等待结算
    const state_5 = 5;// 结算成功


    /**
     * 结算订单逻辑
     */
    public function settleOrders()
    {
        try {
            $currentTime = time();
            $success = 0;
            $fail = 0;
            // 查询需要结算的订单：状态为投放中(2)且未完成
            $orders = Db::name($this->table)->where('state', 2)// 投放中
            ->where('cd', '>', 0)// 总时间大于0
            ->select()->toArray();
            foreach ($orders as $order) {
                try {
                $this->settleSingleOrder($order, $currentTime);
                $success++;
                } catch (\Exception $e) {
                    // 记录错误日志
                    echo "订单结算失败 ID:{$order['id']} - " . $e->getMessage() . "\n";
                    $fail++;
                }
            }
            echo json_encode([
                'success' => $success,
                'fail' => $fail,
                'total' => count($orders)
            ]);
            echo "\n";
        } catch (\Exception $e) {
            echo $e;
            echo "\n";
        }

    }

    /**
     * 结算单个订单
     */
    private function settleSingleOrder($order, $currentTime)
    {
        // 计算已过去的时间（分钟）
        $elapsedMinutes = max(0, round(($currentTime - $order['start_time']) / 60));
        // 计算进度百分比
        if ($order['cd'] <= 0) {
            $schedule = 100;
        } else {
            $schedule = min(100, (($order['cd'] - max(0, $order['cd'] - $elapsedMinutes)) / $order['cd']) * 100);
        }
        // 计算利润（根据你的业务逻辑调整）
        $profit = $this->calculateProfit($order, $schedule);
        // 计算已投放
        $putIn = round($order['money'] * ($schedule / 100), 2);
        // 更新数据
        $updateData = [
            'schedule' => round($schedule, 2),
            'wait_putIn' => $order['money'] - $putIn,
            'putIn' => $putIn,
            'profit' => $profit,
            'update_time' => $currentTime
        ];

        // 如果进度达到100%，更新状态为等待结算(4)
        if ($schedule >= 100) {
            $updateData['state'] = 4;
            $updateData['schedule'] = 100; // 确保进度为100%
        }
        // 更新数据库
        $this->where('id', $order['id'])->update($updateData);
        echo "订单号：{$order['id']},schedule：{$updateData['schedule']},profit:{$profit}\n";
    }

    /**
     * 计算利润（根据你的业务逻辑调整）
     */
    private function calculateProfit($order, $schedule)
    {
        // 这里根据你的业务逻辑计算利润
        // 示例：根据进度比例计算利润
        $progressRatio = $schedule / 100;

        // 基础利润计算（根据实际金额和投放情况）
        $baseProfit = round($order['money'] * $order['rate'] / 100, 2);

        // 根据进度计算当前利润
        $currentProfit = round($baseProfit * $progressRatio, 2);

        // 确保利润不为负数
        return max(0, round($currentProfit, 2));
    }
}