<?php
/**
 * 用户邀请新用户注册送佣金
 * @author Rock
 * @date 2019/03/22
 */


namespace app\common\model;

use think\Db;
use think\Model;

class UserInvite extends Model{

    // config 名称
    public $conf_name = 'user_invite_rule';
    // config 
    public $conf_inc_type = 'user_invite_rule';
    // 网站设置
    public $config = [];
    // 邀新送佣金设置
    public $invite_conf = [];
    // 邀新送佣金开关 0 关闭  1 开启
    public $invite_on_off = 0;
    // 奖励规则
    public $rule = [];

    function __construct(){
        // 实例化时，初始化类
        $this->custo_init();
    }

    // 自定义初始化
    public function custo_init(){
        $config = Db::query("select * from `tp_config` where `name` = '".$this->conf_inc_type."' and `inc_type` = '".$this->conf_inc_type."'");
        if($config){
            $config = $config[0];
            $this->invite_conf = $config['value'] = json_decode($config['value'],true);
            $this->invite_on_off = $config['value']['invite_on_off'];
            $this->rule = $config['value']['rule'];
            $this->config = $config;
        }else{
            return false;
        }
    }


    /**
     * 当前用户主动调用程序
     */
    public function user_invite($user_id = 0){
        $user_id = intval($user_id);
        $info = Db::name('users')->field('first_leader')->where('user_id',$user_id)->find();

        if($info && $info['first_leader']){
           
            $this->invite($info['first_leader'],$user_id);
        }
    }



    /**
     * @param $user_id  邀请人用户ID
     * @param $adduser_id   被邀请的新用户ID
     */
    public function invite($user_id = 0,$adduser_id = 0){
        // write_log('邀请人id'. $user_id );
        // write_log('被邀请人id'. $adduser_id );
        $user_id = intval($user_id);
        $adduser_id = intval($adduser_id);

        if(!$user_id || !$adduser_id || !$this->invite_on_off || !$this->rule){
            return false;
        }
      

        $addlog = Db::name('commission_log')->where('add_user_id',$adduser_id)->count();
        if($addlog){
            return false;
        }

        $rule = $this->rule;
        $num = 1;
        $money = 0.1;
        $time = time();
        $desc = '邀请第'.$num.'个新会员奖励'.$money;
        $log = Db::name('commission_log')->where(['user_id'=>$user_id,'identification' => 2])->field('`num`')->order('id desc')->find();
        if($log){
            $num   = $log['num'] + 1;
            if(!empty($rule[$num]) && $rule[$num] > 0){
                $money = $rule[$num] + 0.1;
            }
            $desc = '邀请第'.$num.'个新会员奖励'.$money;
        }
        // write_log('奖励金额'. $money );
        if($money){
            $insql = "insert into `tp_commission_log` (`user_id`,`add_user_id`,`identification`,`num`,`money`,`addtime`,`desc`) values ";
            $insql .= " ('$user_id','$adduser_id','2','$num','$money','$time','$desc')";
            $res = Db::execute($insql);
            // write_log('记录用户余额变动bool'. $res );
            if($res){
                // write_log('记录用户余额变动'. $user_id );
                Db::execute("update `tp_users` set `user_money` = `user_money` + '$money', `distribut_money` = `distribut_money` + '$money' where `user_id` = '$user_id'");
                //记录用户余额变动
                $user_money = Db::name('users')->where(['user_id'=>$user_id])->value('user_money');
                setBalanceLog($user_id,2,$money,$user_money,'邀请奖励：'.$money);
                return true;
            }
        }
        return false;
    }





}