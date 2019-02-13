<?php
namespace app\index\controller;

use app\common\Config AS ConfigModel;
use app\common\Zhibo AS ZhiboModel;
use app\common\VideoAi AS VideoAiModel;
use app\index\model\Kecheng;
use app\index\model\KechengVideo;
use app\index\model\Fengkong;
use app\index\model\FengkongTougu;
use app\index\model\Task;
use app\index\model\Number;

use think\console\output\formatter\Style;
use think\route\Rule;
use View;
use think\Db;
use Config;
use think\captcha\Captcha;
use Cookie;
use Session;
use Env;
use Cache;
use app\common\Base;
use app\index\model\Wz;
use CURLFile;
use Chumper\Zipper\Zipper;
use tools\ApkParser;
use app\task\crontab\Roommsgtoexl;
use tools\Emoji;
use ipk\QQwry;
use PHPExcel_IOFactory;
use PHPExcel;
use PHPExcel_Writer_Excel2007;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;
use PHPExcel_Style_Alignment;

class Index extends Base
{
    public function verify(){
		$config = [
        // 验证码字符集合
        'codeSet'  => '1234567890',
        // 验证码字体大小(px)，根据所需进行设置验证码字体大小
        'fontSize' => 17,
        // 是否画混淆曲线
        'useCurve' => true,
        // 验证码图片高度，根据所需进行设置高度
        'imageH'   => '46',
        // 验证码图片宽度，根据所需进行设置宽度
        'imageW'   => '114',
        // 验证码位数，根据所需设置验证码位数
        'length'   => 4,
        // 验证成功后是否重置
        'reset'    => true
];
        $captcha = new Captcha($config);
        return $captcha->entry();    
    }

    public function crecklogin(){
        if (!Cookie::has('lsname')){
            //return header("Location: /login");
            $this->redirect('/login');
        }

        if (Cookie::get('cid') == 1){
            return true;
        }

        $a= Db::name('laoshi_class') ->where('id',Cookie::get('cid'))->find();
        $uaid = $a["aid"];
        $url = $this->request->url().".html";
        $urls = explode("?_=",$url);

        if(mb_strpos($url,"?_=") !== false){
            if ($this->request->url() != "/index"){
                $rs= Db::name('menu') ->where('link',$urls[0])->find();
                if(mb_strpos($uaid,$rs["id"]) === false){
                    abort(999,'无权限访问');
                    exit;
                }
            }
        }
    }

    public function index()
    {
        $APP =trim(input('APP'));
        $lid = trim(input('lid'));
        $lsname = trim(input('lsname'));
        $cid = trim(input('cid'));

        if ($APP==1){
            Cookie::set('lid',$lid,3600);
            Cookie::set('lsname',$lsname,3600);
            Cookie::set('cid',$cid,3600);
        }

        $this->crecklogin();
        $a= Db::name('laoshi_class') ->where('id',Cookie::get('cid'))->find();
        $uaid = $a["aid"];
        $classname = $a["name"];
        $uaiday = explode(",",$uaid);

        $rs= Db::name('menu')->where('pid',0) ->order('sort', 'Asc')->select();
        foreach ($rs as $v=>$f){
            $as= Db::name('menu')->where('pid',$f["id"]) ->order('sort', 'Asc')->select();
            foreach ($as as $b=>$c){
                if (in_array($c["id"], $uaiday)){
                    $rs[$v]["zlist"][] = $c;
                }
            }
        }

        $ls = $this->getlaoshiinfo(Cookie::get('lid'));
        if (Cookie::get('lid') == 24){
            $ls["lsimg"] =  "http://tva1.sinaimg.cn/crop.0.0.180.180.180/7fde8b93jw1e8qgp5bmzyj2050050aa8.jpg";
        }

        $u = array(
            "class"=>$classname,
            "username"=>Cookie::get('lsname'),
            "lid"=>Cookie::get('lid'),
            "lsimg"=>$ls["lsimg"],
        );

        $this->assign('u',$u);
        $this->assign('list',$rs);
        $this->assign('sys_info',_get_sys_info());

        if (ismobile()){
            return $this->fetch("index/mo_index");
        }else{
            return $this->fetch();
        }
    }

    public function mochat(){
        $a= Db::name('laoshi_class') ->where('id',Cookie::get('cid'))->find();
        $classname = $a["name"];

        $ls = $this->getlaoshiinfo(Cookie::get('lid'));
        if (Cookie::get('lid') == 24){
            $ls["lsimg"] =  "http://tva1.sinaimg.cn/crop.0.0.180.180.180/7fde8b93jw1e8qgp5bmzyj2050050aa8.jpg";
        }

        $u = array(
            "class"=>$classname,
            "username"=>Cookie::get('lsname'),
            "lid"=>Cookie::get('lid'),
            "lsimg"=>$ls["lsimg"],
        );

        $ulist = json_decode($this->getList(),true);


        $this->assign('u',$u);
        $this->assign('userlist',json_encode($ulist["data"]));
        return $this->fetch("index/mo_chat");
    }

    public function laoshi(){
        $this->crecklogin();

        $a = array(
            "lsimg"=>Config::get('upload.laoshiimg'),
            "lsvideo"=>Config::get('upload.laoshivideo'),
        );

        $cplist= Db::name('chanpin')->order('id', 'asc')->select();
        $this->assign('a',$a);
        $this->assign('cplist',$cplist);
        return $this->fetch();
    }

    public function lsvideo(){
        $this->crecklogin();
        $rs= Db::name('laoshi')->order('id', 'asc')->select();

        $a = array(
            "videoimg"=>Config::get('upload.videoimg'),
            "video"=>Config::get('upload.video'),
            "cid"=> Cookie::get('cid')
        );

        $starttime = gmdate ( 'Y-m-d\T00:00:00\Z',strtotime("-3 day"));
        $endtime = gmdate ( 'Y-m-d\TH:i:s\Z');
        $b = new ZhiboModel();
        $t = $this->webdb["ggrtmpname"].",".$this->webdb["wzrtmpname"];
        $t = explode(",",$t);
        $d = array();

        foreach ($t as $q=>$w){
            $e = explode("/",$w);
            $c = $b->RecordIndexFiles($this->webdb["ggrtmp"],$e[0],$e[1],$starttime,$endtime);
            if (!empty($c["RecordIndexInfoList"]["RecordIndexInfo"])){
                foreach ($c["RecordIndexInfoList"]["RecordIndexInfo"] as $k=>$v){
                    $m=explode("/",$v["OssObject"]);
                    $mm = explode("_",$m[count($m)-1]);
                    $mmm = explode("-",$mm[0]);

                    $z = array(
                        "name" =>$mmm[0]."-".$mmm[1]."-".$mmm[2]." ".$mmm[3],
                        "value" => $v["OssObject"]
                    );
                    $d[] = $z;
                }
            }
            $b->data = array_remove($b->data, "Signature");
        }

        $ctime_str = array();
        foreach($d as $key=>$v){
            $d[$key]['ctime_str'] = strtotime($v['name']);
            $ctime_str[] = $d[$key]['ctime_str'];
        }

        array_multisort($ctime_str,SORT_ASC,$d);

        $this->assign('a',$a);
        $this->assign('list',$rs);
        $this->assign('Recordlist',$d);

        if (ismobile()){
            return $this->fetch("index/mo_video");
        }else{
            return $this->fetch();
        }
    }

    public function lsvideoform(){
        $this->crecklogin();

        $id = intval(input('id'));

        $rs= Db::name('laoshi')->order('id', 'asc')->select();
        $rs1 = array();
        if (isset($id)){
            $rs1= Db::name('laoshi_video') ->where('id',$id)->find();
        }

        $a = array(
            "videoimg"=>Config::get('upload.videoimg'),
            "video"=>Config::get('upload.video'),
        );

        $this->assign('a',$a);
        $this->assign('lslist',$rs);
        $this->assign('info',$rs1);
        return $this->fetch("index/mo_video_form");
    }

    public function lsbooks(){
        $this->crecklogin();
        $rs= Db::name('laoshi')->order('id', 'asc')->select();
        $fl= Db::name('laoshi_book_class')->order('id', 'asc')->select();

        $a = array(
            "cid"=> Cookie::get('cid')
        );

        $this->assign('a',$a);

        $this->assign('list',$rs);
        $this->assign('fllist',$fl);

        if (ismobile()){
            return $this->fetch("index/mo_view");
        }else{
            return $this->fetch();
        }
    }
    //select a.*,c.temperature_alarm_gradient from a,b,c where a.id=b.id and a.id=c.id

    public function lsbooksform(){
        $this->crecklogin();
        $id = intval(input('id'));

        $rs= Db::name('laoshi')->order('id', 'asc')->select();
        $rs1 = array();

        if (isset($id)){
            $rs1= Db::name('laoshi_book') ->where('id',$id)->find();

            $fl= Db::name('laoshi_book_class')->order('id', 'asc')->select();
        }

        
        $this->assign('lslist',$rs);
        $this->assign('info',$rs1);
        $this->assign('fllist',$fl);
        return $this->fetch("index/mo_view_form");
    }

    public function wxask(){
        $this->crecklogin();
        
        return $this->fetch();
    }

    public function zbsetup()
    {
        $this->crecklogin();

        $a = array(
            "zbimg"=>Config::get('upload.zbimg'),
            "zbvideo"=>Config::get('upload.zbvideo'),
        );
        $this->assign('a',$a);
        return $this->fetch();
    }

    public function neican(){
        $this->crecklogin();
        
        return $this->fetch();
    }

    public function login(){
        

        if (ismobile()){
            return $this->fetch("index/mo_login");
        }else{
            return $this->fetch();
        }
    }

    public function zmzt(){
        
        return $this->fetch();
    }

    public function chanpin(){
        $a = array(
            "lsimg"=>Config::get('upload.laoshiimg'),
            "lsvideo"=>Config::get('upload.laoshivideo'),
        );
        return $this->fetch();
    }

    public function system(){
        $this->crecklogin();
        $rs= Db::name('menu')->where('pid',0) ->order('sort', 'Asc')->select();
        foreach ($rs as $v=>$a){
            $rs[$v]["zlist"] = Db::name('menu')->where('pid',$a["id"]) ->order('sort', 'Asc')->select();
        }

        
        $this->assign('list',$rs);
        return $this->fetch();
    }

    public function setup(){
        $a["appicon"] = Config::get('upload.appicon');
        $this->assign('a',$a);
        return $this->fetch();
    }

    public function comment(){
        return $this->fetch();
    }

    public function topstock(){
        if (ismobile()){
            return $this->fetch("index/mo_topstock");
        }else{
            return $this->fetch();
        }
    }

    public function topstockform(){
        $id = intval(input('id'));

        $rs1 = array();
        if (isset($id)){
            $rs1= Db::name('today_topstock') ->where('id',$id)->find();
        }

        $this->assign('info',$rs1);
        return $this->fetch("index/mo_topstock_form");
    }

    public function vasr($id){
        if (!isset($id)|| $id == 0){
            return "请先上次视频~";
        }
        $basedir= Env::get('root_path').'/uploads/aiasr/'.$id.".txt";
        if (!file_exists($basedir)){
            return "AI处理中,请稍后......";
        }else{
            $content = file_get_contents($basedir);
            $content = str_replace(PHP_EOL, '</br>', $content);
            return $content;
        }
    }

    public function savesetup($encryParams){
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");

        //echo $data["zsiosd"];

        $this->setiosplist('zs',$data["zsiosbundleid"],$data["zsiosd"],$data["zsiosv"],$data["zsappicon"],$data["zsappname"]);
        $this->setiosplist('ch',$data["chiosbundleid"],$data["chiosd"],$data["chiosv"],$data["chappicon"],$data["chappname"]);
        $this->setiosplist('zd',$data["zdiosbundleid"],$data["zdiosd"],$data["zdiosv"],$data["zdappicon"],$data["zdappname"]);
        ConfigModel::save_data($data);

        Ajson('提交成功!','0000');
    }

    public function setiosplist($tag,$bid,$url,$v,$img,$name){

        $xml=simplexml_load_file(Env::get('root_path')."public/".$tag."ios.plist");

        $img = $this->webdb["adminurl"]."/uploads/".Config::get('upload.appicon')."/".$img;

        $xml->dict ->array[0]->dict->array[0]->dict[0]->string[1] = $url;//包连接
        $xml->dict ->array[0]->dict->array[0]->dict[1]->string[1] = $img;
        $xml->dict ->array[0]->dict->array[0]->dict[2]->string[1] = $img;

        $xml->dict ->array[0]->dict->dict->string[0] = $bid;//BID
        $xml->dict ->array[0]->dict->dict->string[1] = $v;//版本
        $xml->dict ->array[0]->dict->dict->string[3] = $name;
        $xml->dict ->array[0]->dict->dict->string[4] = $name;

        return $xml->asXML(Env::get('root_path')."public/".$tag."ios.plist");
    }

    public function updatafile(){

        $this->assign('a',_get_sys_info());
        return $this->fetch();
    }

    /**
     * 数组分页函数  核心函数  array_slice
     * 用此函数之前要先将数据库里面的所有数据按一定的顺序查询出来存入数组中
     * $count   每页多少条数据
     * $page   当前第几页
     * $array   查询出来的所有数组
     * order 0 - 不变     1- 反序
     */

    public function page_array($count,$page,$array,$order=0){
        global $countpage; #定全局变量
        $page=(empty($page))?'1':$page; #判断当前页面是否为空 如果为空就表示为第一页面
        $start=($page-1)*$count; #计算每次分页的开始位置
        if($order==1){
            $array=array_reverse($array);
        }
        $totals=count($array);
        $countpage=ceil($totals/$count); #计算总页面数
        $pagedata=array();
        $pagedata=array_slice($array,$start,$count);
        return $pagedata;  #返回查询数据
    }

    public function notupfileck($file){
        $fils = array(
            "database","config"
        );

        foreach ($fils as $v){
            if (strpos($file,$v)){
                return false;
            }
        }

        return true;
    }

    public function getuplist(){
        $page = intval(input('page'));
        $limit = intval(input('limit'));

        $str = $this->http_curl('https://admin.haishunsh.com/getlistfile?domain='.$this->request->domain());
        $filelist = json_decode($str,true);

        $files = array();

        foreach ($filelist as $v=>$a){
            if (file_exists(Env::get('root_path').$a["file"])){
                if ((md5_file(Env::get('root_path').$a["file"]) != $a["md5"] && filemtime(Env::get('root_path').$a["file"]) < $a["time"])){
                    if (!strpos($a["file"],"database")){
                        $files[$v]["filename"] = $a["file"];
                        $files[$v]["oldtime"] = friendlyDate(filemtime(Env::get('root_path').$a["file"]),"full");
                        $files[$v]["time"] = friendlyDate($a["time"],"full");
                    }
                }
            }else{
                if (!strpos($a["file"],"database")){
                    $files[$v]["filename"] = $a["file"];
                    //$files[$v]["oldtime"] = "原本就无";
                    $files[$v]["time"] = friendlyDate($a["time"],"full");
                }
            }

        }

        $re = array(
            "code"=>0,
            "msg"=>"",
            "count"=>count($files),
            "data"=>$this->page_array($limit,$page,$files),
        );

        return json_encode($re);
    }

    public function scanFile($path) {
        global $result;
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($path . '/' . $file)) {
                    $this->scanFile($path . '/' . $file);
                } else {
                    $result[] = $path.'/'.basename($file);
                }
            }
        }
        return $result;
    }

    public function http_curl($url,$data = null){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        //curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);
        }
        curl_close($curl);
        return $output;
    }

    public function makeupdatafile(){
        $filename = Env::get('runtime_path')."md5files.md5";
        if (file_exists($filename)){
            unlink($filename);
        }

        $result = cupfiles();

        $filelist = array();

        foreach ($result as $v=>$a){
            $filelist[$v]["file"] = substr($a,2);
            $filelist[$v]["time"] = filemtime($a);
            $filelist[$v]["md5"] = md5_file($a);
        }

        $str = json_encode($filelist);

        file_put_contents($filename,$str);

        Ajson('操作成功!','0000');
    }

    public function doRequest($host,$path, $param=array()){
        $query = isset($param)? http_build_query($param) : '';

        $port = 443;
        $errno = 0;
        $errstr = '';
        $timeout = 10;

        $fp = fsockopen("ssl://".$host, $port, $errno, $errstr, $timeout);

        $out = "POST ".$path." HTTP/1.1\r\n";
        $out .= "host:".$host."\r\n";
        $out .= "content-length:".strlen($query)."\r\n";
        $out .= "content-type:application/x-www-form-urlencoded\r\n";
        $out .= "connection:close\r\n\r\n";
        $out .= $query;

        fputs($fp, $out);

        fclose($fp);

    }

    private function up_sql($filename=''){
        if(preg_match('/^\/runtime\/sql\/([\w]+)\.sql/', $filename)){
            $this->into_sql(Env::get('root_path').$filename,true,0);
        }
    }

    public function removeDir($dirName)
    {
        if(! is_dir($dirName))
        {
            return false;
        }
        $handle = @opendir($dirName);
        while(($file = @readdir($handle)) !== false)
        {
            if($file != '.' && $file != '..')
            {
                $dir = $dirName . '/' . $file;
                is_dir($dir) ? removeDir($dir) : @unlink($dir);
            }
        }
        closedir($handle);

        return rmdir($dirName) ;
    }

    public function savefile(){
        $filename = Env::get('runtime_path')."temp";
        if (file_exists($filename)){
            $this->removeDir($filename);
        }

        $filename = trim(input("filename"));
        $str = $this->get_server_file($filename);
        //$filename = substr($file,3);

        $this->bakfile(Env::get('root_path').$filename);
        $this->makepath(dirname(Env::get('root_path').$filename));
        if (file_exists(Env::get('root_path').$filename)){
            if (is_writable(Env::get('root_path').$filename)) {
                if( file_put_contents(Env::get('root_path').$filename, $str) ){
                    $this->up_sql($filename);
                    Ajson($filename.'文件升级成功!','0000');
                }
            }else{
                Ajson($filename.'文件升级失败,可能没有写权限!','0001');
            }
        }else{
            if( file_put_contents(Env::get('root_path').$filename, $str) ){
                $this->up_sql($filename);
                Ajson($filename.'文件升级成功!','0000');
            }
        }

    }

    public function get_server_file($filename=''){
        @set_time_limit(600);  //防止超时
        $str = $this->http_curl('https://admin.haishunsh.com/makeclientfile?filename='.$filename.'&domain='.urlencode($this->request->domain()));
        if(substr($str,0,6)=='Naruto'){    //文件核对,防止网络故障,抓取一些出错信息
            $str = substr($str,6);
        }else{
            $str='';
        }
        return $str;
    }

    public function bakfile($filename=''){
        $bakpath = Env::get('runtime_path').'bakfile/';
        if(!is_dir($bakpath)){
            mkdir($bakpath);
        }
        $new_name = $bakpath.date('Y_m_d-H_i--').str_replace(['/','\\'], '--', $filename);

        if (file_exists($filename)){
            copy($filename,$new_name);
        }
    }

    public function makepath($path){
        //这个\没考虑
        $newpath = '';
        $path=str_replace("\\","/",$path);
        $ROOT_PATH=str_replace("\\","/",Env::get('root_path'));
        $detail=explode("/",$path);
        foreach($detail AS $key=>$value){
            if($value==''&&$key!=0){
                //continue;
            }
            $newpath.="$value/";
            if((preg_match("/^\//",$newpath)||preg_match("/:/",$newpath))&&!strstr($newpath,$ROOT_PATH)){continue;}
            if( !is_dir($newpath) ){
                if(substr($newpath,-1)=='\\'||substr($newpath,-1)=='/')
                {
                    $_newpath=substr($newpath,0,-1);
                }
                else
                {
                    $_newpath=$newpath;
                }
                if(!is_dir($_newpath)&&!mkdir($_newpath)&&preg_match("/^\//",Env::get('root_path'))){
                    return false;
                }
                chmod($newpath,0777);
            }
        }
        return $path;
    }

    public function check_bom($filename='',$onlycheck=false){
        $contents = is_file($filename) ? file_get_contents($filename) : $filename;
        $charset[1] = substr($contents, 0, 1);
        $charset[2] = substr($contents, 1, 1);
        $charset[3] = substr($contents, 2, 1);
        if(ord($charset[1]) == 239 && ord($charset[2]) == 187 && ord($charset[3]) == 191){
            if($onlycheck==true){
                return true;
            }else{
                $contents = substr($contents, 3);
            }
        }
        if($onlycheck==false){
            return $contents;
        }
    }

    public function read_file($filename,$method="rb"){
        if($handle=@fopen($filename,$method)){
            @flock($handle,LOCK_SH);
            $filedata=@fread($handle,@filesize($filename));
            @fclose($handle);
        }
        return $filedata;
    }

    public function parse_sql($sql = '', $prefix = [], $limit = 0) {
        // 被替换的前缀
        $from = '';
        // 要替换的前缀
        $to = '';

        // 替换表前缀
        if (!empty($prefix)) {
            $to   = current($prefix);
            $from = current(array_flip($prefix));
        }

        if ($sql != '') {
            // 纯sql内容
            $pure_sql = [];

            // 多行注释标记
            $comment = false;

            // 按行分割，兼容多个平台
            $sql = str_replace(["\r\n", "\r"], "\n", $sql);
            $sql = explode("\n", trim($sql));

            // 循环处理每一行
            foreach ($sql as $key => $line) {
                // 跳过空行
                if ($line == '') {
                    continue;
                }

                // 跳过以#或者--开头的单行注释
                if (preg_match("/^(#|--)/", $line)) {
                    continue;
                }

                // 跳过以/**/包裹起来的单行注释
                if (preg_match("/^\/\*(.*?)\*\//", $line)) {
                    continue;
                }

                // 多行注释开始
                if (substr($line, 0, 2) == '/*') {
                    $comment = true;
                    continue;
                }

                // 多行注释结束
                if (substr($line, -2) == '*/') {
                    $comment = false;
                    continue;
                }

                // 多行注释没有结束，继续跳过
                if ($comment) {
                    continue;
                }

                // 替换表前缀
                if ($from != '') {
                    $line = str_replace('`'.$from, '`'.$to, $line);
                }
                if ($line == 'BEGIN;' || $line =='COMMIT;') {
                    continue;
                }
                // sql语句
                array_push($pure_sql, $line);
            }

            // 只返回一条语句
            if ($limit == 1) {
                return implode($pure_sql, "");
            }

            // 以数组形式返回sql语句
            $pure_sql = implode($pure_sql, "\n");
            $pure_sql = explode(";\n", $pure_sql);
            return $pure_sql;
        } else {
            return $limit == 1 ? '' : [];
        }
    }

    public function into_sql($sql, $replace_pre=true,$type=2){
        if(preg_match('/\.sql$/', $sql)||is_file($sql)){
            $sql = $this->check_bom($this->read_file($sql));
        }
        $prefix = $replace_pre===true ? ['wx_'=>config('database.prefix')] : [];
        $sql_list = $this->parse_sql($sql,$prefix);
        $result = false;
        foreach ($sql_list as $v) {
            if($type==2){   //直接终止
                $result = Db::execute($v);
            }else{
                try {
                    $result = Db::execute($v);
                } catch(\Exception $e) {
                    if($type==1){   //显示错误,不终止后面的程序运行
                        echo '<br>导入SQL失败，请检查install.sql的语句是否正确<pre>'.$v."\n\n".$e.'</pre>';
                    }else{
                        //为0的时候,屏蔽错误
                    }
                }
            }
        }
        return $result;
    }

    public function dologin($encryParams){
        // 检测输入的验证码是否正确，$value为用户输入的验证码字符串
        $data = json_decode($encryParams,true);
        $data["lsname"] = trim(addslashes($data["lsname"]));
        $data["password"] = trim(addslashes($data["password"]));
        $data["verify"] = trim(addslashes($data["verify"]));

//        if (!ismobile()){
//            if( !captcha_check($data["verify"]))
//            {
//                // 验证失败
//                Ajson('验证失败!');
//            }
//        }

        $rs= Db::name('laoshi') ->where('lsname',$data["lsname"])->find();

        if (!isset($rs)){
            Ajson('用户名或密码错误!');
        }

        if ($rs["password"] != getmd5($data["password"])){
            Ajson('用户名或密码错误!');
        }

        Cookie::set('lid',$rs["id"],9999999);
        Cookie::set('lsname',$data["lsname"],9999999);
        Cookie::set('cid',$rs["cid"],9999999);

        $cook["lsname"] = Cookie::get('lsname');
        $cook["cid"] = Cookie::get('cid');
        $cook["lid"] = Cookie::get('lid');

        Ajson('登陆成功!','0000',$cook);
    }

    public function logout(){
        Cookie::delete('lid');
        Cookie::delete('lsname');
        Cookie::delete('cid');

        Ajson('退出成功!','0000');
    }

    public function lschanpin(){
        $this->crecklogin();
        $rs= Db::name('laoshi')->order('id', 'asc')->select();
        
        $this->assign('list',$rs);
        return $this->fetch();
    }

    public function topstocklist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = ($s-1) * $p;
        }


        $rs= Db::name('today_topstock')->order('id', 'Desc')->select();
        $re['total'] = Db::name("today_topstock")->count();

        foreach ($rs as $v=>$a){
            $rs[$v]["dateline"] = friendlyDate($a["dateline"], 'full');
        }

        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function lslist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = ($s-1) * $p;
        }

        if (Cookie::get('cid') == 1){
            $rs= Db::name('laoshi')->order('id', 'asc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi")->count();
        }else{
            $rs= Db::name('laoshi')->where('id', Cookie::get('lid'))->order('id', 'asc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi")->where('id', Cookie::get('lid'))->count();
        }

        foreach ($rs as $v=>$a){
            $class = Db::name('laoshi_class')->where('id',$a["cid"])->find();
            $rs[$v]["cid"] = $class["name"];
        }

        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function lslists(){
        $this->crecklogin();
        $rs= Db::name('laoshi')->order('id', 'asc')->select();

        return  json_encode($rs);
    }

    public function lsvideolist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        $cid = intval(input('cid'));


        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        if (Cookie::get('cid') == 1 || Cookie::get('cid') == 4){
            if (!isset($cid) || $cid == 0){
                $rs= Db::name('laoshi_video')->order('id', 'Desc')->limit($s,$p)->select();
                $re['total'] = Db::name("laoshi_video")->count();
            }else{
                $rs= Db::name('laoshi_video')->where('cid', $cid)->order('id', 'Desc')->limit($s,$p)->select();
                $re['total'] = Db::name("laoshi_video")->where('cid', $cid)->count();
            }

        }else{
            if (!isset($cid) || $cid == 0){
                $rs= Db::name('laoshi_video')->where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
                $re['total'] = Db::name("laoshi_video")->where('lid', Cookie::get('lid'))->count();
            }else{
                $rs= Db::name('laoshi_video')->where('cid', $cid)->where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
                $re['total'] = Db::name("laoshi_video")->where('cid', $cid)->where('lid', Cookie::get('lid'))->count();
            }

        }

        foreach ($rs as $v=>$a){
            $rs[$v]["dateline"] = friendlyDate($rs[$v]["dateline"], 'mohu');
            $f = Db::name('laoshi_video_class') ->where('id',$a["cid"])->find();
            if (!empty($f)){
                $rs[$v]["cid"] = $f["name"];
            }
            $rs[$v]["spdz"] = "/tvideo/".$a["id"];
            $ls = Db::name('laoshi') ->where('id',$a["lid"])->find();
            $rs[$v]["lid"] = $ls["lsname"];

            $t = explode($this->webdb["playurl"],$a["videourl"]);
            $tt = Db::name('laoshi_video_zm') ->where('videourl',$t[1])->find();
            if (!empty($tt)){
                $jobinfo = $this->getzmztforone($tt["JobId"]);
                $rs[$v]["sectime"] = $this->sec2time($jobinfo["Output"]["Properties"]["Streams"]["VideoStreamList"]["VideoStream"][0]["Duration"]);
            }
        }
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function nclist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }
        $rs= Db::name('neican')->order('id', 'Desc')->limit($s,$p)->select();
        $re['total'] = Db::name("neican")->count();
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function lscplist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        if (Cookie::get('cid') == 1){
            $rs= Db::name('laoshi_chanpin')->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi_chanpin")->count();
        }else{
            $rs= Db::name('laoshi_chanpin')->where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi_chanpin")->where('lid', Cookie::get('lid'))->count();
        }

        foreach ($rs as $v=>$a){
            $ls = Db::name('laoshi') ->where('id',$a["lid"])->find();
            $rs[$v]["lid"] = $ls["lsname"];
        }
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function laasklist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        if (Cookie::get('cid') == 1){
            $rs= Db::name('laoshi_ask')->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi_ask")->count();
        }else{
            $rs= Db::name('laoshi_ask')->where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi_ask")->where('lid', Cookie::get('lid'))->count();
        }

        foreach ($rs as $v=>$a){
            $rs[$v]["dateline"] = date("Y年m月d日 H:i:s",$a["dateline"]);
            $ls = Db::name('laoshi') ->where('id',$a["lid"])->find();
            $rs[$v]["lid"] = $ls["lsname"];
        }
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function applaasklist(){
        $s = intval(input('page'));
        $p = intval(input('rows'));
        $cid = intval(input('cid'));

        if ($cid == 1){
            $rs= Db::name('laoshi_ask')->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi_ask")->count();
        }else{
            $rs= Db::name('laoshi_ask')->where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi_ask")->where('lid', Cookie::get('lid'))->count();
        }

        foreach ($rs as $v=>$a){
            $rs[$v]["dateline"] = date("Y年m月d日 H:i:s",$a["dateline"]);
            $ls = Db::name('laoshi') ->where('id',$a["lid"])->find();
            $rs[$v]["lid"] = $ls["lsname"];
        }
        $re['rows']=$rs;

        Ajson('查询成功!','0000',$re);
    }

    public function booklist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        if (Cookie::get('cid') == 1 || Cookie::get('cid') == 4){
            $rs= Db::name('laoshi_book')->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi_book")->count();
        }else{
            $rs= Db::name('laoshi_book')->where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Db::name("laoshi_book")->where('lid', Cookie::get('lid'))->count();
        }

        foreach ($rs as $v=>$a){
            $rs[$v]["dateline"] = friendlyDate($a["dateline"], 'mohu');
            $ls = Db::name('laoshi') ->where('id',$a["lid"])->find();
            $rs[$v]["lid"] = $ls["lsname"];

            $fl = Db::name('laoshi_book_class') ->where('id',$a["cid"])->find();
            $rs[$v]["cid"] = "<font color='".$fl["colour"]."'>".$fl["name"]."</font>";
        }
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function chanpinlist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        if (Cookie::get('cid') == 1){
            $rs= Db::name('chanpin')->order('id', 'Asc')->limit($s,$p)->select();
            $re['total'] = Db::name("chanpin")->count();
        }

        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function classlist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }
        $rs= Db::name('laoshi_class')->order('id', 'Asc')->limit($s,$p)->select();

        $re['total'] = Db::name("laoshi_class")->count();
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function zblaoshi(){
        $this->crecklogin();
        $rs= Db::name('laoshi_zbsetup')->select();
        $ls= Db::name('laoshi') ->where('id',$rs[0]["lid"])->find();
        $rs[0]["lsname"] = $ls["lsname"];
        if (!empty($rs[0]["zbdate"])){
            $rs[0]["zbdate"]=date("Y-m-d H:i:s",$rs[0]["zbdate"]);
        }

        $re['total'] = 1;
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function uploadpic(){
        $this->crecklogin();
        $folder =trim(input('post.folder'));
        $file= request()->file('file');
        if($file){
            if ($folder == "book"){
                $info = $file->move( '../public/static/uploads/'.$folder.'/');
            }else{
                $info = $file->move( '../uploads/'.$folder.'/');
            }

            if($info){
                // 成功上传后 获取上传信息
                // 输出 jpg
                $filename = $info->getSaveName();

            }else{
                // 上传失败获取错误信息
                $filename = $file->getError();

            }
        }

        $a = explode("/",$filename);
        $b = explode(".",$a[1]);


        if ($folder == "book"){
            $folder = $folder."/".$a[0];
            $img = '../public/static/uploads/'.$folder.'/'.$a[1];

            if ($b[1] == "jpg"){
                $filename = $a[0]."/".thumb($img,'../public/static/uploads/'.$folder,$a[1],300,300);
            }
        }else{
            $folder = $folder."/".$a[0];
            $img = '../uploads/'.$folder.'/'.$a[1];

            if ($b[1] == "jpg"){
                $filename = $a[0]."/".thumb($img,$folder,$a[1],300,300);
            }
        }

        $re['code']=0;
        $re['msg']='';
        $re['data']["src"]=$filename;

        return  json_encode($re);
    }

    public function uploadvideo(){
        $this->crecklogin();
        $folder =trim(input('post.folder'));
        $file= request()->file('file');
        if($file){
            $info = $file->move( '../uploads/'.$folder.'/');
            if($info){
                // 成功上传后 获取上传信息
                // 输出 jpg
                $filename = $info->getSaveName();

            }else{
                // 上传失败获取错误信息
                $filename = $file->getError();

            }
        }

        $temp = explode('/',$filename);

        $arry = $this->oss->uploadFile($this->webdb["bucket"],$this->webdb["ossvideoml"].$temp[1],'../uploads/'.$folder.'/'.$filename);

        //$arry = $this->oss->uploadFile($this->webdb["bucket"],"askzm/".$filenzme.".amr",$a);

        if ($arry["info"]["http_code"] == 200){
            $re['code']=0;
            $re['msg']='';
            $re['data']["src"]=$this->webdb["playurl"].$this->webdb["ossvideoml"].$temp[1];

            return  json_encode($re);
        }

    }

    public function uploadapp(){
        $this->crecklogin();
        //zsandroiduppgy,zsiosuppgy,zdandroiduppgy,zdiosuppgy,chandroiduppgy,chiosuppgy
        $folder =trim(input('app'));
        $tag =trim(input('tag'));
        $pgy =trim(input('post.pgy'));

        $folder = $folder."/".$tag;

        $file= request()->file('file');
        if($file){
            $info = $file->move( '../uploads/'.$folder.'/');
            if($info){
                // 成功上传后 获取上传信息
                // 输出 jpg
                $filename = $info->getSaveName();

            }else{
                // 上传失败获取错误信息
                $filename = $file->getError();

            }
        }

        $temp = explode('/',$filename);
        $arry = $this->oss->uploadFile($this->webdb["bucket"],"app/".$folder."/".$temp[1],'../uploads/'.$folder.'/'.$filename);

        if ($pgy == 1){
            $pgyapi = "https://www.pgyer.com/apiv2/app/upload";
            $post_data['_api_key']  = $this->webdb["pgyapikey"];
            $post_data['file']  = new CURLFile(realpath('../uploads/'.$folder.'/'.$filename));

            $res = $this->net->curl_request($pgyapi, $post_data);

            $res = json_decode($res,true);
        }

        if (trim(input('app')) == "ios"){
            $info = $this->getipainfo(Env::get('root_path').'uploads/'.$folder.'/'.$filename);
        }else{
            $info = $this->getapkinfo(Env::get('root_path').'uploads/'.$folder.'/'.$filename);
        }

        if ($arry["info"]["http_code"] == 200){
            $re['code']=0;
            $re['msg']='';
            $re['data']["url"]=$this->webdb["playurl"]."/app/".$folder."/".$temp[1];
            $re['data']["buildKey"]=$res["data"]["buildKey"];
            $re['data']["info"]=$info;

            return  json_encode($re);
        }
    }

    public function array_remove($data, $key){
        if(!array_key_exists($key, $data)){
            return $data;
        }
        $keys = array_keys($data);
        $index = array_search($key, $keys);
        if($index !== FALSE){
            array_splice($data, $index, 1);
        }
        return $data;

    }

    public function savelsinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");
        $data = $this->array_remove($data, "ujt");
        $data["password"] = getmd5($data["password"]);

        Db::name('laoshi')->insert($data);

        Ajson('添加成功!','0000');
    }

    public function savevideo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");
        $data = $this->array_remove($data, "ujt");
        $data = $this->array_remove($data, "clip");
        $data = $this->array_remove($data, "seek");
        $data = $this->array_remove($data, "duration");
        $data = $this->array_remove($data, "end");
        $data = $this->array_remove($data, "record");

        $data["dateline"] = time();
        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }
        $temp = $data;

        if(strstr($data["lid"],",")){
            $temp = $this->array_remove($temp, "lid");
            $list = explode(",",$data["lid"]);

            foreach ($list as $v=>$a){
                $temp["lid"] = $a;
                Db::name('laoshi_video')->cache('h5index')->insert($temp);
                //$url = "https://h5.haishunsh.com/?&yid=3";

                //$this->sendmsg($a,$data["title"],$url);
            }
        }else{
            $vid = Db::name('laoshi_video')->cache('h5index')->insertGetId($temp);

            if ($data["cid"] == 30){
                $ls = $this->getlaoshiinfo($data["lid"]);

                $tag[0]["tag"]=$data["lid"];

                $filter["where"]["and"][0]= array(
                    "or"=> $tag
                );

                $arr = array(
                    "filter"=>$filter,
                    "after_open"=>"go_custom",
                    "ticker"=>"新视频",
                    "title"=>"新视频",
                    "text"=>$ls["lsname"]."发布了".description($data["contents"],50),
                    "custom"=>$this->webdb["h5url"]."/video_detail?vid=".$vid,
                );

                $alert = array(
                    "title"=>"新视频",
                    "body"=>$ls["lsname"]."发布了".description($data["contents"],50)
                );

                $arrios = array(
                    "filter"=>$filter,
                    "alert"=>$alert,
                    "badge"=>1,
                    "sound"=>"chime",
                );

                $url = $this->webdb["h5url"]."/video_detail?vid=".$vid;

                if (!empty($this->androidappMasterSecret)){
                    //$send = json_decode($this->CallCenterSendAndroidBroadcast($arr),true);
                    $send = json_decode($this->CallCenterSendAndroidGroupcast($arr),true);
                }

                if (!empty($this->appMasterSecret)){
                    $sendios =json_decode($this->CallCenterSendIosGroupcast($arrios,$url),true);
                }

            }

        }

        Ajson('添加成功!','0000');
    }

    public function create_password($pw_length = 8){
        $randpwd = '';
        for ($i = 0; $i < $pw_length; $i++) {
            $randpwd .= chr(mt_rand(33, 126));
        }
        return $randpwd;
    }


    public function saveneican($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data["content"] = urldecode($data["content"]);
        $data["pw"] =mt_rand(100000,999999);

        Db::name('neican')->insert($data);

        Ajson('提交成功!新密码:'.$data["pw"],'0000');
    }

    public function savenneican($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        for($i=0;$i<7;$i++){
            $arr[$i]["title"] = $data["title[".$i."]"];
            $arr[$i]["imgs"] = $data["imgs[".$i."]"];
            $arr[$i]["content"] = $data["content[".$i."]"];

            if (mb_strlen($arr[$i]["content"])>1000){
                Ajson(mb_strlen($arr[$i]["content"], 'UTF-8').'字数超限!','0001');exit;
            }
        }

        $in["json"] = json_encode($arr);
        $in["pw"] =mt_rand(100000,999999);

        Db::name('neican')->insert($in);

        Ajson('提交成功!新密码:'.$in["pw"],'0000');
    }

    public function isIncludedImg($string){
        return preg_match('/<img.*?\/?>/is', $string) == 1;
    }

    public function savebook($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data["content"] = urldecode($data["content"]);
        $data["dateline"] = time();

        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }

        preg_match_all("/src=\"\/?(.*?)(jpg|jpeg|gif|bmp|bnp|png)\"/i",$data["content"],$match);

        if(empty($match[0][0])){ //进行正则匹配判断是否有图片
            Ajson('请在文章中包含图片!','0001');exit;
        }

        if (!preg_match_all("/([\x{4e00}-\x{9fa5}]+)/u", $data["content"], $match)){
            Ajson('您老真是惜字如金啊!','0001');exit;
        }

        if ($data["iszbimg"] == 1){
            if (empty($data["zbtime"])){
                Ajson('请设置直播时间!','0001');exit;
            }

            if (empty($data["zblid"])){
                Ajson('请设置直播老师!','0001');exit;
            }
        }

        $data["audiourl"] = contenttomp3($data["content"]);
        $data["zbtime"]=strtotime($data["zbtime"]);

//        $res = $this->baiduckneirong($data["content"]);
//        if ($res["spam"]>0){
//            Ajson('内容包含违禁内容!','0001');exit;
//        }

        $res = $bid = Db::name('laoshi_book')->order('id', 'Desc')->limit(0,10)->select();
        foreach ($res as $v=>$k){
            similar_text($k['title'], $data['title'], $percent); //比较相似度 存放于$percent
            if($percent>90){
                $cf=1;
                break;
            } //相似度高于90% 则判断重复
        }

        if($cf==1){
            Ajson('抱歉！禁止发布重复文章！','0001');exit;
        }

        $bid = Db::name('laoshi_book')->cache('h5index')->insertGetId($data);

        $ls = $this->getlaoshiinfo($data["lid"]);

        $tag[0]["tag"]=$data["lid"];

        $filter["where"]["and"][0]= array(
            "or"=> $tag
        );

        $arrzd = array(
            "filter"=>$filter,
            "after_open"=>"go_custom",
            "ticker"=>"新观点",
            "title"=>"新观点",
            "text"=>$ls["lsname"]."发布了".description($data["content"],50),
            "custom"=>$this->webdb["h5url"]."/viewpointdetail?bid=".$bid,
        );

        $alert = array(
            "title"=>"新观点",
            "body"=>$ls["lsname"]."发布了".description($data["content"],50)
        );

        $arrios = array(
            "filter"=>$filter,
            "alert"=>$alert,
            "badge"=>1,
            "sound"=>"chime",
        );

        $url = $this->webdb["h5url"]."/viewpointdetail?bid=".$bid;

        if (!empty($this->androidappMasterSecret)){
            //$send = json_decode($this->CallCenterSendAndroidBroadcast($arr),true);
            $send = json_decode($this->CallCenterSendAndroidGroupcast($arrzd),true);
        }

        if (!empty($this->appMasterSecret)){
            $sendios =json_decode($this->CallCenterSendIosGroupcast($arrios,$url),true);
        }

        Ajson('提交成功!','0000');
    }

    public function savechanpin($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");

        Db::name('chanpin')->insert($data);

        Ajson('提交成功!','0000');
    }

    public function savelschanpin($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "item_title");
        $data = $this->array_remove($data, "item_number");
        $data = $this->array_remove($data, "item_intime");
        $data = $this->array_remove($data, "item_inxq");
        $data = $this->array_remove($data, "item_outtime");
        $data = $this->array_remove($data, "item_outxq");

        $arry = json_decode($data["bbpz"],true);
        $num = count($arry);

        if ($num > 10){
            Ajson('配置数量不能大于10!');
        }

        $i = 0;
        foreach ($arry as $v){
            $i+= $v["number"];
        }

        if ($i > 100){
            Ajson('占比不能大于100!');
        }

        $rs= Db::name('laoshi_chanpin') ->where('lid',$data["lid"])->find();

        if ($rs){
            Ajson('一个老师只能有一个产品!');
        }

        Db::name('laoshi_chanpin')->insert($data);

        Ajson('添加成功!','0000');
    }

    public function savetopstock($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data["dateline"] = time();

        $count = Db::name('today_topstock') ->whereTime('dateline', 'today')->count();

        if ($count >= 1){
            Ajson('每天只能添加1次!');
        }

        Db::name('today_topstock')->insert($data);

        Ajson('添加成功!','0000');
    }

    public function topstockinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        $rs= Db::name('today_topstock') ->where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function lsinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('laoshi') ->where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);

    }

    public function lsvideoinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('laoshi_video') ->where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function zhiboinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('laoshi_zbsetup') ->where('id',$data["id"])->find();

        if (!empty($rs["zbdate"])){
            $rs["zbdate"]=date("Y-m-d H:i:s",$rs["zbdate"]);
        }

        Ajson('查询成功!','0000',$rs);
    }

    public function neicaninfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('neican') ->where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function bookinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('laoshi_book') ->where('id',$data["id"])->find();
        $rs["zbtime"]=date("Y-m-d H:i:s",$rs["zbtime"]);

        Ajson('查询成功!','0000',$rs);
    }

    public function chanpininfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('chanpin') ->where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function lschanpininfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('laoshi_chanpin') ->where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function lshfinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('laoshi_ask') ->where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function classinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $rs= Db::name('laoshi_class') ->where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function updatalsinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");
        $data = $this->array_remove($data, "ujt");
        $rs= Db::name('laoshi') ->where('id',$data["id"])->find();
        if ($data["password"] != $rs["password"]){
            $data["password"] = getmd5($data["password"]);
        }

        Db::name('laoshi')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function updatavideo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");
        $data = $this->array_remove($data, "ujt");
        $data = $this->array_remove($data, "clip");
        $data = $this->array_remove($data, "seek");
        $data = $this->array_remove($data, "duration");
        $data = $this->array_remove($data, "end");
        $data = $this->array_remove($data, "record");
        
        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }

        Db::name('laoshi_video')->cache('h5index')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function upzblaoshi($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");
        $data["zbdate"]=strtotime($data["zbdate"]);
        
        Db::name('laoshi_zbsetup')->where('id', 1)->update($data);

        Ajson('更新成功!','0000');
    }

    public function updataneican($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data["content"] = urldecode($data["content"]);
        Db::name('neican')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function updatanneican($encryParams){
        $data = json_decode($encryParams,true);

        for($i=0;$i<7;$i++){
            $arr[$i]["title"] = $data["title[".$i."]"];
            $arr[$i]["imgs"] = $data["imgs[".$i."]"];
            $arr[$i]["content"] = $data["content[".$i."]"];

            if (mb_strlen($arr[$i]["content"])>1000){
                Ajson(mb_strlen($arr[$i]["content"], 'UTF-8').'字数超限!','0001');exit;
            }
        }

        $in["json"] = json_encode($arr);
        Db::name('neican')->where('id', $data["id"])->update($in);

        Ajson('更新成功!','0000');
    }

    public function updatabook($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data["content"] = urldecode($data["content"]);
        //$data["dateline"] = time();
        
        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }

        preg_match_all("/src=\"\/?(.*?)(jpg|jpeg|gif|bmp|bnp|png)\"/i",$data["content"],$match);

        if(empty($match[0][0])){ //进行正则匹配判断是否有图片
            Ajson('请在文章中包含图片!','0001');exit;
        }

        if ($data["iszbimg"] == 1){
            if (empty($data["zbtime"])){
                Ajson('请设置直播时间!','0001');exit;
            }

            if (empty($data["zblid"])){
                Ajson('请设置直播老师!','0001');exit;
            }
        }

        $data["audiourl"] = contenttomp3($data["content"]);
        $data["zbtime"]=strtotime($data["zbtime"]);
        
        Db::name('laoshi_book')->cache('h5index')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function updatachanpin($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");

        Db::name('chanpin')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function updatalschanpininfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "item_title");
        $data = $this->array_remove($data, "item_number");
        $data = $this->array_remove($data, "item_intime");
        $data = $this->array_remove($data, "item_inxq");
        $data = $this->array_remove($data, "item_outtime");
        $data = $this->array_remove($data, "item_outxq");
        $arry = json_decode($data["bbpz"],true);
        $num = count($arry);

        if ($num > 10){
            Ajson('配置数量不能大于10!');
        }

        $i = 0;
        foreach ($arry as $v){
            $i+= $v["number"];
        }

        if ($i > 100){
            Ajson('占比不能大于100!');
        }
        
        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }

        Db::name('laoshi_chanpin')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function updatahfask($encryParams){
        //$this->crecklogin();
        //echo $encryParams;要
        $data = json_decode($encryParams,true);
        //hfadudio
        $filenzme = date('YmdHis').rand(0,9);

        if ($data["device"] == "android"){
            if (!strstr($data["hfadudio"], 'mp3')){
                $a = '../uploads/'.Config::get('upload.ask').'/'.$filenzme.".amr";
                $input = "askzm/".$filenzme.".amr";
                $output = "askzm/".$filenzme.".mp3";

                file_put_contents($a,base64_decode( $data["hfadudio"]));
                $arry = $this->oss->uploadFile($this->webdb["bucket"],"askzm/".$filenzme.".amr",$a);

                if ($arry["info"]["http_code"] == 200){
                    $zb = $this->osszm($input,$output,1,0,0,0,"mp3");
                    if ($zb["JobResultList"]["JobResult"][0]["Success"]){
                        $data["hfadudio"] = $this->webdb["playurl"]."/".$output;
                        $data = $this->array_remove($data, "device");
                    }else{
                        Ajson('异常1!','0011',$zb);
                    }
                }else{
                    Ajson('异常2!','0012',$arry);
                }
            }
        }else{
            file_put_contents('../uploads/'.Config::get('upload.ask').'/'.$filenzme.".mp3",base64_decode( $data["hfadudio"]));
            $data["hfadudio"] = $this->webdb["adminurl"]."/uploads/".Config::get('upload.ask')."/".$filenzme.".mp3";
        }

        $data["hit"] = mt_rand(80,400);

        Db::name('laoshi_ask')->cache('h5index')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function updataclassinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('laoshi_class')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function updatatopstock($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('today_topstock')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function deltopstock($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        Db::name('today_topstock')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function dellsinfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('laoshi')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function dellvideo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('laoshi_video')->cache('h5index')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function delneican($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('neican')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function delbook($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('laoshi_book')->cache('h5index')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function delchanpin($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('chanpin')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function dellschanpininfo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('laoshi_chanpin')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function dellshf($encryParams){
        //$this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('laoshi_ask')->cache('h5index')->where('id', $data["id"])->delete($data);
        $d["msg"] = '删除成功!';
        Ajson('删除成功!','0000',$d);
    }

    public function deloaclass($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('laoshi_class')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function delcomment($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Db::name('comment')->where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }
//截图
    public function osspic($oss_input_object,$oss_output_object,$Time,$new=''){
        $oss_location = 'oss-'.$this->webdb["location"];
        $oss_bucket = $this->webdb["bucket"];

        $Input = array(
            "Bucket"=>$oss_bucket,
            "Location"=>$oss_location,
            "Object"=>urlencode($oss_input_object)
        );

        $SnapshotConfig["OutputFile"] = array(
            "Bucket"=>$oss_bucket,
            "Location"=>$oss_location,
            "Object"=>urlencode($oss_output_object)
        );

        $SnapshotConfig["Time"] = $Time;

        $data = array(
            // 公共参数
            'Format' => 'JSON',
            'Version' => '2014-06-18',
            'AccessKeyId' => $this->accessKeyId,
            'SignatureVersion' => '1.0',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce'=> uniqid(),
            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),
            // 接口参数
            'Action' => 'SubmitSnapshotJob',
            'Input'=>json_encode($Input),
            'SnapshotConfig'=>json_encode($SnapshotConfig),
        );
        $data ['Signature'] = computeSignature ( $data, $this->accessKeySecret );
        // 发送请求（此处作了修改）
        $url = 'http://mts.'.$this->webdb["location"].'.aliyuncs.com/?' . http_build_query ( $data );
        $content=$this->net->curl_request($url);

        if ($this->webdb["aist"] == 1){

            $n = explode(".",$oss_output_object);

            $Input["Object"]=$oss_input_object;

            $Out = array(
                "Bucket"=>$oss_bucket,
                "Location"=>$oss_location,
                "Object"=>"hd/wxapp/ls/".$n[0].".jpg"
            );

            $CoverConfig["OutputFile"] = $Out;

            $a = new VideoAiModel();
            $b = $a->SubmitCoverJob(json_encode($Input),$this->webdb["aistpipeline"],json_encode($CoverConfig));

            $inarry = array(
                "Jobid"=>$b["JobId"],
                "file"=>$this->webdb["playurl"]."/".$new,
                "tag"=>"aist"
            );

            if (isset($b["JobId"])){
                Db::name('video_job')->insert($inarry);
            }
        }

        if ($this->webdb["aigif"] == 1){
            $n = explode(".",$oss_output_object);
            $Input["Object"]=$oss_input_object;

            $Out = array(
                "Bucket"=>$oss_bucket,
                "Location"=>$oss_location,
                "Object"=>"hd/wxapp/ls/".$n[0].".gif"
            );

            $CoverConfig["OutputFile"] = $Out;

            $a = new VideoAiModel();
            $b = $a->SubmitVideoGifJob(json_encode($Input),$this->webdb["aigifpipeline"],json_encode($CoverConfig));

            $inarry = array(
                "Jobid"=>$b["JobId"],
                "file"=>$this->webdb["playurl"]."/".$new,
                "tag"=>"aigif"
            );

            if (isset($b["JobId"])){
                Db::name('video_job')->insert($inarry);
            }
        }
        return json_decode($content,true);
    }
//OSS转码

    public function osszm($input,$output,$clip=1,$seek=0,$duration=0,$end=0,$try = "mp4"){
        $pipeline_id = $this->webdb["pipeline"];//1bcde8a0f7284fe0836ee9c1d2b3f1c1 1d2e4900289e4e979b1c656d81baddaf
        $template_id = $this->webdb["template"];//S00000002-200030  S00000001-200020
        $oss_location = 'oss-'.$this->webdb["location"];
        $oss_bucket = $this->webdb["bucket"];

        if ($try == "mp3"){
            $template_id = $this->webdb["templatemp3"];
        }

        $oss_input_object = trim($input);
        $oss_output_object = trim($output);

        $Input = array(
            "Bucket"=>$oss_bucket,
            "Location"=>$oss_location,
            "Object"=>urlencode($oss_input_object)
        );

        $output = array('OutputObject' => urlencode($oss_output_object));
        $output['Container'] = array('Format' => 'mp4');
        $output['TemplateId'] = $template_id;

        if ($clip == 0){
            $output['Clip'] = array(
                "TimeSpan"=>array(
                    "Seek"=>$seek,
                    "Duration"=>$duration,
                )
            );

            if (!empty($end)){
                $output['Clip']["TimeSpan"]["End"] = $end;
            }

            if ($duration== "00:00:00"){
                $output['Clip']["TimeSpan"] = $this->array_remove($output['Clip']["TimeSpan"], "Duration");
            }

        }
        $outputs = array($output);

        $data = array(
            // 公共参数
            'Format' => 'JSON',
            'Version' => '2014-06-18',
            'AccessKeyId' => $this->accessKeyId,
            'SignatureVersion' => '1.0',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce'=> uniqid(),
            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),
            // 接口参数
            'Action' => 'SubmitJobs',
            'Input'=>json_encode($Input),
            'Outputs'=>json_encode($outputs),
            'OutputBucket'=>$oss_bucket,
            'OutputLocation'=>$oss_location,
            'PipelineId'=>$pipeline_id
        );

        $data ['Signature'] = computeSignature ( $data, $this->accessKeySecret );
        // 发送请求（此处作了修改）
        $url = 'http://mts.'.$this->webdb["location"].'.aliyuncs.com/?' . http_build_query ( $data );

        $content=$this->net->curl_request($url);

        if ($this->webdb["aiasr"] == 1){

            $Input["Object"]=$oss_input_object;

            $a = new VideoAiModel();
            $b = $a->SubmitAsrJob(json_encode($Input),$this->webdb["aiasrpipeline"]);

            $inarry = array(
                "Jobid"=>$b["JobId"],
                "file"=>$this->webdb["playurl"]."/".$oss_input_object,
                "tag"=>"aiasr"
            );

            if (isset($b["JobId"])){
                Db::name('video_job')->insert($inarry);
            }
        }
        return json_decode($content,true);
    }

    public function zm(){
        $oss_input_object = trim(input('post.input_object'));
        $oss_output_object = trim(input('post.output_object'));
        $autopic = trim(input('post.autopic'));
        $autofolder = trim(input('post.autofolder'));
        $clip = trim(input('post.clip'));
        $seek = trim(input('post.seek'));
        $duration = trim(input('post.duration'));
        $end = trim(input('post.end'));

        $t = explode(".", $oss_output_object);
        $a = explode('/', $t[0]);

        if ($clip == ""){
            $clip = 1;
        }

        $rs = Db::name('laoshi_video')->where('videourl',$this->webdb["playurl"]."/".$oss_output_object)->find();
        if (empty($rs)){
            if ($clip == 0){
                $zb=$this->osszm($oss_input_object,$oss_output_object,$clip,$seek,$duration,$end);
            }else{
                $zb=$this->osszm($oss_input_object,$oss_output_object);
            }

            if ($autopic == 0){
                $outfile = date( "Ymd")."/".$a[3].".jpg";
                $jt = $this->osspic($oss_input_object,$a[3].".jpg",5,$oss_output_object);
                //
                $save_to = '../uploads/'.$autofolder.'/'.$outfile;

                $basedir = '../uploads/'.$autofolder.'/'.date( "Ymd");
                if(!is_dir($basedir))mkdir($basedir);


                $content = file_get_contents($this->webdb["playurl"]."/".$jt["SnapshotJob"]["SnapshotConfig"]["OutputFile"]["Object"]);
                file_put_contents($save_to, $content);

                $folder = $autofolder."/".date( "Ymd");
                $img = '../uploads/'.$folder.'/'.$a[3].".jpg";

                $filename = date( "Ymd")."/".thumb($img,$folder,$a[3].".jpg",300,300);

            }

            $data = array(
                "JobId"=>$zb["JobResultList"]["JobResult"][0]["Job"]["JobId"],
                "videourl"=>$oss_output_object,
                "dateline"=>time()
            );

            Db::name('laoshi_video_zm')->insert($data);

            //$jobinfo = $this->getzmztforone($zb["JobResultList"]["JobResult"][0]["Job"]["JobId"]);

            if ($zb["JobResultList"]["JobResult"][0]["Success"]){
                Ajson('转码提交成功!','0000',$filename);
            }else{
                Ajson('转码提交失败!','0001');
            }
        }else{
            Ajson('无需转码!','0000');
        }
    }

    public function commentlist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = ($s-1) * $p;
        }

        $emoji = new Emoji();
        $comment= Db::name('comment')->order('id', 'Desc')->limit($s,$p)->select();
        foreach ($comment as $b=>$c){
            $comment[$b]["dateline"] = friendlyDate($c["dateline"], 'mohu');
            $comment[$b]["content"]=$emoji->Decode($c["content"]);
            if ($comment[$b]["tag"] == "book"){
                $comment[$b]["tag"] = "观点";
            }else{
                $comment[$b]["tag"] = "用户提问";
            }
        }
        $re['total'] = Db::name("comment")->count();
        $re['rows']=$comment;

        return  json_encode($re);

    }

    public function zmlist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        $rs= Db::name('laoshi_video_zm')->order('id', 'Desc')->limit($s,$p)->select();
        $re['total'] = Db::name("laoshi_video_zm")->count();

        $Joblist = array();

        $count = count($rs);
        $k = 0;
        foreach ($rs as $v=>$a){
            if ( $v+1 == $count){
                $Joblist[$k] .= $a["JobId"];
            }else{
                $Joblist[$k] .= $a["JobId"].",";
            }

            if (($v+1)%10 == 0){
                $k+=1;
            }
        }

        $job = $this->getzmzt($Joblist);

        foreach ($rs as $v=>$a){
            foreach ($job as $b=>$c){
                if ($a["JobId"] == $c["JobId"]){
                    $rs[$v]["State"] = $this->State($c["State"]);
                    $rs[$v]["Input"] = urldecode($c["Input"]["Object"]);
                    $rs[$v]["Output"] = urldecode($c["Output"]["OutputFile"]["Object"]);
                    $rs[$v]["dateline"] = friendlyDate($rs[$v]["dateline"], 'mohu');
                    $rs[$v]["duration"] = $this->sec2time($c["Output"]["Properties"]["Streams"]["VideoStreamList"]["VideoStream"][0]["Duration"]);
                    $vv= Db::name('laoshi_video')->where('videourl',$this->webdb["playurl"]."/".$rs[$v]["Output"])->find();
                    $rs[$v]["title"] = $vv["title"];
                    if (empty($rs[$v]["title"])){
                        $rs[$v]["title"] = "未知";
                    }
                }
            }
        }

        $re['rows']=$rs;
        return  json_encode($re);
    }



    function Sec2Time($time){
        if(is_numeric($time)){
            $value = array(
                "years" => 0, "days" => 0, "hours" => 0,
                "minutes" => 0, "seconds" => 0,
            );
            if($time >= 31556926){
                $value["years"] = floor($time/31556926);
                $time = ($time%31556926);
            }
            if($time >= 86400){
                $value["days"] = floor($time/86400);
                $time = ($time%86400);
            }
            if($time >= 3600){
                $value["hours"] = floor($time/3600);
                $time = ($time%3600);
            }
            if($time >= 60){
                $value["minutes"] = floor($time/60);
                $time = ($time%60);
            }
            $value["seconds"] = floor($time);
            //return (array) $value;
            if ($value["hours"] == 0){
                $t=$value["minutes"] .":".$value["seconds"];
            }else{
                $t=$value["hours"] .":". $value["minutes"] .":".$value["seconds"];
            }

            Return $t;

        }
    }

    public function State($State){
        switch ($State){
            case "Submitted":
                return "已提交";
            case "Transcoding":
                return "<font color=\"red\">转码中</font>";
            case "TranscodeSuccess":
                return "<font color=\"green\">转码成功</font>";
            case "TranscodeFail":
                return "转码失败";
            case "TranscodeCancelled":
            default:
                return "所有状态";
        }
    }

    public function getzmztforone($Job){
        $data = array(
            // 公共参数
            'Format' => 'JSON',
            'Version' => '2014-06-18',
            'AccessKeyId' => $this->accessKeyId,
            'SignatureVersion' => '1.0',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce'=> uniqid(),
            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),
            'JobIds'=>$Job,
            // 接口参数
            'Action' => 'QueryJobList'
        );
        $data['Signature'] = computeSignature ( $data, $this->accessKeySecret );
        // 发送请求（此处作了修改）
        $url = 'http://mts.'.$this->webdb["location"].'.aliyuncs.com/?' . http_build_query ( $data );

        $content=$this->net->curl_request($url);

        $re = json_decode($content,true);

        return $re["JobList"]["Job"][0];
    }

    public function getzmzt($Joblist){

        foreach ($Joblist as $v=>$a){
            $data = array(
                // 公共参数
                'Format' => 'JSON',
                'Version' => '2014-06-18',
                'AccessKeyId' => $this->accessKeyId,
                'SignatureVersion' => '1.0',
                'SignatureMethod' => 'HMAC-SHA1',
                'SignatureNonce'=> uniqid(),
                'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),
                'JobIds'=>$a,
                // 接口参数
                'Action' => 'QueryJobList'
            );
            $data['Signature'] = computeSignature ( $data, $this->accessKeySecret );
            // 发送请求（此处作了修改）
            $url = 'http://mts.'.$this->webdb["location"].'.aliyuncs.com/?' . http_build_query ( $data );
            $nodes[$v]["url"] = $url;
        }

        $content = $this->net->postMulti($nodes,$timeOut = 5);

        $job = array();
        foreach ($content as $v){
            $temp = json_decode($v,true);
            $job = array_merge($job,$temp["JobList"]["Job"]);
        }

        return $job;
    }

    public function sendmsg($encryParams){
        ignore_user_abort(true); // 忽略客户端断开
        set_time_limit(0);    // 设置执行不超时

        $data = json_decode($encryParams,true);
        $url = "https://h5.haishunsh.com/?yid=3";

        $templateId = '9pGbxH1l4ADa-w2WdV_02h';
        //$url = file_get_contents("http://suo.im/api.php?url=".$url);
        //$urljson = json_decode($urljson,true);
        //$url = $urljson["url_short"];

        $ls = $this->getlaoshiinfo($data["lid"]);
//overdue."}Array{"url":"/sendNotify.json","code":1011,"errorMessage":"your sms package is overdue."}
        //{p1}发表了一个:《{p2}》 {p3}

        $rs= Db::name('laoshi')->where('mobile',"<>",'')->order('id', 'Asc')->select();
        foreach ($rs as $v=>$a){
            if (!empty($a["mobile"])){
                //$rong= $this->rongCloud->SMS()->sendTongZhi($a["mobile"], $templateId, '86',$ls["lsname"],$data["title"]);
                //echo $rong;
                //echo json_decode($rong,true);
            }
        }

        $rs1= Db::name('oa_user')->where('mobile',"<>",'')->order('id', 'Asc')->select();
        foreach ($rs1 as $vv=>$b){
            if (!empty($b["mobile"])){
                //$rong= $this->rongCloud->SMS()->sendTongZhi($b["mobile"], $templateId, '86',$ls["lsname"],$data["title"],$url);
                //echo $rong;
                //echo json_decode($rong,true);
            }
        }
    }

    public function getlaoshiinfo($lid){
        $rs= Db::name('laoshi') ->where('id',$lid)->find();
        $rs["lsimg"] = $this->webdb["adminurl"].'/uploads/ls/'.$rs["lsimg"];
        $rs["lspic"] = $this->webdb["adminurl"].'/uploads/ls/'.$rs["lspic"];
        $rs["lsfmpic"] = $this->webdb["adminurl"].'/uploads/ls/'.$rs["lsfmpic"];
        $rs["bg"] = $this->webdb["adminurl"].'/uploads/ls/T_bg.jpg';
        $rs["gif"] = $this->webdb["adminurl"].'/uploads/ls/20180412164046.gif';

        $lskc = Db::name('chanpin') ->where('id',$rs["cpid"])->find();
        $rs["lskc"] = $lskc;

        return $rs;
    }

    public function zbzt(){
        return $this->fetch();
    }

    public function zbztlist(){
        $s = intval(input('page'));
        $p = intval(input('rows'));

        $a = new ZhiboModel();
        $rs= $a->getzbinfo($this->webdb["ggrtmp"],$s,$p);

        $re['total'] = $rs["TotalNum"];
        $re['rows']=$rs["OnlineInfo"]["LiveStreamOnlineInfo"];

        return  json_encode($re);
    }

    public function lltb(){
        $a = new ZhiboModel();
        $rs= $a->OnlineUserNum($this->webdb["ggrtmp"]);
        $this->assign('a',$rs);

        return $this->fetch();
    }

    public function dktb(){
        $a = new ZhiboModel();
        $rs= $a->OnlineUserNum($this->webdb["ggrtmp"]);
        $this->assign('a',$rs);
        return $this->fetch();
    }

    public function exlin(){
        $starttime = trim(input('starttime'));
        $endtime = trim(input('endtime'));

        if (!empty($starttime)&&!empty($endtime)){
            if(strtotime($endtime)<strtotime($starttime)){
                Ajson('结束日期必须大于开始日期!','0001');
            }
            $starttime = gmdate ( 'Y-m-d\TH:i:s\Z',strtotime($starttime));
            $endtime = gmdate ( 'Y-m-d\TH:i:s\Z',strtotime($endtime));
        }

        if (empty($starttime)){
            $starttime = gmdate ( 'Y-m-d\T00:00:00\Z' );
        }

        if (empty($endtime)){
            $endtime = gmdate ( 'Y-m-d\TH:i:s\Z' );
        }

        $a = new ZhiboModel();
        $rs= $a->BpsData($this->webdb["ggrtmp"],$starttime,$endtime);
        $a->data = array_remove($a->data, "Signature");
        $rs1= $a->TrafficData($this->webdb["ggrtmp"],$starttime,$endtime);

        $b = array();
        foreach ($rs["BpsDataPerInterval"]["DataModule"] as $v=>$a){
            $t=str_replace("T"," ",$a["TimeStamp"]);
            $t=str_replace("Z","",$t);
            $b["TimeStamp"][] = $t;
            $n = (float)$a["BpsValue"]/1000000;
            $b["BpsValue"][$v]["value"] = (int)$n;
            $b["BpsValue"][$v]["name"] = "Mbps";
        }

        $d = array();
        foreach ($rs1["TrafficDataPerInterval"]["DataModule"] as $e=>$f){
            $t=str_replace("T"," ",$f["TimeStamp"]);
            $t=str_replace("Z","",$t);
            $d["TimeStamp"][] = $t;
            $d["TrafficValue"][$e]["value"] = $this->getFilesize($f["TrafficValue"]);
            $d["TrafficValue"][$e]["name"] = "GB";
        }

        $re["BpsData"] = $b;
        $re["TrafficData"] = $d;

        return json_encode($re);
    }

    function getFilesize($num){
        $num /= pow(1024, 3);
        return number_format($num, 3);
    }

    public function getzbinfo($dnamen,$name){
        $data = array(
            // 公共参数
            'Format' => 'JSON',
            'Version' => '2016-11-01',
            'AccessKeyId' => $this->accessKeyId,
            'SignatureVersion' => '1.0',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce'=> uniqid(),
            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),
            // 接口参数
            'Action' => 'DescribeLiveStreamsOnlineList',
            'DomainName'=>$dnamen
        );

        $data ['Signature'] = computeSignature ( $data, $this->accessKeySecret );
        // 发送请求（此处作了修改）
        $url = 'http://live.aliyuncs.com/?' . http_build_query ( $data );

        $content=$this->net->curl_request($url);
        $zb=json_decode($content,true);

        $re = 0;

        $data2 = array(
            // 公共参数
            'Format' => 'JSON',
            'Version' => '2016-11-01',
            'AccessKeyId' => $this->accessKeyId,
            'SignatureVersion' => '1.0',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce'=> uniqid(),
            'Timestamp' => gmdate ( 'Y-m-d\TH:i:s\Z' ),
            // 接口参数
            'Action' => 'DescribeLiveStreamsFrameRateAndBitRateData',
            'DomainName'=>$dnamen
        );
        $data2 ['Signature'] = computeSignature ( $data2, $this->accessKeySecret );
        $url2 = 'http://live.aliyuncs.com/?' . http_build_query ( $data2 );

        $content2=$this->net->curl_request($url2);
        $zb2=json_decode($content2,true);

        foreach($zb["OnlineInfo"]["LiveStreamOnlineInfo"] as $k => $v){
            foreach ($zb2["FrameRateAndBitRateInfos"]["FrameRateAndBitRateInfo"] as $c=>$d){
                if($v["PublishUrl"] == $d["StreamUrl"]){
                    $zb["OnlineInfo"]["LiveStreamOnlineInfo"][$k]["AudioFrameRate"] = $d["AudioFrameRate"];
                    $zb["OnlineInfo"]["LiveStreamOnlineInfo"][$k]["Time"] = $d["Time"];
                    $zb["OnlineInfo"]["LiveStreamOnlineInfo"][$k]["BitRate"] = $d["BitRate"];
                    $zb["OnlineInfo"]["LiveStreamOnlineInfo"][$k]["VideoFrameRate"] = $d["VideoFrameRate"];
                }
            }
        }

        return $zb;
    }

    public function block(){
        return $this->fetch();
    }

    public function rtmpblocklist(){
        $s = intval(input('page'));
        $p = intval(input('rows'));

        $a = new ZhiboModel();
        $rs= $a->BlockList($this->webdb["ggrtmp"],$s,$p);

        $re['total'] = $rs["TotalNum"];

        foreach ($rs["StreamUrls"]["StreamUrl"] as $v=>$a){
            $re['rows'][$v]["StreamUrl"] = $a;
            $b = explode("/",$a);
            $re['rows'][$v]["do"]=$b[0]."@".$b[1]."@".$b[2];
        }

        return  json_encode($re);
    }

    public function rtmp(){
        $do = trim(input('do'));
        $name = trim(input('name'));
        $a = new ZhiboModel();
        $b = explode("@",$name);
        if ($do == "stop"){
            $c = $a->ForbidLiveStream($b[0],$b[1],$b[2]);
        }else{
            $c = $a->ResumeLiveStream($b[0],$b[1],$b[2]);
        }

        Ajson('ok','0000',$c);
    }

    public function nneican(){
        return $this->fetch();
    }

    public function getipainfo($filename){
        // 遍历zip包中的Info.plist文件
        //$iosfilename = Env::get('root_path')."uploads/ios/zs/20180720/30b64468b3217ed2c6a1c22abba9e4e2.ipa";
        $z = new Zipper();
        $zipFiles = $z->make($filename)->listFiles('/Info\.plist$/i');
        $moFiles  = $z->make($filename)->listFiles('/embedded\.mobileprovision/i');

        if ($moFiles){
            foreach ($moFiles as $k => $filePath) {
                if (preg_match("/Payload\/([^\/]*)\/embedded\.mobileprovision/i", $filePath, $matches)) {
                    $app_folder = $matches[1];
                    // 将plist文件解压到ipa目录中的对应包名目录中
                    $z->make($filename)->folder('Payload/'.$app_folder)->extractMatchingRegex(Env::get('runtime_path').'/temp/mobileprovision/'.$app_folder, "/embedded\.mobileprovision/i");
                    // 拼接plist文件完整路径
                    $fp = Env::get('runtime_path').'/temp/mobileprovision/'.$app_folder.'/embedded.mobileprovision';
                    $content = file_get_contents($fp);

                    if (preg_match("/<plist version=\"1.0\">(.*?)<\/plist>/s", $content, $match)){
                        $objectxml = simplexml_load_string($match[0]);//将文件转换成 对象
                        $xmljson= json_encode($objectxml );//将对象转换个JSON
                        $xmlarray=json_decode($xmljson,true);//将json转换成数组
                        $tag = "个人/公司";

                        if(strpos($xmlarray["dict"]["string"][1],'iOS Team Inhouse Provisioning') !==false){
                            $tag = "<span class=\"label label-radius signature_in_house\">企业</span>";
                        }

                        if(strpos($xmlarray["dict"]["string"][1],'iOS Team Ad Hoc Provisioning') !==false){
                            $tag = "<span class=\"label label-radius signature_adhoc\">内测</span>";
                        }
                    }
                }
            }
        }

        $matched = 0;
        if ($zipFiles) {
            foreach ($zipFiles as $k => $filePath) {
                // 正则匹配包根目录中的Info.plist文件
                if (preg_match("/Payload\/([^\/]*)\/Info\.plist$/i", $filePath, $matches)) {
                    $matched = 1;

                    $app_folder = $matches[1];

                    // 将plist文件解压到ipa目录中的对应包名目录中
                    $z->make($filename)->folder('Payload/'.$app_folder)->extractMatchingRegex(Env::get('runtime_path').'/temp/plist/'.$app_folder, "/Info\.plist$/i");
                    // 拼接plist文件完整路径
                    $fp = Env::get('runtime_path').'/temp/plist/'.$app_folder.'/Info.plist';

                    // 获取plist文件内容
                    $content = file_get_contents($fp);

                    // 解析plist成数组
                    $ipa = new \CFPropertyList\CFPropertyList();
                    $ipa->parse($content);
                    $ipaInfo = $ipa->toArray();

                    // ipa 解包信息
                    //$ipa_data_bak = json_encode($ipaInfo);

                    // 包名
                    $package_name = $ipaInfo['CFBundleIdentifier'];

                    // 版本名
                    $version_name = $ipaInfo['CFBundleShortVersionString'];

                    // 版本号
                    $version_code = str_replace('.', '', $ipaInfo['CFBundleShortVersionString']);

                    // 别名
                   // $bundle_name = $ipaInfo['CFBundleName'];

                    // 显示名称
                    $display_name =  $ipaInfo['CFBundleDisplayName'];
                }
            }

        }

        $arr = array(
            "appname"=>$display_name,
            "package_name"=>$package_name,
            "version_name"=>$version_name,
            "version_code"=>$version_code,
            "tag"=>$tag,
        );

        return $arr;
    }

    public function getapkinfo($filename){
        $apk = new \ApkParser\Parser($filename);
        $manifest = $apk->getManifest();
       // $appname = $manifest->getAppName();
// 包名
        $package_name = $manifest->getPackageName();
// 版本号
        $version_name = $manifest->getVersionName();
// 版本编号
        $version_code = $manifest->getVersionCode();

        $arr = array(
            "package_name"=>$package_name,
            "version_name"=>$version_name,
            "version_code"=>$version_code,
        );

        return $arr;
    }


    /**
     * 获取 APK 包信息和应用图标（需要exec支持）
     */
    public function apkParseInfo($apk) {

        $aapt = '/usr/bin/aapt';

        if( FALSE === is_file($aapt) ) {
            return FALSE;
        }

        exec("{$aapt} d badging {$apk}", $output, $return);

        // 解析错误
        if ( $return !== 0 ) {
            return FALSE;
        }

        $output = implode(PHP_EOL, $output);

        $apkinfo = new stdClass;

        // 对外显示名称
        $pattern = "/application: label='(.*)'/isU";
        $results = preg_match($pattern, $output, $res);
        $apkinfo->label = $results ? $res[1] : '';

        // 内部名称，软件唯一的
        $pattern = "/package: name='(.*)'/isU";
        $results = preg_match($pattern, $output, $res);
        $apkinfo->sys_name = $results ? $res[1] : '';

        // 内部版本名称，用于检查升级
        $pattern = "/versionCode='(.*)'/isU";
        $results = preg_match($pattern, $output, $res);
        $apkinfo->version_code = $results ? $res[1] : 0;

        // 对外显示的版本名称
        $pattern = "/versionName='(.*)'/isU";
        $results = preg_match($pattern, $output, $res);
        $apkinfo->version = $results ? $res[1] : '';

        // 系统支持
        $pattern = "/sdkVersion:'(.*)'/isU";
        $results = preg_match($pattern, $output, $res);
        $apkinfo->sdk_version = $results ? $res[1] : 0;

        // 分辨率支持
        $densities = array(
            "/densities: '(.*)'/isU",
            "/densities: '120' '(.*)'/isU",
            "/densities: '160' '(.*)'/isU",
            "/densities: '240' '(.*)'/isU",
            "/densities: '120' '160' '(.*)'/isU",
            "/densities: '160' '240' '(.*)'/isU",
            "/densities: '120' '160' '240' '(.*)'/isU"
        );

        foreach($densities AS $k=>$v) {
            if( preg_match($v, $output, $res) ) {
                $apkinfo->densities[] = $res[1];
            }
        }

        // 应用权限
        $pattern = "/uses-permission:'(.*)'/isU";
        $results = preg_match_all($pattern, $output, $res);
        $apkinfo->permissions = $results ? $res[1] : '';

        // 需要的功能（硬件支持）
        $pattern = "/uses-feature:'(.*)'/isU";
        $results = preg_match_all($pattern, $output, $res);
        $apkinfo->features = $results ? $res[1] : '';

        // 应用图标路径
        if( preg_match("/icon='(.+)'/isU", $output, $res) ) {

            $icon_draw = trim( $res[1] );
            $icon_hdpi = 'res/drawable-hdpi/' . basename($icon_draw);

            $temp = $this->_file_save_path . basename($apk, '.apk') . DIRECTORY_SEPARATOR;

            if( @is_dir($temp) === FALSE ) {
                create_dir($temp);
            }

            exec("unzip {$apk} {$icon_draw} -d " . $temp);
            exec("unzip {$apk} {$icon_hdpi} -d " . $temp);

            $apkinfo->icon = $icon_draw;

            $icon_draw_abs = $temp . $icon_draw;
            $icon_hdpi_abs = $temp . $icon_hdpi;

            $apkinfo->icon = @is_file($icon_hdpi_abs) ? $icon_hdpi_abs : $icon_draw_abs;
        }

        return $apkinfo;
    }

        public function debug(){
        // echo base64_encode ( hash_hmac ( 'sha1', $stringToSign, $accessKeySecret . '&', true ) );
        //$str = '{"type":"result","domain":"live.haishunsh.com","app":"pc","stream":"aliyuns","tag":"online","data":{"beginTime":196373,"endTime":214523,"sentenceId":12,"statusCode":1,"text":"我们在研究中东呢，给大家分享天赋人员，欢迎大家换种嗯好那关于就是今天的研报的话，那老师也是跟大家就是做了一个详尽的一个解释，那在我们平常就是大家在看研报的过程当中，的话的老师有没有一些心得和","targetText":"","asrRequestId":"d9ba12167fd643f6871e101f589b2494"}}';
            //$b = new ZhiboModel();

            //return $b->ZhiboAi($str);
//        $a = new VideoAiModel();
//       // $c = $a->AddPipeline("aigif","AIVideoGif");
//        //var_dump($c);
//            $oss_location = 'oss-'.$this->webdb["location"];
//            $oss_bucket = $this->webdb["bucket"];
//            //$PipelineId = "ec51362c79cd4d11b78cb14314ce7696";//aist
//            //$PipelineId = "b96be52a8ba348e2bfd0449e94921388";//aiasr
//            $PipelineId = "642a654465a14676bc6a5627c98e2d8c";//aigif
//
//            $Input = array(
//                "Bucket"=>$oss_bucket,
//                "Location"=>$oss_location,
//                "Object"=>"hd/wxapp/ls/o_1ci1r83um1l5b3hn1dp111f316m6e1.mp4"
//            );
//
//            $Output = array(
//                "Bucket"=>$oss_bucket,
//                "Location"=>$oss_location,
//                "Object"=>"hd/wxapp/ls/o_1ci1r83um1l5b3hn1dp111f316m6e1.gif"
//            );
//
//            $CoverConfig["OutputFile"] = $Output;

            //$b = $a->SubmitCoverJob(json_encode($Input),$PipelineId,json_encode($CoverConfig));
           // $d = $a->SubmitAsrJob(json_encode($Input),$PipelineId);
            //$f = $a->SubmitVideoGifJob(json_encode($Input),$PipelineId,json_encode($CoverConfig));
//echo $b;
            //var_dump($f);

//            $c = $a->QueryCoverJobList("205192ac06454872a7d3d9b420e53fbd,");
//           var_dump($c);
            //98d34598e9c243d18de464725b9a2f58
//            $e = $a->QueryAsrJobList("98d34598e9c243d18de464725b9a2f58,");
            //$g = $a->QueryVideoGifJobList("89fc6d64bf68493989fe99a893e6784d");
           //var_dump($g);
//
//            $State = $e["JobList"]["Job"][0]["State"];
//            $AsrText = $e["JobList"]["Job"][0]["AsrResult"]["AsrTextList"]["AsrText"];
//            if ($State == "Success"){
//               foreach ($AsrText as $v=>$a){
//                   echo $this->Sec2Time($a["StartTime"]).$this->Sec2Time($a["EndTime"]).$a["Text"]."<br>";
//               }
//           }

//
//           $State = $c["CoverJobList"]["CoverJob"][0]["State"];
//           $CoverImage = $c["CoverJobList"]["CoverJob"][0]["CoverImageList"]["CoverImage"];
//
//           if ($State == "Success"){
//               foreach ($CoverImage as $v=>$a){
//                   echo $a["Time"].$a["Url"].$a["Score"];
//               }
//           }


        //$a = new Roommsgtoexl();
        //$a->run();
       // $filename = Env::get('root_path')."uploads/android/zs/20180720/f4ec1eafbec688f2ded750a2f0a1856d.apk";

//        var_dump($this->apkParseInfo($filename));
//
//
//        // 遍历zip包中的Info.plist文件
//        $iosfilename = Env::get('root_path')."uploads/ios/zs/20180720/30b64468b3217ed2c6a1c22abba9e4e2.ipa";
//        $z = new Zipper();
//        $zipFiles = $z->make($iosfilename)->listFiles('/Info\.plist$/i');
//
//        $matched = 0;
//        if ($zipFiles) {
//            foreach ($zipFiles as $k => $filePath) {
//                // 正则匹配包根目录中的Info.plist文件
//                if (preg_match("/Payload\/([^\/]*)\/Info\.plist$/i", $filePath, $matches)) {
//                    $matched = 1;
//
//                    $app_folder = $matches[1];
//
//                    // 将plist文件解压到ipa目录中的对应包名目录中
//                    $z->make($iosfilename)->folder('Payload/'.$app_folder)->extractMatchingRegex(Env::get('runtime_path').'/temp/plist/'.$app_folder, "/Info\.plist$/i");
//                    // 拼接plist文件完整路径
//                    $fp = Env::get('runtime_path').'/temp/plist/'.$app_folder.'/Info.plist';
//
//                    // 获取plist文件内容
//                    $content = file_get_contents($fp);
//
//                    // 解析plist成数组
//                    $ipa = new \CFPropertyList\CFPropertyList();
//                    $ipa->parse($content);
//                    $ipaInfo = $ipa->toArray();
//
//                    // ipa 解包信息
//                    $ipa_data_bak = json_encode($ipaInfo);
//
//                    // 包名
//                    $package_name = $ipaInfo['CFBundleIdentifier'];
//
//                    // 版本名
//                    $version_name = $ipaInfo['CFBundleShortVersionString'];
//
//                    // 版本号
//                    $version_code = str_replace('.', '', $ipaInfo['CFBundleShortVersionString']);
//
//                    // 别名
//                    $bundle_name = $ipaInfo['CFBundleName'];
//
//                    // 显示名称
//                    $display_name =  $ipaInfo['CFBundleDisplayName'];
//                }
//            }
//
//        }
//
//        var_dump($ipaInfo);

    }

    public function CallCenterSendAndroidBroadcast($alert) {

        try {
            $this->androidpuchBroadcast->setAppMasterSecret($this->androidappMasterSecret);
            $this->androidpuchBroadcast->setPredefinedKeyValue("appkey",$this->androidappkey);
            $this->androidpuchBroadcast->setPredefinedKeyValue("timestamp",$this->timestamp);

            foreach ($alert as $key=>$v){
                $this->androidpuchBroadcast->setPredefinedKeyValue($key,$v);
            }
            $this->androidpuchGroupcast->setExtraField("pushType",0);
            $this->androidpuchBroadcast->setPredefinedKeyValue("production_mode", "false");

            return  $this->androidpuchBroadcast->send();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    private function CallCenterSendAndroidGroupcast($alert){
        try {
            $this->androidpuchGroupcast->setAppMasterSecret($this->androidappMasterSecret);
            $this->androidpuchGroupcast->setPredefinedKeyValue("appkey",$this->androidappkey);
            $this->androidpuchGroupcast->setPredefinedKeyValue("timestamp",$this->timestamp);

            foreach ($alert as $key=>$v){
                $this->androidpuchGroupcast->setPredefinedKeyValue($key,$v);
            }
            $this->androidpuchGroupcast->setExtraField("pushType",0);
            $this->androidpuchGroupcast->setPredefinedKeyValue("production_mode", "false");

            return  $this->androidpuchGroupcast->send();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    private function CallCenterSendIosGroupcast($alert,$url){
        try {
            $this->iospuchGroupcast->setAppMasterSecret($this->appMasterSecret);
            $this->iospuchGroupcast->setPredefinedKeyValue("appkey",$this->appkey);
            $this->iospuchGroupcast->setPredefinedKeyValue("timestamp",$this->timestamp);

            foreach ($alert as $key=>$v){
                $this->iospuchGroupcast->setPredefinedKeyValue($key,$v);
            }

            $this->iospuchGroupcast->setCustomizedField("custom", $url);
            $this->iospuchGroupcast->setCustomizedField("pushType", 0);
            $this->iospuchGroupcast->setPredefinedKeyValue("production_mode", "true");

            return  $this->iospuchGroupcast->send();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getbaidus(){
        $url = 'https://aip.baidubce.com/oauth/2.0/token';
        $post_data['grant_type']       = 'client_credentials';
        $post_data['client_id']      = '7pioFHL95qFL17VjptiQdoNB';
        $post_data['client_secret'] = 'mZl4sCtZGiS9FPBfQ0b1pRw9nvpNddCo';
        $o = "";
        foreach ( $post_data as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);

        $res = $this->net->curl_request($url,$post_data);

        $res = json_decode($res,true);

        return $res;
    }

    public function baiduckneirong($content){
        $url = "https://aip.baidubce.com/rest/2.0/antispam/v2/spam";

        $arr = $this->getbaidus();
        $post_data['access_token']  = $arr["access_token"];
        $post_data['content']  = $content;

        $res = $this->net->curl_request($url, $post_data);

        $res = json_decode($res,true);

        return $res["result"];
    }

    public function getList(){
        $onuuid = Cache::get("onluid");

        $a= Db::name('laoshi')->where("id","<>",Cookie::get('lid'))->select();
        $list = array();
        $onlinenum = 0;
        foreach ($a as $v=>$k){
            $b= Db::name('laoshi_class') ->where('id',$k["cid"])->find();
            $list[$v] = array(
                "sign"=>$b["name"],
                "username"=>$k["lsname"],
                "id"=>(int)$k["id"],
                "avatar"=>$this->webdb["adminurl"].'/uploads/ls/'.$k["lsimg"]
            );

            if ($k["id"] == 24){
                $list[$v]["avatar"] =  "http://tva1.sinaimg.cn/crop.0.0.180.180.180/7fde8b93jw1e8qgp5bmzyj2050050aa8.jpg";
            }

            if (in_array($k["cid"],$onuuid)) {
                $list[$v]["status"] = "online";
                $onlinenum++;
            } else {
                $list[$v]["status"] = "offline";
            }

        }

        $ls = $this->getlaoshiinfo(Cookie::get('lid'));
        if (Cookie::get('lid') == 24){
            $ls["lsimg"] =  "http://tva1.sinaimg.cn/crop.0.0.180.180.180/7fde8b93jw1e8qgp5bmzyj2050050aa8.jpg";
        }

        $c= Db::name('laoshi_class') ->where('id',Cookie::get('cid'))->find();
        $classname = $c["name"];

        $data["mine"] = array(
            "sign"=>$classname,
            "username"=>Cookie::get('lsname'),
            "id"=>(int)Cookie::get('lid'),
            "avatar"=>$ls["lsimg"],
            "status"=>"online"
        );

        $data["friend"][0] = array(
            "groupname"=>"好友",
            "id"=>1,
            "list"=>$list,
            "online"=>$onlinenum
        );

        $data["group"][0] = array(
            "groupname"=>"网络部群",
            "avatar"=>"http://tva1.sinaimg.cn/crop.0.0.180.180.180/7fde8b93jw1e8qgp5bmzyj2050050aa8.jpg",
            "id"=>1,
            "members"=>100
        );

        $re["code"] = 0;
        $re["msg"] = "";
        $re["data"] = $data;

        return json_encode($re);

    }

    public function chatlog(){
        $uid = trim(input('id'));
        $type = trim(input('type'));

        switch ($type){
            case "group":
                $a= Db::name('chat') ->where('type',$type)->where('groupid',$uid)->select();
                break;
            case "friend":
                $a= Db::name('chat') ->where('type',$type)->where('uid',"in",Cookie::get('lid').",".$uid)->where('touid',"in",Cookie::get('lid').",".$uid)->order('timestamp', 'asc')->select();
                break;
        }

        foreach ($a as $v=>$k){
            $b = Db::name('laoshi')->where("id",$k["uid"])->find();

            $data[$v] = array(
                "username"=>$b["lsname"],
                "id"=>$k["uid"],
                "avatar"=>$this->webdb["adminurl"].'/uploads/ls/'.$b["lsimg"],
                "timestamp"=>$k["timestamp"]*1000,
                "content"=>$k["content"]
            );

            if ($k["uid"] == 24){
                $data[$v]["avatar"] =  "http://tva1.sinaimg.cn/crop.0.0.180.180.180/7fde8b93jw1e8qgp5bmzyj2050050aa8.jpg";
            }
        }

        $re["code"] = 0;
        $re["msg"] = "";
        $re["data"] = $data;

        $this->assign('json',json_encode($re));
        return $this->fetch();
    }

    public function msgbox(){
        return $this->fetch();
    }

    public function find(){
        return $this->fetch();
    }

    public function liveasr(){
        $content = file_get_contents("php://input");
        $str = base64_decode($content);

        if ($this->webdb["zbaiasr"] == 1){
            $b = new ZhiboModel();
            return $b->ZhiboAi($str);
        }else{
            return ;
        }
    }

    public function zbmanage(){
        $this->crecklogin();
        $ls = $this->getlaoshiinfo(Cookie::get('lid'));

        $this->assign('info',$ls);
        return $this->fetch();
    }

    public function liveroom($id){
        $this->crecklogin();
        $ls = $this->getlaoshiinfo(Cookie::get('lid'));

        $ls["rtmpurl"] = "rtmp://pushlive.haishunsh.com/laoshi/".$ls["id"]."_".date("Ymd");
        $this->assign('info',$ls);
        $this->assign('roomid',$id);
        return $this->fetch();
    }

    public function setroompw($encryParams){
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "rtmpurl");

        Db::name('laoshi')->where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function kecheng(){
        $rs= Db::name('laoshi')->order('id', 'asc')->select();
        $a = array(
            "lsimg"=>Config::get('upload.laoshiimg'),
            'cid'=>Cookie::get('cid'),
            "videoimg"=>Config::get('upload.videoimg'),
            "video"=>Config::get('upload.video'),
        );

        $this->assign('a',$a);
        $this->assign('list',$rs);
        return $this->fetch();
    }

    public function kechenglist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));

        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        if (Cookie::get('cid') == 1){
            $rs= Kecheng::order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Kecheng::count();
        }else{
            $rs= Kecheng::where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Kecheng::where('lid', Cookie::get('lid'))->count();
        }

        foreach ($rs as $v=>$a){
            $rs[$v]["dateline"] = friendlyDate($rs[$v]["dateline"], 'mohu');
            $ls = Db::name('laoshi') ->where('id',$a["lid"])->find();
            $rs[$v]["lid"] = $ls["lsname"];
            $rs[$v]["znum"] = KechengVideo::where('kid',$a["id"])->count();
        }
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function savekecheng($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");

        $data["dateline"] = time();
        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }

        $vid = Kecheng::insertGetId($data);

        Ajson('添加成功!','0000');
    }

    public function kechenginfo($encryParams){
        $data = json_decode($encryParams,true);
        $rs= Kecheng::where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function updatakecheng($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");

        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }

        Kecheng::where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function delkecheng($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        Kecheng::where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function kechengvideo(){
        $a = array(
            "lsimg"=>Config::get('upload.laoshiimg'),
            "lsvideo"=>Config::get('upload.laoshivideo'),
            'cid'=>Cookie::get('cid')
        );
        $rs= Db::name('laoshi')->order('id', 'asc')->select();
        $kclist= Kecheng::order('id', 'asc')->select();

        $this->assign('a',$a);
        $this->assign('list',$rs);
        $this->assign('kclist',$kclist);
        return $this->fetch();
    }

    public function kechengvideolist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));

        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        if (Cookie::get('cid') == 1){
            $rs= KechengVideo::order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = KechengVideo::count();
        }else{
            $rs= KechengVideo::where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = KechengVideo::where('lid', Cookie::get('lid'))->count();
        }

        foreach ($rs as $v=>$a){
            $rs[$v]["dateline"] = friendlyDate($rs[$v]["dateline"], 'mohu');
            $ls = Db::name('laoshi') ->where('id',$a["lid"])->find();
            $rs[$v]["lid"] = $ls["lsname"];
            $kc = Kecheng::where('id',$a["kid"])->find();
            $rs[$v]["kid"] = $kc["title"];
            $rs[$v]["ispass"] = "免费看";
            if ($a["ispass"] == 1){
                $rs[$v]["ispass"] = "需要密码";
            }

        }
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function savekechengvideo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");
        $data = $this->array_remove($data, "clip");
        $data = $this->array_remove($data, "seek");
        $data = $this->array_remove($data, "duration");
        $data = $this->array_remove($data, "end");
        $data = $this->array_remove($data, "record");

        $data["dateline"] = time();
        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }

        $vid = KechengVideo::insertGetId($data);

        Ajson('添加成功!','0000');
    }

    public function kechengvideoinfo($encryParams){
        $data = json_decode($encryParams,true);
        $rs = KechengVideo::where('id',$data["id"])->find();

        Ajson('查询成功!','0000',$rs);
    }

    public function updatakechengvideo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);
        $data = $this->array_remove($data, "file");
        $data = $this->array_remove($data, "clip");
        $data = $this->array_remove($data, "seek");
        $data = $this->array_remove($data, "duration");
        $data = $this->array_remove($data, "end");
        $data = $this->array_remove($data, "record");

        if (Cookie::get('cid') != 1){
            $data["lid"] = Cookie::get('lid');
        }

        KechengVideo::where('id', $data["id"])->update($data);

        Ajson('更新成功!','0000');
    }

    public function delkechengvideo($encryParams){
        $this->crecklogin();
        $data = json_decode($encryParams,true);

        KechengVideo::where('id', $data["id"])->delete($data);

        Ajson('删除成功!','0000');
    }

    public function order(){
        return $this->fetch();
    }

    public function orderlist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));

        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p)+1;
        }

        if (Cookie::get('cid') == 1){
            $rs = Db::name('client_user_order')->alias('a')->leftJoin('laoshi b','a.lid = b.id')->leftJoin('client_user c','a.cid = c.id')->leftJoin('laoshi_kecheng_video d','a.kid = d.id')->field("a.*,c.user,d.title")->order("id","desc")->limit($s,$p)->select();
            $re['total'] = Db::name('client_user_order')->alias('a')->leftJoin('laoshi b','a.lid = b.id')->leftJoin('client_user c','a.cid = c.id')->leftJoin('laoshi_kecheng_video d','a.kid = d.id')->field("a.*,c.user,d.title")->limit($s,$p)->count();
//            $rs= Db::name('client_user_order')->order('id', 'Desc')->limit($s,$p)->select();
//            $re['total'] = Db::name("client_user_order")->count();
        }else{
            $rs = Db::name('client_user_order')->alias('a')->leftJoin('laoshi b','a.lid = b.id')->leftJoin('client_user c','a.cid = c.id')->leftJoin('laoshi_kecheng_video d','a.kid = d.id')->where("a.lid",Cookie::get('lid'))->field("a.*,c.user,d.title")->order("id","desc")->limit($s,$p)->select();
            $re['total'] = Db::name('client_user_order')->alias('a')->leftJoin('laoshi b','a.lid = b.id')->leftJoin('client_user c','a.cid = c.id')->leftJoin('laoshi_kecheng_video d','a.kid = d.id')->where("a.lid",Cookie::get('lid'))->field("a.*,c.user,d.title")->limit($s,$p)->count();
//            $rs= Db::name('client_user_order')->where('lid', Cookie::get('lid'))->order('id', 'Desc')->limit($s,$p)->select();
//            $re['total'] = Db::name("client_user_order")->where('lid', Cookie::get('lid'))->count();
        }

        foreach ($rs as $v=>$a){
            $rs[$v]["success_time"] = friendlyDate($rs[$v]["success_time"], 'mohu');
            $rs[$v]["order_time"] = friendlyDate($rs[$v]["order_time"], 'mohu');
            $rs[$v]["cid"] = $a["user"];
            $rs[$v]["kid"] = $a["title"];
            $rs[$v]["ip"] = QQwry::getLocation($a['ip'])["area"];
            if ($a["source"] == "alipay"){
                $rs[$v]["source"] = "支付宝";
            }elseif ($a["source"] == "wx"){
                $rs[$v]["source"] = "微信";
            }else{
                $rs[$v]["source"] = "金币";
            }

            if ($a["state"] == 1){
                $rs[$v]["state"] = "<div style=\"color: green\">支付成功</div>";
            }else{
                $rs[$v]["state"] = "<div style=\"color: red\">未支付</div>";
            }

        }
        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function fengkong(){
        $rs = Task::where("id",22)->find();
        $this->assign('start',$rs["start"]);
        return $this->fetch();
    }

    public function tougu(){
        $rs = Task::where("id",25)->find();
        $this->assign('start',$rs["start"]);
        return $this->fetch();
    }

    public function fengkonglist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        $tag = input('tag');

        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p);
        }
        $num = new Number();
        $num->setid(7);

        if (isset($tag)){
            $rs= Fengkong::where("tag",$tag)->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Fengkong::where("tag",$tag)->count();
        }else{
            $rs= Fengkong::order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = Fengkong::count();
        }


        foreach ($rs as $v=>$k){
            $ck = $num->where("number",$k["mobile"])->order('id', 'Desc')->find();
            if ($ck["state"] == 10){
                $rs[$v]["state"] = "已结束";
            }elseif ($ck["state"] == 2){
                $rs[$v]["state"] = "播打中";
            }elseif ($ck["state"] == 3) {
                $rs[$v]["state"] = "通话中";
            }
            $rs[$v]["recall"] = 0;
            $t = explode("recordings", $ck["recordfile"]);
            $rs[$v]["recordfile"] = "https://ai.haishunsh.com/recordings" . $t[1];
            if ($k["tag"] == 0 ){
                if ($ck["state"] == 10){
                    $rs[$v]["tag"] = "失败";
                    $rs[$v]["recall"] = $ck["id"];
                }else{
                    $rs[$v]["tag"] = "无";
                }
            }else{
                $rs[$v]["tag"] = "正常";
            }

        }

        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function delfengkong($encryParams)
    {
        $data = json_decode($encryParams, true);

        Fengkong::where("id",$data["id"])->delete();
        Ajson("删除成功", "0000");
    }

    public function dofengkong(){
        $start = input('post.start');
        $recall = input('post.recall');
        $num = new Number();
        $num->setid(7);
        if ($start == 1){
            if (!isset($recall)){
                $rs = Fengkong::where("tag",0)->select();
                foreach ($rs as $v=>$k){
                    $city["number"] = $k["mobile"];
                    $ck = $num->where("number",$k["mobile"])->find();
                    if (empty($ck)){
                        $num->insert($city);
                    }
                }
            }else{
                if ($recall > 0){
                    $num->where("id",$recall)->setField("state","");
                }
            }
        }

        $in["start"] = $start;
        $in["alter_datetime"] = date("Y-m-d H:i:s");

        Task::where('id',22)->data($in)->update();

        Ajson("操作成功", "0000");

    }

    public function tougulist(){
        $this->crecklogin();
        $s = intval(input('page'));
        $p = intval(input('rows'));
        $tag = input('tag');

        if ($s == 1){
            $s = 0;
        }else{
            $s = (($s-1) * $p);
        }
        $num = new Number();
        $num->setid(8);

        if (isset($tag)){
            $rs= FengkongTougu::where("tag",$tag)->order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = FengkongTougu::where("tag",$tag)->count();
        }else{
            $rs= FengkongTougu::order('id', 'Desc')->limit($s,$p)->select();
            $re['total'] = FengkongTougu::count();
        }

        foreach ($rs as $v=>$k){
            $ck = $num->where("number",$k["mobile"])->order('id', 'Desc')->find();
            if ($ck["state"] == 10){
                $rs[$v]["state"] = "已结束";
            }elseif ($ck["state"] == 2){
                $rs[$v]["state"] = "播打中";
            }elseif ($ck["state"] == 3) {
                $rs[$v]["state"] = "通话中";
            }
            $rs[$v]["recall"] = 0;
            $t = explode("recordings", $ck["recordfile"]);
            $rs[$v]["recordfile"] = "https://ai.haishunsh.com/recordings" . $t[1];
            if ($k["tag"] == 0 ){
                if ($ck["state"] == 10){
                    $rs[$v]["tag"] = "失败";
                    $rs[$v]["recall"] = $ck["id"];
                }else{
                    $rs[$v]["tag"] = "无";
                }
            }else{
                $rs[$v]["tag"] = "正常";
            }

        }

        $re['rows']=$rs;
        return  json_encode($re);
    }

    public function deltougu($encryParams)
    {
        $data = json_decode($encryParams, true);

        FengkongTougu::where("id",$data["id"])->delete();
        Ajson("删除成功", "0000");
    }

    public function dotougu(){
        $start = input('post.start');
        $recall = input('post.recall');
        $gotougu = input('post.gotougu');

        $num = new Number();
        $num->setid(8);
        if ($start == 1){
            if (!isset($recall) && !isset($gotougu)){
                $rs = FengkongTougu::where("tag",0)->select();
                foreach ($rs as $v=>$k){
                    $city["number"] = $k["mobile"];
                    $ck = $num->where("number",$k["mobile"])->find();
                    if (empty($ck)){
                        $num->insert($city);
                    }
                }
            }else{
                if ($recall > 0){
                    $num->where("id",$recall)->setField("state","");
                }

                if ($gotougu > 0){
                    $numf = new Number();
                    $numf->setid(8);
                    $old = $numf->where("id",$gotougu)->find();
                    $old = Fengkong::where("mobile",$old["mobile"])->find();
                    $b = array(
                        "name"=>$old["name"],
                        "sex"=>$old["sex"],
                        "chanpin"=>$old["chanpin"],
                        "mobile"=>$old["mobile"],
                    );
                    FengkongTougu::insert($b);

                    $c["number"] = $old["mobile"];
                    $num->insert($c);
                }
            }
        }

        $in["start"] = $start;
        $in["alter_datetime"] = date("Y-m-d H:i:s");

        Task::where('id',25)->data($in)->update();

        Ajson("操作成功", "0000");

    }

    public function uploadexlhg()
    {
        $file = request()->file('file');
        $tag = trim(input('tag'));

        if ($file) {
            $info = $file->move('../uploads/exl/');

            if ($info) {
                // 成功上传后 获取上传信息
                // 输出 jpg
                $filename = $info->getSaveName();

            } else {
                // 上传失败获取错误信息
                $filename = $file->getError();

            }
        }

        //上传文件的地址
        $filename = Env::get('root_path') . 'uploads' . DIRECTORY_SEPARATOR . 'exl' . DIRECTORY_SEPARATOR . $filename;

        $this->doRequest('admin.haishunsh.com', '/exltomysqlhg', array(
                'filename' => $filename,
                'tag' => $tag,
            )
        );
        //echo $fp;
        Ajson('导入成功!', '0000');
    }

    public function exltomysqlhg()
    {
        ignore_user_abort(true);
        set_time_limit(0);
        $filename = trim(input('post.filename'));
        $tag = trim(input('post.tag'));

        echo $filename;

        file_put_contents(Env::get('runtime_path') . "log/test.txt", "exltomysql@" . $filename, FILE_APPEND);

        //判断截取文件
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        //file_put_contents(Env::get('runtime_path')."log/test.txt", "exltomysql@".$extension, FILE_APPEND);

        //区分上传文件格式
        if ($extension == 'xlsx') {
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objPHPExcel = $objReader->load($filename, $encode = 'utf-8');
        } else if ($extension == 'xls') {
            $objReader = \PHPExcel_IOFactory::createReader('Excel5');
            $objPHPExcel = $objReader->load($filename, $encode = 'utf-8');
        }

        $excel_array = $objPHPExcel->getsheet(0)->toArray();   //转换为数组格式
        array_shift($excel_array);  //删除第一个数组(标题);
        //file_put_contents(Env::get('runtime_path')."log/test.txt", "exltomysql@".json_encode($excel_array), FILE_APPEND);
//        var_dump($excel_array);

        $city = [];
        $i = 0;
        //file_put_contents(Env::get('runtime_path')."log/test.txt", json_encode($excel_array), FILE_APPEND);
        foreach ($excel_array as $k => $v) {
            $name = trim($v[0]);
            $sex = trim($v[1]);
            $chanpin = trim($v[2]);
            if ($tag == "fk"){
                $dateline = trim($v[3]);
                $mobile = trim($v[4]);
            }else{
                $mobile = trim($v[3]);
            }

            if (!empty($mobile)){
                $city[$i]['mobile'] = $mobile;
                $city[$i]['name'] = $name;
                $city[$i]['sex'] = $sex;
                $city[$i]['chanpin'] = $chanpin;
                if ($tag == "fk"){
                    $city[$i]['dateline'] = $dateline;
                }
                $i++;
            }
        }

//        var_dump($city);

        if ($tag == "fk"){
            Fengkong::insertAll($city);
        }elseif ($tag == "tg"){
            FengkongTougu::insertAll($city);
        }

    }

    public function hglisttoexl(){
        $type = trim(input('type'));
        $tag = trim(input('tag'));

        // 设置缓存方式，减少对内存的占用
        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array ( 'cacheTime' => 300 );
        PHPExcel_Settings::setCacheStorageMethod ( $cacheMethod, $cacheSettings );

        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
            ->setLastModifiedBy("Maarten Balliauw")
            ->setTitle("Office 2007 XLSX Test Document")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
            ->setKeywords("office 2007 openxml php")
            ->setCategory("Test result file");

        $objPHPExcel->getActiveSheet()->setTitle('Simple');
        $objPHPExcel->setActiveSheetIndex(0);

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(200);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);

        $objPHPExcel->getActiveSheet()->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

        if ($type=="fengkong"){
            $rs= Fengkong::where("tag",$tag)->order('id', 'Desc')->select();
        }else{
            $rs= FengkongTougu::where("tag",$tag)->order('id', 'Desc')->select();
        }
        $filename = date("Ymdhis")  . '.xlsx';
        $j = 1;
        foreach ($rs as $v=>$a) {
            if ($type=="fengkong"){
                $objPHPExcel->setActiveSheetIndex(0)
                    //Excel的第A列，uid是你查出数组的键值，下面以此类推
                    ->setCellValue('A' . $j, $a['name'])
                    ->setCellValue('B' . $j, $a['chanpin'])
                    ->setCellValue('C' . $j, $a['dateline'])
                    ->setCellValue('D' . $j, $a['mobile']);
            }else{
                $objPHPExcel->setActiveSheetIndex(0)
                    //Excel的第A列，uid是你查出数组的键值，下面以此类推
                    ->setCellValue('A' . $j, $a['name'])
                    ->setCellValue('B' . $j, $a['chanpin'])
                    ->setCellValue('C' . $j, $a['mobile']);
            }
            $j++;
        }

        $filePath =  Env::get('runtime_path')."exldown/".$filename;
        $objWriter = PHPExcel_IOFactory:: createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save($filePath);

        //发出301头部
        header('HTTP/1.1 301 Moved Permanently');
        //跳转到你希望的地址格式
        header('Location: '.$this->webdb["adminurl"]."/exldown/".$filename);
    }

    public function downrecordings(){
        $ids = input("post.ids");
        $tag= input("post.tag");

        $pgyapi = "http://47.101.39.34:1987/downrecordings";
        $post_data['ids']  = $ids;
        $post_data['tag']  = $tag;

        $res = $this->net->curl_request($pgyapi, $post_data);
        $res = json_decode($res,true);

        Ajson('导入成功!', '0000',"https://ai.haishunsh.com/recordings/".str_replace("/home/recordings/","",$res["data"]));
    }
}
