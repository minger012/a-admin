<?php

namespace app\home\controller;

use app\common\validate\CommonValidate;
use think\facade\Db;

class Goods extends Base
{
    public function detail()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)) {
            return apiError($validate->getError());
        }
        try {
            $goodsData = Db::table('goods')
                ->where('id', $params['id'])
                ->find();
            $goodsData['logo'] = getDomain() . $goodsData['logo'];
            $goodsData['image'] = jsonDecode($goodsData['image']);
            $goodsData['app_info'] = jsonDecode($goodsData['app_info']);
            if (!empty($goodsData['image']) && is_array($goodsData['image'])) {
                foreach ($goodsData['image'] as $k => $v) {
                    $goodsData['image'][$k] = getDomain() . $v;
                }
            }
            return apiSuccess('success', $goodsData);
        } catch (\Exception $e) {
            return apiError($e);
        }
    }
}