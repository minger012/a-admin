<?php

namespace app\admin\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;

class Data extends Base
{
    public function register()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        $where = [];
        if (!empty($params['sTime'])) {
            $where[] = ['create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['create_time', '<=', $params['eTime']];
        }
        try {
            if (!$this->isSuperAdmin()) {
                $where[] = $planWhere[] = ['admin_id', '=', $this->adminInfo['id']];
            }
            $list = Db::table('user')->where($where)->field('create_time')->select()->toArray();
            $arr = [];
            var_dump($list);die;
            foreach ($list as $key => $value) {
                $date = date('Y-m-d', $value['create_time']);
                $arr[$date]++;
            }
            $result = [];
            foreach ($arr as $key => $value) {
                $result[] = [
                    'date' => $key,
                    'count' => $value
                ];
            }
            return apiSuccess('success', $result);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }


    /**
     * 按日期统计注册人数
     */
    public function getRegisterStats($startDate = '', $endDate = '', $page = 1, $limit = 20)
    {
        $query = Db::table('user')->field("
            DATE(FROM_UNIXTIME(create_time)) as date,
            COUNT(*) as register_count,
            MAX(create_time) as last_create_time
        ")
            ->group('DATE(FROM_UNIXTIME(create_time))')
            ->order('date DESC');

        // 时间范围筛选
        if (!empty($startDate)) {
            $startTime = strtotime($startDate);
            $query->where('create_time', '>=', $startTime);
        }

        if (!empty($endDate)) {
            $endTime = strtotime($endDate . ' 23:59:59');
            $query->where('create_time', '<=', $endTime);
        }

        return $query->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
    }

    /**
     * 获取日期范围内的所有日期（包括没有注册的日期）
     */
    public function getDateRangeStats($startDate, $endDate, $page = 1, $limit = 20)
    {
        // 生成日期范围
        $dates = $this->generateDateRange($startDate, $endDate);

        // 分页处理
        $total = count($dates);
        $offset = ($page - 1) * $limit;
        $pagedDates = array_slice($dates, $offset, $limit);

        if (empty($pagedDates)) {
            return [
                'data' => [],
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => ceil($total / $limit)
            ];
        }

        // 查询这些日期的注册数据
        $stats = Db::table('user')->field("
            DATE(FROM_UNIXTIME(create_time)) as date,
            COUNT(*) as register_count,
            MAX(create_time) as last_create_time
        ")
            ->whereTime('create_time', 'between', [strtotime($startDate), strtotime($endDate . ' 23:59:59')])
            ->group('DATE(FROM_UNIXTIME(create_time))')
            ->select()
            ->toArray();

        // 转换为以日期为键的数组
        $statsMap = [];
        foreach ($stats as $stat) {
            $statsMap[$stat['date']] = $stat;
        }

        // 合并数据，确保所有日期都有数据
        $result = [];
        foreach ($pagedDates as $date) {
            if (isset($statsMap[$date])) {
                $result[] = [
                    'date' => $date,
                    'register_count' => $statsMap[$date]['register_count'],
                    'create_time' => $statsMap[$date]['last_create_time'] ? date('Y-m-d H:i:s', $statsMap[$date]['last_create_time']) : $date . ' 08:00:13'
                ];
            } else {
                $result[] = [
                    'date' => $date,
                    'register_count' => 0,
                    'create_time' => $date . ' 08:00:13'
                ];
            }
        }

        return [
            'list' => $result,
            'total' => $total,
            'page' => $limit,
            'page_size' => $page,
            'total_page' => ceil($total / $limit)
        ];
    }

    /**
     * 生成日期范围
     */
    private function generateDateRange($startDate, $endDate)
    {
        $dates = [];
        $current = strtotime($startDate);
        $end = strtotime($endDate);

        while ($current <= $end) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }

        // 按日期倒序排列
        return array_reverse($dates);
    }
}
