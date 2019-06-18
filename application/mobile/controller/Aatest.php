<?php


namespace app\mobile\controller;
use think\Db;


class Aatest extends MobileBase
{
    // public function index(){
        // $wx_content  = "邀请成功通知\n5月15日\n恭喜你又收纳一名得力爱将!\n你的团队越来越大！";
        // $wechat = new \app\common\logic\wechat\WechatUtil();
        // // $share_openid = $share_user_info['openid'];
        // $res = $wechat->sendMsg('oa0KY01lp9cyd1NGNyElhBK-AGes', 'text', $wx_content);
    // }

    public function auto_users($limit = 0){
        $maxlen = 1024;
        # 查找有上级关系，上级列缓存未完成的用户
        $user = Db::name('users')->field('user_id,first_leader,parents_cache')->where(['parents_cache' => ['=', 0], 'first_leader' => ['>', 0]])->order('first_leader asc')->find();

        if($user){
            if($user['first_leader'] == $user['user_id']){
                Db::name('users')->where('user_id', $user['user_id'])->update(['first_leader' => 0]);
                goto OnceMore;
            }
            # 找到用户顺位上级列
            $pcache = Db::name('parents_cache')->where(['sort' => 1, 'user_id' => $user['user_id']])->find();
            
            if(!$pcache){
                # 尚未开始组装上级缓存的情况....
                $first_leader = $user['first_leader'];
                $parents[] = $first_leader;

                # 查找上级的上级缓存
                $first_parents_cache = Db::name('parents_cache')->where('user_id', $first_leader)->select();
                if($first_parents_cache){
                    # 组装上级列
                    foreach($first_parents_cache as $fpc){
                        $first_parents = explode(',',$fpc['parents']);
                        // dump($first_parents);exit;
                        rsort($first_parents);
                        foreach($first_parents as $v){
                            $parents[] = (int)$v;
                        }
                    }

                    $count = count($parents) - 1;
                    if($count <= $maxlen){
                        krsort($parents);
                        $parents_str = implode(',', $parents);
                        Db::name('parents_cache')->insert(['user_id' => $user['user_id'], 'sort' => 1, 'parents' => $parents_str, 'count' => $count]);
                        if($parents[$count] == 0){
                            Db::name('users')->where('user_id', $user['user_id'])->update(['parents_cache' => 1]);
                        }
                        goto OnceMore;
                    }else{
                        
                        $len = intval($count/$maxlen);
                        $le = $count%$maxlen;
                        if($le > 0){
                            $len = $len + 1;
                        }
                        
                        // dump($parents);exit;
                        $e = 0;
                        for($len; $len>0; $len--){
                            for($i=0; $i<3;$i++){
                                $d[] = array_shift($parents);
                                
                            }
                            $i = 0;
                            $d = array_filter($d);
                            $c = count($d);
                            if($c > 0){
                                if($d[$c-1] == 0){
                                    $c = $c - 1;
                                    $e = 1;
                                }
                                krsort($d);
                                $parents_str = implode(',', $d) ;
                            }else{
                                Db::name('parents_cache')->where('user_id', $user['user_id'])->setDec('sort');
                                $dec_parents = Db::name('parents_cache')->where(['user_id'=>$user['user_id'],'sort'=>1])->value('parents');
                                $dec_parents = '0,'.$dec_parents;
                                Db::name('parents_cache')->where(['user_id'=>$user['user_id'],'sort'=>1])->update(['parents'=>$dec_parents]);
                                Db::name('users')->where(['user_id'=>$user['user_id']])->update(['parents_cache' => 1]);
                                goto OnceMore;
                            }
                            Db::name('parents_cache')->insert(['user_id'=>$user['user_id'], 'sort' => $len, 'parents'=>$parents_str, 'count' => $c]);
                            $d = '';
                        }
                        if($e){
                            Db::name('users')->where('user_id', $user['user_id'])->update(['parents_cache' => 1]);
                        }
                        goto OnceMore;
                    }
                }else{
                    # 上级不存在上级缓存，设定上级的上级为0【没有上级】
                    $parents[] = 0;
                    krsort($parents);
                    $count = count($parents) - 1;
                    $parents_str = implode(',', $parents);
                    Db::name('parents_cache')->insert(['user_id' => $user['user_id'], 'sort' => 1, 'parents' => $parents_str, 'count' => $count]);
                    if($parents[$count] == 0){
                        Db::name('users')->where('user_id', $user['user_id'])->update(['parents_cache' => 1]);
                    }
                    goto OnceMore;
                }
            }else{
                
                $parents = explode(',',$pcache['parents']);
                if($parents[0] == 0){
                    Db::name('users')->where('user_id', $user['user_id'])->update(['parents_cache' => 1]);
                    goto OnceMore;
                }else{
                    $parent_id = $parents[0];
                    $parent_first_parents_cache = Db::name('parents_cache')->where('user_id', $parent_id)->select();
                    if($parent_first_parents_cache){
                        # 组装上级列
                        foreach($parent_first_parents_cache as $fpc){
                            $first_parents = explode(',',$fpc['parents']);
                            rsort($first_parents);
                            foreach($first_parents as $v){
                                array_unshift($parents, (int)$v);
                            }
                        }
                        
                        $count = count($parents) - 1;
                    
                        if($count <= $maxlen){
                            $parents_str = implode(',', $parents);
                            
                            Db::name('parents_cache')->where(['user_id' => $user['user_id'], 'sort' => 1])->update(['parents' => $parents_str, 'count' => $count]);
                            if($parents[0] == 0){
                                Db::name('users')->where(['user_id', $user['user_id']])->update(['parents_cache' => 1]);
                            }
                            goto OnceMore;
                        }else{
                            $len = intval($count/$maxlen);
                            $le = $count%$maxlen;
                            if($le > 0){
                                $len = $len + 1;
                            }
                            
                            $e = 0;
                            $inc = Db::name('parents_cache')->where('user_id', $user['user_id'])->setInc('sort',$len-1);
                            krsort($parents);
                            for($len; $len>0; $len--){
                                for($i=0; $i<3;$i++){
                                    $d[] = array_shift($parents);
                                    
                                }
                                // echo $len;exit;
                                $i = 0;
                                $c = count($d);
                                if($d[$c-1] == 0){
                                    $c = $c - 1;
                                    $e = 1;
                                }
                                krsort($d);
                                $d = array_filter($d);
                                if(!$d){
                                    Db::name('parents_cache')->where('user_id', $user['user_id'])->setDec('sort');
                                    $dec_parents = Db::name('parents_cache')->where(['user_id'=>$user['user_id'],'sort'=>1])->value('parents');
                                    $dec_parents = '0,'.$dec_parents;
                                    Db::name('parents_cache')->where(['user_id'=>$user['user_id'],'sort'=>1])->update(['parents'=>$dec_parents]);
                                    Db::name('users')->where(['user_id'=>$user['user_id']])->update(['parents_cache' => 1]);
                                    goto OnceMore;
                                }

                                $parents_str = implode(',', $d);
                                $ins = Db::name('parents_cache')->where(['user_id'=>$user['user_id'],'sort'=>$len])->value('id');
                                
                                if($ins){
                                    Db::name('parents_cache')->where('id', $ins)->update(['parents'=>$parents_str,'count'=>count($d)]);
                                    
                                }else{
                                    
                                    Db::name('parents_cache')->insert(['user_id'=>$user['user_id'],'sort'=>$len,'parents'=>$parents_str,'count'=>count($d)]);
                                }

                                $ins = '';
                                $d = '';
                            }
                            goto OnceMore;
                        }
                    }else{
                        array_unshift($parents, 0);
                        $parents_str = implode(',', $parents);
                        Db::name('parents_cache')->where(['user_id'=>$user['user_id'],'sort'=>1])->update(['parents'=>$parents_str]);
                        goto OnceMore;
                    }
                }
            }
        }else{
            exit('END');
        }
        OnceMore:
            if(isset($parents)){
                unset($parents);
            }
            if(isset($parents_str)){
                unset($parents_str);
            }
            if(isset($pcache)){
                unset($pcache);
            }
            if(isset($first_parents_cache)){
                unset($first_parents_cache);
            }
            $limit++;
            if($limit <= 100){
                $this->auto_users($limit);
            }else{
                exit('每次只执行100条');
            }
    }


    public function index(){

        $openid = 'oa0KY0wVI82HFGi6VmPj8Qy9ss8k';
        // $res = $this->Purchase_Success($openid,'商品购买成功！','13年大品牌专业节电专家商业版','购买成功！',369.00,'欢迎再次购买！','https://www.shukongxuanya.com/');
        // $res = $this->Withdrawal_Success($openid,'恭喜你成功提现！',199,time(),'感谢你的努力付出，有付出就有回报！希望你再接再厉！','https://www.shukongxuanya.com/');
        // $res = $this->Invitation_Register($openid,'恭喜你邀请成功！','Rock','15766485478',time(),'恭喜你又收纳一名得力爱将！你的团队越来越大！','https://www.shukongxuanya.com/');
        // $res = $this->Sign_Success($openid, '恭喜你签到成功！','Rock', time(), 1, 0.2, '感谢你每天光顾商城，你的足迹会吸引越来越多的小伙伴。继续努力吧','https://www.shukongxuanya.com/');
        // $res = $this->Out_Order($openid,'你的宝贝已发出，请耐心等待！','进口无线接收器','201905171656161927','神器商城','感谢你的努力付出，有付出就有回报！希望你再接再厉！','https://www.shukongxuanya.com/');




        dump($res);exit;

        echo $openid;

    }

}

