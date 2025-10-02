<?php

namespace app\home\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;

class Plan extends Base
{
    // json字段
    protected $jsonField = ['content', 'orienteering', 'rule'];

    public function list()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        try {
            $paginator = Db::table('plan')
                ->alias('a')
                ->join('goods b', 'a.goods_id = b.id')
                ->field('a.*,b.company')
                ->order('a.id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            // 字段转化
            foreach ($list as $key => $value) {
                foreach ($this->jsonField as $field) {
                    $list[$key][$field] = !empty($value[$field]) ? json_decode($value[$field], true) : [];
                }
            }
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

    public function detail()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)) {
            return apiError($validate->getError());
        }
        try {
            $planData = Db::table('plan')
                ->where('a.id', $params['id'])
                ->alias('a')
                ->join('goods b', 'a.goods_id = b.id')
                ->field('a.*,b.company')
                ->find();
            foreach ($this->jsonField as $field) {
                $planData[$field] = !empty($value[$field]) ? json_decode($value[$field], true) : [];
            }
            return apiSuccess('success', $planData);
        } catch (\Exception $e) {
            return apiError($e);
        }
    }
}