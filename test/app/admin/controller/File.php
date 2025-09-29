<?php

namespace app\admin\controller;


class File extends Base
{
    public function uploadImage()
    {
        // 获取表单上传文件
        $file = request()->file('image');
        if (empty($file)) {
            return apiError('params_error');
        }
        try {
            validate(['image' => 'fileSize:10240|fileExt:jpg|image:200,200,jpg'])->check([$file]);
            $savename = \think\facade\Filesystem::putFile('/upload/image', $file);
            return apiSuccess('success', ['url' => '/' . $savename]);
        } catch (\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}
