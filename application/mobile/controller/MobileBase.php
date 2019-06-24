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
 * $Author: IT宇宙人 2016-08-10 $
 */
namespace app\mobile\controller;

use think\Controller;
use think\Db;
use app\common\logic\CartLogic;
use app\common\logic\UsersLogic;
use app\common\logic\wechat\WechatUtil;
use app\common\model\UserSign;
use app\common\model\UserInvite;

class MobileBase extends Controller {
    public $session_id;
    public $weixin_config;
    public $cateTrre = array();
    public $tpshop_config = array();
    public $user_id;
    public $user;


    /*
     * 初始化操作
     */
    public function _initialize() {

        session('user'); //不用这个在忘记密码不能获取session('validate_code');
        if(!isset($user)) $user = session('user');
        $dfc5b = I('dfc5b',0);
//        var_dump($dfc5b);
        if($dfc5b && !session('dfc5b')){
            if(!$user['user_id'] || $dfc5b != $user['user_id']){
                $dfc5b_user = Db::name('users')->where('user_id', $dfc5b)->value('openid');
//                var_dump($dfc5b_user);die;
                    if($dfc5b_user){
                    session('dfc5b_user', $dfc5b_user);
                    session('dfc5b', $dfc5b);
                    $this->redirect('/Mobile/User/login.html');
                }else{
                    session('dfc5b', 0);
                }
            }
        }
//        Session::start();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        $this->session_id = session_id(); // 当前的 session_id
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用
        // 判断当前用户是否手机                
        if(isMobile())
            cookie('is_mobile','1',3600); 
        else 
            cookie('is_mobile','0',3600);
        
        //微信浏览器
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            $this->weixin_config = M('wx_user')->find(); //取微获信配置
            $this->assign('wechat_config', $this->weixin_config);            
            $user_temp = session('user');
            if (isset($user_temp['user_id']) && $user_temp['user_id']) {
                $user = M('users')->where("user_id", $user_temp['user_id'])->find();
                if (!$user) {
                    $_SESSION['openid'] = 0;
                    session('user', null);
                }
            } 
            if (empty($_SESSION['openid'])){
                
                if(is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1){
                    $wxuser = $this->GetOpenid(); //授权获取openid以及微信用户信息
                    // dump($wxuser);exit;
                    //过滤特殊字符串
                    $wxuser['nickname'] && $wxuser['nickname'] = replaceSpecialStr($wxuser['nickname']);
                    
                    session('subscribe', $wxuser['subscribe']);// 当前这个用户是否关注了微信公众号
                    setcookie('subscribe',$wxuser['subscribe']);
                    $logic = new UsersLogic(); 
                    $is_bind_account = tpCache('basic.is_bind_account');
                    // dump($is_bind_account);exit;
                     if ($is_bind_account) {
                         if (CONTROLLER_NAME != 'User' || ACTION_NAME != 'bind_guide') {
                            $data = $logic->thirdLogin_new($wxuser);//微信自动登录
                            if ($data['status'] != 1 && $data['result'] === '100') {
                                 session("third_oauth" , $wxuser);
                                 $first_leader = I('first_leader');
                                 $this->redirect(U('Mobile/User/bind_guide',['first_leader'=>$first_leader]));
                           }
                         }
                    } else { 
                        $data = $logic->thirdLogin($wxuser);
                    }
                    if($data['status'] == 1){
                        session('user',$data['result']);
                        setcookie('user_id',$data['result']['user_id'],null,'/');
                        setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
                        setcookie('uname',$data['result']['nickname'],null,'/');
                        // 登录后将购物车的商品的 user_id 改为当前登录的id
                        M('cart')->where("session_id" ,$this->session_id)->save(array('user_id'=>$data['result']['user_id']));
                        $cartLogic = new CartLogic();
                        $cartLogic->setUserId($data['result']['user_id']);
                        $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
                    }
                }
            }else{
                setcookie('user_id',$user_temp['user_id'],null,'/');
                setcookie('is_distribut',$user_temp['is_distribut'],null,'/');
            }
        }
        
        $this->public_assign();

        // 主动刷新全局 Access_Token
        $this->Auto_Refresh_Access_Token();

		// 扫码上下级缓存
		$this->share_poster();



        // $user = Db::name('users')->find(17657);
        // session('user',$user);
     
        if($user['user_id']){
            write_log('bind 开始'. $user['user_id']);
//            var_dump($_SESSION);
            $this->user_id = $user['user_id'];
            $this->user = Db::name('users')->find($this->user_id);
            $user = $this->user;
            session('user',$user);

            $this->clear_wx_temp_cache($user['user_id']);

            # 新版 - 上下级关系绑定
            if(session('dfc5b') && !$this->user['first_leader']){
               
                $dfc5b = session('dfc5b');
                if($dfc5b != $this->user_id){
                  
                    $dfc5b_res = Db::name('users')->where('user_id', $this->user_id)->update(['first_leader' => $dfc5b]);
                    if($dfc5b_res){
                        $dfc5b_user = session('dfc5b_user');
                      
                        if($dfc5b_user){
                         
                            $this->Invitation_Register($dfc5b_user,'恭喜你邀请注册成功！',$user['nickname'],$user['mobile'],time(),'恭喜你又收纳一名得力爱将，你的团队越来越大！');
                        }
                        session('dfc5b',0);
                        session('dfc5b_user', '');
                    }
                }else{
                    session('dfc5b',0);
                    session('dfc5b_user', '');
                }
            }

        
            // if( !$user['mobile'] && (CONTROLLER_NAME != 'User' || ACTION_NAME != 'setMobile') ){
            //     echo "<h1 style='text-align:center; margin-top:30%;'>请先设置手机号码</h1>";
            //     echo "<script>setTimeout(function(){window.location.href='/Mobile/User/setMobile'},2000);</script>";
            //     exit;
            // }
            
            
            // 邀请注册送佣金
            $UserInvite = new UserInvite();
            $UserInvite->user_invite($user['user_id']);

            // 签到送佣金
            $UserSign = new UserSign();
            $sign_res = $UserSign->sign($user);
            if($sign_res && $user['openid']){
                $sign_log = Db::name('commission_log')->where(['user_id'=>$user['user_id'],'identification'=>1])->order('id desc')->field('num,money,addtime')->find();
                $this->Sign_Success($user['openid'],'恭喜你签到成功',$user['nickname'],$sign_log['addtime'],$sign_log['num'],$sign_log['money'],'感谢你每天光顾商城，你的足迹会吸引越来越多小伙伴，继续加油吧！');
            }
        }
        
    }


    /**
     * 清理微信待发模板消息缓存
     */
    public function clear_wx_temp_cache($user_id = 0){
        if($user_id > 0){
            $f = Db::name('wx_temp_cache')->where('user_id',$user_id)->find();
        }else{
            $f = Db::name('wx_temp_cache')->find();
        }
        if($f){
            switch($f['type']){
                # 购买成功通知
                case 'Purchase_Success':
                    $openid = Db::name('users')->where('user_id',$f['user_id'])->value('openid');
                    if($openid){
                        $og = Db::name('order_goods')->where('order_id',$f['tid'])->order('rec_id desc')->field('goods_name')->find();
                        if($og){
                            $this->Purchase_Success($openid,'商品支付成功！',$og['goods_name'],'支付成功！',$f['money'],'欢迎再次购买！');
                        }
                    }
                    Db::name('wx_temp_cache')->delete($f['id']);
                break;
                default:
                    return false;
            }
                
        }

    }
    

	
	/**
	* 微信扫码上下级关系缓存处理
	* @author rock
	* @date 2019/3/29
	*/
	public function share_poster(){
		# 删除长时间的缓存
		$del_time = time() - 600;
        Db::execute("delete from `tp_wxshare_cache` where `time` <= '$del_time'");
		
		# 当前用户信息
		$user_temp = session('user');
		if (isset($user_temp['user_id']) && $user_temp['first_leader'] < 1 && $user_temp['openid']) {
			$cache = Db::name('wxshare_cache')->where('openid',$user_temp['openid'])->find();
			if($cache){
                if($user_temp['user_id'] != $cache['share_user']){
                    Db::execute("update `tp_users` set `first_leader` = '".$cache['share_user']."' where `user_id` = '".$user_temp['user_id']."'");
//                    $share_user_openid = Db::name('users')->field('id,openid')->where('user_id',$cache['share_user'])->value('openid');
                    $share_user_openid = Db::name('users')->where('user_id',$cache['share_user'])->value('openid');
//                    var_dump($share_user_openid);die;
                    if($share_user_openid){
                        // $wx_content = "会员ID: ".$user_temp['user_id']." 成为了你的下级!";
                        // $wechat = new \app\common\logic\wechat\WechatUtil();
                        // $wechat->sendMsg($share_user_openid, 'text', $wx_content);
                        
                        $this->Invitation_Register($share_user_openid,'恭喜你邀请注册成功！',$user_temp['nickname'],$user_temp['mobile'],time(),'恭喜你又收纳一名得力爱将，你的团队越来越大！');
                        
                    }
                }
				Db::execute("delete from `tp_wxshare_cache` where `id` = '".$cache['id']."'");
			}
		} 
    }
    

    /**
     * 保存公告变量到 smarty中 比如 导航 
     */   
    public function public_assign()
    {
        $first_login = session('first_login');
        $this->assign('first_login', $first_login);
        if (!$first_login && ACTION_NAME == 'login') {
            session('first_login', 1);
        }
       $tp_config = Db::name('config')->cache(true, TPSHOP_CACHE_TIME, 'config')->select();
       foreach($tp_config as $k => $v)
       {
       	  if($v['name'] == 'hot_keywords'){
       	  	 $this->tpshop_config['hot_keywords'] = explode('|', $v['value']);
       	  }
           $this->tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
       }
       $goods_category_tree = get_goods_category_tree();
       $this->cateTrre = $goods_category_tree;
       $this->assign('goods_category_tree', $goods_category_tree);
       $brand_list = M('brand')->cache(true,TPSHOP_CACHE_TIME)->field('id,cat_id,logo,is_hot')->where("cat_id>0")->select();
       $this->assign('brand_list', $brand_list);
       $this->assign('tpshop_config', $this->tpshop_config);
       /** 修复首次进入微商城不显示用户昵称问题 **/
       $user_id = cookie('user_id');
       $uname = cookie('uname');
       if(empty($user_id) && ($users = session('user')) ){
           $user_id = $users['user_id'];
           $uname = $users['nickname'];
       }
       $this->assign('user_id',$user_id);
       $this->assign('uname',$uname);
      
    }     

    /**
     * 主动刷新微信全局 ACCESS_TOKEN
     * @author Rock
     * @date 2019/03/25
     */
    public function Auto_Refresh_Access_Token($auto = false){
        $conf = Db::name('wx_user')->field('id,appid,appsecret,web_access_token,web_expires')->where('wait_access',1)->find();
        if($conf['appid']){
            if($conf['web_expires'] < time() || $auto){
				$appid = $conf['appid'];
				$appsecret = $conf['appsecret'];
                $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appsecret";
                $res = httpRequest($url,'GET');
                $res = json_decode($res,true);
                if($res['access_token']){
					$access_token = $res['access_token'];
					$expires_in = time() + ($res['expires_in'] - 200);
					Db::execute("update `tp_wx_user` set `web_access_token` = '$access_token',`web_expires` = '$expires_in' where `id` = '$conf[id]'");
				}
            }
        }
    }




    // 网页授权登录获取 OpendId
    public function GetOpenid()
    {
        if($_SESSION['openid'])
            return $_SESSION['data'];
        //通过code获得openid
        if (!isset($_GET['code'])){
            //触发微信返回code码
            //$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            $baseUrl = urlencode($this->get_url());
            $url = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址
            Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
            exit();
        } else {
            //上面获取到code后这里跳转回来
            $code = $_GET['code'];
            $data = $this->getOpenidFromMp($code);//获取网页授权access_token和用户openid
            $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息
            $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
            $data['sex'] = $data2['sex'];
            $data['head_pic'] = $data2['headimgurl']; 
            $data['subscribe'] = $data2['subscribe'];      
            $data['oauth_child'] = 'mp';
            $_SESSION['openid'] = $data['openid'];
            $data['oauth'] = 'weixin';
            if(isset($data2['unionid'])){
            	$data['unionid'] = $data2['unionid'];
            }
            $_SESSION['data'] =$data;
            return $data;
        }
    }

    /**
     * 获取当前的url 地址
     * @return type
     */
    private function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }    
    
    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
    	//1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
    	//2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code);       
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);         
        curl_close($ch);
        return $data;
    }
    
    
        /**
     *
     * 通过access_token openid 从工作平台获取UserInfo      
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    {         
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);            
        curl_close($ch);
        //获取用户是否关注了微信公众号， 再来判断是否提示用户 关注
        //if(!isset($data['unionid'])){
            $wechat = new WechatUtil($this->weixin_config);
            $fan = $wechat->getFanInfo($openid);//获取基础支持的access_token
            if ($fan !== false) {
                $data['subscribe'] = $fan['subscribe'];
            }
        //}
        return $data;
    }

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
//        $urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["secret"] = $this->weixin_config['appsecret'];
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址     
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token,$openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';        
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;                    
    }    
    
    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }
    public function ajaxReturn($data){
        exit(json_encode($data));
    }

    # 发货成功通知
    public function Out_Order($openid,$title,$goods_name,$order_sn,$system='神器商城',$remark,$url=''){
        $data = [
            'touser' => $openid,
            'template_id' => 'p6GUL7lm9Au3tVCKAY3XAGY5t3g9_iHhlhYPOjGUSXY',
            'url' => $url,
            'data' => [
                'first' => [
                    'value' => $title,
                ],
                'keyword1' => [
                    'value' => $goods_name,
                ],
                'keyword2' => [
                    'value' => $order_sn,
                ],
                'keyword3' => [
                    'value' => $system,
                ],
                'remark' => [
                    'value' => $remark,
                ],
            ],
        ];
        return $this->Send_Template_Message($data);
    }


    # 签到成功通知
    public function Sign_Success($openid,$title,$nickname,$time,$sign,$money,$remark,$url=''){

        $data = [
            'touser' => $openid,
            'template_id' => 'is-V83Y5OYkUpjrL9YVzWvR84oW96qvPq_flkFKRlFw',
            'url' => $url,
            'data' => [
                'first' => [
                    'value' => $title,
                ],
                'keyword1' => [
                    'value' => $nickname,
                ],
                'keyword2' => [
                    'value' => date('Y年m月d日 H时i分s秒', $time),
                ],
                'keyword3' => [
                    'value' => $sign,
                ],
                'keyword4' => [
                    'value' => $money . ' 元',
                ],
                'remark' => [
                    'value' => $remark,
                ],
            ],
        ];
        return $this->Send_Template_Message($data);

    }

    # 邀请注册成功通知
    public function Invitation_Register($openid,$title,$nickname,$mobile,$time,$remark,$url=''){

        $data = [
            'touser' => $openid,
            'template_id' => 'EcnwVGHweODRpWRc6arlA9Y8etpKnvS7T3Ev9uohStk',
            'url' => $url,
            'data' => [
                'first' => [
                    'value' => $title,
                ],
                'keyword1' => [
                    'value' => $nickname,
                ],
                'keyword2' => [
                    'value' => $mobile,
                ],
                'keyword3' => [
                    'value' => date('Y年m月d日 H时i分s秒', $time),
                ],
                'remark' => [
                    'value' => $remark,
                ],
            ],
        ];
        return $this->Send_Template_Message($data);

    }



    # 提现成功通知
    public function Withdrawal_Success($openid,$title,$money,$time,$remark,$url=''){
        $data = [
            'touser' => $openid,
            'template_id' => '2kfCT6VDejHU55ttMbZ70rLxrVuq6bjt9ZGtyKqkSE0',
            'url' => $url,
            'data' => [
                'first' => [
                    'value' => $title,
                ],
                'keyword1' => [
                    'value' => $money . ' 元',
                ],
                'keyword2' => [
                    'value' => date('Y年m月d日 H时i分s秒', $time),
                ],
                'remark' => [
                    'value' => $remark,
                ],
            ],
        ];
        return $this->Send_Template_Message($data);
    }


    # 购买成功通知
    public function Purchase_Success($openid,$title,$name,$status,$money,$remark,$url=''){

        $data = [
            'touser' => $openid,
            'template_id' => '10Nmjxq1MFRTmjFZMGXNC5ZLzUX_Eq6z5yG15r6KWYU',
            'url' => $url,
            'data' => [
                'first' => [
                    'value' => $title,
                ],
                'keyword1' => [
                    'value' => $name,
                ],
                'keyword2' => [
                    'value' => $status,
                ],
                'keyword3' => [
                    'value' => $money . ' 元',
                ],
                'remark' => [
                    'value' => $remark,
                ],
            ],
        ];
        return $this->Send_Template_Message($data);
    }

    # 发送模板消息
    public function Send_Template_Message($data){
        if(!$data){
            return false;
        }
        $conf = Db::name('wx_user')->field('id,appid,appsecret,web_access_token,web_expires')->find();
        $token = $conf['web_access_token'];
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$token;
        $res = httpRequest($url,'POST',json_encode($data));
        return $res;

    }

}