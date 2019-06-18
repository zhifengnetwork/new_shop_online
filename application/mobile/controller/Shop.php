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
 * 2015-11-21
 */
namespace app\mobile\controller;

use think\Page;
use think\Request;
use think\db;

class Shop extends MobileBase
{

    public function getShopData () {
        $res = model('DiyEweiShop')->getShopData();
        if (!empty($res)){
            return json(['code'=>1,'msg'=>'','data'=>$res]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据，请添加','data'=>'']);
        }
        
    }

    public function gooodsList () {
        $keyword  = request()->param('keyword','');
        $cat_id   = request()->param('cat_id',0,'intval');
        $page     = request()->param('page',0,'intval');
        $goods    = new Goods();
        $list     = $goods->getGoodsList($keyword,$cat_id,$page);
        if (!empty($list)){
            return json(['code'=>1,'msg'=>'','data'=>$list]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据哦','data'=>$list]);
        }
    }

    public function getGoodsData () {
        $goods_id = request()->param('goods_id',0,'intval');
        $data = model('Goods')->where('goods_id',$goods_id)->find();
        $sku =  Db::table('goods_sku')->where('goods_id',$data['goods_id'])->select();
        $data['sku'] = $sku;
        return json(['code'=>1,'msg'=>'','data'=>$data]);
    }

}