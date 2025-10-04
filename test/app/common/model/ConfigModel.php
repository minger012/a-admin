<?php

namespace app\common\model;

use think\facade\Db;
use think\Model;

class ConfigModel extends Model
{
    protected $name = 'config';

    public static $strId = [1, 2];
    public static $init = [
        ['id' => 1, 'title' => '货币符号', 'value' => ''],
        ['id' => 2, 'title' => '货币代码', 'value' => ''],
        ['id' => 3, 'title' => '客服链接', 'value' => ''],
        ['id' => 4, 'title' => '充值客服链接', 'value' => ''],
        ['id' => 5, 'title' => '店铺星级配置', 'value' => ''],
        ['id' => 6, 'title' => '店铺星级多语言映射配置', 'value' => ''],
        ['id' => 7, 'title' => '常见问题配置', 'value' => ''],
        ['id' => 8, 'title' => '服务条款', 'value' => ''],
        ['id' => 9, 'title' => '广告中心Banner配置', 'value' => ''],
    ];

    public function getConfigValue($id, $uid = 0)
    {
        if ($id == 4 && $uid) {
            $config = Db::table('user')->where('id', $uid)->value('re_service_address');
            if ($config) {
                return $config;
            }
        }
        $config = $this->where('id', $id)->value('value');
        if (in_array($id, self::$strId)) {
            return $config;
        }
        return jsonDecode($config);
    }
}