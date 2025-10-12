<?php
namespace app\admin\model;

use think\Model;

class CodeModel extends Model
{
    protected $table = 'code';

    /**
     * 获取授权码使用统计
     */
    public function getCodeUsageStats($startDate = '', $endDate = '')
    {
        // 设置默认时间范围（最近30天）
        if (empty($startDate)) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        if (empty($endDate)) {
            $endDate = date('Y-m-d');
        }

        $startTime = strtotime($startDate);
        $endTime = strtotime($endDate . ' 23:59:59');

        // 总邀请用户数（state=1的总数）
        $totalCount = $this->where('state', 1)
            ->whereBetween('update_time', [$startTime, $endTime])
            ->count();

        // 计算天数差
        $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
        $dayAverage = $daysDiff > 0 ? $totalCount / $daysDiff : 0;

        // 按月统计
        $monthStats = $this->field("
            DATE_FORMAT(FROM_UNIXTIME(update_time), '%Y年%m月') as month,
            COUNT(*) as register_count
        ")
            ->where('state', 1)
            ->whereBetween('update_time', [$startTime, $endTime])
            ->group("DATE_FORMAT(FROM_UNIXTIME(update_time), '%Y-%m')")
            ->order('month DESC')
            ->select()
            ->toArray();

        // 计算月度占比
        foreach ($monthStats as &$month) {
            $month['percentage'] = $totalCount > 0 ? ($month['register_count'] / $totalCount) * 100 : 0;
        }

        // 按日统计
        $dayStats = $this->field("
            DATE(FROM_UNIXTIME(update_time)) as date,
            COUNT(*) as register_count
        ")
            ->where('state', 1)
            ->whereBetween('update_time', [$startTime, $endTime])
            ->group('DATE(FROM_UNIXTIME(update_time))')
            ->order('date DESC')
            ->select()
            ->toArray();

        // 计算每日占比
        foreach ($dayStats as &$day) {
            $day['percentage'] = $totalCount > 0 ? ($day['register_count'] / $totalCount) * 100 : 0;
        }

        // 明细统计（包含年、月、日、周等信息）
        $dayDetails = $this->field("
            id,
            DATE(FROM_UNIXTIME(update_time)) as date,
            YEAR(FROM_UNIXTIME(update_time)) as year,
            MONTH(FROM_UNIXTIME(update_time)) as month,
            DAY(FROM_UNIXTIME(update_time)) as day,
            WEEK(FROM_UNIXTIME(update_time), 1) as week,
            COUNT(*) as register_count
        ")
            ->where('state', 1)
            ->whereBetween('update_time', [$startTime, $endTime])
            ->group('DATE(FROM_UNIXTIME(update_time))')
            ->order('date DESC')
            ->select()
            ->toArray();

        return [
            'total_register_count' => $totalCount,
            'day_average_count' => round($dayAverage, 2),
            'month_stats' => $monthStats,
            'day_stats' => $dayStats,
            'day_details' => $dayDetails,
        ];
    }

    /**
     * 获取分页的明细数据
     */
    public function getCodeUsageDetails($page = 1, $limit = 10, $startDate = '', $endDate = '')
    {
        $query = $this->field("
            id,
            DATE(FROM_UNIXTIME(update_time)) as date,
            YEAR(FROM_UNIXTIME(update_time)) as year,
            MONTH(FROM_UNIXTIME(update_time)) as month,
            DAY(FROM_UNIXTIME(update_time)) as day,
            WEEK(FROM_UNIXTIME(update_time), 1) as week,
            COUNT(*) as register_count
        ")
            ->where('state', 1)
            ->group('DATE(FROM_UNIXTIME(update_time))')
            ->order('date DESC');

        // 时间范围筛选
        if (!empty($startDate) && !empty($endDate)) {
            $startTime = strtotime($startDate);
            $endTime = strtotime($endDate . ' 23:59:59');
            $query->whereBetween('update_time', [$startTime, $endTime]);
        }

        return $query->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
    }
}