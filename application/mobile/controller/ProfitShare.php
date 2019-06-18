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

class ProfitShare extends MobileBase
{
    public function __construct()
    {

    }
    public function profitTaking(){
        $goodsProfit=new GoodsProfit();
        //获取系统配置看分红模式是0固定还是1利润
        $model=$goodsProfit->get_config('dividend_model');
        if($model==1){
            //获取当天利润
            $today_profit=$goodsProfit->get_goods_profit();
            //获取合伙人个数   默认合伙人等级是5
            $partners=$goodsProfit->get_partners_num(5);
            $managers=$goodsProfit->get_partners_num(3);
            $inspector=$goodsProfit->get_partners_num(4);
            //获取分红比例
            $partners_ratio=$this->get_agent_ratio(5);
            $managers_ratio=$this->get_agent_ratio(3);
            $inspector_ratio=$this->get_agent_ratio(4);

            //获取配置表值
            $today_ratio=$goodsProfit->get_config('today_ratio');

            //本日分红利润每人  $configs['today_ratio']['value']
            if($today_profit<=0 || $today_ratio==0){
                $partnersPartProfit=0.00;
                $managersPartProfit=0.00;
                $inspectorPartProfit=0.00;
            }else{
//            echo $today_profit['total']."````````````".$today_ratio;die;
                $partnersPartProfit=floor($today_profit*$today_ratio/100/$partners*$partners_ratio)/100;
                $managersPartProfit=floor($today_profit*$today_ratio/100/$managers*$managers_ratio)/100;
                $inspectorPartProfit=floor($today_profit*$today_ratio/100/$inspector*$inspector_ratio)/100;
            }

        }else{
            //获取不同等级固定分红
            $partnersPartProfit=$this->get_dividend_model(5);
            $managersPartProfit=$this->get_dividend_model(3);
            $inspectorPartProfit=$this->get_dividend_model(4);
            $today_profit=0;
            $partners=0;
            $managers=0;
            $inspector=0;
            $partners_ratio=0;
            $managers_ratio=0;
            $inspector_ratio=0;
        }

//        if (!isset($today_profit['total'])){
//            $today_profit['total']=0;
//        }
//        $today_profit['total']=$today_profit['total']*$today_profit['goods_num'];
//        var_dump($today_profit);

        //获取所有合伙人uid用于记录
        $partnersIds=$goodsProfit->get_all_partners(5);
        $managersIds=$goodsProfit->get_all_partners(3);
        $inspectorIds=$goodsProfit->get_all_partners(4);
//        var_dump($partnersIds);
//        echo "<hr />";
//        var_dump($managersIds);
//        echo "<hr />";
//        var_dump($inspectorIds);
//        echo "<hr />";

//        if($partners==0){
//            $data['msg']='暂时没有合伙人';
//            //写入记录表
//        }
        //获取合伙人分红百分比


        //写入记录表
        $data=array();
        // 启动事务
        Db::startTrans();
        try {
            foreach($partnersIds as $key=>$value){
                $data['uid']=$value;
                $data['bonus_money']=$partnersPartProfit;
                $data['today_population']=$partners;
                $data['today_profit']=$today_profit;
                $data['today_ratio']=$partners_ratio;
                $data['add_time']=time();
                Db::name('profit_dividend_log')->insert($data);
//                $data[]=['uid'=>$value,'bonus_money'=>"$partProfit",'today_population'=>$partners,'today_profit'=>"'".$today_profit['total']."'",'today_ratio'=>"$today_ratio",'add_time'=>time()];
//                var_dump($data);die;
//                echo "<hr />";
                Db::name('users')->where(['user_id'=>$value])->setInc('user_money',$partnersPartProfit);
                Db::name('users')->where(['user_id'=>$value])->setInc('distribut_money',$partnersPartProfit);
                //全球分红存分销记录表
                $this->set_log($value,$partnersPartProfit,'该合伙人获得当日利润分红'.$partnersPartProfit);
                //用户余额变动记录
                setAccountLog($value,12,$partnersPartProfit,0,"合伙人全球分红");
                //记录用户余额变动
                $user_money=Db::name('users')->where(['user_id'=>$value])->value('user_money');
                setBalanceLog($value,12,$partnersPartProfit,$user_money,'全球分红：'.$partnersPartProfit);
            }

            foreach($managersIds as $ke=>$val){
                $data['uid']=$val;
                $data['bonus_money']=$managersPartProfit;
                $data['today_population']=$managers;
                $data['today_profit']=$today_profit;
                $data['today_ratio']=$managers_ratio;
                $data['add_time']=time();
                Db::name('profit_dividend_log')->insert($data);
//                $data[]=['uid'=>$value,'bonus_money'=>"$partProfit",'today_population'=>$partners,'today_profit'=>"'".$today_profit['total']."'",'today_ratio'=>"$today_ratio",'add_time'=>time()];
//                var_dump($data);die;
//                echo "<hr />";
                Db::name('users')->where(['user_id'=>$val])->setInc('user_money',$managersPartProfit);
                Db::name('users')->where(['user_id'=>$val])->setInc('distribut_money',$managersPartProfit);
                //用户余额变动记录
                setAccountLog($value,12,$managersPartProfit,0,"经理全球分红");
                //全球分红存分销记录表
                $this->set_log($val,$managersPartProfit,'该经理获得当日利润分红'.$managersPartProfit);
                //记录用户余额变动
                $user_money=Db::name('users')->where(['user_id'=>$value])->value('user_money');
                setBalanceLog($value,12,$managersPartProfit,$user_money,'全球分红：'.$managersPartProfit);
            }

            foreach($inspectorIds as $k=>$v){
                $data['uid']=$v;
                $data['bonus_money']=$inspectorPartProfit;
                $data['today_population']=$inspector;
                $data['today_profit']=$today_profit;
                $data['today_ratio']=$inspector_ratio;
                $data['add_time']=time();
                Db::name('profit_dividend_log')->insert($data);
//                $data[]=['uid'=>$value,'bonus_money'=>"$partProfit",'today_population'=>$partners,'today_profit'=>"'".$today_profit['total']."'",'today_ratio'=>"$today_ratio",'add_time'=>time()];
//                var_dump($data);die;
//                echo "<hr />";
                Db::name('users')->where(['user_id'=>$v])->setInc('user_money',$inspectorPartProfit);
                Db::name('users')->where(['user_id'=>$v])->setInc('distribut_money',$inspectorPartProfit);
                //用户余额变动记录
                setAccountLog($value,12,$inspectorPartProfit,0,"总监全球分红");
                //全球分红存分销记录表
                $this->set_log($v,$inspectorPartProfit,'该总监获得当日利润分红'.$inspectorPartProfit);
                //记录用户余额变动
                $user_money=Db::name('users')->where(['user_id'=>$value])->value('user_money');
                setBalanceLog($value,12,$inspectorPartProfit,$user_money,'全球分红：'.$inspectorPartProfit);
            }
            echo '执行成功,插入'.count($partnersIds)+count($managersIds)+count($inspectorIds).'条记录\n';
//            var_dump($data);die;
//            Db::name('profit_dividend_log')->insertAll($data);
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            echo "执行失败了\n";
            Db::rollback();
        }

    }
    private function get_agent_ratio($level){
        $ratio=M('agent_level')->where(['level'=>$level])->find();
        return $ratio['ratio'];
    }
    //后台记录
    private function set_log($user_id,$money,$desc){
        $data=['to_user_id'=>$user_id,'money'=>$money,'create_time'=>time(),'desc'=>$desc,'type'=>4];
        M('distrbut_commission_log')->insert($data);

    }
    //查询固定分红金额
    private function get_dividend_model($level){
        $ratio=M('agent_level')->where(['level'=>$level])->find();
        if($ratio['money']<0){
            $ratio['money']=0.00;
        }
        return $ratio['money'];
    }
}
