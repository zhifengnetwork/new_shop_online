<?php

/**
 * @author: pc
 * Date: 2019-3-25
 */

namespace app\common\logic;

use think\Model;
use app\common\model\Users;
use app\common\model\AgentPerformance as Performance;
use app\common\model\AgentPerformanceLog as PerformanceLog;
use think\Exception;

/**
 * 业绩类逻辑
 * Class PerformanceLogic
 * @package app\common\logic
 */
class PerformanceLogic extends Model
{
	//业绩
	public function per($order_id)
	{
		$order = M('order')->where('order_id',$order_id)->find();
		if (!$order) {
			return false;
		}
		//判断商品是否有购买业绩

		$goods_idarr =  M('order_goods')->where('order_id',$order_id)->select();

		foreach($goods_idarr as $val){

			if($val['is_achievement'] == 1){

				$Performance = new Performance;
				$Users = new Users;
		
				$goods_num = M('order_goods')->where('order_id',$order_id)->sum('goods_num');
				$price = $order['goods_price'];
				$user_id = $order['user_id'];
				$order_sn = $order['order_sn'];
				
				$user = $Users->where('user_id',$user_id)->value('first_leader');
				$is_per = $Performance->where('user_id',$order['user_id'])->find();
			
				//购买者添加业绩
				if ($is_per) {
					$per[] = array(
						'performance_id'=>$is_per['performance_id'],
						'ind_per'=>$is_per['ind_per']+$price,
						'ind_goods_sum'=>$is_per['ind_goods_sum']+$goods_num,
						'update_time'=>Date('Y-m-d H:i:s')
					);
				} else {
					$per[] = array(
						'user_id'=>$user_id,
						'ind_per'=>$price,
						'ind_goods_sum'=>$goods_num,
						'create_time'=>Date('Y-m-d H:i:s'),
						'update_time'=>Date('Y-m-d H:i:s')
					);
				}
				
				$log = array(
					'user_id'=>$user_id,
					'money'=>$price,
					'goods_num'=>$goods_num,
					'order_sn'=>$order_sn,
					'order_id'=>$order_id,
					'create_time'=>Date('Y-m-d H:i:s'),
					'note'=>'订单编号为 '.$order_sn.' 的业绩'
				);
				//上级
				// $id_list = $Users->where('user_id',$user_id)->value('parents');
				// $id_list = $id_list ? explode(',', $id_list) : array();
				// $new_list = array_reverse(array_filter($id_list));
				$new_list = get_parents_ids($user_id);
				
				foreach ($new_list as $key => $value) {
					$is_team = $Performance->where('user_id',$value)->find();
					
					//团队添加业绩
					if ($is_team) {
						$per[] = array(
							'performance_id'=>$is_team['performance_id'],
							'agent_per'=>$is_team['agent_per']+$price,
							'agent_goods_sum'=>$is_team['agent_goods_sum']+$goods_num,
							'update_time'=>Date('Y-m-d H:i:s')
						);
					} else {
						$per[] = array(
							'user_id'=>$value,
							'agent_per'=>$price,
							'agent_goods_sum'=>$goods_num,
							'create_time'=>Date('Y-m-d H:i:s'),
							'update_time'=>Date('Y-m-d H:i:s')
						);
					}
				}
				
				try {
					$code = 1;
					$Performance->saveAll($per);
				} catch (\Exception $e) {
					$code = 0;
					$msg = $e->getMessage();
				}
				$log = $this->per_log($log);
		
				return $code;
			}

		}

		
		
	}

	//业绩日志
	public function per_log($data)
	{
		$PerformanceLog = new PerformanceLog;

		try {
			$code = 1;
			$PerformanceLog->save($data);
		} catch (\Exception $e) {
			$code = 0;
			$msg = $e->getMessage();
		}

		return $code;
	}
}