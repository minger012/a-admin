<?php

namespace app\common\model;

use think\facade\Db;
use think\Model;

class ConfigModel extends Model
{
    protected $name = 'config';

    public static $strId = [1, 2];
    public static $langId = [6, 7, 8, 9, 10];
    public static $imageId = [6, 9];
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

    public function getConfigValue($ids, $uid = 0)
    {
        $config = $this->where([['id', 'in', $ids]])->select()->toArray();
        $res = [];
        foreach ($config as $value) {
            $res[$value['id']] = $value['value'];
            if ($value['id'] == 4 && $uid) {
                $config = Db::table('user')->where('id', $uid)->value('service_address');
                if ($config) {
                    $res[$value['id']] = [
                        ['label' => 'czkefu1', 'link' => $config, 'type' => '跳转app']
                    ];
                    continue;
                }
            }
            if (in_array($value['id'], self::$strId)) {
                $res[$value['id']] = $value['value'];
                continue;
            }
            $res[$value['id']] = jsonDecode($value['value']);
            if (in_array($value['id'], self::$langId)) {
                $res[$value['id']] = $res[$value['id']][Request()->header('think-lang')] ?? [];
            }
            if (in_array($value['id'], self::$imageId)) {
                foreach ($res[$value['id']] as $k1 => $v1) {
                    if (isset($res[$value['id']][$k1]['icon'])) {
                        $res[$value['id']][$k1]['icon'] = fileDomain($v1['icon']);
                    }
                    if (isset($res[$value['id']][$k1]['image'])) {
                        $res[$value['id']][$k1]['image'] = fileDomain($v1['image']);
                    }
                }
            }
        }
        return $res;
    }
}