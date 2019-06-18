<?php
/**
 * 级别升级逻辑
 * ----------------------------------------------
 * @author wu
 * @date 2019-3-25
 */
namespace app\common\logic;

use think\Model;
use think\Db;

class LevelLogic extends Model
{
    
    public function user_in($leaderId)
    {
        write_log('user_in 函数体 开始');
        write_log('user_in 函数体 $leaderId：'.$leaderId);


        ignore_user_abort(true);
        set_time_limit(0);
        $data = file_get_contents("php://input");

        $frist_leader_info = $this->get_up_lists($leaderId); //获取所有上级的id（包括自己）
//        dump($frist_leader_info);die;
        //最大等级
        $max_level = Db::name('agent_level')->max('level');

        //判断是否有上级,有就升级
        if($frist_leader_info){
            foreach($frist_leader_info as $k=>$v){
                //所有下级id列表
//                $d_info = Db::query("select `user_id` from `tp_parents_cache` where find_in_set($v,parents)");
//                dump($v);die;
                //升级条件所需团队人数
                $res = $this->get_count($v);

                //如果该上级id所有下级的同级人数大于条件所需，执行升级，否则跳出这步循环
                if($res['count'] < $res['team_nums']){
                    continue;
                }else{
                    //开始升级
                    $this->upgrade_agent($v,$max_level,$res['count']);
                }
            }
        }

        write_log('user_in 函数体 结束');

    }
  
    /**
     * 升级条件
     */
    public function condition($agent_level)
    {
        $field = "ind_goods_sum, agent_goods_sum, team_nums";
        $condition = Db::name('agent_level')
                    ->field($field)
                    ->where('level',$agent_level)->find();
        return $condition;
    }
    /**
     * 升级
     * $agent_id    用户id
     * $max_level   最大等级
     * $count       下级中同级别人数
     */
    public function upgrade_agent($agent_id,$max_level,$count){
        ignore_user_abort(true);
        set_time_limit(0);
        $data = file_get_contents("php://input");

        global $list_test;
        //用户等级
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');

        $field = "ind_goods_sum, agent_goods_sum";
        //获取用户业绩
        //所有直推下级总业绩
        $down_nums = $this->get_down_all($agent_id);
        $agent_cond = Db::name('agent_performance')
                    ->field($field)
                    ->where('user_id',$agent_id)->find();
        //团队业绩 = 自己业绩 + 所有下级总业绩
        $agent_cond['agent_goods_sum'] = $agent_cond['ind_goods_sum'] + $agent_cond['agent_goods_sum'];
        //个人业绩 = 自己业绩 + 所有直推下级总业绩
        $agent_cond['ind_goods_sum'] = $agent_cond['ind_goods_sum'] + $down_nums;
        //团队标准（团队同级人数）
        $agent_cond['team_nums'] = $count;
        //升级条件
        $condition = $this->condition($agent_level+1);

        $bool = true;
        foreach($agent_cond as $k=>$v){
            if($v < $condition[$k]){
                $bool = false;
                break;
            }
        }
        if($bool == true){
            if($agent_level != $max_level){
                Db::name('users')->where('user_id',$agent_id)->setInc('distribut_level');  //升级
                Db::name('agent_level_log')->insert(['user_id'=>$agent_id,'level'=>$agent_level+1,'up_time'=>time()]);  //升级记录
                // 看是否还要升级
                $res = $this->get_count($agent_id);//dump($res);
                if($res['count'] >= $res['team_nums']){
                    // 再升1级
                    $this->upgrade_agent($agent_id,$max_level,$res['count']);
                }
            }
        }
    }
    
//    /**
//     * 获取推荐上级id
//     */
//    public function user_info_agent($user_id)
//    {
//        $lists = Db::name('users')->where('user_id',$user_id)->value('parents');
//        $parents = [];
//        if($lists){
//            $parents = array_filter(explode(',',$lists));
//        }
//
//        array_push($parents,$user_id);
//        $parents = array_reverse($parents);
//        // $recUser  = $this->getAllUp($data);
//        // $list = array_column($recUser,'user_id');
//        return $parents;
//    }
    


//    /**
//     * 获取团队下级同级人数
//     */
//    public function get_down_nums($agent_id,$d_info)
//    {
//        //每个人的等级
//        $agent_level = M('users')->where('user_id',$agent_id)->value('distribut_level');
//
//        if($d_info){
//            // $id_array =[];
//            $count = 0;
//            foreach($d_info as $k=>$v){
//                // dump($v);
//                $l = Db::name('users')->where('user_id',$v['user_id'])->value('distribut_level');//dump($l);
//                if($l >= $agent_level){
//                    $count += 1;
//                }
//                // $l = Db::query("select `distribut_level` from `tp_users` where `user_id` = $v["user_id"]");
//                // $lev_list[$k] = array_column($l,'distribut_level');
//                // array_push($id_array ,$v['user_id']);
//            }
//        }
//        return $count;
//    }

    /**
     * 获取所有直推下级业绩
     */
    public function get_down_all($agent_id)
    {
        //获取直推下级id
        $ids = Db::name('users')->where('first_leader',$agent_id)->field('user_id')->select();
        $down_count = 0;
        if($ids){
            foreach($ids as $k=>$v){
                $count = Db::name('agent_performance')->where('user_id',$v['user_id'])->value('ind_goods_sum');
                $down_count = $down_count + $count;
            }
        }
        return $down_count;
    }


    /**
     * 新的 获取上级id列表
     */
    public function get_up_lists($user_id)
    {
        $list = Db::name('parents_cache')->where('user_id',$user_id)->order('sort asc')->field('parents,count')->select();
        $list1 = [];
//        if ($list){
            foreach ($list as $k=>$v){
                array_push($list1,$v['parents']);
            }
//        }
        array_push($list1,$user_id);
        $parents = implode($list1,','); //上级列
        $lists = array_reverse(array_filter(explode(',',$parents))); //上级列表数组

        return $lists;
    }

    /**
     * 新的 获取下级同级人数与升级条件所需团队人数
     */
    public function get_count($agent_id)
    {
        $d_info = Db::query("select `user_id` from `tp_parents_cache` where find_in_set($agent_id,parents)");
        $agent_level = Db::name('users')->where('user_id',$agent_id)->value('distribut_level');
        $team_nums = Db::name('agent_level')->where('level',$agent_level+1)->value('team_nums');//升级条件所需团队人数
        $count = 0;

        if ($d_info){
            foreach($d_info as $k1=>$v1){
                //下级等级
                $le = Db::name('users')->where('user_id',$v1['user_id'])->value('distribut_level');//dump($le);

                if($le >= $agent_level){
                    $count += 1;
                    //如果同级人数达到升级条件规定,跳出到下一步
                    if($count >= $team_nums){
                        break;
                    }
                }

            }
        }
        $res['team_nums'] = $team_nums;
        $res['count'] = $count;

        return $res;
    }

//    /**
//     * 新的 获取下级id列表
//     */
//    public  function get_down_lists($user_id)
//    {
//        $d_lists = Db::query("select `user_id` from `tp_parents_cache` where find_in_set($user_id,parents)");
//        dump($d_lists);
//    }
}