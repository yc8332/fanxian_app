<?php

class mdlMobile extends clsModelBase {
    
    private $_db = null;
    private $_device_id = null;
    private $_crc_id = null;
    private $_timestamp = 0;
    private $_uid = 0;
    
    public function crc_id(){
        
        return sprintf("%u", crc32($this->_device_id));
    }
    
    public function __construct() {
        parent::__construct();
       $this->_db = $this->db('app');
     //  $this->_db->query("set names utf8");
       $this->_device_id = $_SESSION['device_id']?$_SESSION['device_id']:0;
       $this->_crc_id = $this->crc_id();
       $this->_timestamp = $_SERVER['REQUEST_TIME'];
    }
    
    //APP首页
    public function index(){
        $sql = "SELECT `uid`,`jifenbao`,`create_time`,`alipay` FROM `user` WHERE `crc_id`=$this->_crc_id AND `device_id`='$this->_device_id' LIMIT 1";
        $res = $this->_db->query($sql);

        if($res && mysql_affected_rows()==1){//存在
            $row = $this->_db->fetch_array($res);
            $_SESSION['uid'] = $row['uid'];
            $_SESSION['jifenbao'] = $row['jifenbao'];
            $_SESSION['create_time'] = $row['create_time'];           
            $_SESSION['alipay'] = $row['alipay'];
            
            if($_SESSION['alipay']){
                $this->_alipay();
            }
            
            
            $this->_db->query("UPDATE `user` SET `last_login`=$this->_timestamp WHERE `crc_id`=$this->_crc_id AND `device_id`='$this->_device_id' ");
            return 1;
        }else{//不存在则创建
            $sql = "INSERT INTO `user` SET `device_id`='$this->_device_id',`crc_id`=$this->_crc_id,`alipay`='',`create_time`=$this->_timestamp,`last_login`=$this->_timestamp";
            $res = $this->_db->query($sql);
            if($res && mysql_affected_rows()==1){
                $_SESSION['uid'] = (string)$this->_db->insert_id();
                $_SESSION['jifenbao'] = '0';
                $_SESSION['create_time'] = (string)$this->_timestamp;
                $_SESSION['alipay'] = "";
                return 1;
                
            }else{
                return -1;
            }
        }
    }
    
    //摇一摇
    public function shake(){
        $this->_start_transaction();
        $uid = $_SESSION['uid'];
       $mshake = $this->memcache()->get('app_shake_'.$this->_device_id);
       if($mshake){//摇过了
           return -1;
       }
       $today = strtotime(date('Y-m-d')) ;
       $res = $this->_db->query("SELECT `create_at` FROM `shake_log` WHERE `crc_id`=$this->_crc_id AND `device_id`='$this->_device_id' ORDER BY `id` DESC LIMIT 1");
       if($res && mysql_num_rows()==1){
           $row = $this->_db->fetch_array($res);
           if($row['create_at'] >= $today ){
               $this->memcache()->set('app_shake_'.$this->_device_id,"true",$today+86400);
               return -1;//摇过了
           }else{
               $result = $this->do_shake();
               $this->_db->query("INSERT INTO `shake_log` SET `device_id`='$this->_device_id',`crc_id`=$this->_crc_id,`jifenbao`=$result,`create_at`=$this->_timestamp");
               $this->memcache()->set('app_shake_'.$this->_device_id,"true",$today+86400);
               $this->jifenbao_chg($uid, $result, 3);
               return $result;
           }
       }else{
           $result = $this->do_shake();
           $this->_db->query("INSERT INTO `shake_log` SET `device_id`='$this->_device_id',`crc_id`=$this->_crc_id,`jifenbao`=$result,`create_at`=$this->_timestamp");
           $this->memcache()->set('app_shake_'.$this->_device_id,"true",$today+86400);
           $this->jifenbao_chg($uid, $result, 3);
           return $result;
       }
       
    }
    
    //摇一摇实现
    private function do_shake(){
        
        $reward = 100;//中奖概率
        $rand = rand(1, 100);//随机概率
        
        $is_over = false;//是否所有奖励发放完毕
        
        if($rand > $reward || $is_over){
            
            return 0;
            
        }else{
            /**
             * total = 100*100 集分宝
             * 100->10
             * 20->100
             * 10->100
             * 2->3000
             */
            
            $rand1 = rand(1,3210);
            if($rand1<=3000){//2枚
                return 2;
            }else if($rand1 <= 3100){//20枚
                return 20;
            }else if($rand1 <= 3200){//10枚
                return 10;
            }else{//100枚
                return 100;
            }
        }
        
        
        
    }
    
    //做任务
    public function dianle($arr){
        
        
        $this->_uid = $arr['uid'];
        $this->_device_id = $arr['device_id'];
        $this->_crc_id = $this->crc_id();
        $sql = "SELECT `uid`,`jifenbao` FROM `user` WHERE `uid`={$this->_uid} AND `crc_id`={$this->_crc_id} LIMIT 1";
        $res = $this->_db->query($sql);

        if($res && mysql_num_rows($res)>0){
            $row = $this->_db->fetch_array($res);
            if($this->_uid != $row['uid']){
                // return -1;
                return false;
               
            }
           // $jifenbao = $row['jifenbao'];
        }else{
           // return -2;
            return false;
        }
        
        
        $this->_start_transaction();
        
        $sql = "INSERT INTO `dianle_log` SET ";
        $i = 0;
        foreach ($arr as $key=>$val){
        
            if($i==0)
                $sql .="`$key`='$val'";
            else
                $sql .=",`$key`='$val'";
            $i++;
        }
        
        if(!$this->_db->query($sql)){//插入点乐记录
            $this->_rollback();
           // return -3;
            return false;
        }
        
        //更新用户资产
        $jifenbao = $arr['currency'];

        $reason = '下载应用赚集分宝('.$arr['ad_name'].')';

        
        $result = $this->jifenbao_chg($this->_uid, $jifenbao, 4,$reason);

        if($result != 1){
            $this->_rollback();
          //  return -4;
            return false;
        }
        $commit = $this->_commit();
        
        if($commit){
            return true;
        }else{
            return false;
        }
        
        
        
        
        
    }
    
    //任务记录
    public function tasked(){
        
    }
    
    //资产明细
    public function account($flag,$date){
        $uid = $_SESSION['uid'];
        if(preg_match('/\d+-\d+-\d+/', $date)){
            $create_at_s = strtotime($date);
            $create_at_e = $create_at_s + 86399;
        }
        switch ($flag){
            
            case 1://收入
                $sql = "SELECT `type`,`jifenbao_chg`,`reason`,`create_at` FROM `jifenbao_log` WHERE `uid`=$uid AND `create_at` BETWEEN $create_at_s AND $create_at_e AND `jifenbao_chg`>0 ORDER BY `id` DESC ";
                break;
            
            case 2://支出
               // $sql = "SELECT `type`,`jifenbao_chg`,`reason`,`create_at` FROM `jifenbao_log` WHERE `uid`=$uid AND `create_at` BETWEEN $create_at_s AND $create_at_e AND `jifenbao_chg`<0 ORDER BY `id` DESC ";
                $s = ($date-1)*10;
                $sql = "SELECT `jifenbao`,`status`,`reason`,`create_at` FROM `tixian` WHERE `uid`=$uid ORDER BY `id` DESC LIMIT $s,10";
                break;
            
        }

        $res = $this->_db->query($sql);
        while($row = $this->_db->fetch_array($res)){

            $result[] = $row;
        }
        
        if(!is_array($result)){
            $result = "null";
        }
        
        return $result;
    }
    
    //返现达人
    public function get_daren(){
        $is_daren = $this->memcache()->get('app_daren_today_'.$this->_device_id);
        $tomorrow_t = strtotime(date('Y-m-d')) + 86400;
        if($is_daren==5){
        
            return -1;
        }
        if($is_daren==false){
            $this->memcache()->set('app_daren_today_'.$this->_device_id,0,$tomorrow_t);
        }
        $timu = $this->memcache()->get('app_daren_timu');//缓存每日题目
        if(!$timu){
            
            $start = strtotime("2014-9-15");
            $current = strtotime(date('Y-m-d'));
            $d = ($current-$start)/86400;
            if($d>0){
                $id_s = 1+$d*5;
                $id_e = $id_s+4; 
            }else{

                $id_s = 1;
                $id_e = $id_s+4; 
            }
            
            $sql = "SELECT * FROM `daren` WHERE `id` BETWEEN $id_s AND $id_e";
            $res = $this->_db->query($sql);
            while($row = $this->_db->fetch_array($res)){
                $timu[] = $row;
            }
            
            if(empty($timu) || count($timu)<5){
                return 'null';
            }
            
            $this->memcache()->set('app_daren_timu',$timu,$tomorrow_t);
  
        }else{
            
            for($i=0;$i<5;$i++){
                if($i<$is_daren)
                    unset($timu[$i]);
            }
            
        }
        
      return array_values($timu);
    
    }
    
    //返现达人提交
    public function daren_submit($tid,$ans){
        $is_daren = $this->memcache()->get('app_daren_today_'.$this->_device_id);
        $timu = $this->memcache()->get('app_daren_timu');
        if($timu && $is_daren!==FALSE){
            foreach ($timu as $key=>$val){
                if($key < $is_daren){    
                    continue;
                }else{
                    if($tid == $val['id']){
                        $uid = $_SESSION['uid'];
                        $this->memcache()->increment('app_daren_today_'.$this->_device_id,1);
                        $this->_start_transaction();
                        $sql = "INSERT INTO `daren_log` SET `uid`=$uid,`daren_id`=$tid,`user_ans`='$ans',`dateline`=$this->_timestamp";
                        $this->_db->query($sql);

                        if($ans == $val['R']){//正确

                            $result = $this->jifenbao_chg($_SESSION['uid'], 2, 1);

                            if($result == 1)
                                return 1;
                            else
                                return null;
                        }else{
                            return $val['R'];
                        }
                    }
                }
                return 'null';
            }
        }else{
            return 'null';
        }
    }
    
    public function jifenbao_chg($uid,$jifenbao,$reason='',$ext=''){
        
        $sql = "SELECT `uid`,`jifenbao`,`create_time`,`alipay` FROM `user` WHERE `uid`=$uid AND `crc_id`=$this->_crc_id LIMIT 1";
        $res = $this->_db->query($sql);
        if($res && mysql_num_rows($res)==1){//存在
            $row = $this->_db->fetch_array($res);
          //  $_SESSION['uid'] = $row['uid'];
          //  $_SESSION['jifenbao'] = $row['jifenbao'];
          //  $_SESSION['create_time'] = $row['create_time'];
            $alipay = $row['alipay'];
        }else{
            return -1;
        }
        $user_jifenbao = $row['jifenbao'] + $jifenbao;

        switch ($reason){
            case 1://返现达人
                $reason = "返现答人";
                $type = 1;
                break;
            
            case 2://提现
                if(!$alipay){
                    return -2;//未设置支付宝
                }
                $reason = "提现支出";
                $sql = "INSERT INTO `tixian` SET `uid`=$uid,`alipay`='$alipay',`jifenbao`=".abs($jifenbao).",`status`=0,`create_at`=$this->_timestamp";
                $res = $this->_db->query($sql);
                $type = 2;
                break;
            
            case 3://摇一摇
                $reason = "摇一摇";
                $type = 3;
                break;
            case 4://点乐下载应用激活
                $reason = $ext;//'下载应用赚集分宝('.$arr['ad_name'].')';
                $type = 4;
                break;
            case 5://点乐深度任务
                
                $type = 5;
                break;
            default :
                
                
        }
        
        $sql = "INSERT INTO `jifenbao_log` SET `uid`=$uid,`type`=$type,`jifenbao_chg`=$jifenbao,`user_jifenbao`=$user_jifenbao,`reason`='$reason',`create_at`=$this->_timestamp";
        $res = $this->_db->query($sql);
        if($this->_db->insert_id()>0){

            $sql1 = "UPDATE `user` SET `jifenbao`=$user_jifenbao WHERE `uid`=$uid";
            $r = $this->_db->query($sql1);
            if(!$r){
                $this->_rollback();
                return -1;
            }
            if(1 == session_status()){
                session_id($this->_device_id);
                session_start();
            }
            $_SESSION['jifenbao'] = (string)$user_jifenbao;
            $this->_commit();
            return 1;
        }else{
            $this->_rollback();
            return -1;
        }

        

        
        

        
        
    }
    
    //用户反馈
    public function suggest($content){
        
        $uid = $_SESSION['uid'];
        $sql = "INSERT INTO `suggestion` SET `uid`=$uid,`content`='".mysql_real_escape_string($content)."',`ip`='".$_SERVER['REMOTE_ADDR']."',`create_at`=$this->_timestamp";
        $res = $this->_db->query($sql);
        if($res){
            return array('data'=>'1');
        }else{
            return array('data'=>'-1');
        }
    }
    
    //绑定支付宝
    public function alipay($alipay){
        
        $sql = "SELECT `uid` FROM `user` WHERE `alipay`='$alipay' LIMIT 1";
        $res = $this->_db->query($sql);
        if($res && mysql_num_rows($res)){//存在
            return -2;
        }else{
            $sql1 = "UPDATE `user` SET `alipay`='$alipay' WHERE `crc_id`=$this->_crc_id AND `device_id`='$this->_device_id'";
            $this->_db->query($sql1);
            $_SESSION['alipay'] = $alipay;
            $this->_alipay();
            return 1;
        }
    }
    
    //支付宝+*
    private function _alipay(){
        
        if(check_email($_SESSION['alipay'])){
            list($head, $host) = explode("@", $_SESSION['alipay']);
            $head = substr($head,0,2);
            $_SESSION['alipay'] = $head."***@".$host;
        }else{
                $head1 = substr($_SESSION['alipay'],0,2);
                $head2 = substr($_SESSION['alipay'],7,10);
                $_SESSION['alipay'] = $head1."****".$head2;
        }
    }

    public function question(){
        
        $sql = "SELECT `title`,`content` FROM `question` LIMIT 10";
        $res = $this->_db->query($sql);
        while($row = $this->_db->fetch_array($res)){
            $result[] = $row;
        }
        
        if(empty($result)){
            return 'null';
        }
        
        return $result;
        
        
        
    }
    
    
    public function _start_transaction(){
        $this->_db->query("SET AUTOCOMMIT=0");
        $this->_db->query("START TRANSACTION");
    }
    
    public function _rollback(){
        $this->_db->query("ROLLBACK");
    }
    
    public function _commit(){
        return $this->_db->query('COMMIT');
    }
    
    
    
    public function execute($sql){
        $res = $this->_db->query($sql);
        while($row = $this->_db->fetch_array($res))
        {
            $result[] = $row;
        }
        var_dump($result);
    }
}
?>
