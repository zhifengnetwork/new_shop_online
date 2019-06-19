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
 * Author: 当燃
 * Date: 2015-09-09
 */

namespace app\admin\controller;
use app\admin\logic\UpgradeLogic;
use app\common\logic\Saas;
use think\Controller;
use think\Db;
use think\response\Json;
use think\Session;
class Base extends Controller {

    public $begin;
    public $end;
    public $page_size = 0;
    public $admin_id = 0;

    /**
     * 析构函数
     */
    function __construct() 
    {
        Session::start();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        parent::__construct();
        $upgradeLogic = new UpgradeLogic();
        $upgradeMsg = $upgradeLogic->checkVersion(); //升级包消息        
        $this->assign('upgradeMsg',$upgradeMsg);    
        //用户中心面包屑导航
        $navigate_admin = navigate_admin();
        $this->assign('navigate_admin',$navigate_admin);
        tpversion();

        $this->Auto_Refresh_Access_Token();
   }
    
    /**
     * 初始化操作
     */
    public function _initialize()
    {
        Saas::instance()->checkSso();

        //过滤不需要登陆的行为 
        if (!in_array(ACTION_NAME, array('login', 'vertify'))) {
            if (session('admin_id') > 0) {
                $this->check_priv();//检查管理员菜单操作权限
                $this->admin_id = session('admin_id');
            }else {
                (ACTION_NAME == 'index') && $this->redirect( U('Admin/Admin/login'));
                $this->error('请先登录', U('Admin/Admin/login'), null, 1);
            }
        }
        $this->public_assign();
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

    /**
     * 保存公告变量到 smarty中 比如 导航 
     */
    public function public_assign()
    {
       $tpshop_config = array();

       $tp_config = M('config')->cache(true, TPSHOP_CACHE_TIME, 'config')->select();
       if($tp_config){
           foreach($tp_config as $k => $v)
           {
               $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
           }
       }
       
        if(I('start_time')){
            $begin = I('start_time');
            $end = I('end_time');
        }else{
            $begin = date('Y-m-d', strtotime("-3 month"));//30天前
            $end = date('Y-m-d', strtotime('+1 days'));
        }
        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);
        $this->begin = strtotime($begin);
        $this->end = strtotime($end)+86399;
        $this->page_size = C('PAGESIZE');
       $this->assign('tpshop_config', $tpshop_config);
    }
    
    public function check_priv()
    {
    	$ctl = CONTROLLER_NAME;
    	$act = ACTION_NAME;
        $act_list = session('act_list');
		//无需验证的操作
		$uneed_check = array('login','logout','vertifyHandle','vertify','imageUp','upload','videoUp','delupload','login_task');
    	if($ctl == 'Index' || $act_list == 'all' || $ctl == 'Wx3rd'){
    		//后台首页控制器无需验证,超级管理员无需验证
    		return true;
    	}elseif((request()->isAjax() && $this->verifyAjaxRequest($act)) || strpos($act,'ajax')!== false || in_array($act,$uneed_check)){
    		//部分ajax请求不需要验证权限
    		return true;
    	}else{
            $res = $this->verifyAction();
    		if($res['status'] == -1){
                $this->error($res['msg'],$res['url']);
            };
    	}
    }
    
    public function ajaxReturn($data,$type = 'json'){                        
         exit(json_encode($data));
    }

    /**
     * 要验证的ajax
     * @param $act
     * @return bool
     */
    private function verifyAjaxRequest($act){
        $verifyAjaxArr = ['delGoodsCategory','delGoodsAttribute','delBrand','delGoods'];
        if(request()->isAjax() && in_array($act,$verifyAjaxArr)){
            $res = $this->verifyAction();
            if($res['status'] == -1){
                $this->ajaxReturn($res);
            }else{
                return true;
            };
        }else{
            return true;
        }
    }
    private function verifyAction(){
        if(IS_SAAS){
            return 1;
        }
        $ctl = CONTROLLER_NAME;
        $act = ACTION_NAME;
        $act_list = session('act_list');
        $right = M('system_menu')->where("id", "in", $act_list)->cache(true)->getField('right',true);
        $role_right = '';
        foreach ($right as $val){
            $role_right .= $val.',';
        }
        $role_right = explode(',', $role_right);
        //检查是否拥有此操作权限
        if(!in_array($ctl.'@'.$act, $role_right)){
            return ['status'=>-1,'msg'=>'您没有操作权限['.($ctl.'@'.$act).'],请联系超级管理员分配权限','url'=>U('Admin/Index/welcome')];
        }
    }
}