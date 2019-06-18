<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */

namespace app\mobile\controller;

use app\common\model\GoodsProfit;
use think\Db;
use think\Request;

class SendMessage
{
    public function __construct()
    {

    }
    /**
     * 获取验证码
     */
    public function getPhoneVerify(){
        $param = input('post.');
        $sms_type = intval($param['sms_type']);
        if(!$sms_type || !$param['phone']){
            return json(array('code' => 0, 'msg' => '缺少参数'));
        }
        $data = ['sms_type'=>$sms_type, 'phone'=>$param['phone']];
        $res = getPhoneCode($data);
        return json($res);
    }
}
