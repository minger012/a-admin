<?php

namespace app\home\controller;

use app\common\model\ConfigModel;
use app\common\model\FlowModel;
use app\common\model\PlanOrderModel;
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

    // 添加银行卡
    public function bankCardAdd()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new UserValidate();
        if (!$validate->check($params, $validate->bank_car_rule)) {
            return apiError($validate->getError());
        }
        try {
            $id = Db::name('bank_card')->where('uid', $this->userInfo['id'])->value('id');
            if (!empty($id)) {
                Db::name('bank_card')->where('uid', $this->userInfo['id'])->update($params);
            } else {
                $params['uid'] = $this->userInfo['id'];
                $params['create_time'] = time();
                Db::name('bank_card')->insert($params);
            }
            return apiSuccess('success');
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 获取银行卡
    public function bankCardList()
    {
        try {
            $bankCard = Db::name('bank_card')->where('uid', $this->userInfo['id'])->find();
            return apiSuccess('success', $bankCard);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
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
    public function withdraw()
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
            $cardInfo = Db::name('bank_card')->where('id', $this->userInfo['id'])->find();
            if (empty($cardInfo)) {
                throw new \Exception(lang('bank_error'));
            }
            // 判断余额
            $userInfo = Db::name('user')
                ->where('id', $this->userInfo['id'])
                ->lock(true)
                ->field('money,pay_password')
                ->find();
            if ($userInfo['pay_password'] !== getMd5Password($params['password'])) {
                throw new \Exception(lang('password_error'));
            }
            if ($params['money'] > $userInfo['money']) {
                throw new \Exception(lang('money_error'));
            }
            // 插入提现表
            $params['uid'] = $this->userInfo['id'];
            $params['admin_id'] = $this->userInfo['admin_id'];
            $params['fb_id'] = $this->fb_id;
            $params['methods'] = $cardInfo['methods'];
            $params['currency'] = $cardInfo['currency'];
            $params['address'] = $cardInfo['address'];
            $params['state'] = 0;
            $params['update_time'] = time();
            $params['create_time'] = time();
            unset($params['password']);
            Db::name('withdraw')->insert($params);
            // 扣除资金
            Db::table('user')->where('id', $this->userInfo['id'])
                ->dec('money', $params['money'])
                ->inc('freeze_money', $params['money'])
                ->update();
            // 插入流水
            Db::table('flow')->insert([
                'uid' => $this->userInfo['id'],
                'type' => FlowModel::type_2,
                'admin_id' => $this->userInfo['admin_id'],
                'before' => $userInfo['money'],
                'after' => $userInfo['money'] - $params['money'],
                'cha' => -$params['money'],
                'fb_id' => $this->fb_id,
                'update_time' => time(),
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

    // 设置密码
    public function setPassWord(UserModel $model)
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new UserValidate();
        if (!$validate->check($params, $validate->set_password_rule)) {
            return apiError($validate->getError());
        }
        try {
            $uif = $model::where(['id' => $this->userInfo['id']])
                ->field('password')
                ->find()->toArray();
            if (!empty($uif['password'])) {
                if ($uif['password'] !== getMd5Password($params['orpassword'])) {
                    throw new \Exception(lang('password_error'));
                }
            }
            $model::where(['id' => $this->userInfo['id']])
                ->update(['password' => getMd5Password($params['password'])]);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 设置交易密码
    public function setPayPassWord(UserModel $model)
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new UserValidate();
        if (!$validate->check($params, $validate->set_password_rule)) {
            return apiError($validate->getError());
        }
        try {
            $uif = $model::where(['id' => $this->userInfo['id']])
                ->field('pay_password')
                ->find()->toArray();
            if (!empty($uif['pay_password'])) {
                if ($uif['pay_password'] !== getMd5Password($params['orpassword'])) {
                    throw new \Exception(lang('password_error'));
                }
            }
            $model::where(['id' => $this->userInfo['id']])
                ->update(['pay_password' => getMd5Password($params['password'])]);
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
                ->field('sign_time,sign')
                ->find()->toArray();
            if (isToday($uif['sign_time'])) {
                throw new \Exception(lang('已签到'));
            }
            $model::where(['id' => $this->userInfo['id']])
                ->update(['sign_time' => time(), 'sign' => $uif['sign'] + 1]);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 优惠券列表
    public function couponList()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->append('state', 'require|in:0,1,2')->check($params, $validate->page_rule)) {
            return apiError($validate->getError());
        }
        try {
            $where = [['a.uid', '=', $this->userInfo['id']]];
            if ($params['state'] == 1) {
                $where[] = [['a.use_time', '=', 0]];
            } elseif ($params['state'] == 2) {
                $where[] = [['a.use_time', '!=', 0]];
            }
            $paginator = Db::table('user_coupon')
                ->alias('a')
                ->join('coupon b', 'a.cid=b.id')
                ->field('a.*,b.name,b.intro,b.type,b.discount,b.discount_amount')
                ->where($where)
                ->order('a.id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 100, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            foreach ($list as $k => $v) {
                if ($v['use_time']) {
                    $list[$k]['state'] = 2;// 已使用
                } else {
                    if ($v['end_time'] > time()) {
                        $list[$k]['state'] = 1;// 未使用
                    } else {
                        $list[$k]['state'] = 3;// 已过期
                    }
                }
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
            return apiError($e->getMessage());
        }
    }

    // 邮件列表
    public function mailList()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->append('state', 'require|in:0,1,2')
            ->append('type', 'require|in:0,1,2,3')
            ->check($params, $validate->page_rule)
        ) {
            return apiError($validate->getError());
        }
        try {
            $where = [['uid', '=', $this->userInfo['id']]];
            if ($params['type'] != 0) {
                $where[] = ['type', '=', $params['type']];
            }
            if ($params['state'] == 1) {
                $where[] = [['read_time', '=', 0]];
            } elseif ($params['state'] == 2) {
                $where[] = [['read_time', '!=', 0]];
            }
            $paginator = Db::table('user_mail')
                ->where($where)
                ->order('id', 'desc')// 按ID倒序（可选）
                ->paginate([
                    'list_rows' => $params['pageSize'] ?? 100, // 每页记录数
                    'page' => $params['page'] ?? 1,     // 当前页码
                ]);
            $list = $paginator->items();
            $res = [
                'list' => $list,       // 当前页数据
                'total' => $paginator->total(),       // 总记录数
                'page' => $paginator->currentPage(), // 当前页码
                'page_size' => $paginator->listRows(),    // 每页记录数
                'total_page' => $paginator->lastPage(),    // 总页数
            ];
            return apiSuccess('success', $res);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 邮件阅读
    public function mailRead()
    {
        $input = request()->getContent();
        $params = json_decode($input, true);
        $validate = new CommonValidate();
        if (!$validate->check($params, $validate->id_rule)) {
            return apiError($validate->getError());
        }
        try {
            Db::table('user_mail')
                ->where('id', $params['id'])
                ->update(['read_time' => time()]);
            return apiSuccess();
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    // 邮件未读数量
    public function mailNoRead()
    {
        try {
            $count = Db::table('user_mail')
                ->where('id', $this->userInfo['id'])
                ->where('read_time', 0)
                ->count();
            return apiSuccess('success', $count);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }


    // 钱包
    public function wallet(UserModel $model)
    {
        try {
            $uif = $model::where(['id' => $this->userInfo['id']])
                ->field('money')
                ->find()->toArray();

            $wait_putIn = Db::table('plan_order')
                ->where([
                    ['uid', '=', $this->userInfo['id']],
                    ['state', '=', PlanOrderModel::state_2],
                ])
                ->value('count(wait_putIn)');
            $wait_money = Db::table('plan_order')
                ->where([
                    ['uid', '=', $this->userInfo['id']],
                    ['state', '=', PlanOrderModel::state_4],
                ])
                ->value('count(money)');

            return apiSuccess('success', [
                'money' => $uif['money'],
                'wait_putIn' => $wait_putIn,
                'wait_money' => $wait_money,

            ]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }

    public function uploadImage(UserModel $model)
    {
        // 获取表单上传文件
        $file = request()->file('image');
        if (empty($file)) {
            return apiError('params_error');
        }
        try {
            validate(['image' => 'fileSize:10240|fileExt:jpg|image:200,200,jpg'])->check([$file]);
            $savename = \think\facade\Filesystem::putFile('/upload/image', $file);
            $url = '/' . $savename;
            $model::where(['id' => $this->userInfo['id']])
                ->update(['image' => $url]);
            return apiSuccess('success', ['url' => fileDomain($url)]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
