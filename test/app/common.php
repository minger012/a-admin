<?php
use think\facade\Request;

/**
 * API数据格式化输出
 * @param int $code 0 错误 1正常
 * @param string $msg
 * @param array $data
 */
function apiSuccess($msg = 'success', $data = [])
{
    $res = [
        'code' => 1,//0 错误 1正常
        'msg' => lang($msg),
        'data' => $data,
    ];
    return json($res, 200);
}

/**
 * API数据格式化输出
 * @param int $code 0 错误 1正常
 * @param string $msg
 * @param array $data
 */
function apiError($msg = 'error', $code = 0)
{
    $res = [
        'code' => $code,
        'msg' => lang($msg),
        'data' => [],
    ];
    return json($res, 200);
}

//获取加密密码
function getMd5Password($password, $salt = '')
{
    return md5(md5($password) . $salt);
}

/**
 * 检测密码
 * @param string $password 未加密密码
 * @param string $md5Password 加密过后的密码
 *
 * @return string
 */
function checkPassword($password, $md5Password)
{
    if (getMd5Password($password) == $md5Password) {
        return true;
    } else {
        return false;
    }
}

//随机字符
function getRandomCode($num)
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 1; $i <= $num; $i++) {
        $code .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $code;
}

//是否有重复
function hasDuplicates($array)
{
    return count($array) !== count(array_unique($array));
}

function isSubset($array1, $array2)
{
    // array_diff() 返回在 array1 但不在 array2 中的元素
    return empty(array_diff($array1, $array2));
}

/**
 * 生成订单号
 * 格式：年月日时分秒+4位随机数
 * 示例：202306101430259876
 */
function generateOrderNo()
{
    return date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function getDomain()
{
    return Request::scheme() . '://' .
        Request::host() .
        (in_array(Request::port(), [80, 443]) ? '' : ':' . Request::port());
}

// 过滤字符串
function filtrationStr($str)
{
    $str = htmlspecialchars(trim($str)); // 过滤HTML和空格
    return addslashes($str); // 防止SQL注入
}

// 时间戳 + 随机数
function generateShortId() {
    $timePart = time() % 100000; // 取时间戳的后5位
    $randomPart = mt_rand(1000, 9999); // 4位随机数
    return (int)($timePart . $randomPart);
}

function getFbId()
{
    return (new SnowflakeClass(0, 0))->nextId();
}

/**
 * 判断时间戳是否是今天
 * @param int $timestamp 要判断的时间戳
 * @return bool
 */
function isToday($timestamp)
{
    return date('Y-m-d', $timestamp) === date('Y-m-d');
}

/**
 * 获取今天0点的时间戳
 * @return int
 */
function getTodayStart()
{
    return strtotime('today');
}