<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;

// 路由判斷
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$host = $_SERVER['HTTP_HOST'] ?? '';

// 从指定的配置文件直接读取域名配置
// 注意：由于是在全局初始化之前，需直接读取配置文件
$configFile = __DIR__ . '/../config/admin.php';
$appConfig = include $configFile;
$adminDomain = $appConfig['domain'] ?? '';

// 后台判断
if ($host === $adminDomain && $requestUri === '/') {
    include __DIR__ . '/admin/index.html';
    exit;
} else if(empty($adminDomain) && $requestUri === "/A7K9X2LQ"){
    include __DIR__ . '/admin/index.html';
    exit;
}
// 判斷是否為根路徑
if ($requestUri === '/') {
    include __DIR__ . '/client/index.html';
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);
