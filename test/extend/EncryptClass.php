<?php

//验证类
class EncryptClass
{
    protected $_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.';
    protected $_key = '-x6g6ZWm2G9g_vr0Bo.pOq3kRIxsZ6rm';

    /*
    * 加密，可逆
    * 可接受任何字符
    * 安全度非常高
    *
    */
    public function myEncrypt($txt, $key)
    {
        $nh1 = random_int(0, 64);
        $nh2 = random_int(0, 64);
        $nh3 = random_int(0, 64);
        $ch1 = $this->_chars{$nh1};
        $ch2 = $this->_chars{$nh2};
        $ch3 = $this->_chars{$nh3};
        $nhnum = $nh1 + $nh2 + $nh3;
        $knum = 0;
        $i = 0;
        while (isset($key{$i})) $knum += ord($key{$i++});
        $mdKey = substr(md5(md5(md5($key . $ch1) . $ch2 . $this->_key) . $ch3), $nhnum % 8, $knum % 8 + 16);
        $txt = base64_encode($txt);
        $txt = str_replace(array('+', '/', '='), array('-', '_', '.'), $txt);
        $tmp = '';
        $j = 0;
        $k = 0;
        $tlen = strlen($txt);
        $klen = strlen($mdKey);
        for ($i = 0; $i < $tlen; $i++) {
            $k = $k == $klen ? 0 : $k;
            $j = ($nhnum + strpos($this->_chars, $txt{$i}) + ord($mdKey{$k++})) % 64;
            $tmp .= $this->_chars{$j};
        }
        $tmplen = strlen($tmp);
        $tmp = substr_replace($tmp, $ch3, $nh2 % ++$tmplen, 0);
        $tmp = substr_replace($tmp, $ch2, $nh1 % ++$tmplen, 0);
        $tmp = substr_replace($tmp, $ch1, $knum % ++$tmplen, 0);

        return $tmp;
    }

    /*
     * 解密
     *
     */
    public function decrypt($txt, $key)
    {
        $knum = 0;
        $i = 0;
        $tlen = strlen($txt);
        while (isset($key{$i})) $knum += ord($key{$i++});
        $ch1 = $txt{$knum % $tlen};
        $nh1 = strpos($this->_chars, $ch1);
        $txt = substr_replace($txt, '', $knum % $tlen--, 1);
        $ch2 = $txt{$nh1 % $tlen};
        $nh2 = strpos($this->_chars, $ch2);
        $txt = substr_replace($txt, '', $nh1 % $tlen--, 1);
        $ch3 = $txt{$nh2 % $tlen};
        $nh3 = strpos($this->_chars, $ch3);
        $txt = substr_replace($txt, '', $nh2 % $tlen--, 1);
        $nhnum = $nh1 + $nh2 + $nh3;
        $mdKey = substr(md5(md5(md5($key . $ch1) . $ch2 . $this->_key) . $ch3), $nhnum % 8, $knum % 8 + 16);
        $tmp = '';
        $j = 0;
        $k = 0;
        $tlen = strlen($txt);
        $klen = strlen($mdKey);
        for ($i = 0; $i < $tlen; $i++) {
            $k = $k == $klen ? 0 : $k;
            $j = strpos($this->_chars, $txt{$i}) - $nhnum - ord($mdKey{$k++});
            while ($j < 0) {
                $j += 64;
            }
            $tmp .= $this->_chars{$j};
        }
        $tmp = str_replace(array('-', '_', '.'), array('+', '/', '='), $tmp);
        return trim(base64_decode($tmp));
    }

    /**
     * 加密生成全字符邀请码
     * @param $txt
     * @param $key
     * @return array|string|string[]
     */
    public function codeEncrypt($txt, $key)
    {
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $ikey = "H2XIIUSHVMLDHAQAQGWISFFSOLNVRRT7";
        $nh1 = rand(0, 61);
        $nh2 = rand(0, 61);
        $nh3 = rand(0, 61);
        $ch1 = $chars{$nh1};
        $ch2 = $chars{$nh2};
        $ch3 = $chars{$nh3};
        $nhnum = $nh1 + $nh2 + $nh3;
        $knum = 0;
        $i = 0;
        while (isset($key{$i})) $knum += ord($key{$i++});
        $mdKey = substr(md5(md5(md5($key . $ch1) . $ch2 . $ikey) . $ch3), $nhnum % 8, $knum % 8 + 16);
        $txt = base64_encode($txt);
        $txt = str_replace(array('+', '/', '='), array('-', '_', '.'), $txt);
        $tmp = '';
        $j = 0;
        $k = 0;
        $tlen = strlen($txt);
        $klen = strlen($mdKey);
        for ($i = 0; $i < $tlen; $i++) {
            $k = $k == $klen ? 0 : $k;
            $j = ($nhnum + strpos($chars, $txt{$i}) + ord($mdKey{$k++})) % 61;
            $tmp .= $chars{$j};
        }
        $tmplen = strlen($tmp);
        $tmp = substr_replace($tmp, $ch3, $nh2 % ++$tmplen, 0);
        $tmp = substr_replace($tmp, $ch2, $nh1 % ++$tmplen, 0);
        $tmp = substr_replace($tmp, $ch1, $knum % ++$tmplen, 0);

        return $tmp;
    }

    /**
     * 解密全字符邀请码
     * @param $txt
     * @param string $key
     * @return string
     */
    public function codeDecrypt($txt, $key)
    {
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $ikey = "H2XIIUSHVMLDHAQAQGWISFFSOLNVRRT7";
        $knum = 0;
        $i = 0;
        $tlen = strlen($txt);
        while (isset($key{$i})) $knum += ord($key{$i++});
        $ch1 = $txt{$knum % $tlen};
        $nh1 = strpos($chars, $ch1);
        $txt = substr_replace($txt, '', $knum % $tlen--, 1);
        $ch2 = $txt{$nh1 % $tlen};
        $nh2 = strpos($chars, $ch2);
        $txt = substr_replace($txt, '', $nh1 % $tlen--, 1);
        $ch3 = $txt{$nh2 % $tlen};
        $nh3 = strpos($chars, $ch3);
        $txt = substr_replace($txt, '', $nh2 % $tlen--, 1);
        $nhnum = $nh1 + $nh2 + $nh3;
        $mdKey = substr(md5(md5(md5($key . $ch1) . $ch2 . $ikey) . $ch3), $nhnum % 8, $knum % 8 + 16);
        $tmp = '';
        $j = 0;
        $k = 0;
        $tlen = strlen($txt);
        $klen = strlen($mdKey);
        for ($i = 0; $i < $tlen; $i++) {
            $k = $k == $klen ? 0 : $k;
            $j = strpos($chars, $txt{$i}) - $nhnum - ord($mdKey{$k++});
            while ($j < 0) $j += 61;
            $tmp .= $chars{$j};
        }
        $tmp = str_replace(array('-', '_', '.'), array('+', '/', '='), $tmp);
        return trim(base64_decode($tmp));
    }

    public function opensslEncrypt($data, $key)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public function opensslDecrypt($data, $key)
    {
        list($encryptedData, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encryptedData, 'aes-256-cbc', $key, 0, $iv);
    }
}