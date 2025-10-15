<?php

namespace app\admin\controller;

use app\common\model\PlanOrderModel;
use think\facade\Db;

class Cron
{
    // 定时任务结算
    public function accounts()
    {
        // 取消内存限制（设置为-1表示无限制）
        ini_set('memory_limit', '-1');
        // 设置脚本最大执行时间为无限制
        set_time_limit(0);
        // 记录开始时间和内存
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        echo date('Y-m-d h:i:s') . "：开始\n";

        /***************脚本内容 star ********************/

        try {
            (new PlanOrderModel())->settleOrders();
        } catch (\Exception $e) {
            echo $e->getMessage();
            echo "\n";
        }

        /***************脚本内容 end********************/

        // 记录结束时间
        $end_time = microtime(true);
        // 计算总运行时间（秒）
        $execution_time = $end_time - $start_time;
        echo "脚本执行时间: " . $execution_time . " 秒\n";
        // 记录结束内存
        $end_memory = memory_get_usage();
        $memory_consumed = ($end_memory - $start_memory) / (1024 * 1024);
        echo "内存消耗: " . $memory_consumed . " MB\n";
        // 获取脚本峰值内存使用量
        $peak_memory = memory_get_peak_usage(true) / (1024 * 1024);
        echo "峰值内存使用: " . $peak_memory . " MB\n";
    }

    // 爬虫
    public function copyGoods()
    {
        // 取消内存限制（设置为-1表示无限制）
        ini_set('memory_limit', '-1');
        // 设置脚本最大执行时间为无限制
        set_time_limit(0);
        // 记录开始时间和内存
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        echo date('Y-m-d h:i:s') . "：开始\n";

        /***************脚本内容 star ********************/

        try {
            // 从API接口获取数据
            $apiUrl = 'http://43.199.170.203:25002/api/facebook/v1/productInfo/query?token%20=%20123';
            $authorization = 'facebook-managerToken eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NjA3MDYxNzgsImZhY2Vib29rLW1hbmFnZXJpZGVudGl0eSI6IjJkZmM5NTZlLWZiMzktNGIxMS05NmYwLWFlNmQ1ZWI2MDg3MiIsIm9yaWdfaWF0IjoxNzYwNDQ2OTc4fQ.wqeyvGdRSD1VVw8HG1FA1gMJdCqBeefUuBwchZYuuA8';

            echo "开始从API获取数据...\n";

            $dataArray = self::post($apiUrl, $authorization);

            if (empty($dataArray)) {
                throw new \Exception('从API获取数据失败或数据为空');
            }
            echo "成功获取 " . count($dataArray['data']['data']) . " 条数据\n";

            return;
            $arr = $dataArray['data']['data'];
            $arr = array_reverse($arr);
            $sql = [];
            foreach ($arr as $item) {
                $sql[] = [
                    'logo' => $item['logo'] ?? '',
                    'image' =>  $item['image_urls'] ? jsonEncode(json_decode($item['image_urls'],true)): '',
                    'name' => $item['name'] ?? '',
                    'company' => $item['company'] ?? '',
                    'type_name' => $item['type'] ?? '',
                    'intro' => $this->removeEmoji($item['description']) ,
                    'google_play' => $item['googlePlayUrl'] ?? '',
                    'app_store' => $item['appStoreUrl'] ?? '',
                    'app_info' => $this->convertAppInfoFormat($item['appInfos'] ?? ''),
                    'is_home' => 0,
                    'is_hot' => 0,
                    'update_time' => time(),
                    'create_time' => time(),
                    'state' => 1
                ];
            }
            Db::table('goods')->insertAll($sql);
        } catch (\Exception $e) {
            echo $e->getMessage();
            echo "\n";
        }

        /***************脚本内容 end********************/

        // 记录结束时间
        $end_time = microtime(true);
        // 计算总运行时间（秒）
        $execution_time = $end_time - $start_time;
        echo "脚本执行时间: " . $execution_time . " 秒\n";
        // 记录结束内存
        $end_memory = memory_get_usage();
        $memory_consumed = ($end_memory - $start_memory) / (1024 * 1024);
        echo "内存消耗: " . $memory_consumed . " MB\n";
        // 获取脚本峰值内存使用量
        $peak_memory = memory_get_peak_usage(true) / (1024 * 1024);
        echo "峰值内存使用: " . $peak_memory . " MB\n";
    }
    /**
     * 移除emoji字符
     */
    private function removeEmoji($str)
    {
        // 匹配4字节的UTF-8字符（emoji）
        return preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $str);
    }
    /**
     * 转换app_info为指定格式
     */
    private function convertAppInfoFormat($appInfosJson)
    {
        if (empty($appInfosJson)) {
            return '[]';
        }

        $appInfos = json_decode($appInfosJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($appInfos)) {
            return '[]';
        }

        $newFormat = [];
        foreach ($appInfos as $info) {
            if (isset($info['title']) && isset($info['value'])) {
                $newFormat[] = [
                    'title' => $info['title'],
                    'content' => $info['value']
                ];
            }
        }

        return jsonEncode($newFormat);
    }
    /**
     * 发送 POST 请求 - 修正版
     */
    public static function post($url, $token, $data = [], $headers = [], $timeout = 30)
    {
        $ch = curl_init();

        $defaultHeaders = [
            'Authorization: ' . $token,
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        // 根据错误信息，可能需要调整数据结构
        // 尝试不同的数据格式
        $postData = self::formatPostData($data);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'code' => 500,
                'msg' => $error,
                'body' => null
            ];
        }

        $responseData = json_decode($response, true);

        return [
            'success' => $httpCode === 200,
            'code' => $httpCode,
            'data' => $responseData,
            'body' => $response
        ];
    }
    /**
     * 格式化 POST 数据，尝试不同的结构
     */
    private static function formatPostData($data)
    {
        // 如果数据为空，返回空对象而不是空数组
        if (empty($data)) {
            return '{}';
        }

        // 尝试不同的数据结构
        $formats = [];

        // 格式1：直接使用传入的数据
        $formats[] = $data;

        // 格式2：包装在 query 字段中
        $formats[] = ['query' => $data];

        // 格式3：包装在 condition 字段中
        $formats[] = ['condition' => $data];

        // 格式4：包装在 query_build 字段中
        $formats[] = ['query_build' => $data];

        // 格式5：使用 QueryCondition 结构
        $formats[] = [
            'query_build' => [
                'QueryCondition' => $data
            ]
        ];

        // 返回第一个格式的 JSON
        return json_encode($formats[0], JSON_UNESCAPED_UNICODE);
    }
}