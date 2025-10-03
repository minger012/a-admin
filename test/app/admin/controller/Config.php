<?php

namespace app\admin\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;

class Config extends Base
{
    public function list()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        try {
            $paginator = Db::table('config')
                ->order('id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            if (empty($list)) {
                $list = [
                    ['id' => 1, 'title' => '货币符号', 'content' => ''],
                    ['id' => 2, 'title' => '货币代码', 'content' => ''],
                    ['id' => 3, 'title' => '客服链接', 'content' => ''],
                    ['id' => 4, 'title' => '充值客服链接', 'content' => ''],
                    ['id' => 5, 'title' => '店铺星级配置', 'content' => ''],
                    ['id' => 6, 'title' => '店铺星级多语言映射配置', 'content' => ''],
                    ['id' => 7, 'title' => '常见问题配置', 'content' => ''],
                    ['id' => 8, 'title' => '服务条款', 'content' => ''],
                    ['id' => 9, 'title' => '广告中心Banner配置', 'content' => ''],
                ];
            }
            foreach ($list as $k => $v) {
                $list[$k]['content'] = json_decode(base64_decode($v['content']), true) ?? [];
            }
            $res = [
                'list' => $list,       // 当前页数据
                'total' => $paginator->total(),       // 总记录数
                'page' => $paginator->currentPage(), // 当前页码
                'page_size' => $paginator->listRows(),    // 每页记录数
                'total_page' => $paginator->lastPage(),    // 总页数
            ];
            return apiSuccess('', $res);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function edit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        try {
            foreach ($params['arr'] as $v) {
                $updateData = ['value' => base64_encode(json_encode($v['value'], JSON_UNESCAPED_UNICODE)) ?? ''];
                Db::name('config')
                    ->where(['id' => $v['id']])
                    ->update($updateData);
            }
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
