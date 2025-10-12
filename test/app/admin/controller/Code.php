<?php

namespace app\admin\controller;

use app\admin\validate\CodeValidate;
use app\common\validate\CommonValidate;
use think\facade\Db;

class Code extends Base
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
        if (isset($params['state']) && $params['state'] != '') {
            $where[] = ['state', '=', $params['state']];
        }
        if (!empty($params['sTime'])) {
            $where[] = ['create_time', '>=', $params['sTime']];
        }
        if (!empty($params['eTime'])) {
            $where[] = ['create_time', '<=', $params['eTime']];
        }
        try {
            if (!empty($params['name'])) {
                $admin_id = $res = Db::name('admin')->where(['username' => $params['name']])->value('id');
                if (!empty($admin_id)) {
                    $where[] = ['admin_id', '=', $params['admin_id']];
                }
            }
            $paginator = Db::table('code')
                ->where($where)
                ->order('id', 'desc')// 按ID倒序（可选）
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

    public function add()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CodeValidate();
        if (!$validate->check($params)) {
            return apiError($validate->getError());
        }
        $params['admin_id'] = $this->adminInfo['id'];
        $params['update_time'] = time();
        $params['create_time'] = time();
        Db::name('code')->insert($params);
        return apiSuccess();
    }

//    public function edit()
//    {
//        $input = request()->getContent();
//        $params = json_decode($input, true);
//        $validate = new CodeValidate();
//        if (!$validate->append('id', 'require|number')->check($params)) {
//            return apiError($validate->getError());
//        }
//        try {
//            $res = Db::name('code')->where(['id' => $params['id']])->find();
//            if (!$res) {
//                return apiError('non_existent');
//            }
//            $params['update_time'] = time();
//            Db::name('code')
//                ->where(['id' => $params['id']])
//                ->update($params);
//            return apiSuccess();
//        } catch (\Exception $e) {
//            return apiError($e->getMessage());
//        }
//    }

//    public function del()
//    {
//        $input = request()->getContent();
//        $params = json_decode($input, true);
//        if (!(int)$params['id']) {
//            return apiError('params_error');
//        }
//        try {
//            Db::name('code')->where(['id' => $params['id']])->delete();
//            return apiSuccess();
//        } catch (\Exception $e) {
//            return apiError($e->getMessage());
//        }
//    }
}