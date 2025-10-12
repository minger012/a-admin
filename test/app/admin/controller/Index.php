<?php

namespace app\admin\controller;

use app\admin\model\CodeModel;
use app\common\validate\CommonValidate;
use think\facade\Db;
use think\facade\Request;

class Index extends Base
{
    /**
     * 获取授权码使用统计数据
     */
    public function getCodeUsageStats()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            // 参数处理
            $startDate = isset($params['start_date']) ? date('Y-m-d', $params['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($params['end_date']) ? date('Y-m-d', $params['end_date']) : date('Y-m-d');
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 10;
            $viewType = isset($params['view_type']) ? $params['view_type'] : 'summary';
            $adminUsername = isset($params['admin_username']) ? $params['admin_username'] : '';

            // 时间范围转换
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');

            // 构建查询条件
            $where = [
                ['state', '=', 1],
                ['update_time', 'between', [$startTime, $endTime]]
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

            // 总邀请用户数（state=1的总数）
            $totalCount = Db::table('code')
                ->where($where)
                ->count();

            // 计算天数差
            $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
            $dayAverage = $daysDiff > 0 ? $totalCount / $daysDiff : 0;

            // 按月统计
            $monthStats = Db::table('code')
                ->field("
                    DATE_FORMAT(FROM_UNIXTIME(update_time), '%Y年%m月') as month,
                    COUNT(*) as register_count
                ")
                ->where($where)
                ->group("DATE_FORMAT(FROM_UNIXTIME(update_time), '%Y-%m')")
                ->order('month DESC')
                ->select()
                ->toArray();

            // 计算月度占比
            foreach ($monthStats as &$month) {
                $month['percentage'] = $totalCount > 0 ? ($month['register_count'] / $totalCount) * 100 : 0;
            }

            // 按日统计（汇总视图）
            $dayStats = Db::table('code')
                ->field("
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    COUNT(*) as register_count
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time))')
                ->order('date DESC')
                ->select()
                ->toArray();

            // 计算每日占比
            foreach ($dayStats as &$day) {
                $day['percentage'] = $totalCount > 0 ? ($day['register_count'] / $totalCount) * 100 : 0;
            }

            // 明细统计（包含年、月、日、周等信息）
            $detailQuery = Db::table('code')
                ->field("
                    id,
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    YEAR(FROM_UNIXTIME(update_time)) as year,
                    MONTH(FROM_UNIXTIME(update_time)) as month,
                    DAY(FROM_UNIXTIME(update_time)) as day,
                    WEEK(FROM_UNIXTIME(update_time), 1) as week,
                    COUNT(*) as register_count
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time))')
                ->order('date DESC');

            // 根据视图类型返回不同的数据结构
            if ($viewType === 'detail') {
                // 明细视图需要分页
                $dayDetails = $detailQuery->paginate([
                    'list_rows' => $limit,
                    'page' => $page
                ]);

                $detailData = $dayDetails->items();
                $detailPagination = [
                    'page' => $dayDetails->currentPage(),
                    'pageSize' => $dayDetails->listRows(),
                    'pageCount' => $dayDetails->lastPage(),
                    'itemCount' => $dayDetails->total(),
                ];
            } else {
                // 汇总视图不需要分页，取所有数据
                $detailData = $detailQuery->select()->toArray();
                $detailPagination = [
                    'page' => 1,
                    'pageSize' => count($detailData),
                    'pageCount' => 1,
                    'itemCount' => count($detailData),
                ];
            }

            $responseData = [
                'total_register_count' => $totalCount,
                'day_average_count' => round($dayAverage, 2),
                'month_stats' => $monthStats,
                'day_stats' => $dayStats,
                'day_details' => $detailData,
            ];

//            // 如果是明细视图，添加分页信息
//            if ($viewType === 'detail') {
                $responseData['pagination'] = $detailPagination;
//            }

            return json([
                'code' => 1,
                'message' => 'success',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'message' => '获取统计数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取单独的明细数据（用于明细视图的分页）
     */
    public function getCodeUsageDetails()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 10;
            $startDate = isset($params['start_date']) ? date('Y-m-d', $params['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($params['end_date']) ? date('Y-m-d', $params['end_date']) : date('Y-m-d');
            $adminUsername = isset($params['admin_username']) ? $params['admin_username'] : '';

            // 时间范围转换
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');

            // 构建查询条件
            $where = [
                ['state', '=', 1],
                ['update_time', 'between', [$startTime, $endTime]]
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

            $detailQuery = Db::table('code')
                ->field("
                    id,
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    YEAR(FROM_UNIXTIME(update_time)) as year,
                    MONTH(FROM_UNIXTIME(update_time)) as month,
                    DAY(FROM_UNIXTIME(update_time)) as day,
                    WEEK(FROM_UNIXTIME(update_time), 1) as week,
                    COUNT(*) as register_count
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time))')
                ->order('date DESC');

            $paginator = $detailQuery->paginate([
                'list_rows' => $limit,
                'page' => $page
            ]);

            return json([
                'code' => 1,
                'message' => 'success',
                'data' => [
                    'day_details' => $paginator->items(),
                    'pagination' => [
                        'page' => $paginator->currentPage(),
                        'pageSize' => $paginator->listRows(),
                        'pageCount' => $paginator->lastPage(),
                        'itemCount' => $paginator->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'message' => '获取明细数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取用户充值统计数据
     */
    public function getRechargeStats()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            // 参数处理
            $startDate = isset($params['start_date']) ? date('Y-m-d', $params['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($params['end_date']) ? date('Y-m-d', $params['end_date']) : date('Y-m-d');
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 10;
            $viewType = isset($params['view_type']) ? $params['view_type'] : 'summary';
            $adminUsername = isset($params['admin_username']) ? $params['admin_username'] : '';

            // 时间范围转换
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');

            // 构建查询条件
            $where = [
                ['update_time', 'between', [$startTime, $endTime]]
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

            // 总充值笔数
            $totalCount = Db::table('order')
                ->where($where)
                ->count();

            // 总充值金额
            $totalAmount = Db::table('order')
                ->where($where)
                ->sum('money');

            // 计算天数差
            $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
            $dayAverage = $daysDiff > 0 ? $totalAmount / $daysDiff : 0;

            // 充值类型统计
            $typeStats = Db::table('order')
                ->field("
                    type,
                    COUNT(*) as recharge_count,
                    SUM(money) as recharge_amount
                ")
                ->where($where)
                ->group('type')
                ->select()
                ->toArray();

            // 计算类型占比
            foreach ($typeStats as &$type) {
                $type['type_name'] = $this->getRechargeTypeName($type['type']);
                $type['percentage'] = $totalAmount > 0 ? ($type['recharge_amount'] / $totalAmount) * 100 : 0;
            }

            // 充值状态统计
            $statusStats = Db::table('order')
                ->field("
                    state as status,
                    COUNT(*) as recharge_count,
                    SUM(money) as recharge_amount
                ")
                ->where($where)
                ->group('state')
                ->select()
                ->toArray();

            // 计算状态占比
            foreach ($statusStats as &$status) {
                $status['status_name'] = $this->getRechargeStatusName($status['status']);
                $status['percentage'] = $totalAmount > 0 ? ($status['recharge_amount'] / $totalAmount) * 100 : 0;
            }

            // 按日统计（汇总视图）
            $dayStats = Db::table('order')
                ->field("
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    COUNT(*) as recharge_count,
                    SUM(money) as recharge_amount
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time))')
                ->order('date DESC')
                ->select()
                ->toArray();

            // 明细统计
            $detailQuery = Db::table('order')
                ->field("
                    id,
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    type,
                    state as status,
                    COUNT(*) as recharge_count,
                    SUM(money) as recharge_amount
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time)), type, state')
                ->order('date DESC, type ASC');

            // 根据视图类型返回不同的数据结构
            if ($viewType === 'detail') {
                // 明细视图需要分页
                $dayDetails = $detailQuery->paginate([
                    'list_rows' => $limit,
                    'page' => $page
                ]);

                $detailData = $dayDetails->items();
                $detailPagination = [
                    'page' => $dayDetails->currentPage(),
                    'pageSize' => $dayDetails->listRows(),
                    'pageCount' => $dayDetails->lastPage(),
                    'itemCount' => $dayDetails->total(),
                ];
            } else {
                // 汇总视图不需要分页，取所有数据
                $detailData = $detailQuery->select()->toArray();
                $detailPagination = [
                    'page' => 1,
                    'pageSize' => count($detailData),
                    'pageCount' => 1,
                    'itemCount' => count($detailData),
                ];
            }

            $responseData = [
                'total_recharge_count' => $totalCount,
                'total_recharge_amount' => round($totalAmount, 2),
                'day_average_amount' => round($dayAverage, 2),
                'type_stats' => $typeStats,
                'status_stats' => $statusStats,
                'day_stats' => $dayStats,
                'day_details' => $detailData,
            ];

            // 如果是明细视图，添加分页信息
            if ($viewType === 'detail') {
                $responseData['pagination'] = $detailPagination;
            }

            return json([
                'code' => 1,
                'message' => 'success',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'message' => '获取充值统计数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取单独的明细数据（用于明细视图的分页）
     */
    public function getRechargeDetails()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 10;
            $startDate = isset($params['start_date']) ? date('Y-m-d', $params['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($params['end_date']) ? date('Y-m-d', $params['end_date']) : date('Y-m-d');
            $adminUsername = isset($params['admin_username']) ? $params['admin_username'] : '';

            // 时间范围转换
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');

            // 构建查询条件
            $where = [
                ['update_time', 'between', [$startTime, $endTime]]
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

            $detailQuery = Db::table('order')
                ->field("
                    id,
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    type,
                    state as status,
                    COUNT(*) as recharge_count,
                    SUM(money) as recharge_amount
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time)), type, state')
                ->order('date DESC, type ASC');

            $paginator = $detailQuery->paginate([
                'list_rows' => $limit,
                'page' => $page
            ]);

            return json([
                'code' => 1,
                'message' => 'success',
                'data' => [
                    'day_details' => $paginator->items(),
                    'pagination' => [
                        'page' => $paginator->currentPage(),
                        'pageSize' => $paginator->listRows(),
                        'pageCount' => $paginator->lastPage(),
                        'itemCount' => $paginator->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'message' => '获取充值明细数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取充值类型名称
     */
    private function getRechargeTypeName($type)
    {
        $typeMap = [
            1 => '真实充值',
            2 => '虚拟充值',
            3 => '系统赠送',
        ];
        return $typeMap[$type] ?? '未知类型';
    }

    /**
     * 获取充值状态名称
     */
    private function getRechargeStatusName($status)
    {
        $statusMap = [
            0 => '失败',
            1 => '成功',
        ];
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取用户提现统计数据
     */
    public function getWithdrawStats()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            // 参数处理
            $startDate = isset($params['start_date']) ? date('Y-m-d', $params['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($params['end_date']) ? date('Y-m-d', $params['end_date']) : date('Y-m-d');
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 10;
            $viewType = isset($params['view_type']) ? $params['view_type'] : 'summary';
            $adminUsername = isset($params['admin_username']) ? $params['admin_username'] : '';

            // 时间范围转换
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');

            // 构建查询条件
            $where = [
                ['state', '=', 1], // 只统计审核通过的提现
                ['update_time', 'between', [$startTime, $endTime]]
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

            // 总提现笔数
            $totalCount = Db::table('withdraw')
                ->where($where)
                ->count();

            // 总提现金额
            $totalAmount = Db::table('withdraw')
                ->where($where)
                ->sum('money');

            // 计算天数差
            $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
            $dayAverage = $daysDiff > 0 ? $totalAmount / $daysDiff : 0;

            // 提现状态统计（虽然只统计state=1，但这里展示所有状态的比例）
            $statusStats = Db::table('withdraw')
                ->field("
                    state as status,
                    COUNT(*) as withdraw_count,
                    SUM(money) as withdraw_amount
                ")
                ->where([
                    ['update_time', 'between', [$startTime, $endTime]]
                ])
                ->group('state')
                ->select()
                ->toArray();

            // 计算状态占比（基于总金额）
            $allStatusTotalAmount = Db::table('withdraw')
                ->where([
                    ['update_time', 'between', [$startTime, $endTime]]
                ])
                ->sum('money');

            foreach ($statusStats as &$status) {
                $status['status_name'] = $this->getWithdrawStatusName($status['status']);
                $status['percentage'] = $allStatusTotalAmount > 0 ? ($status['withdraw_amount'] / $allStatusTotalAmount) * 100 : 0;
            }

            // 按日统计（汇总视图）- 只统计审核通过的
            $dayStats = Db::table('withdraw')
                ->field("
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    COUNT(*) as withdraw_count,
                    SUM(money) as withdraw_amount
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time))')
                ->order('date DESC')
                ->select()
                ->toArray();

            // 计算每日占比
            foreach ($dayStats as &$day) {
                $day['percentage'] = $totalAmount > 0 ? ($day['withdraw_amount'] / $totalAmount) * 100 : 0;
            }

            // 明细统计 - 只统计审核通过的
            $detailQuery = Db::table('withdraw')
                ->field("
                    id,
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    state as status,
                    COUNT(*) as withdraw_count,
                    SUM(money) as withdraw_amount
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time)), state')
                ->order('date DESC');

            // 根据视图类型返回不同的数据结构
            if ($viewType === 'detail') {
                // 明细视图需要分页
                $dayDetails = $detailQuery->paginate([
                    'list_rows' => $limit,
                    'page' => $page
                ]);

                $detailData = $dayDetails->items();
                $detailPagination = [
                    'page' => $dayDetails->currentPage(),
                    'pageSize' => $dayDetails->listRows(),
                    'pageCount' => $dayDetails->lastPage(),
                    'itemCount' => $dayDetails->total(),
                ];
            } else {
                // 汇总视图不需要分页，取所有数据
                $detailData = $detailQuery->select()->toArray();
                $detailPagination = [
                    'page' => 1,
                    'pageSize' => count($detailData),
                    'pageCount' => 1,
                    'itemCount' => count($detailData),
                ];
            }

            $responseData = [
                'total_withdraw_count' => $totalCount,
                'total_withdraw_amount' => round($totalAmount, 2),
                'day_average_amount' => round($dayAverage, 2),
                'status_stats' => $statusStats,
                'day_stats' => $dayStats,
                'day_details' => $detailData,
            ];

            // 如果是明细视图，添加分页信息
            if ($viewType === 'detail') {
                $responseData['pagination'] = $detailPagination;
            }

            return json([
                'code' => 1,
                'message' => 'success',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'message' => '获取提现统计数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取单独的明细数据（用于明细视图的分页）
     */
    public function getWithdrawDetails()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 10;
            $startDate = isset($params['start_date']) ? date('Y-m-d', $params['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($params['end_date']) ? date('Y-m-d', $params['end_date']) : date('Y-m-d');
            $adminUsername = isset($params['admin_username']) ? $params['admin_username'] : '';

            // 时间范围转换
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');

            // 构建查询条件
            $where = [
                ['state', '=', 1], // 只统计审核通过的提现
                ['update_time', 'between', [$startTime, $endTime]]
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

            $detailQuery = Db::table('withdraw')
                ->field("
                    id,
                    DATE(FROM_UNIXTIME(update_time)) as date,
                    state as status,
                    COUNT(*) as withdraw_count,
                    SUM(money) as withdraw_amount
                ")
                ->where($where)
                ->group('DATE(FROM_UNIXTIME(update_time)), state')
                ->order('date DESC');

            $paginator = $detailQuery->paginate([
                'list_rows' => $limit,
                'page' => $page
            ]);

            return json([
                'code' => 1,
                'message' => 'success',
                'data' => [
                    'day_details' => $paginator->items(),
                    'pagination' => [
                        'page' => $paginator->currentPage(),
                        'pageSize' => $paginator->listRows(),
                        'pageCount' => $paginator->lastPage(),
                        'itemCount' => $paginator->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'message' => '获取提现明细数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取提现状态名称
     */
    private function getWithdrawStatusName($status)
    {
        $statusMap = [
            0 => '待审核',
            1 => '审核通过',
            2 => '审核拒绝',
        ];
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取用户充值明细统计数据
     */
    public function getRechargeDetailStats()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            // 参数处理
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 10;
            $userId = isset($params['user_id']) ? $params['user_id'] : '';
            $nickname = isset($params['nickname']) ? $params['nickname'] : '';
            $rechargeType = isset($params['recharge_type']) ? $params['recharge_type'] : '';
            $status = isset($params['status']) ? $params['status'] : '';
            $refundStatus = isset($params['refund_status']) ? $params['refund_status'] : '';

            $startDate = isset($params['start_date']) ? date('Y-m-d', $params['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($params['end_date']) ? date('Y-m-d', $params['end_date']) : date('Y-m-d');

            // 时间范围转换
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');

            // 构建查询条件
            $where = [
                ['o.create_time', 'between', [$startTime, $endTime]]
            ];

            // 权限判断
            if (!$this->isSuperAdmin()) {
                $where[] = ['o.admin_id', '=', $this->adminInfo['id']];
            }

            // 用户ID筛选
            if (!empty($userId)) {
                $where[] = ['o.uid', '=', $userId];
            }

            // 充值类型筛选
            if ($rechargeType !== '') {
                $where[] = ['o.type', '=', $rechargeType];
            }

            // 状态筛选
            if ($status !== '') {
                $where[] = ['o.state', '=', $status];
            }

            // 退款状态筛选（虚拟充值）
            if ($refundStatus !== '') {
                $where[] = ['o.virtual_state', '=', $refundStatus];
            }

            // 总充值次数
            $totalCount = Db::table('order')
                ->alias('o')
                ->where($where)
                ->count();

            // 总充值金额
            $totalAmount = Db::table('order')
                ->alias('o')
                ->where($where)
                ->sum('o.money');

            // 排行榜TOP 3用户
            $topUsers = Db::table('order')
                ->alias('o')
                ->field("
                    o.uid as user_id,
                    o.fb_id,
                    COUNT(*) as order_count,
                    SUM(o.money) as total_amount
                ")
                ->where($where)
                ->group('o.uid')
                ->order('total_amount DESC')
                ->limit(3)
                ->select()
                ->toArray();

            // 处理排行榜数据，获取用户昵称
            foreach ($topUsers as &$user) {
                $userInfo = Db::table('user')->where('id', $user['user_id'])->field('short_name')->find();
                $user['nickname'] = $userInfo['nickname'] ?? '未知用户';
            }

            // 明细列表查询
            $detailQuery = Db::table('order')
                ->alias('o')
                ->leftJoin('user u', 'o.uid = u.id')
                ->field("
                    o.id,
                    o.uid as user_id,
                    u.short_name,
                    o.fb_id,
                    o.money as recharge_amount,
                    '系统充值' as payment_method, -- 这里可以根据实际支付方式字段调整
                    o.state as status,
                    o.type as recharge_type,
                    o.remarks as admin_remark,
                    o.user_remarks as user_remark,
                    o.create_time,
                    o.virtual_state as refund_status
                ")
                ->where($where);

            // 用户昵称筛选
            if (!empty($nickname)) {
                $detailQuery->where('u.short_name', 'like', "%{$nickname}%");
            }

            // 执行分页查询
            $paginator = $detailQuery->order('o.create_time DESC')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page
                ]);

            $detailList = $paginator->items();

            // 处理时间格式
            foreach ($detailList as &$item) {
                $item['create_time'] = date('c', $item['create_time']); // ISO 8601格式
            }

            $responseData = [
                'total_recharge_count' => $totalCount,
                'total_recharge_amount' => round($totalAmount, 2),
                'top_users' => $topUsers,
                'detail_list' => $detailList,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'pageSize' => $paginator->listRows(),
                    'pageCount' => $paginator->lastPage(),
                    'itemCount' => $paginator->total(),
                ]
            ];

            return json([
                'code' => 1,
                'message' => 'success',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'message' => '获取充值明细数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取用户提现明细统计数据
     */
    public function getWithdrawDetailStats()
    {
        try {
            // 接收JSON参数
            $input = request()->getContent();
            $params = json_decode($input, true);

            // 参数处理
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 10;
            $userId = isset($params['user_id']) ? $params['user_id'] : '';
            $nickname = isset($params['nickname']) ? $params['nickname'] : '';
            $status = isset($params['status']) ? $params['status'] : '';

            $startDate = isset($params['start_date']) ? date('Y-m-d', $params['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($params['end_date']) ? date('Y-m-d', $params['end_date']) : date('Y-m-d');

            // 时间范围转换
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');

            // 构建查询条件
            $where = [
                ['w.create_time', 'between', [$startTime, $endTime]]
            ];

            // 权限判断
            if (!$this->isSuperAdmin()) {
                $where[] = ['w.admin_id', '=', $this->adminInfo['id']];
            }

            // 用户ID筛选
            if (!empty($userId)) {
                $where[] = ['w.uid', '=', $userId];
            }

            // 状态筛选
            if ($status !== '') {
                $where[] = ['w.state', '=', $status];
            }

            // 总提现次数
            $totalCount = Db::table('withdraw')
                ->alias('w')
                ->where($where)
                ->count();

            // 总提现金额
            $totalAmount = Db::table('withdraw')
                ->alias('w')
                ->where($where)
                ->sum('w.money');

            // 排行榜TOP 3用户
            $topUsers = Db::table('withdraw')
                ->alias('w')
                ->field("
                    w.uid as user_id,
                    w.fb_id,
                    COUNT(*) as withdraw_count,
                    SUM(w.money) as total_amount
                ")
                ->where($where)
                ->group('w.uid')
                ->order('total_amount DESC')
                ->limit(3)
                ->select()
                ->toArray();

            // 处理排行榜数据，获取用户昵称
            foreach ($topUsers as &$user) {
                $userInfo = Db::table('user')->where('id', $user['user_id'])->field('short_name')->find();
                $user['nickname'] = $userInfo['nickname'] ?? '未知用户';
            }

            // 明细列表查询
            $detailQuery = Db::table('withdraw')
                ->alias('w')
                ->leftJoin('user u', 'w.uid = u.id')
                ->field("
                    w.id,
                    w.uid as user_id,
                    u.short_name,
                    w.fb_id,
                    w.money as withdraw_amount,
                    w.state as status,
                    w.remarks as admin_remark,
                    w.user_remarks as user_remark,
                    w.create_time
                ")
                ->where($where);

            // 用户昵称筛选
            if (!empty($nickname)) {
                $detailQuery->where('u.short_name', 'like', "%{$nickname}%");
            }

            // 执行分页查询
            $paginator = $detailQuery->order('w.create_time DESC')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page
                ]);

            $detailList = $paginator->items();

            // 处理时间格式
            foreach ($detailList as &$item) {
                $item['create_time'] = date('c', $item['create_time']); // ISO 8601格式
            }

            $responseData = [
                'total_withdraw_count' => $totalCount,
                'total_withdraw_amount' => round($totalAmount, 2),
                'top_users' => $topUsers,
                'detail_list' => $detailList,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'pageSize' => $paginator->listRows(),
                    'pageCount' => $paginator->lastPage(),
                    'itemCount' => $paginator->total(),
                ]
            ];

            return json([
                'code' => 1,
                'message' => 'success',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'message' => '获取提现明细数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}