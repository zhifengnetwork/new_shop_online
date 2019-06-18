<?php
/**
 * 用户签到送佣金
 * @author Rock
 * @date 2019/03/21
 */

namespace app\common\model;

use think\Db;
use think\Model;

class UserSign extends Model{


    // config 名称
    public $conf_name = 'user_sign_rule';
    // config 
    public $conf_inc_type = 'user_sign_rule';
    // 网站设置
    public $config = [];
    // 签到送佣金设置
    public $sign_conf = [];
    // 签到送佣金开关 0 关闭  1 开启
    public $sign_on_off = 0;
    // 签到送佣金额度
    public $sign_integral = 0;
    // 连续签到送佣金开关 0 关闭 1 开启
    public $continued_on_off = 0;
    // 连续签到送佣金规则
    public $rule = [];
    // 会员ID
    public $userid = '';

    function __construct(){
        // 实例化时，初始化类
        $this->custo_init();
    }

    // 自定义初始化
    public function custo_init(){
        $config = Db::query("select * from `tp_config` where `name` = '".$this->conf_inc_type."' and `inc_type` = '".$this->conf_inc_type."'");
        if($config){
            $config = $config[0];
            $this->sign_conf = $config['value'] = json_decode($config['value'],true);
            $this->sign_on_off = $config['value']['sign_on_off'];
            $this->sign_integral = $config['value']['sign_integral'];
            $this->continued_on_off = $config['value']['continued_on_off'];
            $this->rule = $config['value']['rule'];
            $this->config = $config;
        }else{
            return false;
        }
    }

    // 签到是否开启
    function check_sign(){
        return $this->sign_on_off ? true : false;
    }
    
    // 连续签到是否开启
    function check_continued(){
        return $this->continued_on_off ? true : false;
    }

    // 检索用户佣金记录
    function check_commission_log($user_id = 0, $date = '', $re = true){
        if(!$date){
            $date = date('Ymd');
        }
        $log = Db::name('commission_log')->where(['user_id'=>$user_id,'identification'=>1,'date'=>$date])->order('id desc')->find();
        if($re){
            return $log;
        }else{
            return $log ? true : false;
        }
    }

    
    // 签到记录信息
    function getInfo($id = 0){
        if(intval($id)){
            return Db::name('commission_log')->where(['id'=>$id, 'identification'=>1])->find();
        }
        return false;
    }

    // 最后一条签到信息
    function getLastInfo($user_id = 0, $re = true){
        if(!intval($user_id)){
            return false;
        }
        $info = Db::name('commission_log')->where(['identification'=>1])->order('date desc')->find();
        if($re){
            return $info;
        }else{
            return $info ? true : false;
        }
    }


    // 拼装 运行 签到数据
    function _sql($user_id){
        if(!intval($user_id)) return false;
        $yesterday = date('Ymd',strtotime('-1 day'));
        $date = date('Ymd');
        $time = time();

        $log = Db::name('commission_log')->where(['user_id' => $user_id, 'identification'=>1, 'date' => $date])->count();
        if($log) return false;

        $money = $this->sign_integral;
        $num = 1;
        $desc = '签到奖励：'.$money;
        
        $lastInfo = Db::name('commission_log')->where(['identification'=>1, 'user_id'=>$user_id])->order('date desc,id desc')->find();
        if($lastInfo){
            if($lastInfo['date'] == $yesterday){
                $num = $num + intval($lastInfo['num']);
            }
        }
        if($this->continued_on_off){
            $rule = $this->rule;
            $extra_money = $rule[$num];
            if($extra_money){
                $desc .= '，连续签到'.$num.'天奖励：'.$extra_money;
                $money += $extra_money;
            } 
        }
        $insql = "insert into `tp_commission_log` (`user_id`,`identification`,`num`,`money`,`addtime`,`desc`,`date`) values ";
        $insql .= "('$user_id','1','$num','$money','$time','$desc','$date')";
        
        $inr = Db::execute($insql);
        if($inr){
            Db::execute("update `tp_users` set `user_money` = `user_money` + '$money', `distribut_money` = `distribut_money` + '$money' where `user_id` = '$user_id'");
            //记录用户余额变动
            $user_money=Db::name('users')->where(['user_id'=>$user_id])->value('user_money');
            setBalanceLog($user_id,1,$money,$user_money,'签到奖励：'.$money);
            return true;
        }else{
            return false;
        }
    }


    // 签到
    function sign($pram = ''){
        if(is_array($pram)){
            $user_id = intval($pram['user_id']);
        }else{
            $user_id = intval($pram);
        }
        if(!intval($user_id)){
            return false;
        }
        if(!$this->sign_on_off){
            return false;
        }
        return $this->_sql($user_id);
    }













}
