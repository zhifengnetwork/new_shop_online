<?php

namespace app\home\controller;

use app\common\logic\WechatLogic;
use think\Db;

class Weixin
{
    /**
     * 处理接收推送消息
     */
    public function index()
    {
		
		$data = file_get_contents("php://input");
    	if ($data) {

			$re = $this->xmlToArray($data);
			
			// Db::name('wx_temp')->insert(['content'=>json_encode($re)]);
			
			/**
			* 微信扫描分享带参数的二维码
			*/
			if(($re['Event'] == 'SCAN' && $re['EventKey'] == 'sharePoster') || ($re['Event'] == 'subscribe' && $re['EventKey'] == 'qrscene_sharePoster')){
				$this->sharePoster($re);
			}
	    	// $url = SITE_URL.'/mobile/message/index?eventkey='.$re['EventKey'].'&openid='.$re['FromUserName'].'&event='.$re['Event'];
	    	// httpRequest($url);
        }

        $config = Db::name('wx_user')->find();
        if ($config['wait_access'] == 0) {
            ob_clean();
            exit($_GET["echostr"]);
        }
        $logic = new WechatLogic($config);
        $logic->handleMessage();
    }

	/**
	* 微信扫描分享带参数的二维码
	* @author Rock
	* @date 2019/3/29
	*/
	public function sharePoster($data){
		if(!isset($data)){
			return false;
		}
		
		$share_user = Db::query("select `user_id`,`openid` from `tp_users` where `shareposter` like '%".$data['Ticket']."%' limit 1");

		if(!empty($share_user[0])){
			$share_user_openid = $share_user[0]['openid'];
			$share_user = $share_user[0]['user_id'];
			
		}else{
			return false;
		}
		
		// 查询用户是否已经注册
		$user = Db::query("select `user_id`,`first_leader`,`is_employees` from `tp_users` where `openid` = '".$data['FromUserName']."'");
		if(!empty($user[0])){
			// 老用户
			$user = $user[0];
			// 用户是否已有上级
			if($user['first_leader'] > 0){
				return false;
			}else{
				// 用户没有上级且用户不是公司人员绑定上下级
				if ($user['is_employees'] == 1) {
					return false;
				} else {
					if($user['user_id'] == $share_user){
						return false;
					}
					Db::execute("update `tp_users` set `first_leader` = '".$share_user."' where `user_id` = '".$user['user_id']."'");
					if($share_user_openid){
						$wx_content = "会员ID: ".$user['user_id']." 成为了你的下级!";
						$wechat = new \app\common\logic\wechat\WechatUtil();
						$wechat->sendMsg($share_user_openid, 'text', $wx_content);
					}
				}
			}	
		}else{
			// 新用户 - 写入关系缓存表，新用户注册后自动更新
			$cache = Db::table('tp_wxshare_cache')->where('openid',$data['FromUserName'])->find();
			if(!$cache){
				$insql = "insert into `tp_wxshare_cache` (`openid`,`share_user`,`ticket`,`time`) values ('".$data['FromUserName']."','".$share_user."','".$data['Ticket']."','".time()."')";
				
				Db::execute($insql);
			}
		}
		
	}


    public function xmlToArray($xml)
    {
    	$obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		$json = json_encode($obj);
		$arr = json_decode($json, true);  
		return $arr;
    }

    function write_log($content)
	{
		$content = "[" . date('Y-m-d H:i:s') . "]" . $content . "\r\n";
		$dir = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . '/logs';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		$path = $dir . '/' . date('Ymd') . '.txt';
		file_put_contents($path, $content, FILE_APPEND);
	}
    

}