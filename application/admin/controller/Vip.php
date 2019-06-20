<?php

/**
 * tpshop
 * Vip控制器
 * ----------------------------------------------
 * @author wu
 * Date 2019-3-25
 */
namespace app\admin\controller;

header('content-type: text/html; charset=utf-8');

use think\Db;
use think\Page;
use think\Loader;
use app\common\logic\PerformanceLogic;
use app\admin\logic\UsersLogic;
use think\Cache;

/**
 *  分销
 **/
class Vip extends Base
{
    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 返佣日志
     * @return mixed
     */
    public function commission_log(){
        
        $Ad = M('vip_commission_log');
        $p = input('p/d');

        $type = input('type',0);
        $ctime = urldecode(I('ctime'));
        $user_name = urldecode(I('user_name'));
        $order_sn = I('order_sn');
        $user_type = I('user_type');
        $start_time = I('start_time');
        $end_time = I('end_time');
        // var_dump($type);die;
        $where = [];
        $log_ids = '';
        $where = get_comm_condition($type); //获取条件
        if($user_name){
            $user['nickname'] = ['like', "%$user_name%"];
            $id_list = M('users')->where($user)->column('user_id');

            switch($user_type){
                case 1:
                    $where['user_id'] = [['in',$id_list],['neq',0],'and'];
                    break;
                case 2:
                    $where['to_user_id'] = [['in',$id_list],['neq',0],'and'];
                    break;
                default: break;
            }
        }
        if($ctime){
            $gap = explode(' - ', $ctime);
            $where['create_time'] = [['>= time',strtotime($start_time)],['< time',strtotime($end_time." 23:59:59")],'and'];
        }
        if ($order_sn) {
            $where['order_sn'] = ['like',"%$order_sn%"];
        }

        $res = $Ad->where($where)->order('create_time','desc')->page($p . ',20')->select();

        if ($res) {
            foreach ($res as $val) {
                $id_lists[] = $val['log_id'];
                $list[] = $val;
            }
            $log_ids = implode(',',$id_lists);
            $user_ids = array_column($list, 'user_id');
            $to_user_ids = array_column($list, 'to_user_id');
            $all_user_ids = array_merge($user_ids,$to_user_ids);
            $all_user_name = M('users')->whereIn('user_id',$all_user_ids)->column('user_id,nickname,mobile');
            $avatar = get_avatar($all_user_ids);

            foreach ($list as $key => $value) {
                $list[$key]['user_name'] = $all_user_name[$value['user_id']]['nickname'] ?: $all_user_name[$value['user_id']]['mobile'];
                $list[$key]['to_user_name'] = $all_user_name[$value['to_user_id']]['nickname'] ?: $all_user_name[$value['to_user_id']]['mobile'];
                $list[$key]['user_head_pic'] = $avatar[$value['user_id']];
                $list[$key]['to_user_head_pic'] = $avatar[$value['to_user_id']];
            }
        }

        $is_type = 6;

        $this->assign('is_type',$is_type);
        $this->assign('type',$type);
        $this->assign('user_type',$user_type);
        $this->assign('log_ids',urldecode($log_ids));
        $this->assign('user_name',$user_name);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('ctime',$gap[0].' - '.$gap[1]);
        $this->assign('order_sn',$order_sn);
        $this->assign('list', $list);
        $count = $Ad->where($where)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        // dump($show);die;
        $this->assign('count',$count);
        $this->assign('page', $show);
        return $this->fetch();
    }

    
    /**
     * 销售日志
     * @return mixed
     */
    public function sales_log(){
        $Ad = M('buy_vip');
        $p = input('p/d');

        $type = input('type',0);
        $ctime = urldecode(I('ctime'));
        $user_name = urldecode(I('user_name'));
        $order_sn = I('order_sn');
        $user_type = I('user_type');
        $start_time = I('start_time');
        $end_time = I('end_time');
        // var_dump($type);die;
        $where = [];
        $log_ids = '';
        $where = get_comm_condition($type); //获取条件
        if($user_name){
            $user['nickname'] = ['like', "%$user_name%"];
            $id_list = M('users')->where($user)->column('user_id');

            switch($user_type){
                case 1:
                    $where['user_id'] = [['in',$id_list],['neq',0],'and'];
                    break;
                case 2:
                    $where['to_user_id'] = [['in',$id_list],['neq',0],'and'];
                    break;
                default: break;
            }
        }
        if($ctime){
            $gap = explode(' - ', $ctime);
            $where['ctime'] = [['>= time',strtotime($start_time)],['< time',strtotime($end_time." 23:59:59")],'and'];
        }
        if ($order_sn) {
            $where['order_sn'] = ['like',"%$order_sn%"];
        }
            $where['pay_status'] = 1;

        $res = $Ad->where($where)->order('ctime','desc')->page($p . ',20')->select();

        if ($res) {
            foreach ($res as $val) {
                $id_lists[] = $val['log_id'];
                $list[] = $val;
            }
            $log_ids = implode(',',$id_lists);
            $user_ids = array_column($list, 'user_id');
            $to_user_ids = array_column($list, 'to_user_id');
            $all_user_ids = array_merge($user_ids,$to_user_ids);
            $all_user_name = M('users')->whereIn('user_id',$all_user_ids)->column('user_id,nickname,mobile');
            $avatar = get_avatar($all_user_ids);

            foreach ($list as $key => $value) {
                $list[$key]['user_name'] = $all_user_name[$value['user_id']]['nickname'] ?: $all_user_name[$value['user_id']]['mobile'];
                $list[$key]['to_user_name'] = $all_user_name[$value['to_user_id']]['nickname'] ?: $all_user_name[$value['to_user_id']]['mobile'];
                $list[$key]['user_head_pic'] = $avatar[$value['user_id']];
                $list[$key]['to_user_head_pic'] = $avatar[$value['to_user_id']];
            }
        }

        $is_type = 6;

        $this->assign('is_type',$is_type);
        $this->assign('type',$type);
        $this->assign('user_type',$user_type);
        $this->assign('log_ids',urldecode($log_ids));
        $this->assign('user_name',$user_name);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('ctime',$gap[0].' - '.$gap[1]);
        $this->assign('order_sn',$order_sn);
        $this->assign('list', $list);
        $count = $Ad->where($where)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        // dump($show);die;
        $this->assign('count',$count);
        $this->assign('page', $show);
        return $this->fetch();
    }


    //购买返佣
    public function export_commission_log()
    {
        $log_ids = I('log_ids');
        $type = I('type',0);
        $title_name = array("返佣","级别利润","每台奖励","同级奖励","分红","全球分红");

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr >';
        $strTable .= '<td style="text-align:center;font-size:14px;width:120px;">ID</td>';
        if ($type != 5) {
            $strTable .= '<td style="text-align:center;font-size:14px;" width="*">用户名</td>';
        }

        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">获得返利用户名</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">所得金额</td>';
        if ($type != 5) {
            $strTable .= '<td style="text-align:center;font-size:14px;" width="*">订单编号</td>';
            $strTable .= '<td style="text-align:center;font-size:14px;" width="*">数量</td>';
        }

        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">时间</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">描述</td>';
        $strTable .= '</tr>';

        $condition = array();
        $condition = get_comm_condition($type); //获取条件
        if ($log_ids) {
            $condition['log_id'] = ['in', explode(',',$log_ids)];
        }
        $count = DB::name('vip_commission_log')->where($condition)->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $log_list = M('vip_commission_log')->where($condition)->order('log_id')->limit($start, 5000)->select();
            if (is_array($log_list)) {
                $user_ids = array_column($log_list,'user_id');
                $to_user_ids = array_column($log_list,'to_user_id');
                $n_user_ids = array_merge($user_ids,$to_user_ids);
                $n_user_ids = array_unique($n_user_ids);
                $user_names = Db::name('users')->where('user_id','in',$n_user_ids)->column('user_id,nickname,mobile');
                foreach ($log_list as $k => $val) {
                    $username = $user_names[$val['user_id']]['nickname'] ? $user_names[$val['user_id']]['nickname'] : $user_names[$val['user_id']]['mobile'];
                    $to_username = $user_names[$val['to_user_id']]['nickname'] ? $user_names[$val['to_user_id']]['nickname'] : $user_names[$val['to_user_id']]['mobile'];

                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['log_id'] . '</td>';
                    if ($type != 5) {
                        $strTable .= '<td style="text-align:center;font-size:12px;">' . $username . ' </td>';
                    }
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $to_username . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['money'] . '</td>';
                    if ($type != 5) {
                        $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['order_sn'] . '</td>';
                        $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['num'] . '</td>';
                    }
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . date('Y-m-d H:i', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['desc'] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($log_list);
            }
        }
        $strTable .= '</table>';
        $i = ($i == 1) ? '' : '_'.$i;
        downloadExcel($strTable, $title_name[$type].'明细表' . $i);
        exit();
    }


}