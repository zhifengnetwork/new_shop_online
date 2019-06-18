<?php
/**
*	tpshop
*  ---------------------------------------------------------------------------------------
*	author: pc
*	date: 2019-3-25
**/

namespace app\common\logic;

use think\Model;
use think\Db;
use think\Cache;

/**
 * 返佣类逻辑
 * Class Sales
 * @package app\common\logic
 */
class Sales extends Model
{
	private $user_id; //用户id
	private $order_id;//订单id
	private $goods_id;//商品id

	public function __construct($user_id,$order_id,$goods_id)
	{	
		$this->user_id = $user_id;
		$this->order_id = $order_id;
		$this->goods_id = $goods_id;
	}

	public function sales()
	{
		$user_id = $this->user_id;
		$user = $this->get_user();
		$bonus_products_id = 0;
		
		if (!$user) {
			return array('msg'=>"该用户不存在",'code'=>0);
		}
		
		//获取下级id列表
		$d_info = Db::query("select `user_id`, `first_leader`,`parents` from `tp_users` where 'first_leader' = $user_id or parents like '%,$user_id,%'");
		$d_info = $d_info ? array_column($d_info,'user_id') : array();
		$goods = $this->order();
		if (($goods['code'] == 1) && ($goods['data']['is_team_prize'] == 1)) {
			$bonus_products_id = $goods['data']['goods_id'];
			array_push($d_info,$user_id);
			M('users')->where('user_id','in',$d_info)->where('bonus_products_id','>',0)->update(['bonus_products_id'=>0]);
			$first_leader_id = $user['first_leader'];
			$bool = M('users')->where('user_id',$first_leader_id)->update(['bonus_products_id'=>$goods['data']['goods_id']]);
		}
		
		$user_level = $user['distribut_level'];
		//$parents_id = array();
        $parents_id = get_parents_ids($user_id);
		
		if ($parents_id) {
			//$parents_id = explode(',', $user['parents']);
			$parents_id = array_filter($parents_id);  //去除0
		
			if ($bonus_products_id > 0) {
				M('users')->where('user_id','in',$parents_id)->where('user_id','neq',$first_leader_id)->where('bonus_products_id','>',0)->update(['bonus_products_id'=>0]);
			}
		}
		
		$this->cash_unlock($parents_id);	//提现解锁
		// $is_repeat = $this->repeat_buy($this->user_id,$this->goods_id);
		
		$is_repeat = false;
		if($user_level > 0){
			$is_repeat = true;
		}

		//是否重复购买
		if ($is_repeat) {
			$reward = $this->repeat_reward($parents_id,$user_level,$is_repeat);
		} else {
			$reward = $this->reward($parents_id,$user_level,$is_repeat);
		}
		
		$this->team_bonus($parents_id);	//团队奖励
		
		return $reward;
	}

	// //是否重复购买
	// public function repeat_buy($user_id,$goods_id)
	// {
	// 	$is_repeat = false;
	// 	$order_num = 0;
	// 	// $order_num = Db::name('order_goods')->alias('goods')
	// 	// 			 ->distinct(true)
	// 	// 			 ->join('order order','goods.order_id = order.order_id')
	// 	// 			 ->where(['goods.goods_id'=>$this->goods_id,'order.user_id'=>$this->user_id])
	// 	// 			 ->count();
	// 	$order_goods = M('order_goods')->where(['goods_id'=>$goods_id])->select();

	// 	if ($order_goods) {
	// 		$ids = array_column($order_goods,'order_id');
	// 		$order_num = M('order')->where('user_id',$user_id)->where('order_id','in',$ids)->where('pay_status',1)->count();
	// 	}
		
	// 	if ($order_num > 1) {
	// 		$is_repeat = true;
	// 	}
	// 	return $is_repeat;
	// }

	//第一次购买奖励
	public function reward($parents_id,$user_level,$is_repeat)
	{
		$order = $this->order();
		
		if ($order['code'] != 1) {
			return $order;
		}

		$order = $order['data'];
		
		$parents_id = array_reverse($parents_id);	//按原数组倒序排列
		$all_user = $this->all_user($parents_id);	//获取所有用户信息
		
		$comm = $this->get_goods_prize($is_repeat,$this->goods_id);
		$basic_reward = $comm['basic'];  //直推奖励
		$poor_prize = $comm['poor_prize'];//极差奖励
		$first_layer = $comm['first_layer'];//同级一层奖励
		$second_layer = $comm['second_layer'];//同级二层奖励
		
		if(is_array($basic_reward)){
			ksort($basic_reward );	//按键值升序排列
		}
		if (is_array($poor_prize)) {
			ksort($poor_prize);
		}
		
		$distribut_type = 0;
		$layer = 0;
		$msg = "";
		$is_prize = false;
		$total_money = 0;
		$status = 1;
		$data = array();
		$result = array('code'=>0);

		//专员等级以上购买返佣
		if ($user_level > 0) {
			$my_prize = floatval($comm['preferential'][$user_level]);
			if ($my_prize > 0) {
				$user_id = $this->user_id;
				$total_money = $my_prize;
				$user = M('users')->where('user_id',$user_id)->field('user_money,distribut_money')->find();
				$my_user_money = $my_prize + $user['user_money'];
				$my_distribut_money = $my_prize + $user['distribut_money'];
				$bool = M('users')->where('user_id',$user_id)->update(['user_money'=>$my_user_money,'distribut_money'=>$my_distribut_money]);
				$result['code'] = 0;
				$status = 0;
				if ($bool) {
					$result['code'] = 1;
					$status = 1;
				}
				
				$msg = "自购优惠 ".$my_prize."（元），商品：".$order['goods_num']." 件";

				$data[] = array(
					'user_id' => $this->user_id,
					'to_user_id' => $this->user_id,
					'money' => $my_prize,
					'order_sn' => $order['order_sn'],
					'order_id' => $this->order_id,
					'goods_id' => $this->goods_id,
					'num' => $order['goods_num'],
					'type' => 1,
					'distribut_type' => 1,
					'status' => $status,
					'create_time' => time(),
					'desc' => $msg
				);
			}
		}
		
		foreach ($all_user as $key => $value) {
			$money = 0;
			$user_money = 0;
			// //没有等级没有奖励
			// if ($value['distribut_level'] <= 0) {
			// 	continue;
			// }
			//账号冻结了没有奖励
			if ($value['is_lock'] == 1) {
				continue;
			}
			// //不是分销商不奖励
			// if ($value['is_distribut'] != 1) {
			// 	continue;
			// }
			
			//等级比下级低没有奖励
			if ($user_level > $value['distribut_level']) {
				continue;
			}
			
			//平级奖
			if ($user_level == $value['distribut_level']) {
				$layer ++;
				//超过设定层数没有奖励
				if ($layer > 2) {
					continue;
				}
				//直推奖，直推奖已奖励的不再奖励
				if (!$is_prize) {
					$money = $basic_reward ? $basic_reward[$value['distribut_level']] : 0;
					$is_prize = true;
					$msg = "直推奖 ";
					$distribut_type = 2;
					$msg = $msg.$money."（元），商品：".$order['goods_num']." 件";

					if ($money > 0) {
						$user_money += $money;
						
						$data[] = array(
							'user_id' => $this->user_id,
							'to_user_id' => $value['user_id'],
							'money' => $money,
							'order_sn' => $order['order_sn'],
							'order_id' => $this->order_id,
							'goods_id' => $this->goods_id,
							'num' => $order['goods_num'],
							'type' => 1,
							'distribut_type' => $distribut_type,
							'status' => $status,
							'create_time' => time(),
							'desc' => $msg
						);
					}
				}
				$msg = "同级奖 ";
				$distribut_type = 4;
				$money = 0;
				//同级奖
				switch($layer){
					case 1:
						$money += $first_layer[$value['distribut_level']] * $order['goods_num'];
						break;
					case 2:
						$money += $second_layer[$value['distribut_level']] * $order['goods_num'];
						break;
					default:
						break;
				}
				
				$msg = $msg.$money."（元），商品：".$order['goods_num']." 件";
				if ($money > 0) {
					$user_money += $money;
					
					$data[] = array(
						'user_id' => $this->user_id,
						'to_user_id' => $value['user_id'],
						'money' => $money,
						'order_sn' => $order['order_sn'],
						'order_id' => $this->order_id,
						'goods_id' => $this->goods_id,
						'num' => $order['goods_num'],
						'type' => 1,
						'distribut_type' => $distribut_type,
						'status' => $status,
						'create_time' => time(),
						'desc' => $msg
					);
				}
			}
			//极差奖
			if ($user_level < $value['distribut_level']) {
				$layer = 0;

				//直推奖，直推奖已奖励的不再奖励
				if (!$is_prize) {
					$money = $basic_reward ? $basic_reward[$value['distribut_level']] : 0;
					$is_prize = true;
					$msg = "直推奖 ";
					$msg = $msg.$money."（元），商品：".$order['goods_num']." 件";
					$distribut_type = 2;

					if ($money > 0) {
						$user_money += $money;
						$data[] = array(
							'user_id' => $this->user_id,
							'to_user_id' => $value['user_id'],
							'money' => $money,
							'order_sn' => $order['order_sn'],
							'order_id' => $this->order_id,
							'goods_id' => $this->goods_id,
							'num' => $order['goods_num'],
							'type' => 1,
							'distribut_type' => $distribut_type,
							'status' => $status,
							'create_time' => time(),
							'desc' => $msg
						);
					}
				}
				$msg = "极差奖 ";
				$distribut_type = 3;
				$money = 0;
				reset($poor_prize);	//重置数组指针
				
				//计算极差奖金
				while(list($k1,$v1) = each($poor_prize)){
					if ($user_level >= $k1) {
						continue;
					}
					if ($k1 <= $value['distribut_level']) {
						$v1 = $v1 ? $v1 : 0;
						$money += $v1 * $order['goods_num'];
						continue;
					}
					break;
				}
				$user_level = $value['distribut_level'];
				$msg = $msg.$money."（元），商品：".$order['goods_num']." 件";
				if ($money > 0) {
					$user_money += $money;

					$data[] = array(
						'user_id' => $this->user_id,
						'to_user_id' => $value['user_id'],
						'money' => $money,
						'order_sn' => $order['order_sn'],
						'order_id' => $this->order_id,
						'goods_id' => $this->goods_id,
						'num' => $order['goods_num'],
						'type' => 1,
						'distribut_type' => $distribut_type,
						'status' => $status,
						'create_time' => time(),
						'desc' => $msg
					);
				}
			}
			if (!$user_money) {
				continue;
			}
			
			$total_money += $user_money;
			$user_money += $value['user_money'];
			$distribut_money = $user_money+$value['distribut_money'];
			
			$bool = M('users')->where('user_id',$value['user_id'])->update(['user_money'=>$user_money,'distribut_money'=>$distribut_money]);
			
			$result['code'] = 0;
			$status = 0;
			if ($bool) {
				$result['code'] = 1;
			}
		}
		
		if ($data) {
			$divide = array(
				'order_id'=>$this->order_id,
				'user_id'=>$this->user_id,
				'status'=>1,
				'goods_id'=>$this->goods_id,
				'money'=>$total_money,
				'add_time'=>Date('Y-m-d H:i:s')
			);

			$this->writeLog($data,$divide);

			if (!$bool) {
				M('distrbut_commission_log')->where(['user_id'=>$this->user_id,'order_id'=>$this->order_id,'goods_id'=>$this->goods_id])->update(['status'=>0]);
				M('order_divide')->where(['user_id'=>$this->user_id,'order_id'=>$this->order_id,'goods_id'=>$this->goods_id])->update(['status'=>0]);
			}
		}

		return $result;
	}

	//重复购买奖励
	public function repeat_reward($parents_id,$user_level,$is_repeat)
	{
		$order = $this->order();
		
		if ($order['code'] != 1) {
			return $order;
		}

		$order = $order['data'];
		
		$parents_id = array_reverse($parents_id);	//按原数组倒序排列
		$all_user = $this->all_user($parents_id);	//获取所有用户信息
		
		$comm = $this->get_goods_prize($is_repeat,$this->goods_id);
		$basic_reward = $comm['basic'];  //直推奖励
		$poor_prize = $comm['poor_prize'];//极差奖励
		$first_layer = $comm['first_layer'];//同级一层奖励
		$second_layer = $comm['second_layer'];//同级二层奖励
		
		if(is_array($basic_reward)){
			ksort($basic_reward );	//按键值升序排列
		}
		if (is_array($poor_prize)) {
			ksort($poor_prize);
		}
		
		$distribut_type = 0;
		$layer = 0;
		$msg = "";
		$is_prize = false;
		$total_money = 0;
		$data = array();
		$result = array('code' => 0);
		
		//专员等级以上购买返佣
		if ($user_level > 0) {
			$my_prize = floatval($comm['preferential'][$user_level]);
			if ($my_prize > 0) {
				$user_id = $this->user_id;
				$total_money = $my_prize;
				$user = M('users')->where('user_id',$user_id)->field('user_money,distribut_money')->find();
				$my_user_money = $my_prize + $user['user_money'];
				$my_distribut_money = $my_prize + $user['distribut_money'];
				$bool = M('users')->where('user_id',$user_id)->update(['user_money'=>$my_user_money,'distribut_money'=>$my_distribut_money]);
				$result['code'] = 0;
				$status = 0;
				if ($bool) {
					$result['code'] = 1;
					$status = 1;
				}
				
				$msg = "自购优惠 ".$my_prize."（元），商品：".$order['goods_num']." 件";

				$data[] = array(
					'user_id' => $this->user_id,
					'to_user_id' => $this->user_id,
					'money' => $my_prize,
					'order_sn' => $order['order_sn'],
					'order_id' => $this->order_id,
					'goods_id' => $this->goods_id,
					'num' => $order['goods_num'],
					'type' => 2,
					'distribut_type' => 1,
					'status' => $status,
					'create_time' => time(),
					'desc' => $msg
				);
			}
		}
		
		//第二次购买上级返佣
		foreach ($all_user as $key => $value) {
			$money = 0;
			$user_money = 0;
			// //没有等级没有奖励
			// if ($value['distribut_level'] <= 0) {
			// 	continue;
			// }
			//账号冻结了没有奖励
			if ($value['is_lock'] == 1) {
				continue;
			}
			// //不是分销商不奖励
			// if ($value['is_distribut'] != 1) {
			// 	continue;
			// }
			
			//等级比下级低没有奖励
			if ($user_level > $value['distribut_level']) {
				continue;
			}
			
			//平级奖
			if ($user_level == $value['distribut_level']) {
				$layer ++;
				//超过设定层数没有奖励
				if ($layer > 2) {
					continue;
				}
				//直推奖，直推奖已奖励的不再奖励
				if (!$is_prize) {
					$money = $basic_reward ? $basic_reward[$value['distribut_level']] : 0;
					$is_prize = true;
					$msg = "自购直推奖 ";
					$distribut_type = 2;

					$msg = $msg.$money."（元），商品：".$order['goods_num']." 件";

					if ($money > 0) {
						$user_money += $money;
						
						$data[] = array(
							'user_id' => $this->user_id,
							'to_user_id' => $value['user_id'],
							'money' => $money,
							'order_sn' => $order['order_sn'],
							'order_id' => $this->order_id,
							'goods_id' => $this->goods_id,
							'num' => $order['goods_num'],
							'type' => 2,
							'distribut_type' => $distribut_type,
							'status' => $status,
							'create_time' => time(),
							'desc' => $msg
						);
					}
				} 
				$msg = "自购同级奖 ";
				$distribut_type = 4;
				$money = 0;
				//同级奖
				switch($layer){
					case 1:
						$money += $first_layer[$user_level] * $order['goods_num'];
						break;
					case 2:
						$money += $second_layer[$user_level] * $order['goods_num'];
						break;
					default:
						break;
				}
				
				$msg = $msg.$money."（元），商品：".$order['goods_num']." 件";

				if ($money > 0) {
					$user_money += $money;
					
					$data[] = array(
						'user_id' => $this->user_id,
						'to_user_id' => $value['user_id'],
						'money' => $money,
						'order_sn' => $order['order_sn'],
						'order_id' => $this->order_id,
						'goods_id' => $this->goods_id,
						'num' => $order['goods_num'],
						'type' => 2,
						'distribut_type' => $distribut_type,
						'status' => $status,
						'create_time' => time(),
						'desc' => $msg
					);
				}
			}
			//极差奖
			if ($user_level < $value['distribut_level']) {
				$layer = 0;

				//直推奖，直推奖已奖励的不再奖励
				if (!$is_prize) {
					$money = $basic_reward ? $basic_reward[$value['distribut_level']] : 0;
					$is_prize = true;
					$msg = "自购直推奖 ";
					$distribut_type = 2;
					$msg = $msg.$money."（元），商品：".$order['goods_num']." 件";

					if ($money > 0) {
						$user_money += $money;
						$data[] = array(
							'user_id' => $this->user_id,
							'to_user_id' => $value['user_id'],
							'money' => $money,
							'order_sn' => $order['order_sn'],
							'order_id' => $this->order_id,
							'goods_id' => $this->goods_id,
							'num' => $order['goods_num'],
							'type' => 2,
							'distribut_type' => $distribut_type,
							'status' => $status,
							'create_time' => time(),
							'desc' => $msg
						);
					}
				}
				$msg = "自购极差奖 ";
				$distribut_type = 3;
				$money = 0;
				reset($poor_prize);	//重置数组指针
				
				//计算极差奖金
				while(list($k1,$v1) = each($poor_prize)){
					if ($user_level >= $k1) {
						continue;
					}
					if ($k1 <= $value['distribut_level']) {
						$v1 = $v1 ? $v1 : 0;
						$money += $v1 * $order['goods_num'];
						continue;
					}
					break;
				}
				
				$user_level = $value['distribut_level'];
				$msg = $msg.$money."（元），商品：".$order['goods_num']." 件";

				if ($money > 0) {
					$user_money += $money;
					$data[] = array(
						'user_id' => $this->user_id,
						'to_user_id' => $value['user_id'],
						'money' => $money,
						'order_sn' => $order['order_sn'],
						'order_id' => $this->order_id,
						'goods_id' => $this->goods_id,
						'num' => $order['goods_num'],
						'type' => 2,
						'distribut_type' => $distribut_type,
						'status' => $status,
						'create_time' => time(),
						'desc' => $msg
					);
				}
			}
			if (!$user_money) {
				continue;
			}
			
			$total_money += $user_money;
			$user_money += $value['user_money'];
			$distribut_money = $user_money+$value['distribut_money'];
			
			$bool = M('users')->where('user_id',$value['user_id'])->update(['user_money'=>$user_money,'distribut_money'=>$distribut_money]);
			
			$result['code'] = 0;
			$status = 0;
			if ($bool) {
				$result['code'] = 1;
				$status = 1;
			}
		}

		if ($data) {
			$divide = array(
				'order_id'=>$this->order_id,
				'user_id'=>$this->user_id,
				'status'=>1,
				'goods_id'=>$this->goods_id,
				'money'=>$total_money,
				'add_time'=>Date('Y-m-d H:i:s')
			);

			$this->writeLog($data,$divide);

			if (!$bool) {
				M('distrbut_commission_log')->where(['user_id'=>$this->user_id,'order_id'=>$this->order_id,'goods_id'=>$this->goods_id])->update(['status'=>0]);
				M('order_divide')->where(['user_id'=>$this->user_id,'order_id'=>$this->order_id,'goods_id'=>$this->goods_id])->update(['status'=>0]);
			}
		}
		
		return $result;
	}

	//团队奖励
	public function team_bonus($parents_id)
	{
		$order = $this->order();
		
		if ($order['code'] != 1) {
			return $order;
		}
		$order = $order['data'];

		$leader = M('users')->whereIn('user_id',$parents_id)->where('bonus_products_id','>',0)->find();
		if (!$leader) {
			return ['code'=>0,'msg'=>"该用户没有获得团队奖励的上级"];
		}
		
		//获取奖励百分比
		$goods = $this->goods($leader['bonus_products_id']);
		if ($goods['code'] == 0) {
			return $goods;
		}
		$goods = $goods['goods'];
		
		$money = $order['goods_price'] * $order['goods_num'] * ($goods['prize_ratio'] / 100);
		
		if(!$money){
			return ['code'=>0];
		}

		$money = round($money,2); //四舍五入保留两位小数
		$result['code'] = 0;
		$user_money = $money + $leader['user_money'];
		$distribut_money = $money + $leader['distribut_money'];
		$msg = "团队分红 ". $money . "（元），商品：".$order['goods_num']." 件，比率：".$goods['prize_ratio']."%";

		$bool = M('users')->where('user_id',$first_leader)->update(['user_money'=>$user_money,'distribut_money'=>$distribut_money]);

		$data[] = array(
			'user_id' => $this->user_id,
			'to_user_id' => $leader['user_id'],
			'money' => $money,
			'order_sn' => $order['order_sn'],
			'order_id' => $this->order_id,
			'goods_id' => $this->goods_id,
			'num' => $order['goods_num'],
			'type' => 3,
			'create_time' => time(),
			'desc' => $msg
		);

		$divide = array(
			'order_id'=>$this->order_id,
			'user_id'=>$this->user_id,
			'status'=>1,
			'goods_id'=>$this->goods_id,
			'money'=>$money,
			'add_time'=>Date('Y-m-d H:i:s')
		);

		$this->writeLog($data,$divide);
		if ($bool) {
			$result['code'] = 1;
		}

		return $result;
	}

	//获取用户信息
	public function get_user()
	{
		$user = Db::name('users')->where('user_id',$this->user_id)->find();
		return $user;
	}
	
	//获取返佣配置信息
	public function get_goods_prize($is_repeat,$goods_id)
	{
		$goods_prize = M('goods')->where('goods_id',$goods_id)->value('goods_prize');
		$ids = json_decode($goods_prize,true);
		
		if($is_repeat){
			$fields = 'level,preferential,self_buying as basic,self_poor_prize as poor_prize,self_reword as first_layer,self_reword2 as second_layer';
		} else {
			$fields = 'level,reward as basic,poor_prize,same_reword as first_layer,same_reword2 as second_layer';
		}
		$comm = M('goods_commission')->where('id','in',$ids)->column($fields);
		$result['basic'] = array();
		$result['poor_prize'] = array();
		$result['first_layer'] = array();
		$result['second_layer'] = array();
		$result['preferential'] = array();
		
		if($comm){
			foreach($comm as $key => $value){
				$result['basic'][$key] = $value['basic'];
				$result['poor_prize'][$key] = $value['poor_prize'];
				$result['first_layer'][$key] = $value['first_layer'];
				$result['second_layer'][$key] = $value['second_layer'];
				$result['preferential'][$key] = $is_repeat ? $value['preferential'] : 0;
			}
		}
		
		return $result;
	}

	//获取所有用户信息
	public function all_user($parents_id)
	{
		$all = M('users')->where('user_id','in',$parents_id)->column('user_id,first_leader,distribut_level,is_lock,user_money,distribut_money,is_distribut');
		$result = array();

		foreach ($parents_id as $key => $value) {
			array_push($result, $all[$value]);
		}
		return $result;
	}

	//订单信息
	public function order()
	{
		$order_sn = M('order')->where('order_id',$this->order_id)->value('order_sn');
		$order_goods = M('order_goods')
						->where('order_id',$this->order_id)
						->where('goods_id',$this->goods_id)
						->find();
		
		if (!$order_goods) {
			return array('msg'=>"没有该商品的订单信息",'code'=>0);
		}

		$order_goods['order_sn'] = $order_sn;

		return array('data'=>$order_goods,'code'=>1);
	}

	//商品信息
	public function goods($goods_id)
	{
		$goods = M('goods')->where('goods_id',$goods_id)->field('goods_id,shop_price,is_team_prize,prize_ratio')->find();

		if (!$goods) {
			return array('msg'=>"没有该商品的信息",'code'=>0);
		}

		return array('goods'=>$goods,'code'=>1);
	}

	//记录日志
	public function writeLog($data,$divide)
	{
		$bool = M('distrbut_commission_log')->insertAll($data);

		if($divide){
			$order_divide = M('order_divide')->where('user_id',$divide['user_id'])->where('order_id',$divide['order_id'])->where('goods_id',$divide['goods_id'])->find();
			if (!$order_divide) {
				//分钱记录日志
				M('order_divide')->insert($divide);
			} else {
				M('order_divide')->where('user_id',$divide['user_id'])->where('order_id',$divide['order_id'])->where('goods_id',$divide['goods_id'])->setInc('money',$divide['money']);
			}
		}
		
		return $bool;
	}

	/**
	 * 提现解锁
	 */
	public function cash_unlock($parents_id)
	{
		if (!$parents_id) {
			return false;
		}

		$is_cash = tpCache('cash.goods_id');
		
		if (intval($is_cash) == $this->goods_id) {
			M('users')->where('user_id','in',$parents_id)->update(['is_cash'=>1]);
		}
	}
}