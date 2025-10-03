<?php

namespace app\home\controller;

use app\BaseController;
use app\common\service\OnlineUserService;
use app\home\model\UserModel;
use SnowflakeClass;
use think\exception\HttpResponseException;

class Base extends BaseController
{
    /**
     * 当前登陆信息
     */
    protected $userInfo;
    // 不限制的api
    protected $noLimitApi = [
        'Lottery' => ['getLotteryNow'],
    ];
    // 关联组id
    protected $fb_id;
    protected function initialize()
    {
        // 用户登录信息
        $this->userInfo = UserModel::getUserInfo();
        if (empty($this->userInfo)) {
            // 抛出带有JSON响应的异常
            throw new HttpResponseException(apiError('login_out', -1));
        }
//        if (!$this->_accessLimit()) {
//            // 抛出带有JSON响应的异常
//            throw new HttpResponseException(apiError('The network is busy'));
//        }
        // 设置用户上线
        OnlineUserService::online($this->userInfo['id'], $this->userInfo['admin_id']);
        $this->fb_id = (new SnowflakeClass(0, 0))->nextId();
    }

    // 访问频率限制
    protected function _accessLimit()
    {
        //获取控制器
        $c = $this->request->controller();
        //获取方法
        $a = $this->request->action();
        if ($this->noLimitApi[$c]) {
            if (in_array($a, $this->noLimitApi[$c])) {
                // 抛出带有JSON响应的异常
                throw new HttpResponseException(apiError('login_out', -1));
            }
        }
        return true;
    }
}
