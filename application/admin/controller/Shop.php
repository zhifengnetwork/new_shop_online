<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 当燃
 * 拼团控制器
 * Date: 2016-06-09
 */

namespace app\admin\controller;

use app\common\model\Shopper;
use think\Loader;
use think\Db;
use think\Page;

class Shop extends Base
{
    public function index()
    {
        return false;
    }

    /**
     * 门店自提点
     * @return mixed
     */
    public function info()
    {
        $shop_id = input('shop_id/d');
        if ($shop_id) {
            $Shop = new \app\common\model\Shop();
            $shop = $Shop->where(['shop_id' => $shop_id,'deleted' => 0])->find();
            if (empty($shop)) {
                $this->error('非法操作');
            }
            $city_list = Db::name('region')->where(['parent_id'=>$shop['province_id'],'level'=> 2])->select();
            $district_list = Db::name('region')->where(['parent_id'=>$shop['city_id']])->select();
            $shop_image_list = Db::name('shop_images')->where(['shop_id'=>$shop['shop_id']])->select();
            $this->assign('city_list', $city_list);
            $this->assign('district_list', $district_list);
            $this->assign('shop_image_list', $shop_image_list);
            $this->assign('shop', $shop);
        }
        $province_list = Db::name('region')->where(['parent_id'=>0,'level'=> 1])->cache(true)->select();
        $suppliers_list = Db::name("suppliers")->where(['is_check'=>1])->select();
        $this->assign('suppliers_list', $suppliers_list);
        $this->assign('province_list', $province_list);
        return $this->fetch();
    }

    public function add(){
        return false;
    }

    public function save(){
        return false;
    }

    /**
     * 删除
     */
    public function delete()
    {
        $shop_id = input('shop_id/d');
        if(empty($shop_id)){
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $Shop = new \app\common\model\Shop();
        $shop = $Shop->where(['shop_id'=>$shop_id])->find();
        if(empty($shop)){
            $this->ajaxReturn(['status' => 0, 'msg' => '非法操作', 'result' => '']);
        }
        $row = $shop->save(['deleted'=>1]);
        if($row !== false){
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功', 'result' => '']);
        }else{
            $this->ajaxReturn(['status' => 0, 'msg' => '删除失败', 'result' => '']);
        }
    }


    
    public function page_delete () {
        $id = request()->param('id',0,'intval');
        if (!empty($id)) {
            $getPage = model('DiyEweiShop')->where(['id' => $id])->find();
            if (!empty($getPage)){
                $delete = model('DiyEweiShop')->where(['id'=>$id])->update(['status'=>-1]);
                if ($delete){
                    return json(['code'=>1, 'msg'=>'操作成功','data'=>[]]);
                }else{
                    return json(['code'=>0, 'msg'=>'操作失败','data'=>[]]);
                }
            }else{
                return json(['code'=>0, 'msg'=>'页面不存在！','data'=>[]]);
            }
        }else{
            return json(['code'=>0, 'msg'=>'id不存在','data'=>[]]);
        }
    }

    public function editShop () {
            $id        = I('id');
            $page_name = I('page_name');
            $data      = I('data/a');

            if (empty($page_name)){
                return json(['code'=>0,'msg'=>'请填写页面名称']);
            }
            if (!empty($data)){
                
                $res = model('DiyEweiShop')->edit($data,$this->admin_id,$page_name,$id);

                if ($res){
                    return json(['code'=>1,'msg'=>'保存成功','data'=>['id'=>$res]]);
                }else{
                    return json(['code'=>0,'msg'=>'保存失败']);
                }

            }else{
                return json(['code'=>0,'msg'=>'首页不能为空，请您添加组件']);
            }

    }

    public function getShopData () {
        $id = request()->param('id');

        $res = model('DiyEweiShop')->getShopData($id);
        if (!empty($res)){
            return json(['code'=>1,'msg'=>'','data'=>$res]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据，请添加','data'=>'']);
        }
    }

    public function gooodsList () {
        $keyword = request()->param('keyword');
        $page    = request()->param('page');
     
        if($page < 0 || empty($page)){ $page = 1;}
        $data    = model('Goods')->getGoodsList($keyword,0,$page);
       
        if (!empty($data['list'])){
            foreach ( $data['list'] as &$v){
                $v['original_img'] = SITE_URL.$v['original_img'];
            }
        }
        if (!empty($data['list'])){
            $this->ajaxReturn(['code'=>1,'msg'=>"操作成功",'total' => $data['total'],'per_page'=>$data['per_page'],'last_page' => $data['last_page'],'current_page' => $data['current_page'],'data' =>$data['list']]);
        }else{
            $this->ajaxReturn(['code'=>0,'msg'=>'还没有商品哦','data'=>'']);
        }
    }


    public function page_enable () {
        $id     = request()->param('id',0,'intval');
        $status = request()->param('status',0,'intval');
        if (!empty($id)){
            $getPage = model('DiyEweiShop')->where(['id'=>$id])->find();
            if (!empty($getPage)){
                if ($getPage['status'] == $status){
                    model('DiyEweiShop')->where(['id'=>$getThisEnablePage['id']])->update(['status'=>$status]);
                }else{
                    if ($status == 1){
                        $getThisEnablePage = model('DiyEweiShop')->where(['status'=>1])->find();
                        if (!empty($getThisEnablePage)){
                            model('DiyEweiShop')->where(['id'=>$getThisEnablePage['id']])->update(['status'=>0]);
                        }
                    }
                    $updateThisPage = model('DiyEweiShop')->where(['id'=>$id])->update(['status'=>$status]);
                    if ($updateThisPage){
                        $this->ajaxReturn(['code'=>1, 'msg'=>'操作成功','data'=>[]]);
                    }else{
                        $this->ajaxReturn(['code'=>0, 'msg'=>'操作失败','data'=>[]]);
                    }
                }
            }else{
                $this->ajaxReturn(['code'=>0, 'msg'=>'页面不存在！','data'=>[]]);
            }
        }else{
            $this->ajaxReturn(['code'=>0, 'msg'=>'id不存在','data'=>[]]);
        }
    }


    public function shop_img(){
        $img      = I('img');
        if(empty($img)){
            $this->ajaxReturn(['code'=>0,'msg'=>'上传图片不能为空','data'=>'']);
        }
        $saveName       = request()->time().rand(0,99999) . '.png';
        $base64_string  = explode(',', $img);
        $imgs           = base64_decode($base64_string[1]);
        //生成文件夹
        $names = "shops";
        $name  = "shops/" .date('Ymd',time());
        if (!file_exists(ROOT_PATH .UPLOAD_PATH.$names)){ 
            mkdir(ROOT_PATH .UPLOAD_PATH.$names,0777,true);
        }
        //保存图片到本地
        $r   = file_put_contents(ROOT_PATH .UPLOAD_PATH.$name.$saveName,$imgs);
        $this->ajaxReturn(['code'=>1,'msg'=>'ok','data'=>SITE_URL.'/public/upload/'.$name.$saveName]);
    }




}
