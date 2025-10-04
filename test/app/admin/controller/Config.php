<?php

namespace app\admin\controller;

use app\common\model\ConfigModel;
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
                $list = ConfigModel::$init;
                Db::table('config')->insertAll($list);
            }
            foreach ($list as $k => $v) {
                if (in_array($v['id'], ConfigModel::$strId)) {
                    continue;
                }
                $list[$k]['content'] = jsonDecode($v['content']);
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
            foreach ($params['config'] as $v) {
                $value = $v['content'];
                if (!in_array($v['id'], ConfigModel::$strId)) {
                    $value = jsonEncode($v['content']);
                }
                $updateData = ['value' => $value];
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
