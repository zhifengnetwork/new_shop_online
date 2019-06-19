<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace app\common\model;

use think\Db;
use think\Model;

class GoodsProfit extends Model
{
    //查询并计算当日的利润总和
    public function get_goods_profit(){
        //今日起始时间戳
        $todaytime=strtotime(date('Y-m-d 00:00:00',time()));
        //当日总利润
        $total=0;
        //算前一天的利润
        $yestoday_start=strtotime(date('Y-m-d 00:00:00',time()-86400));
        $yestoday_end=strtotime(date('Y-m-d 23:59:59',time()-86400));
        //组合查询确认收货后返佣和付款后返佣
//        $sql1="select count(og.goods_id) as a,og.* from zf_order_goods og,zf_order o where og.order_id=o.order_id and o.pay_time between ".$yestoday_start." and ".$yestoday_end." and og.is_receiving_commission=0";
//        $sql2="select count(*) as b from zf_or"
//        $goods1=M('order_goods')->alias('og')->join('order o','og.order_id=o.order_id')->where('o.pay_time',">=",$yestoday_start)->where('o.pay_time','<=',$yestoday_end)->select();
        $order1=M('order')->where('pay_time',">=",$yestoday_start)->where('pay_time','<=',$yestoday_end)->select();
        if(isset($order1) && !empty($order1)){
            foreach ($order1 as $key=>$value){
                //订单中商品数量
                if($this->get_order_number($value['order_id'])){
                    continue;
                }
//                echo $this->get_total($value['order_id']).'<hr />';
                $total+=$this->get_total($value['order_id']);
            }
        }
        $order2=M('order')->where('confirm_time',">=",$yestoday_start)->where('confirm_time','<=',$yestoday_end)->select();
        if(isset($order2) && !empty($order2)){
            foreach ($order2 as $k=>$v){
                if($this->get_order_number($v['order_id'])){
                    $total+=$this->get_total($v['order_id']);
//                    echo $this->get_total($v['order_id']).'<hr />';
                }
            }
        }

//        $goods_profit=M('order_goods')->alias('og')->join('order o','og.order_id=o.order_id')->join('goods g','og.goods_id=g.goods_id')->where('o.pay_time','>=',$yestoday_start)->where('o.pay_time','<=',$yestoday_end)->field('goods_num,sum(og.final_price-og.cost_price) total')->find();
//        //查有需要确认收货之后再返佣的商品的订单
//        $comfirm_profit=M('order')->alias('o')->join('order_goods og','og.order_id=o.order_id')->join('goods g','og.goods_id=g.goods_id')->where(['g.is_receiving_commission'=>1])->where('o.confirm_time','>=',$yestoday_start)->where('o.confirm_time','<=',$yestoday_end)->column('o.order_id');
//        if(isset($goods_profit) && !empty($goods_profit)){
//            return $goods_profit;
//        }
        return $total;
    }
    //查询合伙人的个数   $level是要查询的等级
    public function get_partners_num($level){
        return M('users')->where(['distribut_level'=>$level,'is_lock'=>0])->count();
    }
    //查询所有分红的人
    public function get_all_partners($level){
        return M('users')->where(['distribut_level'=>$level,'is_lock'=>0])->column('user_id');
    }
    public function get_config($name){
        // 获取配置表
        $configs = Db::name('config')->field('name,value')->select();
        // 把配置项name转换成$configs['price_min1']['value']
        $configs = $this->arr2name($configs);
        return $configs[$name]['value'];
    }
//数组转换成[配置项名称]获取数据
    public function arr2name($data,$key=''){
        $return_data=array();
        if(!$data||!is_array($data)){
            return $return_data;
        }
        if(!$key){
            $key='name';
        }
        foreach($data as $dv){
            $return_data[$dv[$key]]=$dv;
        }
        return $return_data;
    }
    //获取订单中返佣商品数量
    public function get_order_number($order_id){
        if(is_numeric($order_id) && $order_id>0){
          return M('order_goods')->where(['order_id'=>$order_id,'is_receiving_commission'=>1])->count();
        }else{
            return 0;
        }
    }
    //算商品利润
    public function get_total($order_id){
        if (is_numeric($order_id) && $order_id>0){
//            $total=M('order_goods')->where(['order_id'=>$order_id])->fetchSql(true)->field('sum((final_price-cost_price)*goods_num) to')->find();
            $total=M('order_goods')->where(['order_id'=>$order_id])->field('sum((final_price-cost_price)*goods_num) a')->find();
//            return $total;
            if(isset($total) && !empty($total['a'])){
                return $total['a'];
            }
        }
        return 0;
    }
}
