<?php

namespace app\home\controller;

use app\common\model\ConfigModel;
use app\common\model\FlowModel;
use app\common\validate\CommonValidate;
use app\home\model\UserModel;
use app\home\validate\UserValidate;
use think\facade\Db;

class User extends Base
{
    // 获取信息
    public function info(UserModel $model)
    {
        try {
            $uif = $model::where(['id' => $this->userInfo['id']])
                ->field('money')
                ->find()->toArray();
            return apiSuccess('success', $uif);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    //退出登录
    public function loginOut(UserModel $model)
    {
        $model::loginOut($this->userInfo['username']);
        return apiSuccess();
    }

    //设置语言
    public function setLang($lang, UserModel $model)
    {
        $cfg = config('lang.allow_lang_list');
        if (!in_array($lang, $cfg)) {
            return apiError('params_error');
        }
        // 或者使用 where 条件
        $model::where(['username' => $this->userInfo['username']])->update(['lang' => $lang]);
        $this->userInfo['lang'] = $lang;
        $model::setUserInfo($this->userInfo['username'], $this->userInfo);
        return apiSuccess();
    }

    //添加银行卡
    public function bankCardAdd()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new UserValidate();
        $params['uid'] = $this->userInfo['id'];
        if (!$validate->check($params, $validate->bank_car_rule)) {
            return apiError($validate->getError());
        }
        $name = Db::table('user')->where('id', $this->userInfo['id'])->value('name');
        if (empty($name)) {
            Db::table('user')->where('id', $this->userInfo['id'])->update(['name' => $params['name']]);
        }
        $params['password'] = getMd5Password($params['password']);
        $params['create_time'] = time();
        $res = Db::name('bank_card')->insert($params);
        if ($res) {
            return apiSuccess();
        } else {
            return apiError();
        }
    }

    // 提现列表
    public function withdrawList()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        try {
            $where = [['username', '=', $this->userInfo['username']]];
            if (!empty($params['sTime'])) {
                $where[] = ['create_time', '>=', $params['sTime']];
            }
            if (!empty($params['eTime'])) {
                $where[] = ['create_time', '<=', $params['eTime']];
            }
            $paginator = Db::table('withdraw')
                ->where($where)
                ->order('id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 100, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $total_money = Db::name('withdraw')
                ->where('username', $this->userInfo['username'])
                ->where('state', 1)
                ->value('SUM(money) as total_money');
            $res = [
                'list' => $paginator->items(),       // 当前页数据
                'total' => $paginator->total(),       // 总记录数
                'page' => $paginator->currentPage(), // 当前页码
                'page_size' => $paginator->listRows(),    // 每页记录数
                'total_page' => $paginator->lastPage(),    // 总页数
                'total_money' => $total_money ?? 0,    // 总额
            ];
            return apiSuccess('success', $res);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    //提现
    public function withdraw(ConfigModel $model)
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new UserValidate();
        if (!$validate->check($params, $validate->withdraw_rule)) {
            return apiError($validate->getError());
        }
        try {
            // 开始事务
            Db::startTrans();
            $cardInfo = Db::name('bank_card')->where('id', $params['card_id'])->find();
            if ($cardInfo['password'] !== getMd5Password($params['password'])) {
                throw new \Exception(lang('password_error'));
            }
            // 提现最低金额
            $withdrawMin = $model->getConfigValue($model::id_2);
            if (!empty($withdrawMin) && $withdrawMin > $params['money']) {
                throw new \Exception(vsprintf(lang('withdraw_min'), [$withdrawMin]));
            }
            // 提现每日限制
            $dayNum = $model->getConfigValue($model::id_3);
            if (!empty($dayNum)) {
                $todayNum = Db::name('withdraw')
                    ->where('create_time', '>=', strtotime('today'))
                    ->where('uid', $this->userInfo['id'])
                    ->value('count(id)');
                if ($todayNum >= $dayNum) {
                    throw new \Exception(vsprintf(lang('withdraw_num'), [$dayNum]));
                }
            }
            // 判断余额
            $userInfo = Db::name('user')
                ->where('username', $this->userInfo['username'])
                ->lock(true)
                ->field('money,withdraw_limit,state')
                ->find();
            if ($userInfo['state'] == UserModel::STATE_4) {
                throw new \Exception(lang('withdraw_impose'));
            }
            if ($params['money'] > $userInfo['money']) {
                throw new \Exception(lang('money_error'));
            }
            if ($params['money'] > $userInfo['withdraw_limit']) {
                throw new \Exception(vsprintf(lang('withdraw_limit'), [$userInfo['withdraw_limit']]));
            }
            // 插入提现表
            unset($params['password'], $params['card_id']);
            $params['uid'] = $this->userInfo['id'];
            $params['admin_id'] = $this->userInfo['admin_id'];
            $params['username'] = $this->userInfo['username'];
            $params['name'] = $cardInfo['name'];
            $params['card'] = $cardInfo['cardno'];
            $params['bank'] = $cardInfo['bank'];
            $params['before_money'] = $userInfo['money'];
            $params['type'] = 2;
            $params['state'] = 0;
            $params['create_time'] = time();
            Db::name('withdraw')->insert($params);
            //扣除资金
            Db::table('user')->where('id', $this->userInfo['id'])
                ->dec('money', $params['money'])
                ->dec('withdraw_limit', $params['money'])
                ->inc('freeze_money', $params['money'])
                ->update();
            //插入流水
            Db::table('flow')->insert([
                'uid' => $this->userInfo['id'],
                'type' => FlowModel::type_4,
                'before' => $userInfo['money'],
                'after' => $userInfo['money'] - $params['money'],
                'cha' => -$params['money'],
                'create_time' => time(),
            ]);
            // 提交事务
            Db::commit();
            return apiSuccess();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return apiError($e->getMessage());
        }
    }

    // 流水列表
    public function flowList()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        try {
            $where = [['uid', '=', $this->userInfo['id']]];
            if (isset($params['type']) && $params['type'] == 1) {
                $where[] = ['type', 'in', '1,7']; //只显示充值，赠送
            } else {
                $where[] = ['type', 'in', '2,3'];
            }
            if (!empty($params['sTime'])) {
                $where[] = ['create_time', '>=', $params['sTime']];
            }
            if (!empty($params['eTime'])) {
                $where[] = ['create_time', '<=', $params['eTime']];
            }
            $paginator = Db::table('flow')
                ->where($where)
                ->order('id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 100, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $total_money = Db::name('flow')
                ->where($where)
                ->value('SUM(cha) as total_money');

            $res = [
                'list' => $paginator->items(),       // 当前页数据
                'total' => $paginator->total(),       // 总记录数
                'page' => $paginator->currentPage(), // 当前页码
                'page_size' => $paginator->listRows(),    // 每页记录数
                'total_page' => $paginator->lastPage(),    // 总页数
                'total_money' => $total_money ?? 0,
            ];
            return apiSuccess('success', $res);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function bankCardDel()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)) {
            return apiError($validate->getError());
        }
        try {
            Db::name('bank_card')->where(['id' => $params['id']])->delete();
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    //设置密码
    public function setPassWord(UserModel $model)
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new UserValidate();
        if (!$validate->check($params, $validate->set_password_rule)) {
            return apiError($validate->getError());
        }
        try {
            $uif = $model::where(['username' => $this->userInfo['username']])
                ->field('password')
                ->find()->toArray();
            if ($uif['password'] !== getMd5Password($params['orpassword'])) {
                throw new \Exception(lang('password_error'));
            }
            $model::where(['username' => $this->userInfo['username']])
                ->update(['password' => getMd5Password($params['password'])]);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 签到
    public function sign(UserModel $model)
    {
        try {
            $uif = $model::where(['id' => $this->userInfo['id']])
                ->field('sign_time')
                ->find()->toArray();
            if (isToday($uif['sign_time'])) {
                throw new \Exception(lang('已签到'));
            }
            $model::where(['id' => $this->userInfo['id']])
                ->update(['sign_time' => time()]);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }


}
