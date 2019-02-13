<?php
namespace app\index\controller;
use app\index\model\Video;
use app\index\model\Laoshi;
use think\App;
use think\Controller;
use think\Request;
use tools\NetWork;
use tools\Oss;

class Index extends Controller
{
    protected $net;
    protected $oss;
    protected $accessKeyId;
    protected $accessKeySecret;
    protected $location;
    protected $bucket;

    public function __construct(App $app = null)
    {
        $this->net = New NetWork();
        $this->accessKeyId = "D4T6zPxc2Jm2ZSLw";
        $this->accessKeySecret = 'LNY5L6owScHQoSmrLyXDaYDZVPD3f2';
        $this->location = 'cn-shanghai';
        $this->oss = new Oss('',$this->accessKeyId,$this->accessKeySecret,"oss-".$this->location.".aliyuncs.com");
        $this->bucket = "mkangou";

        parent::__construct($app);
    }

    //文件上传表单
    public function index()
    {
        $list = Laoshi::select();
        $this->assign('list', json_encode($list));
       return $this->fetch();
    }

    public function osspic($oss_input_object,$oss_output_object,$Time,$new=''){
        $oss_location = 'oss-'.$this->location;
        $oss_bucket = $this->bucket;

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
        $url = 'http://mts.'.$this->location.'.aliyuncs.com/?' . http_build_query ( $data );
        $content=$this->net->curl_request($url);


        return json_decode($content,true);
    }

    // 视频转码
    public function osszm($input,$output,$clip=1,$seek=0,$duration=0,$end=0,$try = "mp4"){
        $pipeline_id = "1bcde8a0f7284fe0836ee9c1d2b3f1c1";//1bcde8a0f7284fe0836ee9c1d2b3f1c1 1d2e4900289e4e979b1c656d81baddaf
        $template_id = "S00000002-200030";//S00000002-200030  S00000001-200020
        $oss_location = 'oss-'.$this->location;
        $oss_bucket = $this->bucket;

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
        $url = 'http://mts.'.$this->location.'.aliyuncs.com/?' . http_build_query ( $data );

        $content=$this->net->curl_request($url);

        return json_decode($content,true);
    }

    // 上传视频  到oss
    public function addVideo() {
        $oss_input_object = trim(input('post.input_object'));
        $oss_output_object = trim(input('post.output_object'));
        $autopic = 1;
        $autofolder = "autofolder";
//        echo $oss_output_object;

        $t = explode(".", $oss_output_object);
        $a = explode('/', $t[0]);
        //echo "https://play.haishunsh.com/".$oss_output_object;
        $rs = video::where('src',"https://play.haishunsh.com/".$oss_output_object)->find();
//        var_dump($rs);
        if (empty($rs)) {
            $zb=$this->osszm($oss_input_object,$oss_output_object);
//            var_dump($zb);
            if ($autopic == 1) {
                $outfile = date("Ymd") . "/" . $a[1] . ".jpg";
                $jt = $this->osspic($oss_input_object, $a[1] . ".jpg", 5, $oss_output_object);
                //
                $save_to = '../uploads/' . $autofolder . '/' . $outfile;

                $basedir = '../uploads/' . $autofolder . '/' . date("Ymd");
                if (!is_dir($basedir)) mkdir($basedir);


                $content = file_get_contents( "https://play.haishunsh.com/" . $jt["SnapshotJob"]["SnapshotConfig"]["OutputFile"]["Object"]);
                file_put_contents($save_to, $content);

                $folder = $autofolder . "/" . date("Ymd");
                $img = '../uploads/' . $folder . '/' . $a[1] . ".jpg";

                $filename = date("Ymd") . "/" . thumb($img, $folder, $a[1] . ".jpg", 300, 300);

            }

        }

//        $name = input("post.name");
//        $type = input("post.type");
//        $date = date('Y-m-d H:i:s');
//        echo date('Y-m-d H:i:s');
        $src = "https://play.haishunsh.com/".$oss_output_object;
        $data = [ 'src'=>$src, 'picaddr'=>$img];
//        video::insert($data);
        echo json_encode(array("success" => true, 'data' => $data));
    }

    //提交上传视频  到数据库
    public function uploadVideos($lid){
        $name = input("post.name");
        $type = input("post.type");
        $src = input("post.src");
        $picaddr = input("post.picaddr");
        $description = input("post.description");

        if ($name == '' || !isset($name)) {
            echo json_encode(array("success" => false, 'msg' => "视频名称不能为空"));
            exit();
        }

        $picaddr = str_replace("../uploads/","https://zv.haishunsh.com/",$picaddr);

        $data = array(
            "name"=>$name,
            "type"=>$type,
            "lid"=>$lid,
            "src"=>$src,
            "picaddr"=>$picaddr,
            "description"=>$description,
            "date"=>time()
        );

        $video = new Video;
        if ($video->allowField(['name', 'type', 'lid', 'src', 'picaddr', 'date','description', 'delflag'])->save($data)) {
            $totalPage = ceil(db('video')->where('delflag', '0')->count() / 10);
            echo json_encode(
                array("success" => true,
                    "totalPage" => $totalPage,
                ));
            exit();
        } else {
            echo json_encode(array("success" => false, 'msg' => "服务器繁忙，请稍后重试"));
            exit();
        }
    }

    // 获取视频列表
    public function videos()
    {
        $page = (isset($_POST['page'])) ? $_POST['page'] : 1;
        $videos = video::where('delflag', '0')->page($page, 10)->select();
        foreach ($videos as $v=>$k){
            $videos[$v]["type"] = $this->vtype($k["type"]);
            $videos[$v]["date"] = friendlyDate($k["date"]);
            $rs = Laoshi::where("id",$k["lid"])->find();
            $videos[$v]["lid"] = $rs["lsname"];
        }
        $totalPage = ceil(video::where('delflag', '0')->count() / 10);
        echo json_encode(array("videos" => $videos, "totalPage" => $totalPage, "success" => true));
    }


    public function vtype($n){
        switch ($n){
            case 0:
                return "明星老师";
                break;
            case 1:
                return "技术分析";
                break;
            case 2:
                return "公司分析";
                break;
            case 3:
                return "精品推荐";
                break;
            case 4:
                return "热门课程";
                break;
            case 5:
                return "海顺选股";
        }
    }

    // 删除视频
    public function deleteone()
    {

        if (video::update(['delflag'  => 1],['id' => $_POST['id']])) {
            echo json_encode(array("success" => true));
        } else {
            echo json_encode(array("msg" => "删除失败", "success" => false));
        }
    }

    // 修改视频
    public function updateVideo()
    {
        if ($_POST['name'] == '' || !isset($_POST['name'])) {
            echo json_encode(array("success" => false, 'msg' => "视频名称不能为空"));
            exit();
        }
        $video = new Video;
        if ($video->allowField(['name', 'type', 'lid'])->save($_POST, ['id' => $_POST['id']])) {
            echo json_encode(array("success" => true));
            exit();
        } else {
            echo json_encode(array("success" => false, 'msg' => "服务器繁忙，请稍后重试"));
            exit();
        }
    }

    // 获取所有数据的api
    public function api(){
        $type = input("post.type");
        $page = input("post.page");
        if (!isset($page)){
            $page = 1;
        }
        $rows = input("post.rows");

        if ($rows>=1) {
            if (isset($type)) {
                $videos = video::where('delflag', '0')->where('type', $type)->page($page, $rows)->select();
            } else {
                $videos = video::where('delflag', '0')->page($page, $rows)->select();
            }

            foreach ($videos as $v => $k) {
                $videos[$v]["type"] = $this->vtype($k["type"]);
                $videos[$v]["date"] = friendlyDate($k["date"]);
//                $videos[$v]["lid"] = $this->getLaoshiinfo($k["lid"]);
            }
            $totalPage = ceil(video::where('delflag', '0')->where('type', $type)->count() / $rows);
            echo json_encode(array("videos" => $videos, "totalPage" => $totalPage, "success" => true));
        }
        else{
            echo json_encode(array("reason"=>"rows不能为0", "success"=>false));
        }
    }

    // 获取指定id的老师的详细信息
    public function getLaoshiinfo($id){
        $rs = Laoshi::where('id', $id)->find();
        $arr = array(
            "lsname"=>$rs["lsname"],
            "jieshao"=>$rs["lsjieshao"],
            "img"=>"https://admin.haishunsh.com/uploads/ls/".$rs["lsimg"],
        );
        echo json_encode(array("Laoshiinfo" => $arr, "success" => true));
    }

    // 获取视频播放次数
    public function hitapi(){
        $id = input("id");
        video::where('id', $id)->setInc('hit');
        $ck = video::where('id', $id)->find();
        echo json_encode(array("hit"=>$ck["hit"], "success"=>true));
    }

    // 通过老师姓名获取指定老师的所有视频
    public function lsnameapi() {
        $lsname = input("lsname");
        $ck = Laoshi::where('lsname', $lsname)->find();
        if ($ck){
            $videos = video::where('delflag', '0')->where('lid', $ck["id"])->select();
            echo json_encode(array("videos"=>$videos, "success"=>true));
        }
        else{
            echo json_encode(array("success"=>false));
        }
    }

    // 获取所有老师信息
    public function laoshiapi() {
        $laoshis = laoshi::where("cid", 2)->select();
        foreach ($laoshis as $v => $k) {
            $img = $laoshis[$v]["lsimg"];
            $laoshis[$v]["lsimg"] = "https://admin.haishunsh.com/uploads/ls/".$img;
            $img2 = $laoshis[$v]["lsfmpic"];
            $laoshis[$v]["lsfmpic"] = "https://admin.haishunsh.com/uploads/ls/".$img2;
            $img3 = $laoshis[$v]["lspic"];
            $laoshis[$v]["lspic"] = "https://admin.haishunsh.com/uploads/ls/".$img3;
        }
        echo json_encode(array("laoshis"=>$laoshis, "success"=>true));
    }

    // 通过老师编号获取指定老师的所有视频
    public function lsapi() {
        $lid = input("lid");
        $videos = video::where('delflag', '0')->where('lid', $lid)->select();
        echo json_encode(array("videos"=>$videos, "success"=>true));
    }
}