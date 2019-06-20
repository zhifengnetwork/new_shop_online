<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * Author: JY
 * Date: 2015-09-23
 */

namespace app\home\controller;
use app\common\logic\UsersLogic;
use think\Db;
use think\Session;
use think\Verify;
use think\Cookie;

class Api extends Base {
    public  $send_scene;
    
    public function _initialize() {
        parent::_initialize();
        session('user');
    }
    /*
     * 获取地区
     */
    public function getRegion(){
        $parent_id = I('get.parent_id/d');
        $selected = I('get.selected',0);        
        $data = M('region')->where("parent_id",$parent_id)->select();
        $html = '';
        if($data){
            foreach($data as $h){
            	if($h['id'] == $selected){
            		$html .= "<option value='{$h['id']}' selected>{$h['name']}</option>";
            	}
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }
    
    public function shop(){
        return null;
    }
    
    public function getTwon(){
    	$parent_id = I('get.parent_id/d');
    	$data = M('region')->where("parent_id",$parent_id)->select();
    	$html = '';
    	if($data){
    		foreach($data as $h){
    			$html .= "<option value='{$h['id']}'>{$h['name']}</option>";
    		}
    	}
    	if(empty($html)){
    		echo '0';
    	}else{
    		echo $html;
    	}
    }

    /**
     * 获取省
     */
    public function getProvince()
    {
        $province = Db::name('region')->field('id,name')->where(array('level' => 1))->cache(true)->select();
        $res = array('status' => 1, 'msg' => '获取成功', 'result' => $province);
        exit(json_encode($res));
    }

    /**
     * 获取市或者区
     */
    public function getRegionByParentId()
    {
        $parent_id = input('parent_id');
        $res = array('status' => 0, 'msg' => '获取失败，参数错误', 'result' => '');
        if($parent_id){
            $region_list = Db::name('region')->field('id,name')->where(['parent_id'=>$parent_id])->select();
            $res = array('status' => 1, 'msg' => '获取成功', 'result' => $region_list);
        }
        exit(json_encode($res));
    }
    
    /*
     * 获取地区
     */
    public function get_category(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
            $list = M('goods_category')->where("parent_id", $parent_id)->select();
        
        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['name']}</option>"; 
        exit($html);
    }  
    
    
    /**
     * 前端发送短信方法: APP/WAP/PC 共用发送方法
     */
    public function send_validate_code(){
         
        $this->send_scene = C('SEND_SCENE');

        $type = I('type');
        $scene = I('scene');    //发送短信验证码使用场景
        $mobile = I('mobile');
        $sender = I('send');
        $verify_code = I('verify_code');
        $mobile = !empty($mobile) ?  $mobile : $sender ;
        $session_id = I('unique_id' , session_id());
        session("scene" , $scene);
        
        //注册
        if($scene == 1 && !empty($verify_code)){
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_reg')) {
                ajaxReturn(array('status'=>-1,'msg'=>'图像验证码错误'));
            }
        }
        if($type == 'email'){
            //发送邮件验证码
            $logic = new UsersLogic();
            $res = $logic->send_email_code($sender);
            ajaxReturn($res);
        }else{
            //发送短信验证码
            $res = checkEnableSendSms($scene);
            if($res['status'] != 1){
                ajaxReturn($res);
            }
            //判断是否存在验证码
            $data = M('sms_log')->where(array('mobile'=>$mobile,'session_id'=>$session_id, 'status'=>1))->order('id DESC')->find();
            //获取时间配置
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 120;
            //120秒以内不可重复发送
            if($data && (time() - $data['add_time']) < $sms_time_out){
                $return_arr = array('status'=>-1,'msg'=>$sms_time_out.'秒内不允许重复发送');
                ajaxReturn($return_arr);
            }
            //随机一个验证码
            $code = rand(1000, 9999); 
            $params['code'] =$code;
            
            //发送短信
            $resp = sendSms($scene , $mobile , $params, $session_id);

            if($resp['status'] == 1){
                //发送成功, 修改发送状态位成功
                M('sms_log')->where(array('mobile'=>$mobile,'code'=>$code,'session_id'=>$session_id , 'status' => 0))->save(array('status' => 1));
                $return_arr = array('status'=>1,'msg'=>'发送成功,请注意查收');
            }else{
                $return_arr = array('status'=>-1,'msg'=>'发送失败'.$resp['msg']);
            }
            ajaxReturn($return_arr);
        }
    }
    
    /**
     * 验证短信验证码: APP/WAP/PC 共用发送方法
     */
    public function check_validate_code(){

        $code = I('post.code');
        $mobile = I('mobile');
        $sms_type = I('sms_type');
//        $sender = empty($mobile) ? $send : $mobile;
//        $type = I('type');
//        $session_id = I('unique_id', session_id());
//        $scene = I('scene', -1);

//        $logic = new UsersLogic();
//        $res = $logic->check_validate_code($code, $sender, $type ,$session_id, $scene);
//        ajaxReturn($res);

        if (empty($mobile)) {
            return array('code' => 0, 'msg' => '请输入手机号');
        }
        $check_phone = check_mobile_number($mobile);
        if (!$check_phone) {
            return array('code' => 0, 'msg' => '手机号格式不正确');
        }
        if (!$code) {
            return array('code' => 0, 'msg' => '请输入验证码');
        }
        // 验证码
        $checkData['sms_type'] = $sms_type;
        $checkData['code'] = $code;
        $checkData['phone'] = $mobile;
        $res = checkPhoneCode($checkData);
        if ($res['code'] == 0) {
            return json(['code' => 0, 'msg' => $res['msg']]);
        }else{
            return json(['code' => 1, 'msg' => $res['msg']]);
        }
    }
    
    /**
     * 检测手机号是否已经存在
     */
    public function issetMobile()
    {
      $mobile = I("mobile",'0');  
      $users = M('users')->where('mobile',$mobile)->find();
      if($users)
          exit ('1');
      else 
          exit ('0');      
    }

    public function issetMobileOrEmail()
    {
        $mobile = I("mobile",'0');        
        $users = M('users')->where("email",$mobile)->whereOr('mobile',$mobile)->find();
        if($users)
            exit ('1');
        else
            exit ('0');
    }
    /**
     * 查询物流
     */
    public function queryExpress($shipping_code, $invoice_no,$rejson=false)
    {
        // $shipping_code = input('shipping_code');
        // $invoice_no = input('invoice_no');
        // if(empty($shipping_code) || empty($invoice_no)){
        //     return json(['status'=>0,'message'=>'参数有误','result'=>'']);
        // }
        // return json(queryExpress($shipping_code,$invoice_no));

        //判断变量是否为空
        if((!$shipping_code) or (!$invoice_no)){
            return ['status' => -1, 'message' => '参数有误', 'result' => ''];
        }

        //快递公司转换
        switch ($shipping_code) {
            case 'YD':
            $shipping_code = 'YUNDA';
                break;
            
            case 'shunfeng':
            $shipping_code = 'SFEXPRESS';
                break;
			
			case 'YZPY':
            $shipping_code = 'CHINAPOST';
                break;
			
			case 'YTO':
            $shipping_code = 'YTO';
                break;

			case 'ZTO':
            $shipping_code = 'ZTO';
                break;

            default:
            $shipping_code = '';
                break;
        }

        $condition = array(
            'shipping_code' => $shipping_code,
            'invoice_no' => $invoice_no,
        );
        $is_exists = M('delivery_express')->where($condition)->find();

       
       //判断物流记录表是否已有记录,没有则去请求新数据
        if($is_exists){
            $result = unserialize($is_exists['result']);
//            if($invoice_no=='8060175081273327979'){
//                var_dump($result);die;
//            }
            if($result['msg']=='没有消息'){
                $result = $this->getDelivery($shipping_code, $invoice_no);
//            var_dump($result);die;
                $result = json_decode($result, true);

                $flag = $this->insertData($result, $shipping_code, $invoice_no);
                return $result;
            }
            //1为订单签收状态,订1单已经签收,已签收则不去请求新数据
            if($is_exists['issign'] == 1){
                return $rejson ? json($result) : $result;
            }

            $pre_time = time();
            $flag_time = (int)$is_exists['update_time'];
            $space_time = $pre_time - $flag_time;
            //请求状态正常的数据请求时间间隔小于两小时则不请求新数据
            //其他数据请求时间间隔小于半小时则不请求新数据
            if($result['status'] == 0){
                if($space_time < 7200){
                    return $rejson ? json($result) : $result;
                }
            }else{
                if($space_time < 1800){
                    return $rejson ? json($result) : $result;
                }
            }
            
            $result = $this->getDelivery($shipping_code, $invoice_no);
            $result = json_decode($result, true);
            //更新表数据
            $flag = $this->updateData($result, $is_exists['id']);
            return $result;
            
        }else{
            $result = $this->getDelivery($shipping_code, $invoice_no);
//            var_dump($result);die;
            $result = json_decode($result, true);

            $flag = $this->insertData($result, $shipping_code, $invoice_no);
            return $result;
        }
    }

    /**
    *物流接口
    */
    private function getDelivery($shipping_code, $invoice_no)
    {
        $host = "https://wuliu.market.alicloudapi.com";//api访问链接
        $path = "/kdi";//API访问后缀
        $method = "GET";
        //物流
        $appcode = 'c5ccb196109848fe8ea5e1668410132a';//替换成自己的阿里云appcode
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "no=".$invoice_no."&type=".$shipping_code;  //参数写在这里
        $bodys = "";
        $url = $host . $path . "?" . $querys;//url拼接

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        //curl_setopt($curl, CURLOPT_HEADER, true); 如不输出json, 请打开这行代码，打印调试头部状态码。
        //状态码: 200 正常；400 URL无效；401 appCode错误； 403 次数用完； 500 API网管错误
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        return curl_exec($curl);
    }

    //物流表更新
    public function updateData($result, $id)
    {
        $data = array(
            'result' => serialize($result),
            // 'issign' => $result['result']['issign'],
            'update_time' => time(),
        );
        if(isset($result['result']['issign'])){
            $data['issign'] = $result['result']['issign'];
        }
        
        return M('delivery_express')->where('id', $id)->update($data);
    }

    //物流插表
    public function insertData($result, $shipping_code, $invoice_no)
    {
        $data = array(
            'shipping_code' => $shipping_code,
            'invoice_no' => $invoice_no,
            'result' => serialize($result),
            // 'issign' => $result['result']['issign'],
            'update_time' => time(),
        );
        if(isset($result['result']['issign'])){
            $data['issign'] = $result['result']['issign'];
        }
       
        return M('delivery_express')->insert($data);
    }
    
    /**
     * 检查订单状态
     */
    public function check_order_pay_status()
    {
        $order_id = I('order_id/d');
        if(empty($order_id)){
            $res = ['message'=>'参数错误','status'=>-1,'result'=>''];
            $this->AjaxReturn($res);
        }
        $order = M('order')->field('pay_status')->where(['order_id'=>$order_id])->find();
        if($order['pay_status'] != 0){
            $res = ['message'=>'已支付','status'=>1,'result'=>$order];
        }else{
            $res = ['message'=>'未支付','status'=>0,'result'=>$order];
        }
        $this->AjaxReturn($res);
    }

    /**
     * 检查订单状态
     */
    public function check_order_pay_status()
    {
        $order_id = I('order_id/d');
        if(empty($order_id)){
            $res = ['message'=>'参数错误','status'=>-1,'result'=>''];
            $this->AjaxReturn($res);
        }
        $order = M('order')->field('pay_status')->where(['order_id'=>$order_id])->find();
        if($order['pay_status'] != 0){
            $res = ['message'=>'已支付','status'=>1,'result'=>$order];
        }else{
            $res = ['message'=>'未支付','status'=>0,'result'=>$order];
        }
        $this->AjaxReturn($res);
    }

     /**
     * 检查订单状态
     */
    public function check_order_pay_status_vip()
    {
        $order_id = I('order_id/d');
        if(empty($order_id)){
            $res = ['message'=>'参数错误','status'=>-1,'result'=>''];
            $this->AjaxReturn($res);
        }
        $order = M('buy_vip')->field('pay_status')->where(['order_id'=>$order_id])->find();
        if($order['pay_status'] != 0){
            $res = ['message'=>'已支付','status'=>1,'result'=>$order];
        }else{
            $res = ['message'=>'未支付','status'=>0,'result'=>$order];
        }
        $this->AjaxReturn($res);
    }

    /**
     * 广告位js
     */
    public function ad_show()
    {
        $pid = I('pid/d',1);
        $where = array(
            'pid'=>$pid,
            'enable'=>1,
            'start_time'=>array('lt',strtotime(date('Y-m-d H:00:00'))),
            'end_time'=>array('gt',strtotime(date('Y-m-d H:00:00'))),
        );
        $ad = D("ad")->where($where)->order("orderby desc")->cache(true,TPSHOP_CACHE_TIME)->find();
        $this->assign('ad',$ad);
        return $this->fetch();
    }
    /**
     *  搜索关键字
     * @return array
     */
    public function searchKey(){
        $searchKey = input('key');
        $searchKeyList = Db::name('search_word')
            ->where('keywords','like',$searchKey.'%')
            ->whereOr('pinyin_full','like',$searchKey.'%')
            ->whereOr('pinyin_simple','like',$searchKey.'%')
            ->limit(10)
            ->select();
        if($searchKeyList){
            return json(['status'=>1,'msg'=>'搜索成功','result'=>$searchKeyList]);
        }else{
            return json(['status'=>0,'msg'=>'没记录','result'=>$searchKeyList]);
        }
    }

    /**
     * 根据ip设置获取的地区来设置地区缓存
     */
    public function doCookieArea()
    {
//        $ip = '183.147.30.238';//测试ip
        $address = input('address/a',[]);
        if(empty($address) || empty($address['province'])){
            $this->setCookieArea();
            return;
        }
        $province_id = Db::name('region')->where(['level' => 1, 'name' => ['like', '%' . $address['province'] . '%']])->limit('1')->value('id');
        if(empty($province_id)){
            $this->setCookieArea();
            return;
        }
        if (empty($address['city'])) {
            $city_id = Db::name('region')->where(['level' => 2, 'parent_id' => $province_id])->limit('1')->order('id')->value('id');
        } else {
            $city_id = Db::name('region')->where(['level' => 2, 'parent_id' => $province_id, 'name' => ['like', '%' . $address['city'] . '%']])->limit('1')->value('id');
        }
        if (empty($address['district'])) {
            $district_id = Db::name('region')->where(['level' => 3, 'parent_id' => $city_id])->limit('1')->order('id')->value('id');
        } else {
            $district_id = Db::name('region')->where(['level' => 3, 'parent_id' => $city_id, 'name' => ['like', '%' . $address['district'] . '%']])->limit('1')->value('id');
        }
        $this->setCookieArea($province_id, $city_id, $district_id);
    }

    /**
     * 设置地区缓存
     * @param $province_id
     * @param $city_id
     * @param $district_id
     */
    private function setCookieArea($province_id = 1, $city_id = 2, $district_id = 3)
    {
        Cookie::set('province_id', $province_id);
        Cookie::set('city_id', $city_id);
        Cookie::set('district_id', $district_id);
    }
    
}