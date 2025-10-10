<?php

namespace app\admin\controller;

use app\admin\model\CodeModel;
use think\facade\Request;

class Index extends Base
{
    /**
     * 获取授权码使用统计数据
     */
    public function getCodeUsageStats()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        try {
            $startDate = $params['sTime'] ?? '';
            $endDate = $params['eTime'] ?? '';
            $codeModel = new CodeModel();
            $stats = $codeModel->getCodeUsageStats($startDate, $endDate);

            return apiSuccess('success', $stats);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    /**
     * 获取分页的明细数据
     */
    public function getCodeUsageDetails()
    {
        try {
            $page = Request::param('page', 1);
            $limit = Request::param('limit', 10);
            $startDate = Request::param('start_date', '');
            $endDate = Request::param('end_date', '');

            $codeModel = new CodeModel();
            $paginator = $codeModel->getCodeUsageDetails($page, $limit, $startDate, $endDate);

            return json([
                'code' => 200,
                'message' => 'success',
                'data' => [
                    'list' => $paginator->items(),
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
                'code' => 500,
                'message' => '获取明细数据失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}