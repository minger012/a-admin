<?php

namespace app\admin\controller;

use app\admin\validate\GoodsValidate;
use app\common\validate\CommonValidate;
use think\facade\Db;

class Goods extends Base
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
            $paginator = Db::table('goods')
                ->order('id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 10, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            foreach ($list as $key => $value) {
                $list[$key]['i18n'] = !empty($value['i18n']) ? json_decode($value['i18n']) : (object)[];
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
        $validate = new GoodsValidate();
        if (!$validate->check($params)) {
            return apiError($validate->getError());
        }
        // 验证应用信息参数格式
        if (isset($params['app_info'])) {
            $validationResult = $this->validateApp_infoFormat($params['app_info']);
            if ($validationResult !== true) {
                return apiError($validationResult);
            }
            // 转为JSON字符串
            $params['app_info'] = json_encode($params['app_info'], JSON_UNESCAPED_UNICODE);
        }
        $params['create_time'] = time();
        Db::name('goods')->insert($params);
        return apiSuccess();
    }

    public function edit()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new GoodsValidate();
        if (!$validate->append('id', 'require|number')->check($params)) {
            return apiError($validate->getError());
        }
        try {
            $res = Db::name('goods')->where(['id' => $params['id']])->find();
            if (!$res) {
                return apiError('non_existent');
            }

            // 验证app_info参数格式
            if (isset($params['app_info'])) {
                $validationResult = $this->validateApp_infoFormat($params['app_info']);
                if ($validationResult !== true) {
                    return apiError($validationResult);
                }

                // 将app_info转为JSON字符串
                $params['app_info'] = json_encode($params['app_info'], JSON_UNESCAPED_UNICODE);
            }

            Db::name('goods')
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
            Db::name('goods')->where(['id' => $params['id']])->delete();
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e);
        }
    }

    /**
     * 验证app_info格式是否正确
     *
     * @param array $app_info app_info数据
     * @return true|string 验证通过返回true，否则返回错误信息
     */
    protected function validateApp_infoFormat(&$app_info)
    {
        if (!is_array($app_info)) {
            return 'app_info must be an array';
        }
        // 检查所有语言版本的格式
        $defaultKeys = ['title', 'content'];
        foreach ($app_info as $v) {
            foreach ($v as $v1) {
                if (!is_array($v1)) {
                    return "app_info.$v1 must be an array";
                }
                // 检查字段类型，如果字段不存在，设置为空字符串
                foreach ($defaultKeys as $key) {
                    if (!is_string($v1[$key]) || empty($v1[$key])) {
                        return "app_info.$v1[$key].$key must be a string";
                    }
                }
            }
        }
        return true;
    }
}
