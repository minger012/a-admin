<?php

namespace app\admin\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;

class userCoupon extends Base
{
    public function list()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        $where = [];
        if (!empty($params['fb_id'])) {
            $where[] = ['a.fb_id', '=', $params['fb_id']];
        }
        if (!empty($params['state'])) {
            if ($params['state'] == 1) {
                $where[] = [['a.use_time', '=', 0]];
            } elseif ($params['state'] == 2) {
                $where[] = [['a.use_time', '!=', 0]];
            }
        }
        if (!empty($params['uid'])) {
            $where[] = ['a.uid', '=', $params['uid']];
        }
        if (!empty($params['cid'])) {
            $where[] = ['a.cid', '=', $params['cid']];
        }
        if (!empty($params['sTime'])) {
            $where[] = ['a.create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['a.create_time', '<=', $params['eTime']];
        }
        try {
            $paginator = Db::table('user_coupon')
                ->alias('a')
                ->join('user b', 'a.uid=b.id')
                ->join('coupon c', 'a.cid=c.id')
                ->field('a.*,b.short_name,c.name')
                ->where($where)
                ->order('a.id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            $res = [
                'list' => $list,       // 当前页数据
                'total' => $paginator->total(),       // 总记录数
                'page' => $paginator->currentPage(), // 当前页码
                'page_size' => $paginator->listRows(),    // 每页记录数
                'total_page' => $paginator->lastPage(),    // 总页数
            ];
            return apiSuccess('success', $res);
        } catch (\Exception $e) {
            return apiError($e);
        }
    }
}
