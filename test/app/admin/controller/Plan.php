<?php

namespace app\admin\controller;

use app\admin\validate\PlanValidate;
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
        $where = [];
        if (!empty($params['id'])) {
            $where[] = ['id', '=', $params['id']];
        }
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
            $paginator = Db::table('plan')
                ->where($where)
                ->order('id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            // 字段转化
            foreach ($list as $key => $value) {
                foreach ($this->jsonField as $field) {
                    $list[$key][$field] = !empty($value[$field]) ? json_decode(base64_decode($value[$field]), true) : [];
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

    public function add()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new PlanValidate();
        if (!$validate->check($params)) {
            return apiError($validate->getError());
        }
        foreach ($this->jsonField as $field) {
            $params[$field] = !empty($params[$field]) ? base64_encode(json_encode($params[$field], JSON_UNESCAPED_UNICODE)) : '';
        }
        $params['number'] = generateShortId();
        $params['update_time'] = time();
        $params['create_time'] = time();
        Db::name('plan')->insert($params);
        return apiSuccess();
    }

    public function edit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new PlanValidate();
        if (!$validate->append('id', 'require|number')->check($params)) {
            return apiError($validate->getError());
        }
        try {
            $res = Db::name('plan')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }
            foreach ($this->jsonField as $field) {
                $params[$field] = !empty($params[$field]) ? base64_encode(json_encode($params[$field], JSON_UNESCAPED_UNICODE)) : '';
            }
            $params['update_time'] = time();
            Db::name('plan')
                ->where(['id' => $params['id']])
                ->update($params);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e);
        }
    }

    public function del()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        if (!(int)$params['id']) {
            return apiError('params_error');
        }
        try {
            Db::name('plan')->where(['id' => $params['id']])->delete();
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e);
        }
    }
}