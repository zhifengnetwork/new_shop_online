<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: IT宇宙人     
 * Date: 2015-09-09
 */
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use app\common\model\GoodsAttr;
use app\common\model\GoodsAttribute;
use app\common\model\GoodsType;
use app\common\model\Spec;
use app\common\model\SpecGoodsPrice;
use app\common\model\SpecItem;
use app\common\model\GoodsCategory;
use app\common\util\TpshopException;
use app\common\logic\Sales;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

use app\admin\logic\StockLogic;

class Goods extends Base {

    /**
     *  商品分类列表
     */
    public function categoryList(){     
        $GoodsLogic = new GoodsLogic();               
        $cat_list = $GoodsLogic->goods_cat_list();
        $this->assign('cat_list',$cat_list);        
        return $this->fetch();
    }
    
    /**
     * 添加修改商品分类
     * 手动拷贝分类正则 ([\u4e00-\u9fa5/\w]+)  ('393','$1'), 
     * select * from tp_goods_category where id = 393
     *  select * from tp_goods_category where parent_id = 393
     *   update tp_goods_category  set parent_id_path = concat_ws('_','0_76_393',id),`level` = 3 where parent_id = 393
     *   insert into `tp_goods_category` (`parent_id`,`name`) values 
     *   ('393','时尚饰品'),
     */
    public function addEditCategory(){
        
            $GoodsLogic = new GoodsLogic();        
            if(IS_GET)
            {
                $goods_category_info = D('GoodsCategory')->where('id='.I('GET.id',0))->find();
                $this->assign('goods_category_info',$goods_category_info);
                
                $all_type = M('goods_category')->where("level<3")->getField('id,name,parent_id');//上级分类数据集，限制3级分类，那么只拿前两级作为上级选择
                if(!empty($all_type)){
                	$parent_id = empty($goods_category_info) ? I('parent_id',0) : $goods_category_info['parent_id'];
                	$all_type = $GoodsLogic->getCatTree($all_type);
                	$cat_select = $GoodsLogic->exportTree($all_type,0,$parent_id);
                	$this->assign('cat_select',$cat_select);
                }
                
                //$cat_list = M('goods_category')->where("parent_id = 0")->select(); 
                //$this->assign('cat_list',$cat_list);         
                return $this->fetch('_category');
                exit;
            }

            $GoodsCategory = new GoodsCategory(); // D('GoodsCategory'); //

            $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新                        
            //ajax提交验证
            if(I('is_ajax') == 1)
            {
                // 数据验证            
                $validate = \think\Loader::validate('GoodsCategory');
                if(!$validate->batch()->check(input('post.')))
                {                          
                    $error = $validate->getError();
                    $error_msg = array_values($error);
                    $return_arr = array(
                        'status' => -1,
                        'msg' => $error_msg[0],
                        'data' => $error,
                    );
                    $this->ajaxReturn($return_arr);
                } else {
                    $GoodsCategory->data(input('post.'),true); // 收集数据
                    $GoodsCategory->parent_id = I('parent_id');
                    
                    //查找同级分类是否有重复分类
                    $par_id = ($GoodsCategory->parent_id > 0) ? $GoodsCategory->parent_id : 0;
                    $sameCateWhere = ['parent_id'=>$par_id , 'name'=>$GoodsCategory['name']];
                    $GoodsCategory->id && $sameCateWhere['id'] = array('<>' , $GoodsCategory->id);
                    $same_cate = M('GoodsCategory')->where($sameCateWhere)->find();
                    if($same_cate){
                        $return_arr = array('status' => 0,'msg' => '同级已有相同分类存在','data' => '');
                        $this->ajaxReturn($return_arr);
                    }
                    
                    if ($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id) {
                        //  编辑
                        $return_arr = array('status' => 0,'msg' => '上级分类不能为自己','data' => '',);
                        $this->ajaxReturn($return_arr);
                    }

                    //判断不能为自己的子类
                    if ($GoodsCategory->id > 0) {
                        $category_id_list = db('goods_category')->where('parent_id',$GoodsCategory->id)->field('id')->select();
                        $category_id_list = array_column($category_id_list,'id');
                        if (in_array($GoodsCategory->parent_id,$category_id_list)) {
                            $return_arr = array('status' => 0,'msg' => '上级分类不能为自己的子类','data' => '');
                            $this->ajaxReturn($return_arr);
                        }
                    }


                    // if($GoodsCategory->commission_rate > 100)
                    // {
                    //     //  编辑
                    //     $return_arr = array('status' => -1,'msg'   => '分佣比例不得超过100%','data'  => '');
                    //     $this->ajaxReturn($return_arr);                        
                    // }   
                   
                    if ($type == 2)
                    {
                        $GoodsCategory->isUpdate(true)->save(); // 写入数据到数据库
                        $GoodsLogic->refresh_cat(I('id'));
                    }
                    else
                    {
                        $GoodsCategory->save(); // 写入数据到数据库
                        $insert_id = $GoodsCategory->getLastInsID();
                        $GoodsLogic->refresh_cat($insert_id);
                    }
                    $return_arr = array(
                        'status' => 1,
                        'msg'   => '操作成功',
                        'data'  => array('url'=>U('Admin/Goods/categoryList')),
                    );
                    $this->ajaxReturn($return_arr);

                }  
            }

    }

    /**
     * 删除分类
     */
    public function delGoodsCategory(){
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        // 判断子分类
        $count = Db::name("goods_category")->where("parent_id = {$ids}")->count("id");
        $count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下还有分类不得删除!']);
        // 判断是否存在商品
        $goods_count = Db::name('Goods')->where("cat_id = {$ids}")->count('1');
        $goods_count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下有商品不得删除!']);
        // 删除分类
        DB::name('goods_category')->where('id',$ids)->delete();
        $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/Goods/categoryList')]);
    }
    
    
    /**
     *  商品列表
     */
    public function goodsList(){	
        // $info = (new Sales(20021,2286,6))->vip_reward(20021,8831);
        // var_dump($info);
        // die;
        $GoodsLogic = new GoodsLogic();        
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        return $this->fetch();
    }
    
    /**
     *  商品列表
     */
    public function ajaxGoodsList(){            
        
        $where = ' 1 = 1 '; // 搜索条件                
        I('intro')    && $where = "$where and ".I('intro')." = 1" ;        
        I('brand_id') && $where = "$where and brand_id = ".I('brand_id') ;
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = ".I('is_on_sale') ;                
        $cat_id = I('cat_id');
        // 关键词搜索               
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')" ;
        }
        
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandson($cat_id); 
            $where .= " and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }
        
        $count = M('Goods')->where($where)->count();
        $Page  = new AjaxPage($count,20);
        /**  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        */
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('Goods')->where($where)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList',$catList);
        $this->assign('goodsList',$goodsList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }


    /**
     * 出入库日志
     */
    public function stockList()
    {
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
        }
        $logic = new StockLogic();
        $res = $logic->getStockList();
        $this->assign('pager',$res['pager']);
        $this->assign('page',$res['page']);// 赋值分页输出
        $this->assign('stock_list',$res['stock_list']);
        $this->assign('stockChangeType', $res['stockChangeType']);
        return $this->fetch('stock_list');
    }

    /**
     * 库存预警
     */
    public function lowStockWarn()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        return $this->fetch();
    }

    /**
     * 库存预警(获取列表）
     */
    public function ajaxLowStockWarn()
    {
        $logic = new StockLogic();
        $res = $logic->getAjaxLowStockWarn();
        $this->assign('pager',$res['pager']);
        $this->assign('page',$res['page']);// 赋值分页输出
        $this->assign('catList',$res['catList']);
        $this->assign('brand_list',$res['brand_list']);
        $this->assign('goodsList',$res['goodsList']);
        return $this->fetch('ajaxAlterStock');
    }

    /**
     * 库存盘点
     */
    public function alterStock()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        return $this->fetch();
    }

    /**
     * 库存盘点（ajax获取库存列表）
     */
    public function ajaxAlterStock()
    {

        $logic = new StockLogic();
        $res = $logic->getAjaxAlterStock();
        $this->assign('pager',$res['pager']);
        $this->assign('page',$res['page']);// 赋值分页输出
        $this->assign('catList',$res['catList']);
        $this->assign('brand_list',$res['brand_list']);
        $this->assign('goodsList',$res['goodsList']);
        return $this->fetch();
    }

    /**
     * 库存盘点（快速修改库存）
     */
    public function changeStockVal()
    {
        $logic = new StockLogic();
        $res = $logic->doChangeStockVal($this->admin_id); //传入admin_id用于记录stock_log
        ajaxReturn($res);

    }


    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {
        
      
        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\common\model\Goods();
        $goods_id = input('id');
        $setting = array();
        
        if($goods_id){
            $goods = $Goods->where('goods_id', $goods_id)->find();
            $level_cat = $GoodsLogic->find_parent_cat($goods['cat_id']); // 获取分类默认选中的下拉框
            $level_cat2 = $GoodsLogic->find_parent_cat($goods['extend_cat_id']); // 获取分类默认选中的下拉框
            $brandList = $GoodsLogic->getSortBrands($goods['cat_id']);   //获取三级分类下的全部品牌
            $setting = json_decode($goods['goods_prize'],true);

            $this->assign('goods', $goods);
            $this->assign('level_cat', $level_cat);
            $this->assign('level_cat2', $level_cat2);
            $this->assign('brandList', $brandList);
        }
        $cat_list = Db::name('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $goodsType = Db::name("GoodsType")->select();
        $suppliersList = Db::name("suppliers")->where(['is_check'=>1])->select();
        $freight_template = Db::name('freight_template')->where('')->select();
        $level_name = $this->get_level_name();
        $level_name = array_map(function($level){
            return array('level'=>$level['level'],'level_name'=>$level['level_name']);
        },$level_name);
        $sales = array();
        $num = 0;
        foreach ($level_name as $key => $value) {
            $sales[$num] =array(
                'level' => $value['level'],
                'level_name' => $value['level_name'],
                'reward' => 0,
                'poor_prize' => 0,
                'same_reword' => 0,
                'same_reword2' => 0,
                'preferential' => 0,
                'self_buying' => 0,
                'self_poor_prize' => 0,
                'self_reword' => 0,
                'self_reword2' => 0,
            );
            $num ++;
        }  
		
        //返佣设置
        if ($setting) {
            $num = 0;
            $setting_info = M('goods_commission')->select($setting);
            foreach ($setting_info as $k1 => $v1) {
                $sales[$num] = array(
                    'level' => $v1['level'],
                    'level_name' => $level_name[$v1['level']]['level_name'],
                    'reward' => $v1['reward'],
                    'poor_prize' => $v1['poor_prize'],
                    'same_reword' => $v1['same_reword'],
                    'same_reword2' => $v1['same_reword2'],
                    'preferential' => $v1['preferential'],
                    'self_buying' => $v1['self_buying'],
                    'self_poor_prize' => $v1['self_poor_prize'],
                    'self_reword' => $v1['self_reword'],
                    'self_reword2' => $v1['self_reword2']
                );
                $num ++;
            }
        }
        
        $this->assign('sales',$sales);
        $this->assign('freight_template',$freight_template);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsType', $goodsType);
        return $this->fetch('_goods');
    }

    //获取等级名称
    public function get_level_name()
    {
        $level_name = Db::name('agent_level')->field('level,level_name')->select();

        return $level_name;
    }

    //商品保存
    public function save(){
        $data = input('post.');
        $spec_item = input('item/a');
        $setting = '';
        $validate = Loader::validate('Goods');// 数据验证
        if (!$validate->batch()->check($data)) {
            $error = $validate->getError();
            $error_msg = array_values($error);
            $return_arr = ['status' => 0, 'msg' => $error_msg[0], 'result' => $error];
            $this->ajaxReturn($return_arr);
        }
        if ($data['goods_id'] > 0) {
            $goods = \app\common\model\Goods::get($data['goods_id']);
            $store_count_change_num = $data['store_count'] - $goods['store_count'];//库存变化量
            $cart_update_data = ['market_price'=>$data['market_price'],'goods_price'=>$data['shop_price'],'member_goods_price'=>$data['shop_price']];
            db('cart')->where(['goods_id'=>$data['goods_id'],'spec_key'=>''])->save($cart_update_data);
            $is_setting = $goods['goods_prize'];
            //编辑商品的时候需清楚缓存避免图片失效问题
            clearCache();
        }else{
            $goods = new \app\common\model\Goods();
            $store_count_change_num = $data['store_count'];
        }
        
        $level_name = $this->get_level_name();
        $ids = array();

        $is_setting = $is_setting ? json_decode($setting,true) : array();
        $have_reward = M('goods_commission')->column('id,level');
        
        //返佣设置
        foreach ($level_name as $key => $value) {
            foreach ($data as $k2 => $v2) {
                $str = 'level_'.$key;
                if ($str == $k2) {
                    $setting['level'] = $key;
                    $setting['reward'] = floatval($data['reward_'.$key]);
                    $setting['poor_prize'] = floatval($data['poor_prize_'.$key]);
                    $setting['same_reword'] = floatval($data['same_reword_'.$key]);
                    $setting['same_reword2'] = floatval($data['same_reword2_'.$key]);
                    $setting['preferential'] = floatval($data['preferential_'.$key]);
                    $setting['self_buying'] = floatval($data['self_buying_'.$key]);
                    $setting['self_poor_prize'] = floatval($data['self_poor_prize_'.$key]);
                    $setting['self_reword'] = floatval($data['self_reword_'.$key]);
                    $setting['self_reword2'] = floatval($data['self_reword2_'.$key]);
                    $setting['create_time'] = time();
                    $setting['update_time'] = time();

                    if ($is_setting) {
                        $bool = false;
                        foreach ($have_reward as $k3 => $v3) {
                            if ($v3 == $k2) {
                                $bool = true;
                                break;
                            }
                        }
                        if ($bool) {
                            Db::name('goods_commission')->where('level',$key)->update($setting);
                        } else {
                            $is_setting[] = Db::name('goods_commission')->insertGetId($setting);
                            $ids = $is_setting;
                        }
                        
                    } else {
                        $id = Db::name('goods_commission')->insertGetId($setting);
                        $ids[] = $id ? $id : 0;
                    }
                }
                
            }
        }
        
        $goods->data($data, true);
        $goods->last_update = time();
        $goods->price_ladder = true;
        $goods->goods_prize = json_encode($ids);
        $goods->save();
        
        if(empty($spec_item)){
            update_stock_log(session('admin_id'), $store_count_change_num, ['goods_id' => $goods['goods_id'], 'goods_name' => $goods['goods_name']]);//库存日志
        }
        $GoodsLogic = new GoodsLogic();
        $GoodsLogic->afterSave($goods['goods_id']);
        $GoodsLogic->saveGoodsAttr($goods['goods_id'], $goods['goods_type']); // 处理商品 属性
        $return_arr = ['status' => 1, 'msg' => '操作成功'];

        $this->ajaxReturn($return_arr);
    }

    public  function getCategoryBrandList(){
        $cart_id = I('cart_id/d',0);
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands($cart_id);
        $this->ajaxReturn(['status'=>1,'result'=>$brandList]);
    }
    /**
     * 商品类型  用于设置商品的属性
     */
    public function type_list()
    {
        $name = input('name/s');
        $where = [];
        if ($name) {
            $where['name'] = ['like','%'.$name.'%'];
        }
        $GoodsType = new GoodsType();
        $count = $GoodsType->where($where)->count();
        $page = new Page($count, 14);
        $goods_type_list = $GoodsType->where($where)->order("id desc")->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page);
        $this->assign('goods_type_list', $goods_type_list);
        return $this->fetch();
    }

    //商品模型编辑页
    public function type()
    {
        $id = input('id/d');
        if($id){
            $goods_type = GoodsType::get(['id'=>$id]);
            $this->assign('goods_type', $goods_type);
        }
        return $this->fetch();
    }

    //商品模型保存
    public function saveType()
    {
        $data = input('post.');
        $validate = Loader::validate('GoodsType');// 数据验证
        if (!$validate->batch()->check($data)) {
            $error = $validate->getError();
            $error_msg = array_values($error);
            $this->ajaxReturn(['status' => 0, 'msg' => $error_msg[0], 'result' => $error]);
        }
        if ($data['id'] > 0) {
            $goodsType = GoodsType::get($data['id']);
        }else{
            $goodsType = new GoodsType();
        }
        try{
            $goodsType->data($data, true)->save();
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功','type_id'=>$goodsType->id]);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn(['status' => 1, 'msg' => $error[0]]);
        }
    }

    //删除商品模型
    public function deleteType()
    {
        $id = input('id/d');
        if(empty($id)){
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $GoodsType = new GoodsType();
        $goods_type = $GoodsType->where(['id'=>$id])->find();
        try {
            $goods_type->delete();
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }

    //删除规格项
    public function deleteSpe()
    {
        $id = input('id/d');
        if (empty($id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $Spec = new Spec();
        $spec = $Spec->where('id', $id)->find();
        try {
            $spec->delete();
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    //删除规格值
    public function deleteSpeItem()
    {
        $id = input('id/d');
        if (empty($id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $SpeItem = new SpecItem();
        $spec_item = $SpeItem->where('id', $id)->find();
        try{
            $spec_item->delete();
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    //删除属性
    public function deleteAttribute()
    {
        $attr_id = input('attr_id/d');
        if (empty($attr_id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $GoodsAttribute = new GoodsAttribute();
        $goods_attribute = $GoodsAttribute->where('attr_id', $attr_id)->find();
        try {
            $goods_attribute->delete();
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    /**
     * 更改指定表的指定字段
     */
    public function updateField(){
        $primary = array(
                'goods' => 'goods_id',
                'goods_category' => 'id',
                'brand' => 'id',            
                'goods_attribute' => 'attr_id',
        		'ad' =>'ad_id',            
        );        
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];        
        $model->save();   
        $return_arr = array(
            'status' => 1,
            'msg'   => '操作成功',                        
        );
        $this->ajaxReturn($return_arr);
    }

    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput()
    {
        $type_id = input('type_id/d', 0);
        $goods_id = input('goods_id/d', 0);
        $GoodsAttribute = new GoodsAttribute();
        $attribute_list = $GoodsAttribute->where(['type_id' => $type_id,'attr_index'=>1])->order('`order` desc')->select();
        if ($attribute_list) {
            $attribute_list = collection($attribute_list)->append(['attr_values_to_array'])->toArray();
        }
        $GoodsAttr = new GoodsAttr();
        foreach ($attribute_list as $attribute_key => $attribute_val) {
            $goods_attr = $GoodsAttr->where(['goods_id' => $goods_id, 'attr_id' => $attribute_val['attr_id']])->find();
            $attribute_list[$attribute_key]['goods_attr'] = $goods_attr;
        }
        $this->ajaxReturn($attribute_list);
    }

    /**
     * 删除商品
     */
    public function delGoods()
    {
        $ids = I('post.ids','');
        
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        $goods_ids = rtrim($ids,",");
        $$commission_ids = array();
        $goods_commission = M('goods')->whereIn('goods_id',$goods_ids)->column('goods_prize');

        if ($goods_commission) {
            foreach ($goods_commission as $key1 => $value1) {
                if ($value1) {
                    $comm_ids = json_decode($value1,true);
                    foreach ($comm_ids as $key2 => $value2) {
                        $commission_ids[] = intval($value2);
                    }
                }
            }
        }
        
        // 判断此商品是否有订单
        $ordergoods_count = Db::name('OrderGoods')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($ordergoods_count)
        {
            $goods_count_ids = implode(',',$ordergoods_count);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$goods_count_ids}】的商品有订单,不得删除!",'data'  =>'']);
        }
         // 商品团购
        $groupBuy_goods = M('group_buy')->whereIn('goods_id',$goods_ids)->group('goods_id')->getField('goods_id',true);
        if($groupBuy_goods)
        {
            $groupBuy_goods_ids = implode(',',$groupBuy_goods);
            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$groupBuy_goods_ids}】的商品有团购,不得删除!",'data'  =>'']);
        }
        
        //删除用户收藏商品记录
        M('GoodsCollect')->whereIn('goods_id',$goods_ids)->delete();
        //删除返佣设置
        M('goods_commission')->delete($commission_ids);
        // 删除此商品        
        M("Goods")->whereIn('goods_id',$goods_ids)->delete();  //商品表
        M("cart")->whereIn('goods_id',$goods_ids)->delete();  // 购物车
        M("comment")->whereIn('goods_id',$goods_ids)->delete();  //商品评论
        M("goods_consult")->whereIn('goods_id',$goods_ids)->delete();  //商品咨询
        M("goods_images")->whereIn('goods_id',$goods_ids)->delete();  //商品相册
        M("spec_goods_price")->whereIn('goods_id',$goods_ids)->delete();  //商品规格
        M("spec_image")->whereIn('goods_id',$goods_ids)->delete();  //商品规格图片
        M("goods_attr")->whereIn('goods_id',$goods_ids)->delete();  //商品属性
        M("goods_collect")->whereIn('goods_id',$goods_ids)->delete();  //商品收藏

        $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/goods/goodsList")]);
    }
    /**
     * 品牌列表
     */
    public function brandList(){
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : "";
        $count = Db::name("Brand")->where($where)->count();
        $Page = $pager = new Page($count,10);        
        $brandList = Db::name("Brand")->where($where)->order("sort desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $show  = $Page->show(); 
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
        $this->assign('cat_list',$cat_list);       
        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('brandList',$brandList);
        return $this->fetch('brandList');
    }

    /**
     * 添加修改编辑  商品品牌
     */
    public function addEditBrand()
    {
        $id = I('id');
        if (IS_POST) {
            $data = I('post.');
            $brandVilidate = Loader::validate('Brand');
            if (!$brandVilidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '操作失败', 'result' => $brandVilidate->getError()];
                $this->ajaxReturn($return);
            }
            if ($id) {
                Db::name("Brand")->update($data);
            } else {
                Db::name("Brand")->insert($data);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result' => '']);
        }
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单

        
        $this->assign('cat_list', $cat_list);
        $brand = M("Brand")->find($id);
        $this->assign('brand', $brand);
        return $this->fetch('_brand');
    }    
    
    /**
     * 删除品牌
     */
    public function delBrand()
    {
        $ids = I('post.ids','');
        empty($ids) && $this->ajaxReturn(['status' => -1,'msg' => '非法操作！']);
        $brind_ids = rtrim($ids,",");
        // 判断此品牌是否有商品在使用
        $goods_count = Db::name('Goods')->whereIn("brand_id",$brind_ids)->group('brand_id')->getField('brand_id',true);
        $use_brind_ids = implode(',',$goods_count);
        if($goods_count)
        {
            $this->ajaxReturn(['status' => -1,'msg' => 'ID为【'.$use_brind_ids.'】的品牌有商品在用不得删除!','data'  =>'']);
        }
        $res=Db::name('Brand')->whereIn('id',$brind_ids)->delete();
        if($res){
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/goods/brandList")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '操作失败','data'  =>'']);
    }      

    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect()
    {
        $goods_id = input('goods_id/d', 0);
        $type_id = input('type_id/d', 0);
        $specList = db('Spec')->where("type_id", $type_id)->order('`order` desc')->select();
        foreach ($specList as $k => $v)
            $specList[$k]['spec_item'] = db('SpecItem')->where("spec_id = " . $v['id'])->order('id')->getField('id,item'); // 获取规格项
        $items_id = db('SpecGoodsPrice')->where('goods_id', $goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);
        // 获取商品规格图片
        if ($goods_id) {
            $specImageList = db('spec_image')->where("goods_id", $goods_id)->getField('spec_image_id,src');
            $this->assign('specImageList', $specImageList);
        }
        $this->assign('items_ids', $items_ids);
        $this->assign('specList', $specList);
        return $this->fetch('ajax_spec_select');
    }    
    
    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */    
    public function ajaxGetSpecInput(){     
         $GoodsLogic = new GoodsLogic();
         $goods_id = I('goods_id/d') ? I('goods_id/d') : 0;
         $str = $GoodsLogic->getSpecInput($goods_id ,I('post.spec_arr/a',[[]]));
         exit($str);   
    }
    
    /**
     * 删除商品相册图
     */
    public function del_goods_images()
    {
        $path = I('filename','');
        M('goods_images')->where("image_url = '$path'")->delete();
    }

    /**
     * 初始化商品关键词搜索
     */
    public function initGoodsSearchWord(){
        $searchWordLogic = new SearchWordLogic();
        $successNum = $searchWordLogic->initGoodsSearchWord();
        $this->success('成功初始化'.$successNum.'个搜索关键词');
    }

    /**
     * 初始化地址json文件
     */
    public function initLocationJsonJs()
    {
        $goodsLogic = new GoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        $area_list = $goodsLogic->getAreaList();
        $data = "var locationJsonInfoDyr = ".json_encode($region_list, JSON_UNESCAPED_UNICODE).';'."var areaListDyr = ".json_encode($area_list, JSON_UNESCAPED_UNICODE).';';
        file_put_contents(ROOT_PATH."public/js/locationJson.js", $data);
        $this->success('初始化地区json.js成功。文件位置为'.ROOT_PATH."public/js/locationJson.js");
    }

    /**
     * 获取商品模型下拉列表
     */
    public function ajaxGetGoodsTypeList($type_id)
    {
        $GoodsType = new GoodsType();
        $goods_type = $GoodsType->select();

        $html = '<option value="0">选择商品模型';
        foreach ($goods_type as $v){
            $html .= "<option value='{$v['id']}'";
            if($type_id == $v['id']){
                $html .= ' selected="selected" ';
            }
            $html .= ">{$v['name']}</option>";
        }
        ajaxReturn($html);
    }

}