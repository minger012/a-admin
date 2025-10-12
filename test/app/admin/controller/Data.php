<?php

namespace app\admin\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;

class Data extends Base
{
    public function register()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['pageSize']) ? intval($params['pageSize']) : 20;
            $sTime = isset($params['sTime']) ? intval($params['sTime']) : 0;
            $eTime = isset($params['eTime']) ? intval($params['eTime']) : 0;
            $adminUsername = isset($params['admin_username']) ? $params['admin_username'] : '';

            // 时间范围处理
            if ($sTime && $eTime) {
                $startTime = $sTime;
                $endTime = $eTime;
            } else {
                // 默认最近20天
                $endTime = time();
                $startTime = strtotime('-19 days', $endTime); // 包括今天共20天
            }

            // 构建查询条件
            $where = [
                ['create_time', 'between', [$startTime, $endTime]]
            ];

            // 权限判断
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            } elseif (!empty($adminUsername)) {
                $admin_id = Db::name('admin')->where(['username' => $adminUsername])->value('id');
                if (!empty($admin_id)) {
                    $where[] = ['admin_id', '=', $admin_id];
                }
            }

            // 获取数据库第一条数据的时间
            $firstUserQuery = Db::name('user');
            if (!empty($where)) {
                $firstUserQuery->where($where);
            }
            $firstUser = $firstUserQuery->order('create_time ASC')->find();

            if (!$firstUser) {
                // 如果没有数据，返回空结果
                return json([
                    'code' => 1,
                    'msg' => '成功',
                    'data' => [
                        'list' => [],
                        'total' => 0,
                        'page' => $page,
                        'page_size' => $limit,
                        'total_page' => 0
                    ]
                ]);
            }
            $firstDataTime = $firstUser['create_time'];
            // 时间范围处理
            if ($sTime && $eTime) {
                $startTime = $sTime;
                $endTime = $eTime;
            } else {
                // 默认从第一条数据时间到今天
                $endTime = time();
                $startTime = $firstDataTime;

                // 如果时间范围超过20天，默认只显示最近20天
                $daysDiff = ($endTime - $startTime) / 86400;
                if ($daysDiff > 20) {
                    $startTime = strtotime('-19 days', $endTime);
                }
            }

            // 确保开始时间不早于第一条数据时间
            if ($startTime < $firstDataTime) {
                $startTime = $firstDataTime;
            }
            // 生成日期范围
            $dateRange = $this->generateDateRange($startTime, $endTime);

            // 分页处理
            $total = count($dateRange);
            $offset = ($page - 1) * $limit;
            $pagedDates = array_slice($dateRange, $offset, $limit);

            // 查询实际注册数据
            $actualStats = Db::name('user')
                ->field("
                    DATE(FROM_UNIXTIME(create_time)) as date,
                    COUNT(*) as register_count,
                    MAX(create_time) as last_create_time
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(create_time))')
                ->select()
                ->toArray();

            // 转换为以日期为键的数组
            $statsMap = [];
            foreach ($actualStats as $stat) {
                $statsMap[$stat['date']] = $stat;
            }

            // 构建返回数据，确保所有日期都有数据
            $list = [];
            foreach ($pagedDates as $date) {
                if (isset($statsMap[$date])) {
                    $list[] = [
                        'date' => $date,
                        'register_count' => $statsMap[$date]['register_count'],
                        'create_time' => $statsMap[$date]['last_create_time'] ?
                            date('Y-m-d H:i:s', $statsMap[$date]['last_create_time']) :
                            $date . ' 00:00:00'
                    ];
                } else {
                    $list[] = [
                        'date' => $date,
                        'register_count' => 0,
                        'create_time' => $date . ' 00:00:00'
                    ];
                }
            }

            return json([
                'code' => 1,
                'msg' => '成功',
                'data' => [
                    'list' => $list,
                    'total' => $total,
                    'page' => $page,
                    'page_size' => $limit,
                    'total_page' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }


    /**
     * 生成日期范围
     */
    private function generateDateRange($startTime, $endTime)
    {
        $dates = [];
        $current = strtotime(date('Y-m-d', $startTime));
        $end = strtotime(date('Y-m-d', $endTime));

        while ($current <= $end) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }

        // 按日期倒序排列（最新的在前）
        return array_reverse($dates);
    }

    public function withdraw()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['pageSize']) ? intval($params['pageSize']) : 20;
            $sTime = isset($params['sTime']) ? intval($params['sTime']) : 0;
            $eTime = isset($params['eTime']) ? intval($params['eTime']) : 0;
            $adminUsername = isset($params['admin_username']) ? $params['admin_username'] : '';

            // 时间范围处理
            if ($sTime && $eTime) {
                $startTime = $sTime;
                $endTime = $eTime;
            } else {
                // 默认最近20天
                $endTime = time();
                $startTime = strtotime('-19 days', $endTime); // 包括今天共20天
            }

            // 构建查询条件
            $where = [
                ['state', '=', 1],
                ['create_time', 'between', [$startTime, $endTime]]
            ];

            // 权限判断
            if (!$this->isSuperAdmin()) {
                $where[] = ['admin_id', '=', $this->adminInfo['id']];
            } elseif (!empty($adminUsername)) {
                $admin_id = Db::name('admin')->where(['username' => $adminUsername])->value('id');
                if (!empty($admin_id)) {
                    $where[] = ['admin_id', '=', $admin_id];
                }
            }

            // 获取数据库第一条数据的时间
            $firstUserQuery = Db::name('withdraw');
            if (!empty($where)) {
                $firstUserQuery->where($where);
            }
            $firstUser = $firstUserQuery->order('create_time ASC')->find();

            if (!$firstUser) {
                // 如果没有数据，返回空结果
                return json([
                    'code' => 1,
                    'msg' => '成功',
                    'data' => [
                        'list' => [],
                        'total' => 0,
                        'page' => $page,
                        'page_size' => $limit,
                        'total_page' => 0
                    ]
                ]);
            }
            $firstDataTime = $firstUser['create_time'];
            // 时间范围处理
            if ($sTime && $eTime) {
                $startTime = $sTime;
                $endTime = $eTime;
            } else {
                // 默认从第一条数据时间到今天
                $endTime = time();
                $startTime = $firstDataTime;

                // 如果时间范围超过20天，默认只显示最近20天
                $daysDiff = ($endTime - $startTime) / 86400;
                if ($daysDiff > 20) {
                    $startTime = strtotime('-19 days', $endTime);
                }
            }

            // 确保开始时间不早于第一条数据时间
            if ($startTime < $firstDataTime) {
                $startTime = $firstDataTime;
            }
            // 生成日期范围
            $dateRange = $this->generateDateRange($startTime, $endTime);

            // 分页处理
            $total = count($dateRange);
            $offset = ($page - 1) * $limit;
            $pagedDates = array_slice($dateRange, $offset, $limit);

            // 查询实际注册数据
            $actualStats = Db::name('withdraw')
                ->field("
                    DATE(FROM_UNIXTIME(create_time)) as date,
                    COUNT(*) as withdraw_count,
                    SUM(money) as total_amount,
                    MAX(create_time) as last_create_time
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(create_time))')
                ->select()
                ->toArray();
            // 转换为以日期为键的数组
            $statsMap = [];
            foreach ($actualStats as $stat) {
                $statsMap[$stat['date']] = $stat;
            }

            // 构建返回数据，确保所有日期都有数据
            $list = [];
            foreach ($pagedDates as $date) {
                if (isset($statsMap[$date])) {
                    $list[] = [
                        'date' => $date,
                        'withdraw_count' => $statsMap[$date]['withdraw_count'],
                        'total_amount' => round($statsMap[$date]['total_amount'], 2),
                        'create_time' => $statsMap[$date]['last_create_time'] ?
                            date('Y-m-d H:i:s', $statsMap[$date]['last_create_time']) :
                            $date . ' 00:00:00'
                    ];
                } else {
                    $list[] = [
                        'date' => $date,
                        'withdraw_count' => 0,
                        'total_amount' => 0.00,
                        'create_time' => $date . ' 08:00:00'
                    ];
                }
            }

            return json([
                'code' => 1,
                'msg' => '成功',
                'data' => [
                    'list' => $list,
                    'total' => $total,
                    'page' => $page,
                    'page_size' => $limit,
                    'total_page' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
