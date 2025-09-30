<?php

namespace app\admin\controller;

use app\admin\model\AdminModel;
use app\BaseController;
use Snowflake;
use think\exception\HttpResponseException;

class Base extends BaseController
{
    /**
     * 当前登陆管理员信息
     * @var
     */
    protected $adminInfo;
    // 关联组id
    protected $fb_id;

    protected $menuCfg = [
        ['id' => 1],// 管理员管理
        ['id' => 101],// 角色管理
        ['id' => 102],// 用户管理
        ['id' => 2],// 产品库
        ['id' => 201],// 产品库
        ['id' => 3],// 计划库
        ['id' => 301],// 计划管理
        ['id' => 4],// 优惠券
        ['id' => 401],// 优惠券库
        ['id' => 402],// 用户优惠券
        ['id' => 5],// 授权码
        ['id' => 501],// 授权码管理
        ['id' => 6],// 财务管理
        ['id' => 601],// 充值记录
        ['id' => 602],// 提现记录
        ['id' => 603],// 资金明细
        ['id' => 7],// 用户计划管理
        ['id' => 701],// 用户计划列表
        ['id' => 8],// 系统设置
        ['id' => 9],// 数据统计
        ['id' => 901],// 注册统计
        ['id' => 902],// 充值提现统计
        ['id' => 10],// 用户管理
        ['id' => 1001],// 用户管理
        ['id' => 11],// 用户反馈
        ['id' => 1101],// 用户通知
    ];

    protected function initialize()
    {
        // 用户登录信息
        $this->adminInfo = AdminModel::getAdminInfo();
        if (empty($this->adminInfo)) {
            // 抛出带有JSON响应的异常
            throw new HttpResponseException(apiError('login_out', -1));
        }
        // 权限判断(不包括超级管理员)
        if ($this->adminInfo['auth_id'] != 1 && !$this->_auth()) {
            // 抛出带有JSON响应的异常
            throw new HttpResponseException(apiError('no_auth', -2));
        }
        $this->fb_id = (new Snowflake(0, 0))->nextId();
    }

    //权限判断 todo
    protected function _auth()
    {
//        //获取控制器
//        $c = $this->request->controller();
//        //获取方法
//        $a = $this->request->action();
//        $auth = $this->adminInfo['auth'];
//        $c = strtolower($c);
//        $a = strtolower($a);
//        //之后记得删掉false 添加权限方法还未实现
//        if (!in_array($c, ['index', 'upload'])) {
//            if (empty($auth[$c])) {
//                return false;
//            } else {
//                if (!in_array($a, $auth[$c])) {
//                    return false;
//                }
//            }
//        }
        return true;
    }

    // 是否超级管理员
    public function isSuperAdmin()
    {
        return $this->adminInfo['auth_id'] == 1;
    }

}
