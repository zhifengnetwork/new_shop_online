<?php

namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use app\common\model\UserLabel;
use think\AjaxPage;
use think\console\command\make\Model;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use app\common\logic\MessageTemplateLogic;
use app\common\logic\MessageFactory;
use app\common\model\Withdrawals;
use app\common\model\Users;
use app\common\model\UserInvite;
use think\Loader;

class User extends Base
{
    public function index()
    {
		
		
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function ajaxindex()
    {
        // 搜索条件
        $condition = array();
        $nickname = I('nickname');
        $account = I('account');
        $id = intval(I('id'));
        $distribut_level = I('dist_level');
        if ($distribut_level) {
            $level = M('agent_level')->where('level_name', 'like', "%$distribut_level%")->column('level');
            $level ? $condition['distribut_level'] = ['in',$level] : $condition['user_id'] = ['<',0];
            $this->assign('dist_level',$distribut_level);
        }
        $account ? $condition['email|mobile'] = ['like', "%$account%"] : false;
        $nickname ? $condition['nickname'] = ['like', "%$nickname%"] : false;
        $id ? $condition['user_id'] = $id : false;
        
        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by') . ' ' . I('sort');

        $usersModel = new Users();
        $count = $usersModel->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        $userList = $usersModel->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();
		
        $user_id_arr = get_arr_column($userList, 'user_id');
        if (!empty($user_id_arr)) {
			// dump($user_id_arr);exit;
            // $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(" . implode(',', $user_id_arr) . ")  group by first_leader");
            // $first_leader = convert_arr_key($first_leader, 'first_leader');

            // $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(" . implode(',', $user_id_arr) . ")  group by second_leader");
            // $second_leader = convert_arr_key($second_leader, 'second_leader');

            // $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(" . implode(',', $user_id_arr) . ")  group by third_leader");
            // $third_leader = convert_arr_key($third_leader, 'third_leader');
			
			foreach($user_id_arr as $v){
                $last_cout[$v]['direct'] = Db::name('users')->where('first_leader', $v)->count();
                $last_cout[$v]['team'] = Db::query("select count(*) as count from `__PREFIX__parents_cache` where find_in_set('$v', parents)")[0]['count'];
				// $last_cout[$v]['team'] = Db::query("select count(*) as count from `__PREFIX__users` where find_in_set('$v', parents)")[0]['count'];
			}
			
			$this->assign('last_cout', $last_cout);
        }

        $agnet_name = M('agent_level')->column('level,level_name');
        $this->assign('agnet_name', $agnet_name);
        
        if ($id) {
            $this->assign('id',$id);
        }
        
        $show = $Page->show();
        $this->assign('userList', $userList);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    #会员等级统计
    public function level_count()
    {
        $level = M('agent_level')->column('level,level_name');
        
        ksort($level); //键值升序排列
        $user = M('users')->column('user_id,distribut_level');

        $count = count($user);
        $count_level = array_count_values($user); //统计数组中值出现的次数
        $count_list = array();

        if ($level) {
            foreach($level as $k => $v){
                $num = $count_level[$k] ?: 0;
                $count_list[] = array('level'=>$k,'level_name'=>$v,'num'=>$num);
            }
        }

        $this->assign('count',$count);
        $this->assign('count_list',$count_list);
        return $this->fetch();
    }
	
	# 直推下级列表
	public function direct_list(){
        
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        $list = Db::query("select * from `tp_users` where `first_leader` > 0 and `first_leader` = $id");
        $count = count($list);
        $agnet_name = $this->all_level();

        $this->assign('agnet_name',$agnet_name);

        $this->assign('list',$list);
        $this->assign('count',$count);
		return $this->fetch();
    }

    # 团队列表
	public function team_list(){
        
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $all_lower = get_all_lower($id);
        $all_lower = implode(',',$all_lower);
        $list = array();
        if ($all_lower) {
            $list = Db::query("select * from `tp_users` where `first_leader` > 0 and `user_id` in ($all_lower)");
        } 
        
        // $list = Db::query("select * from `tp_users` where `first_leader` > 0 and find_in_set('$id',parents)");
        
        $count = count($list);
        $agnet_name = $this->all_level();

        $this->assign('agnet_name',$agnet_name);
        $this->assign('list',$list);
        $this->assign('count',$count);
		return $this->fetch();
    }
    
    # 获取等级
    public function all_level()
    {
        $agent_level = M('agent_level')->field('level,level_name')->select();
        if($agent_level){
            foreach($agent_level as $v){
                $agnet_name[$v['level']] = $v['level_name'];
            }
        }

        return $agnet_name;
    }

    /**
     * 会员详细信息查看
     */
    public function detail()
    {
        $uid = I('get.id');
        $user = D('users')->where(array('user_id' => $uid))->find();
        if (!$user) {
            exit($this->error('会员不存在'));
        }
        if (IS_POST) {
            //  会员信息编辑
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password != '' && $password != $password2) {
                exit($this->error('两次输入密码不同'));
            }
            if ($password == '' && $password2 == '') {
                unset($_POST['password']);
            } else {
                $_POST['password'] = encrypt($_POST['password']);
            }

            if (!empty($_POST['email'])) {
                $email = trim($_POST['email']);
                $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }

            if (!empty($_POST['mobile'])) {
                $mobile = trim($_POST['mobile']);
                $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }
            $fleader = $_POST['fleader'] ? intval($_POST['fleader']) : 0;
            
            $post_data = $_POST;

            $u_info = Db::name('users')->field('user_id,first_leader')->find($uid);


           
//            if($fleader > 0 && $fleader != $u_info['first_leader']){
            if($fleader != $u_info['first_leader']){
                $post_data['first_leader'] = $_POST['fleader'];
                $post_data['second_leader'] = 0;
                $post_data['third_leader'] = 0;
                $post_data['parents'] = '';
                Db::name('users')->where('user_id',$uid)->update(['parents_cache'=>0]);
                Db::query("delete from `tp_parents_cache` where `user_id`=$uid or find_in_set($uid,`parents`)");
            }

            
            unset($post_data['fleader']);

            // $userLevel = D('user_level')->where('level_id=' . $_POST['level'])->value('discount');
            // $_POST['discount'] = $userLevel / 100;
            $row = M('users')->where(array('user_id' => $uid))->save($post_data);
            if ($row) {
                exit($this->success('修改成功','User/index'));
            }
            exit($this->error('未作内容修改或修改失败'));
        }

        $user['first_lower'] = M('users')->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = M('users')->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower'] = M('users')->where("third_leader = {$user['user_id']}")->count();

        $this->assign('user', $user);
        return $this->fetch();
    }

    public function add_user()
    {
        if (IS_POST) {
            $data = I('post.');
            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if ($res['status'] == 1) {
                $this->success('添加成功', U('User/index'));
                exit;
            } else {
                $this->error('添加失败,' . $res['msg'], U('User/index'));
            }
        }
        return $this->fetch();
    }

    public function export_user()
    {
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '</tr>';
        $user_ids = I('user_ids');
        if ($user_ids) {
            $condition['user_id'] = ['in', $user_ids];
        } else {
            $mobile = I('mobile');
            $email = I('email');
            $mobile ? $condition['mobile'] = $mobile : false;
            $email ? $condition['email'] = $email : false;
        };
        $count = DB::name('users')->where($condition)->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $userList = M('users')->where($condition)->order('user_id')->limit($start, 5000)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['level'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['email'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['reg_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['last_login']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_points'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['total_amount'] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'users_' . $i);
        exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address()
    {
        $uid = I('get.id');
        $lists = D('user_address')->where(array('user_id' => $uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList', $regionList);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete()
    {
        $uid = I('get.id');

        //先删除ouath_users表的关联数据
        M('OuathUsers')->where(array('user_id' => $uid))->delete();
        $row = M('users')->where(array('user_id' => $uid))->delete();
        if ($row) {
            $this->success('成功删除会员');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 删除会员
     */
    public function ajax_delete()
    {
        $uid = I('id');
        if ($uid) {
            $row = M('users')->where(array('user_id' => $uid))->delete();
            if ($row !== false) {
                //把关联的第三方账号删除
                M('OauthUsers')->where(array('user_id' => $uid))->delete();
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            } else {
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log()
    {
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
//        $count = M('account_log')->where(array('user_id' => $user_id))->count();
        $count = M('change_balance_log')->where(array('user_id' => $user_id))->count();
        $page = new Page($count);
//        $lists = M('account_log')->where(array('user_id' => $user_id))->order('change_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $lists = M('change_balance_log')->where(array('user_id' => $user_id))->order('change_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('user_id', $user_id);
        $this->assign('page', $page->show());
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit()
    {
        $user_id = I('user_id');
        if (!$user_id > 0) {
            $this->ajaxReturn(['status' => 0, 'msg' => "参数有误"]);
        }
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock')->where('user_id', $user_id)->find();
        if (IS_POST) {
            $desc = I('post.desc');
            if (!$desc) {
                $this->ajaxReturn(['status' => 0, 'msg' => "请填写操作说明"]);
            }
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money = $m_op_type ? $user_money : 0 - $user_money;
            if (($user['user_money'] + $user_money) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
            }
            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points = $p_op_type ? $pay_points : 0 - $pay_points;
            if (($pay_points + $user['pay_points']) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户剩余积分不足！！']);
            }
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
            if ($revision_frozen_money != 0) {    //有加减冻结资金的时候
                $frozen_money = $f_op_type ? $revision_frozen_money : 0 - $revision_frozen_money;
                $frozen_money = $user['frozen_money'] + $frozen_money;    //计算用户被冻结的资金
                if ($f_op_type == 1 && $revision_frozen_money > $user['user_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
                }
                if ($f_op_type == 0 && $revision_frozen_money > $user['frozen_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "冻结的资金不足！！"]);
                }
                $user_money = $f_op_type ? 0 - $revision_frozen_money : $revision_frozen_money;    //计算用户剩余资金
                M('users')->where('user_id', $user_id)->update(['frozen_money' => $frozen_money]);
            }
            if (accountLog($user_id, $user_money, $pay_points, $desc, 0)) {
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/User/account_log", array('id' => $user_id))]);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => "操作失败"]);
            }
            exit;
        }
        $this->assign('user_id', $user_id);
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function recharge()
    {
        $timegap = urldecode(I('timegap'));
        $nickname = I('nickname');
        $map = array();
        if ($timegap) {
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['ctime'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        if ($nickname) {
            $map['nickname'] = array('like', "%$nickname%");
            $this->assign('nickname', $nickname);
        }
        $count = M('recharge')->where($map)->count();
        $page = new Page($count);
        $lists = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        if ($lists) {
            $user_ids = array_column($lists, 'user_id');
            $avatar = get_avatar($user_ids);

            foreach ($lists as $key => $value) {
                $lists[$key]['head_pic'] = $avatar[$value['user_id']];
            }
        }
        
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function level()
    {
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $level_id = I('get.level_id');
        if ($level_id) {
            $level_info = D('user_level')->where('level_id=' . $level_id)->find();
            $this->assign('info', $level_info);
        }
        return $this->fetch();
    }

    public function levelList()
    {
        $Ad = M('user_level');
        $p = $this->request->param('p');
        $res = $Ad->order('level_id')->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->where('level_id=' . $data['level_id'])->save($data);
                if ($r !== false) {
                    $discount = $data['discount'] / 100;
                    D('users')->where(['level' => $data['level_id']])->save(['discount' => $discount]);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('user_level')->where('level_id=' . $data['level_id'])->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if ($search_key == '') {
            $this->ajaxReturn(['status' => -1, 'msg' => '请按要求输入！！']);
        }
        $list = M('users')->where(['nickname' => ['like', "%$search_key%"]])->select();
        if ($list) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'result' => $list]);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '未查询到相应数据！！']);
    }

    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
        $list = M('users')->where("first_leader = 1")->select();
        return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送系统通知消息
     * @author yhj
     * @time  2018/07/10
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $message_content = I('post.text', '');//内容
        $message_title = I('post.title', '');//标题
        $message_type = I('post.type', 0);//个体or全体
        $users = I('post.user/a');//个体id
        $message_val = ['name' => ''];
        $send_data = array(
            'message_title' => $message_title,
            'message_content' => $message_content,
            'message_type' => $message_type,
            'users' => $users,
            'type' => 0, //0系统消息
            'message_val' => $message_val,
            'category' => 0,
            'mmt_code' => 'message_notice'
        );

        $messageFactory = new MessageFactory();
        $messageLogic = $messageFactory->makeModule($send_data);
        $messageLogic->sendMessage();

        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email = I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if ($email) {
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     *  转账汇款记录
     */
    public function remittance()
    {
        $status = I('status', 1);
        $realname = I('realname');
        $bank_card = I('bank_card');
        $where['status'] = $status;
        $realname && $where['realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['bank_card'] = array('like', '%' . $bank_card . '%');

        $create_time = urldecode(I('create_time'));
        // echo urldecode($create_time);
        // echo $create_time;exit;
        // $create_time = str_replace('+', '', $create_time);

        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        if ($status == 2) {
            $time_name = 'pay_time';
            $export_time_name = '转账时间';
            $export_status = '已转账';
        } else {
            $time_name = 'check_time';
            $export_time_name = '审核时间';
            $export_status = '待转账';
        }
        $where[$time_name] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));
        $withdrawalsModel = new Withdrawals();
        $count = $withdrawalsModel->where($where)->count();
        $Page = new page($count, C('PAGESIZE'));
        $list = $withdrawalsModel->where($where)->limit($Page->firstRow, $Page->listRows)->order("id desc")->select();
        if (I('export') == 1) {
            # code...导出记录
            $selected = I('selected');
            if (!empty($selected)) {
                $selected_arr = explode(',', $selected);
                $where['id'] = array('in', $selected_arr);
            }
            $list = $withdrawalsModel->where($where)->order("id desc")->select();
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户昵称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">银行机构名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户号码</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户开户名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">' . $export_time_name . '</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">备注</td>';
            $strTable .= '</tr>';
            if (is_array($list)) {
                foreach ($list as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['users']['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $export_status . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val[$time_name]) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }

        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('status', $status);
        $this->assign('Page', $Page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        $this->assign('withdraw_status', C('WITHDRAW_STATUS'));
        return $this->fetch();
    }

    public function get_withdrawals_list($status = '')
    {
        $id = I('selected/a');
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = urldecode(I('create_time'));
        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        $where['w.create_time'] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));

        $status = empty($status) ? I('status') : $status;
        if ($status !== '') {
            $where['w.status'] = $status;
        } else {
            $where['w.status'] = ['lt', 2];
        }
        if ($id) {
            $where['w.id'] = ['in', $id];
        }
        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['w.bank_card'] = array('like', '%' . $bank_card . '%');
        $export = I('export');
        if ($export == 1) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
            if (is_array($remittanceList)) {
                foreach ($remittanceList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['money'] . ' </td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['bank_name'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, '用户提现明细表');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        if ($list) {
            $user_ids = array_column($list, 'user_id');
            $avatar = get_avatar($user_ids);

            foreach ($list as $key => $value) {
                $list[$key]['head_pic'] = $avatar[$value['user_id']];
            }
        }
        
        //$this->assign('create_time',$create_time2);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        C('TOKEN_ON', false);
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $id = I('del_id/d');
        $res = Db::name("withdrawals")->where(['id' => $id])->delete();
        if ($res !== false) {
            $return_arr = ['status' => 1, 'msg' => '操作成功', 'data' => '',];
        } else {
            $return_arr = ['status' => -1, 'msg' => '删除失败', 'data' => '',];
        }
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $withdrawals = Db::name("withdrawals")->find($id);
        $user = M('users')->where(['user_id' => $withdrawals['user_id']])->find();
        if ($user['nickname']) {
            $withdrawals['user_name'] = $user['nickname'];
        } elseif ($user['email']) {
            $withdrawals['user_name'] = $user['email'];
        } elseif ($user['mobile']) {
            $withdrawals['user_name'] = $user['mobile'];
        }
        $status = $withdrawals['status'];
        $withdrawals['status_code'] = C('WITHDRAW_STATUS')["$status"];
        //看看这次之后有没有提现了
        $other=Db::name("withdrawals")->where(['user_id'=>$withdrawals['user_id']])->where('create_time','>',$withdrawals['create_time'])->sum('money');
        $user['user_money']+=$other;
        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        return $this->fetch();
    }

    /**
     *  处理会员提现申请
     */
    public function withdrawals_update()
    {
        $id_arr = I('id/a');
        $ids = implode(',', $id_arr);

        $data['status'] = $status = I('status');
        $data['remark'] = I('remark');
        $users = '';
       
        $data['check_time'] = time();

        $users = M('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->whereIn('w.id',$ids)->select();

        if ($status != 1) {
            $data['refuse_time'] = time();
        }

       $r = Db::name('withdrawals')->whereIn('id', $ids)->update($data);
       if ($r !== false) {

            if($users){
                foreach($users as $v){

                    //记录用户余额变动
                    $user_money = Db::name('users')->where(['user_id'=>$v['user_id']])->value('user_money');

                    //提现成功
                    if($status == 1){

                        //微信提现
                        if($v['bank_name'] == '微信'){
                             //微信
                            $result = $this->withdrawals_weixin($v['id']);
                            if(isset($result['status'])){
                                // 操作失败
                                accountLog($v['user_id'], $v['money'] , 0, '提现失败退回：'.$v['money'].'元', 0, 0 , '');
                                if($v['openid']){
                                    $this->Withdrawal_Success($v['openid'],'提现失败！',$v['money'],time(),'微信提现接口出错：'.json_encode($result));
                                }
                               
                            }else{

                                $result['payment_time'] = strtotime($result['payment_time']);
                                $result['money'] = $falg['money'];
                                $result['user_id'] = $falg['user_id'];
                                $flag = M('withdrawals_weixin')->insert($result);

                                if($v['openid']){
                                    $this->Withdrawal_Success($v['openid'],'恭喜你提现成功！',$v['money'],time(),'感谢你的努力付出，有付出就有回报！希望你再接再厉！');
                                }
                                setBalanceLog($v['user_id'],2,$v['money'],$user_money,'成功提现：'.$v['money']);

                            } 
                        }

                    }else{
                        //提现失败
                        //退钱

                        accountLog($v['user_id'], $v['money'] , 0, '提现失败退回：'.$v['money'].'元', 0, 0 , '');
                        if($v['openid']){
                            $this->Withdrawal_Success($v['openid'],'提现失败！',$v['money'],time(),'拒绝理由：'.$data['remark']);
                        }

                        setBalanceLog($v['user_id'],2,$v['money'],$user_money,'提现失败：'.$v['money']);
                    }

                }
            }
            $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
        }
    }


     //用户微信提现
     private function withdrawals_weixin($id){
        $falg = M('withdrawals')->where(['id'=>$id])->find();
        $openid = M('users')->where('user_id', $falg['user_id'])->value('openid');
        $data['openid'] = $openid;
        $data['pay_code'] = $falg['id'].$falg['user_id'];
        $data['desc'] = '提现ID'.$falg['id'];
        // if($falg['taxfee'] >= $falg['money']){
        //     return array('status'=>1, 'msg'=>"提现额度必须大于手续费！" );
        // }else{
            $data['money'] = bcsub($falg['money'], $falg['taxfee'], 2);
        // }
        include_once PLUGIN_PATH . "payment/weixin/weixin.class.php";
        $weixin_obj = new \weixin();
        $result = $weixin_obj->transfer($data);
        // if($result){
        //     $result['payment_time'] = strtotime($result['payment_time']);
        //     $result['money'] = $falg['money'];
        //     $result['user_id'] = $falg['user_id'];
        // }
        return $result;
    }



    // 用户申请提现
    public function transfer()
    {
        $id = I('selected/a');
        if (empty($id)) {
            $this->error('请至少选择一条记录');
        }
        $atype = I('atype');
        if (is_array($id)) {
            $withdrawals = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->select();
        } else {
            $withdrawals = M('withdrawals')->where(array('id' => $id))->select();
        }


        $messageFactory = new \app\common\logic\MessageFactory();
        $messageLogic = $messageFactory->makeModule(['category' => 0]);

        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach ($withdrawals as $val) {
            $user = M('users')->where(array('user_id' => $val['user_id']))->find();
            //$oauthUsers = M("OauthUsers")->where(['user_id'=>$user['user_id'] , 'oauth_child'=>'mp'])->find();
            $oauthUsers = M("OauthUsers")->where(['user_id' => $user['user_id'], 'oauth' => 'weixin'])->find();
            //获取用户绑定openId
            $user['openid'] = $oauthUsers['openid'];
            if ($user['user_money'] < $val['money']) {
                $data = array('status' => -2, 'remark' => '账户余额不足');
                M('withdrawals')->where(array('id' => $val['id']))->save($data);
                $this->error('账户余额不足');
            } else {
                $rdata = array('type' => 1, 'money' => $val['money'], 'log_type_id' => $val['id'], 'user_id' => $val['user_id']);
                if ($atype == 'online') {
                    return false;
                } else {
                    accountLog($val['user_id'], ($val['money'] * -1), 0, "管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('withdrawals')->where(array('id' => $val['id']))->save(array('status' => 2, 'pay_time' => time()));
                    expenseLog($rdata);//支出记录日志
                    // 提现通知
                    $messageLogic->withdrawalsNotice($val['id'], $val['user_id'], $val['money'] - $val['taxfee']);
                }
            }
        }
        if ($alipay['batch_num'] > 0) {
            //支付宝在线批量付款
            include_once PLUGIN_PATH . "payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!", U('remittance'), 3);
    }

    /**
     * 会员签到记录
     * @author Rock
     * @data 2019/03/21
     */
    public function userSignList(){

        
        return $this->fetch();
    }

    public function ajaxUserSignList()
    {
        
        $where['identification'] = ['=', 1];
        $count = Db::name('commission_log')->where($where)->count();
        $Page = new AjaxPage($count, 15);
        $sign_id = I('ids');
       
        if(input('post.mobile')){
            $mobile = input('post.mobile');
            $seach = Db::query("select `user_id` from `tp_users` where `mobile` like '%$mobile%'");
            if($seach){
                foreach($seach as $v){
                    $seachid[] = $v['user_id'];
                }
                $seachid = implode("','",$seachid);
                $where['user_id'] = ['in', $seachid];
                
            }else{
                $where['user_id'] = ['<',0];
            }
        }
        
        $list = Db::name('commission_log')
            ->field('id,user_id,num,money,addtime,desc')
            ->where($where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        
        if($list){
            foreach($list as $k => $v){
                $sign_ids[] = $v['id'];
                $user_id_arr[] = $v['user_id'];
                $list[$k]['addtime'] = date('Y-m-d H:i:s',$v['addtime']);
            }
            $user_id_str = implode("','",$user_id_arr);
            $user = Db::query("select `user_id`,`nickname`,`mobile`,`head_pic` from `tp_users` where `user_id` in ('$user_id_str')");
            if($user){
                foreach($user as $v){
                    $userinfo[$v['user_id']] = $v; 
                }
                foreach($list as $k => $v){
                    $list[$k]['nickname'] = $userinfo[$v['user_id']]['nickname'];
                    $list[$k]['mobile'] = $userinfo[$v['user_id']]['mobile'];
                    $list[$k]['head_pic'] = $userinfo[$v['user_id']]['head_pic'];
                }
            }
            
            $sign_id = implode(',',$sign_ids);
        }
        // dump($list);exit;
        $show = $Page->show();

        $this->assign('sign_id',$sign_id);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }
    /**
     * 会员签到规则
     * @author Rock
     * @data 2019/03/21
     */
    public function userSignRule(){
        if(IS_POST){
            $data = input('post.');
            if($data['inc_type'] != 'user_sign_rule'){
                $this->error('未知的来源');
            }
            $pram['sign_on_off'] = intval($data['sign_on_off']);
            $pram['sign_integral'] = intval($data['sign_integral'] * 100) / 100;
            $pram['continued_on_off'] = intval($data['continued_on_off']);
            if($pram['sign_on_off'] == 0 &&  $pram['continued_on_off'] == 1){
                $pram['continued_on_off'] = 0;
            }
            if($data['continuedk']){
                foreach($data['continuedk'] as $k => $v){
                    $key = intval($v);
                    $val = intval($data['continuedv'][$k] * 100) / 100;
                    if($key && ($val > 0)){
                        $pram['rule'][$key] = $val;
                    }
                }
            }
            
            $name = $data['inc_type'];
            $value = json_encode($pram);
            $inc_type = $data['inc_type'];
            $desc = "用户登录签到送佣金";
            # 插入或更新设置
            $info = Db::name('config')->where('inc_type',$inc_type)->find();
            if($info){
                $bool = Db::execute("update `tp_config` set `value` = '$value' where `name` = '$name' and `inc_type` = '$inc_type'");
            }else{
                $bool = Db::execute("insert into `tp_config` (`name`,`value`,`inc_type`,`desc`) values ('$name','$value','$inc_type','$desc')");
            }
            if (!$bool) {
                $this->error('操作失败');
            }
            $this->success('操作成功');
            exit;
        }
        
        # 获取设置信息
        $info = Db::name('config')->where('inc_type','user_sign_rule')->find();
        if($info){
            $config = json_decode($info['value'],true);
            if($config['rule']){
                ksort($config['rule']);
                $first_key = each($config['rule'])['key'];
                $this->assign('first_key',$first_key);
            }
            // dump($config);exit;
            $this->assign('config',$config);
        }


        return $this->fetch();
    }

    /**
     * 会员邀新记录
     * @author Rock
     * @data 2019/03/21
     */
    public function userInviteList(){
        
        return $this->fetch();
    }

    public function ajaxUserInviteList(){

        $where['identification'] = ['=', 2];
        $count = Db::name('commission_log')->where($where)->count();
        $Page = new AjaxPage($count, 15);
        $invite_id = I('invite_id');
       
        if(input('post.mobile')){
            $mobile = input('post.mobile');
            $seach = Db::query("select `user_id` from `tp_users` where `mobile` like '%$mobile%'");
            if($seach){
                foreach($seach as $v){
                    $seachid[] = $v['user_id'];
                }
                $seachid = implode("','",$seachid);
                $where['user_id'] = ['in', $seachid];
                
            }else{
                $where['user_id'] = ['<',0];
            }
        }
        
        $list = Db::name('commission_log')
            ->field('id,user_id,add_user_id,num,money,addtime,desc')
            ->where($where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        
        if($list){
            foreach($list as $k => $v){
                $invite_ids[] = $v['id'];
                $user_id_arr[] = $v['user_id'];
                $user_id_arr[] = $v['add_user_id'];
                $list[$k]['addtime'] = date('Y-m-d H:i:s',$v['addtime']);
            }
            $user_id_str = implode("','",$user_id_arr);
            $user = Db::query("select `user_id`,`nickname`,`mobile`,`head_pic` from `tp_users` where `user_id` in ('$user_id_str')");
            if($user){
                foreach($user as $v){
                    $userinfo[$v['user_id']] = $v; 
                }
                foreach($list as $k => $v){
                    $lisk[$k]['addnickname'] = $userinfo[$v['add_user_id']]['nickname'];
                    $list[$k]['addmobile'] = $userinfo[$v['add_user_id']]['mobile'];
                    $list[$k]['nickname'] = $userinfo[$v['user_id']]['nickname'];
                    $list[$k]['mobile'] = $userinfo[$v['user_id']]['mobile'];
                    $list[$k]['head_pic'] = $userinfo[$v['user_id']]['head_pic'];
                }
            }
            $invite_id = implode(',',$invite_ids);
        }
        // dump($list);exit;
        $show = $Page->show();

        $this->assign('invite_id',$invite_id);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    /**
     * 会员邀新规则
     * @author Rock
     * @data 2019/03/21
     */
    public function userInviteRule(){
        if(IS_POST){
            $data = input('post.');
            if($data['inc_type'] != 'user_invite_rule'){
                $this->error('未知的来源');
            }
            $pram['invite_on_off'] = intval($data['invite_on_off']);
            if($data['rulek']){
                foreach($data['rulek'] as $k => $v){
                    $key = intval($v);
                    $val = intval($data['rulev'][$k] * 100) / 100;
                    if($key && ($val > 0)){
                        $pram['rule'][$key] = $val;
                    }
                }
            }
            $name = $data['inc_type'];
            $value = json_encode($pram);
            $inc_type = $data['inc_type'];
            $desc = "邀请注册送佣金";

            # 插入或更新设置
            $info = Db::name('config')->where('inc_type',$inc_type)->find();
            if($info){
                Db::execute("update `tp_config` set `value` = '$value' where `name` = '$name' and `inc_type` = '$inc_type'");
            }else{
                Db::execute("insert into `tp_config` (`name`,`value`,`inc_type`,`desc`) values ('$name','$value','$inc_type','$desc')");
            }
            $this->success('操作成功');
            exit;
        }

         # 获取设置信息
         $info = Db::name('config')->where('inc_type','user_invite_rule')->find();
         if($info){
             $config = json_decode($info['value'],true);
             if($config['rule']){
                 ksort($config['rule']);
                 $first_key = each($config['rule'])['key'];
                 $this->assign('first_key',$first_key);
             }
            //  dump($config);exit;
             $this->assign('config',$config);
         }
    
        return $this->fetch();
    }


    //导出签到、邀新返佣明细
    public function export_commission_log()
    {
        $ids = I('ids');
        $identification = I('ident');
        
        switch($identification){
            case 1:
                $title = '签到返佣明细表';
                $addStrTable = '';
                $title_name = '连续签到天数';
                break;
            case 2:
                $title = '邀请新会员返佣明细表';
                $title_name = '邀新个数';
                $addStrTable = '<td style="text-align:center;font-size:14px;" width="*">获得返利用户名</td>';
                break;
            default:
                $title = '签到返佣明细表';
                $addStrTable = '';
                break;
        }

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr >';
        $strTable .= '<td style="text-align:center;font-size:14px;width:120px;">ID</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">用户名</td>';
        $strTable .= $addStrTable;
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">所得金额</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">' . $title_name . '</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">时间</td>';
        $strTable .= '<td style="text-align:center;font-size:14px;" width="*">描述</td>';
        $strTable .= '</tr>';
        
        $condition = array();
        if ($ids) {
            $condition['id'] = ['in', explode(',',$ids)];
        }
        $count = DB::name('commission_log')->where(['identification'=>$identification])->where($condition)->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $commission_log = M('commission_log')->where(['identification'=>$identification])->where($condition)->order('id')->limit($start, 5000)->select();
            if (is_array($commission_log)) {
                $user_ids = array_column($commission_log,'user_id');
                $to_user_ids = array_column($commission_log,'add_user_id');
                $n_user_ids = array_merge($user_ids,$to_user_ids);
                $n_user_ids = array_unique($n_user_ids);
                $user_names = Db::name('users')->where('user_id','in',$n_user_ids)->column('user_id,nickname,mobile');
                foreach ($commission_log as $k => $val) {
                    $username = $user_names[$val['user_id']]['nickname'] ? $user_names[$val['user_id']]['nickname'] : $user_names[$val['user_id']]['mobile'];
                    $to_username = $user_names[$val['add_user_id']]['nickname'] ? $user_names[$val['add_user_id']]['nickname'] : $user_names[$val['to_user_id']]['mobile'];
                    $order_sn = $val['order_sn'];

                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['id'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $username . ' </td>';
                    $strTable .= ($identification == 2 ) ? '<td style="text-align:center;font-size:12px;">' . $to_username . '</td>' : '';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['money'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['num'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . date('Y-m-d H:i', $val['add_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['desc'] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($log_list);
            }
        }
        $strTable .= '</table>';
        $i = ($i == 1) ? '' : '_'.$i;

        downloadExcel($strTable,  $title. $i);
        exit();
    }


    /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList()
    {
        return false;
    }


    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList()
    {
        return false;
    }

    /**
     * 签到规则设置
     * @date 2017/09/28
     */
    public function signRule()
    {
        return false;
    }

    /**
     * 会员标签列表
     */
    public function labels()
    {
        $p = input('p/d');
        $Label = new UserLabel();
        $label_list = $Label->order('label_order')->page($p, 10)->select();
        $this->assign('label_list', $label_list);
        $Page = new Page($Label->count(), 10);
        $this->assign('page', $Page);
        return $this->fetch();
    }

    /**
     * 添加、编辑页面
     */
    public function labelEdit()
    {
        $label_id = input('id/d');
        if ($label_id) {
            $Label = new UserLabel();
            $label = $Label->where('id', $label_id)->find();
            $this->assign('label', $label);
        }
        return $this->fetch();
    }

    /**
     * 会员标签添加编辑删除
     */
    public function label()
    {
        $label_info = input();
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        $userLabelValidate = Loader::validate('UserLabel');
        $UserLabel = new UserLabel();
        if (request()->isPost()) {
            if ($label_info['label_id']) {
                if (!$userLabelValidate->scene('edit')->batch()->check($label_info)) {
                    $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLabelValidate->getError()];
                } else {
                    $UserLabel->where('id', $label_info['label_id'])->save($label_info);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => ''];
                }
            } else {
                if (!$userLabelValidate->batch()->check($label_info)) {
                    $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLabelValidate->getError()];
                } else {
                    $UserLabel->insert($label_info);
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => ''];
                }
            }
        }
        if (request()->isDelete()) {
            $UserLabel->where('id', $label_info['label_id'])->delete();
            $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }
}
