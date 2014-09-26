<?php
class ctlMobile{
    
    private $_session = null;
    private $_device_id = null;
    private $_uid = null;
    
    public function __construct() {

        if($_GET['ac'] == 'test_api'){
            $this->test_api();
            exit;
        }
        
//        var_dump($this->_is_android());
//        exit;
    //    file_put_contents("/tmp/clients", json_encode($_SERVER)."\n\n",FILE_APPEND);
        
        if($_REQUEST['token']){
            if(trim($_REQUEST['token']) && $_REQUEST['time_stamp'] )
                //== 'k1TaWTlIa0vcNbAt8SjaTMpZrAqO8Dbr'
                $this->dianle();
            exit;
        }
        
        $this->_device_id = trim($_REQUEST['device_id']);

        if($this->_device_id){
            session_id($this->_device_id);
            session_start();
            if(!$_SESSION['device_id'] || !$_SESSION['jifenbao'])
            {
                $_SESSION['device_id'] = $this->_device_id;
                $result = M('Mobile')->index();
                if(-1 == $result){
                    if($this->_is_android()){
                        echo json_encode("system error");
                        exit;
                    }
                    $result1[] = array("error"=>"system error");
                    echo json_encode(array("data"=>$result1));
                    exit;
                }
            }else{
            }
                
        }else{
            if($this->_is_android()){
                echo json_encode("invalid device_id");
                exit;
            }
            $result[] = array("error"=>"invalid device_id");
            echo json_encode(array("data"=>$result));
            exit;
        }
    }
    
    private function _is_android(){
        
        return (bool)strstr($_SERVER["HTTP_USER_AGENT"],"thinkandroid");
    }
    public function index() {
        
        $result[] = array('create_at'=>$_SESSION['create_time'],'uid'=>$_SESSION['uid'],'jifenbao'=>$_SESSION['jifenbao'],'alipay'=>$_SESSION['alipay']);
        
        if($this->_is_android()){
                echo json_encode($result[0]);
                exit;
            }
        
        echo json_encode(array('data'=>$result));
        
    }
    
    //用户基本信息
    public function user(){
        $this->index();
    }

    //用户资产
    public function account(){
        if($_REQUEST['device_id']){
            
            if($_REQUEST['flag']=='plus'){
                $date = $_REQUEST['date']?trim($_REQUEST['date']):date('Y-m-d');
                $result = M('Mobile')->account(1,$date);
                if(is_array($result)){
                    $mykey = -1;
                    foreach($result as $key=>$val){
                        if($val['type'] == 1 && $mykey == -1)
                        {
                            $mykey = $key;
                        }else if($val['type'] == 1){
                            $result[$mykey]['jifenbao_chg'] +=2;
                            unset($result[$key]);
                        }
                    }
                    $result[$mykey]['jifenbao_chg'] = (string)$result[$mykey]['jifenbao_chg'];
                    $result = array_values($result);
                }else{
                    if($this->_is_android()){
                        $result = null;
                        echo $result;
                        exit;
                    }
                    $result1[] = array("error"=>$result);
                    $result = $result1;
                }
                
                $result = array("data"=>$result);
                echo json_encode($result);
            }else if($_REQUEST['flag']=='minus'){
                $date = $_REQUEST['date']?trim($_REQUEST['date']):1;
                $result = M('Mobile')->account(2,$date);
                if(!is_array($result)){
                     if($this->_is_android()){
                         $result = null;
                        echo $result;
                        exit;
                    }
                    $result1[] = array("error"=>$result);
                    $result = $result1;
                }
                $result = array("data"=>$result);
                echo json_encode($result);
            }
        }
    }

    //摇一摇
    public function shake(){
         if($_REQUEST['device_id']){
            $result = M('Mobile')->shake();
            echo json_encode(array('data'=>(string)$result));
         }
    }
    
    
    //APP返现达人
    public function daren(){
        if($_REQUEST['device_id']){
            //device_id不存在
            $result = M('Mobile')->get_daren();
            if(is_array($result)){
                foreach ($result as $key=>$val){
                    if(isset($val['R']))
                        unset($result[$key]['R']);
                }
                $result = array("data"=>$result);
                
            }else{
                 if($this->_is_android()){
                        if($result=='null')$result=null;
                        echo json_encode($result);
                        exit;
                    }
                $result1[] = array('error'=>(string)$result);
                $result = array("data"=>$result1);
            }
            
            echo json_encode($result);
        }
    }

    public function daren_submit(){
        if($_REQUEST['device_id'] && $_REQUEST['tid'] && $_REQUEST['ans'] ){
            $result = M('Mobile')->daren_submit(intval($_REQUEST['tid']),trim($_REQUEST['ans']));
            echo json_encode(array('data'=>(string)$result));
        }
    }

    public function suggest(){
        if($_REQUEST['device_id'] && $_REQUEST['content']){
            $result  = M('Mobile')->suggest(trim($_REQUEST['content']));
            echo json_encode($result);
        }
    }
    
    public function new_ver(){
        if($_REQUEST['ver'] && $_REQUEST['plat']==1){//安卓
            if($_REQUEST['ver'] ==2.0){
                echo json_encode(array('data'=>""));
            }else{
               
                echo json_encode(array('data'=>'http://'.$_SERVER['HTTP_HOST']."/app/XM_4399_CashBack_2.0.apk",'ver'=>"2.0"));
            }
        }else if($_REQUEST['ver'] && $_REQUEST['plat']==2){//IOS
            if($_REQUEST['ver']==1.0){
                echo json_encode(array('data'=>""));
            }else{
                echo json_encode(array('data'=>"http://fanxian.com/app/XM_4399_CashBack_2.0.apk"));
            }
        }
    }
    
    private function _get_app_version(){
       $path = PATH_ROOT.'wwwroot/app';
       $dir = opendir($path);
       //列出  目录中的文件
        while (($file = readdir($dir)) !== false){
            
            if($file == '.' || $file == '..')continue;
            echo $file;
        }
        closedir($dir);
       return $path;
    }
    
    public function task(){
        if($_REQUEST['device_id'] && $_REQUEST['reason'] && $_REQUEST['jifenbao']){
            
        }
    }

    public function tasked(){
        $result = array('data'=>array('app_id'=>111,'app_name'=>'xxxx'),array('app_id'=>111,'app_name'=>'xxxx'));

        echo json_encode($result);
    }

    //9.9
    public function sales(){
        
        $page = $_REQUEST['page']?intval($_REQUEST['page']):1;
        $cid = isset($_REQUEST['cid']) ? intval($_REQUEST['cid']) : 100;
        $sales_list = M("Sales")->get_static_data();
        if($cid>0 || !$sales_list){
            $topic_info = M("Sales")->get_topic_info();
            $sales_list = M("Sales")->get_sales($topic_info['id'],$cid,($page-1)*20);
        }
        
        foreach($sales_list['data'] as $key=>$val){
            
            $result[] = array('item_id'=>$val['item_id'],'item_name'=>$val['item_name'],'item_price'=>$val['item_price'],
                  'original_price'=>$val['original_price'],'item_pic_src'=>$val['item_pic_src']);
  
        }
        

       // $sales_list = array("data"=>);
        echo json_encode(array('data'=>$result));
    }
    
    public function sales_cat(){
        
        $arr = array(
            array('cid'=>'100','name'=>'全部'),
            array('cid'=>'1','name'=>'服装内衣'),
            array('cid'=>'2','name'=>'鞋包配饰'),
            array('cid'=>'3','name'=>'居家生活'),
            array('cid'=>'4','name'=>'数码电器'),
            array('cid'=>'5','name'=>'食品保健'),
            array('cid'=>'6','name'=>'母婴用品'),
            array('cid'=>'7','name'=>'车品户外'),
            array('cid'=>'8','name'=>'美妆'),            
        );
        
        $arr = array("data"=>$arr);
        echo json_encode($arr);
    }
    
    
    public function alipay(){
        if($_REQUEST['device_id'] && $_REQUEST['alipay']){
            
            $alipay = trim($_REQUEST['alipay']);
            
            $result = -1;
            if(preg_match('/[a-zA-Z-_0-9]{1,}@([a-zA-Z-_0-9]{1,}\.)+[a-zA-Z]{2,3}/', $alipay) || preg_match('/^1[0-9]{10}$/',$alipay) ){
                $result = M('Mobile')->alipay($alipay);
            }
             if($this->_is_android()){
                        echo json_encode($result);
                        exit;
            }
            echo json_encode(array('data'=>(string)$result));
        }
    }
    
    public function tixian(){
        
        if($_REQUEST['device_id'] && isset($_REQUEST['jifenbao'])){
            
            $tixian = intval($_REQUEST['jifenbao']);
            $result = -1;
            if($tixian >9 && $tixian<=$_SESSION['jifenbao']){//
                $result = M('Mobile')->jifenbao_chg($_SESSION['uid'],-$tixian,2);
                 if($this->_is_android()){
                        echo json_encode($result);
                        exit;
                    }
                echo json_encode(array('data'=>(string)$result));
            }else{
                 if($this->_is_android()){
                        echo json_encode($result);
                        exit;
                    }
                echo json_encode(array('data'=>(string)$result));
            }
            
        }
    }
    
    //常见问题
    public function question(){
        
        $result = M('Mobile')->question();
        $result = array("data"=>$result);
        echo json_encode($result);
    }
    
    public function clear(){
        M('Mobile')->memcache()->delete('app_daren_today_'.$this->_device_id);
        M('Mobile')->memcache()->delete('app_shake_'.$this->_device_id);
    }
    
    
    public function test(){
    //    var_dump(M('Mobile')->memcache()->delete('app_daren_today_860843022845368'));
   // M('Mobile')->memcache()->delete('app_daren_timu');
//        var_dump(M('Mobile')->memcache()->delete('app_daren_timu'));
////        M('Mobile')->memcache()->set('app_daren_timu','aaaaa',3600);
 //      $result = M('Mobile')->memcache()->get('app_daren_timu');
//        
   //    var_dump($result);
//        var_dump($_SESSION);
//        var_dump(session_destroy());
        
       // M('Mobile')->execute("SELECT * FROM `daren`");
    }
    
    public function dianle(){
        
        $arr['time_stamp'] = intval($_GET['time_stamp']);//时间戳
        $token = trim($_GET['token']);//token
        $arr['device_id'] = trim($_GET['device_id']);//设备唯一id
        $arr['uid'] = intval($_GET['snuid']);//用户uid
        $arr['currency'] = intval($_GET['currency']);//积分
        $arr['app_ratio'] = floatval($_GET['app_ratio']);//兑换比例，一分钱等于多少积分
        $arr['trade_type'] = intval($_GET['trade_type']);//1安装激活任务，4深度任务
        $arr['task_id'] = $_GET['task_id']?trim($_GET['task_id']):0;//当trade_type=1时不会出现
        
        $arr['ad_name'] = trim($_GET['ad_name']);//广告名
        $arr['pack_name'] = trim($_GET['pack_name']);//包名
        $arr['order_id'] = trim($_GET['order_id']);//订单id
        $arr['create_at'] = time();

        $secret_key = 'k1TaWTlIa0vcNbAt8SjaTMpZrAqO8Dbr';
        if(md5($arr['time_stamp'].$secret_key) != $token){
            echo 'invaild';
            exit;
        }
        
        
        $result = M('Mobile')->dianle($arr);

        if($result){
            echo "200";
        }else{
            echo "500";
        }
        
        
//        $server = json_encode($_SERVER);
        $get = json_encode($_GET);
//        
       file_put_contents('/tmp/dianle_api', $get."\n\n",FILE_APPEND);
       // echo "200";
        
    }
    
    public function exe(){
        M('Mobile')->execute("SELECT * FROM `daren` limit 5");
    }
    
    public function test_api(){
        
        $str = <<<HTML
                <!DOCTYPE html>
                <html>
                    <head>
                        <title>API测试工具</title>
                <meta charset="utf8">
                        <!-- 新 Bootstrap 核心 CSS 文件 -->
                        <link rel="stylesheet" href="http://cdn.bootcss.com/bootstrap/3.2.0/css/bootstrap.min.css">
                        <!-- jQuery文件。务必在bootstrap.min.js 之前引入 -->
                        <script src="http://cdn.bootcss.com/jquery/1.11.1/jquery.min.js"></script>

                        <!-- 最新的 Bootstrap 核心 JavaScript 文件 -->
                        <script src="http://cdn.bootcss.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
                    </head>
                    <body>
                        <div class="container" style="margin-top:200px">
                            <div class="row">
                                <div class="col-lg-4">
                                    <div class="row">
                                        <label style="float:left;">API：</label><select class="form-control action">
                                        <option>index</option>
                                        <option>account</option>
                                        <option>daren</option>
                                        <option>daren_submit</option>
                                        <option>question</option>
                                      </select>
                                    </div>
                                </div>
                                <div class="col-lg-8"></div>
                            </div>
                        </div>
                    </body>
                </html>
HTML;
        
        echo $str;
    }
}
?>
