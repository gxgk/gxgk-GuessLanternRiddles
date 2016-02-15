<?php
namespace Home\Controller;
use Think\Controller;
class RiddleController extends Controller {
    public function index(){
    	if(IS_POST){
    		$key=I('post.key',"");
        if($key!="gxgkdevelor"){
          $this->data='非法请求';
          $this->display();
          exit;
        }
    		$openid=I('post.openid',"");
    		if($openid==""){
    			$this->data='非法请求';
  				$this->display();
  				exit;
    		}
    		$nickname=I('post.nickname',"");
    		if($nickname==""){
    			$this->data='非法请求';
  				$this->display();
  				exit;
    		}
    		$msg=I('post.msg',"");
    		if($msg==""){
    			$this->data='非法请求';
  				$this->display();
  				exit;
    		}
  		}
  		else{
  			$this->data='非法请求';
  			$this->display();
  			exit;
  		}
      //录入手机
  		$SqlContact=D("Contact");
  		$result=$SqlContact->where('openid="%s"',$openid)->count();
  		if($result==0){
  			if(!preg_match("/1[3458]{1}\d{9}$/",$msg)){
  				$this->data='请输入正确的手机号';
  				$this->display();
  				exit;
  			}
  			else{
  				$contect['openid']=$openid;
  				$contect['nickname']=$nickname;
  				$contect['phone']=$msg;
  				$contect['jointime']=date('Y-m-d H:i:s');
  				$SqlContact->data($contect)->add();
  				$this->data='成功录入您的手机号，请回复“开始”，开始计时';
  				$this->display();
  				exit;
  			}
  		}
      //对于新参加用户，插入数据
  		$SqlUser=D("User");
  		$result=$SqlUser->where('openid="%s"',$openid)->count();
  		if($result==0){
  			$user['openid']=$openid;
  			$user['status']='willstart';
  			$SqlUser->data($user)->add();
  		}
      //进入赠送积分状态
      $UserInfo=$SqlUser->where('openid="%s"',$openid)->find();
      if($UserInfo['status']==='End' AND strstr($msg,'赠送')){
        unset($user);
        $user['openid']=$openid;
        $user['status']='WillSend';
        $SqlUser->data($user)->save();
        $this->data='请输入要对方的手机号\n注意：赠送积分将全部赠送';
        $this->display();
        exit;
      }
      if($UserInfo['status']==='WillSend'){
        $this->_send_Grade($openid,$msg);
        exit;
      }
      if($UserInfo['status']==='Sended'){
        $this->data='本活动只能参与一次，不可多次参与\n查看<a href=\"http://lantern.gxgk.cc/?s=/Home/Riddle/rank/openid/'.$openid.'\">个人排名点我</a>\n\n回复“取消”回到正常模式';
        $this->display();
        exit;
      }
      //已结束参加活动
  		if($UserInfo['status']==='End'){
  			$this->data='本活动只能参与一次，不可多次参与\n查看<a href=\"http://lantern.gxgk.cc/?s=/Home/Riddle/rank/openid/'.$openid.'\">个人排名点我</a>\n温馨提示：可将积分赠送给他人，为好友助攻，回复“赠送”，把积分赠送给好友\n\n回复“取消”回到正常模式';
  			$this->display();
  			exit;
  		}
      //活动计时到达
      if($UserInfo['status']==='starting' AND strtotime("now")-strtotime($UserInfo['starttime'])>1800){
        unset($user);
        $user['openid']=$openid;
        $user['status']='End';
        $user['finaltime']=date('Y-m-d H:i:s');
        $SqlUser->data($user)->save();
        $this->data='时间到！！！感谢你参与本次活动，快拉上亲友团送积分吧！\n查看<a href="http://lantern.gxgk.cc/?s=/Home/Riddle/rank/openid/'.$openid.'">个人排名点我</a>';
        $this->display();
        exit;
      }
      //开始活动
  		if($UserInfo['status']==='willstart' AND $msg=='开始'){
        unset($user);
  			$user['openid']=$openid;
  			$user['status']='starting';
  			$user['starttime']=date('Y-m-d H:i:s');
  			$SqlUser->data($user)->save();
        $UserInfo=$SqlUser->where('openid="%s"',$openid)->find();
  		}
      if($UserInfo['status']==='starting'){
        $this->_judge_Answer($openid,$msg);
        $this->_getRiddle($openid);
        exit;
      }
  		else{
  			$this->data='请回复“开始”，开始计时';
  			$this->display();
  			exit;
  		}
    }
  protected function _getRiddle($openid){
    $SqlAnswer=D("Answer");
    $SqlRiddle=D("Riddle");
    $AnswerInfo=$SqlAnswer->where('openid="%s"',$openid)->select();
    $RiddleInfo=$SqlRiddle->select();
    if(!$RiddleInfo){
      $this->data='抽出题目错误！！！系统异常';
      $this->display();
      exit;
    }
    for($id=0;$id<sizeof($AnswerInfo);$id++){
      $aid=$AnswerInfo[$id]['riddleid'];
      unset($RiddleInfo[$aid]);
    }
    unset($id);unset($aid);
    if(sizeof($RiddleInfo)===0){
        $this->data='题库空啦，已经没题目啦！！';
        $this->display();
        exit();
    }
    $val=array_rand($RiddleInfo,1);
    $this->data='谜题：'.$RiddleInfo[$val]['question'];
    $this->display();
    $SqlUser=D("User");
    $data['openid']=$openid;
    $data['finalquestion']=$val;
    $SqlUser->data($data)->save();
    //记录已答题目
    unset($data);
    $data['openid']=$openid;
    $data['riddleid']=$val;
    $SqlAnswer->data($data)->add();
  }
  protected function _judge_Answer($openid,$msg){
    $SqlRiddle=D("Riddle");
    $SqlUser=D("User");
    $UserInfo=$SqlUser->where('openid="%s"',$openid)->find();
    $FinalRiddle=$UserInfo['finalquestion'];
    if(!$FinalRiddle){
      return;
    }
    $RiddleInfo=$SqlRiddle->select();
    if(!$RiddleInfo){
        $this->data='获取谜底失败，请重试';
        $this->display();
        exit();
    }
    $SqlAnswer=D("Answer");
    $AnswerData['openid']=$openid;
    $AnswerData['riddleid']=$FinalRiddle;
    if(strstr($msg,$RiddleInfo[$FinalRiddle]['answer'])){
      unset($data);
      $data['openid']=$openid;
      $data['grade']=$UserInfo['grade']+5;
      $SqlUser->data($data)->save();

      $AnswerData['YesOrNot']=1;
      $this->data2='恭喜你，回答正确，加5分\n当前分数为：'.$data['grade'].'分\n\n';
    }
    else{
      $AnswerData['YesOrNot']=0;
      $this->data2='很遗憾，回答错误\n不要紧，答错不会扣分\n可以换题哦！\n当前分数为：'.$UserInfo['grade'].'分\n\n';
    }
    $AnswerData['AnswerTime']=date('Y-m-d H:i:s');
    $SqlAnswer->data($AnswerData)->save();
  }
  public function rank(){
    $mydata=false;
    $funtion=false;
    if(IS_POST){
      $key=I('post.key',"");
      if($key!="gxgkdevelor"){
        $this->data='非法请求';
        $this->display();
        exit;
      }
      $openid=I('post.openid',"");
      if($openid==""){
        $this->data='非法请求';
        $this->display();
        exit;
      }
      $mydata=true;
      $funtion=true;
    }
    else if(IS_GET){
      $openid=I('get.openid',"");
      if($openid!=""){
        $mydata=true;
      }
    }
    $this->mydata=$mydata;
    $this->funtion=$funtion;

    $SqlUser=D("User");
    $SqlUser->join('Contact ON Contact.openid = User.openid');
    $UserInfo=$SqlUser->order('grade DESC')->field('User.openid,nickname,grade')->select();
    for($myrank=0;$myrank<sizeof($UserInfo);$myrank++){
      if($UserInfo[$myrank]['openid']===$openid){
        $UserMy=$UserInfo[$myrank];
        break;
      }
    }
    if(sizeof($UserMy)===0){
      $this->mygrade='暂无积分';
      $this->myrank='暂无排名';
    }
    else{
      $this->mygrade=$UserMy['grade'];
      $this->myrank=$myrank+1;
    }
    for($id=0;$id<10;$id++){
      $toprank[$id]['id']=$id+1;
      $toprank[$id]['nickname']=$UserInfo[$id]['nickname'];
      $toprank[$id]['grade']=$UserInfo[$id]['grade'];
    }
    $this->toprank=$toprank;
    $this->display();
  }
  protected function _send_Grade($openid,$msg){
    $SqlUser=D("User");
    $SqlContact=D("Contact");
    if(!preg_match("/1[3458]{1}\d{9}$/",$msg)){
      $this->data='请输入正确的手机号';
      $this->display();
      exit;
    }
    $result=$SqlContact->where('phone="%s"',$msg)->find();
    if(!$result){
      $this->data='该手机号未参加活动';
      $this->display();
      exit;
    }
    $ReceiveMan=$SqlUser->where('openid="%s"',$result['openid'])->find();
    $SendMan=$SqlUser->where('openid="%s"',$openid)->find();
    //清空发送者的分数
    $data['openid']=$openid;
    $data['grade']=0;
    $data['status']='Sended';
    $SqlUser->data($data)->save();
    //给予接受者增加分数
    unset($data);
    $data['openid']=$ReceiveMan['openid'];
    $data['grade']=$ReceiveMan['grade']+$SendMan['grade'];
    $SqlUser->data($data)->save();
    $this->data='已经将积分赠送给'.$result['nickname'].'啦！';
    $this->display();
    exit;
  }
  protected function _exchange_Postcard($openid,$msg){

  }
}