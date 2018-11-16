<?php
/*
|---------------------------------------------------------------
|  Copyright (c) 2018
|---------------------------------------------------------------
| 作者：qieangel2013
| 联系：qieangel2013@gmail.com
| 版本：V1.0
| 日期：2018/6/1
|---------------------------------------------------------------
*/
require_once dirname(__DIR__).'/vendor/autoload.php';
use Swoole\Coroutine as co;
use Phpml\Classification\SVC;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\Classification\KNearestNeighbors;
use Phpml\Classification\NaiveBayes;
use Phpml\Association\Apriori;
use Phpml\Regression\LeastSquares;
use Phpml\Regression\SVR;
include 'spider_dom.php';
class SpiderServer
{
    public static $instance;
    private $taskconfig;
    private $queue_master;
    private $queue;
    private $queue_slave;
    private $lock;
    private $server;
    private $msg='';
    private $msgtable;
    private $classifier;
    private $errcounts=10;
    private $artpagecount=2;
    private $mysql_connection;
    private $mysqlPoolServer;
    private $redis_connection;
    private $weixin_arr;
    private $iderrorcount=100;
    private $spiderdom;
    private $mysql_num=10;
    private $command='/usr/local/php/bin/php';
    public function __construct()
    {
        $this->server = new swoole_server('0.0.0.0',9538);
        $this->taskconfig=array(
            'counter' =>60
            );
        $this->mysql_connection=array(
            'host' =>'localhost',
            'dbuser'=>'root',
            'dbpasswd'=>'51cto',
            'dbdatabase'=>'v9'
            );
        $this->redis_connection=array(
            'host' =>'127.0.0.1',
            'port'=>6379
            );
        $this->weixin_arr=array('http://www.huoxing24.com/news');
         //建立机器学习模型
        $this->classifier = new NaiveBayes();
        $samplesdata=$this->getsamples(dirname(__DIR__).'/data/sample.txt');
        if(!empty($samplesdata)){
            $this->classifier->train($samplesdata['samples'], $samplesdata['labels']);
        }
        //建立消息队列
        $this->queue= new Swoole\Channel(1024 * 256);
        $this->queue_master= new co\Channel(1024 * 256);
        $this->queue_slave= new co\Channel(1024 * 256);
        //生成多少个mysql客户端
        if (isset($config['logfile'])) {
            $this->server->set(
                array(
                    'worker_num'  => 4,
                    'daemonize' => true,
                    'buffer_output_size' => 50 * 1024 *1024,
                    'socket_buffer_size' => 50 * 1024 *1024,
                    'task_worker_num' => 20,
                    'open_length_check' => true,
                    'package_length_type' => 'N',
                    'package_length_offset' => 0,
                    'package_body_offset' => 4,
                    'package_max_length' => 2000000,
                    'heartbeat_check_interval' => 5,
    				'heartbeat_idle_time' => 10
                    //'log_file' => $task_config['logfile']
                )
            );
        } else {
            $this->server->set(
            array(
                'worker_num'  => 4,
                'daemonize' => true,
                'buffer_output_size' => 50 * 1024 *1024,
                'socket_buffer_size' => 50 * 1024 *1024,
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 2000000,
                'heartbeat_check_interval' => 5,
    			'heartbeat_idle_time' => 10,
                'task_worker_num' => 20,
                'log_file' => dirname(__DIR__).'/log/spider.log'
            )
            );
        }

        $this->server->on('WorkerStart', array($this , 'onWorkerStart'));

        $this->server->on('Receive', array($this , 'onReceive'));

        $this->server->on('Task', array($this , 'onTask'));

        $this->server->on('Finish', array($this , 'onFinish'));

        $this->server->start();
    }

    public function onWorkerStart($server,$worker_id){
    	if($worker_id == 0 && !$server->taskworker){
    		$server->tick(600000,function($id){ 
    		   $rsdata=$this->getlist('http://www.huoxing24.com/news');
    		   if($rsdata){
    		   		foreach ($rsdata as $k => $v) {
    		   			$taskdata= json_encode(array('type' =>'get','url'=>$v),true);
               			$this->server->task($this->packmes($taskdata));
    		   		}
    		   } 
            });
    	}
    	
   //      if($worker_id == 1 && !$server->taskworker){
   //  		go(function (){
			// 	while (1) {
			// 	  		if($this->queue->stats()['queue_num']>0){
			// 	  			$data=$this->queue->pop();
			// 	  			$data=json_decode($data,true);
			// 				$db = new co\MySQL();
			// 	    		$db->connect($this->mysql_connection);
			// 				if($db){
		 //    					$this->queue_slave->push($db);
		 //    					$sql_insert="";
		 //        				$sql_insert_data="";
		 //                    	$sql_selects = "select * from  v9_news where title='".trim($data['title'])."'";//后期会改成查redis
		 //                    	$result = $db->query($sql_selects);
		 //                    	if (is_array($result) && empty($result)){
			// 		                                $sql_insert ="insert into v9_news(catid,typeid,title,keywords,description,url,status,sysadd,username,inputtime,updatetime)values(".$data['catid'].",0,'".$data['title']."','".$data['keyword']."','".$data['abstract']."','"."/api"."',"."1".",1,'".$data['posterScreenName']."',".$data['publishDate'].",".$data['publishDate'].")";
			// 		                                $sql1=$db->query($sql_insert);
			// 		                                print_r($sql1);
			// 		                                $sqlid=$db->insert_id;
			// 		                                $log = array(
			// 		                                        'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
			// 		                                        'content' => "时间：".date('Ymd-H:i:s',time())."\r\ninfo:sqlid为:".$sqlid."\r\n"
			// 		                                    );
			// 		                                $this->log($log);
			// 		                                if($sqlid){
			// 		                                    $sql_insert_update="update v9_news set url='/content/index/show.html?catid=".$data['catid']."&id=".$sqlid."' where id=".$sqlid;
			// 		                                    $sql2=$db->query($sql_insert_update);
			// 		                                    $sql_insert_data ="insert into v9_news_data(id,content,groupids_view,paginationtype,maxcharperpage,template,isred)values(".$sqlid.",'".$data['content']."','',0,10000,'','".$data['isred']."')";
			// 		                                    $sql3=$db->query($sql_insert_data);
			// 		                                    $sql_hit="insert into v9_hits(hitsid,catid,updatetime)values('c-1-".$sqlid."','".$data['catid']."','".$data['publishDate']."')";
			// 		                                    $sql4=$db->query($sql_hit);
			// 		                                }
		 //    					}

			// 				}else{
			// 					while($slvdb=$this->queue_slave->pop()){
		 //    						$this->queue_master->push($slvdb);
		 //    		 			}
			// 				}
			// 			}	
			// 		}
			// });
   //      }
        
    }

    public function onReceive($serv, $fd, $from_id, $data)
    {
        $param = array(
            'fd' => $fd,
            'data'=>json_decode($this->unpackmes($data), true)
        );
        if(isset($param['data']['data']['wz_type']) && strpos($param['data']['data']['wz_type'],'5')===false){
            $this->queue->push($data);
        }else{
            $this->queue_baidu->push($data);
        }
        
    }
    
    public function createco(){
    	for ($i=0; $i <$this->mysql_num ; $i++) { 
    		go(function (){
    			$db = new co\MySQL();
			    $db->connect($this->mysql_connection);
			    $this->queue_master->push($db);
			});
    	}
    	
    }

    public function onTask($serv, $task_id, $from_id, $data)
    {
        $tmp_data=json_decode($this->unpackmes($data), true);
        $link=new mysqli(
            $this->mysql_connection['host'],
            $this->mysql_connection['dbuser'],
            $this->mysql_connection['dbpasswd'],
            $this->mysql_connection['dbdatabase']
            );
        if ($link->connect_error) {
            $log = array(
                'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:".printf("Can't connect to MySQL Server. Errorcode: %s ",$link->connect_error)."\r\n"
            );
            $this->log($log);
            return false;
        }
        $link->query("set names 'utf8'");
        $errcount=0;
        $redis = new Redis();
        $redis->connect($this->redis_connection['host'],$this->redis_connection['port']);
        $this->getresult($tmp_data,$errcount,$redis,$link);
        if(!isset($tmp_data['log'])){
            $log = array(
                'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                'content' => "时间：".date('Ymd-H:i:s',time())."\r\n请求内容为:".json_encode( $tmp_data,true )."\r\n"
            );
            $this->log($log);
        }else{
            $this->log($tmp_data['log']);
        }
        $redis->close();
        $link->close();
        return "result.";
    }

    public function onFinish($serv, $task_id, $data)
    {
          $log = array(
                'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                'content' => "时间：".date('Ymd-H:i:s',time())."\r\ntask任务为:".$task_id." finish\n Result为：".$data."\r\n"
            );
          $this->log($log);
    }

    private function getlist($url){
    	$resdata=array();
		$html = str_get_html($this->GetData($url));
		foreach($html->find('#newsListContent a') as $element){
		   if(!preg_match_all('/hot\/(.*)/i',$element->href,$out)){
		      if(!in_array($element->href,$resdata)){
                $resdata[]=$element->href;
              }
		   }
		 }
	    return $resdata;
    }

    private function getweixin($file_path){
        $result=array();
        if(file_exists($file_path)){
            $file_arr = file($file_path);
            for($i=0;$i<count($file_arr);$i++){
                if(trim($file_arr[$i])!=''){
                    $result[]=$file_arr[$i];
                }
            }
            return $result;
        }else{
            $log = array(
                'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:打开文件失败！\r\n"
            );
            $this->log($log);
        }
        return array();
    }

    private function getsamples($file_path){
        if(file_exists($file_path)){
            $file_arr = file($file_path);
            for($i=0;$i<count($file_arr);$i++){
                if(trim($file_arr[$i])!=''){
                    $tmpfiledata=explode(":",$file_arr[$i]);
                    $labels[]=array_pop($tmpfiledata);
                    // $cidata=$this->distance($tmpfiledata[0]);
                    // if($cidata){
                    //     $tmpfiledata[0]=$cidata[1]*1000000;
                    // }else{
                    //     $tmpfiledata[0]=1*1000000;
                    // }
                    $samples[]=$tmpfiledata;
                    unset($tmpfiledata);
                }
            }
            $result['samples']=$samples;
            $result['labels']=$labels;
            return $result;
        }else{
            $log = array(
                'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:打开文件失败！\r\n"
            );
            $this->log($log);
        }
        return array();
    }

   private function distance_pre($keyword) {
        exec ( "/data/w2v/trunk/distancecli  /data/w2v/trunk/vectors.bin " . $keyword, $outputArray );
        if (isset ( $outputArray[0] )) {
            return $outputArray;
        } else {
            return array();
        }
    }
    private function distance($keyword) {
        exec ( "/data/w2v/trunk/distancecli /data/w2v/trunk/vectors.bin " . $keyword, $outputArray );
        $resultdata=array();
        if (isset ( $outputArray[0] )) {
            $tmparr=explode(",",$outputArray[0]);
            $tmpssr=$this->distance_pre($tmparr[0]);
            foreach ($tmpssr as $k => $v) {
                $tmpdata=explode(",",$v);
                if($tmpdata[0]==$keyword){
                    $resultdata=$tmpdata;
                    break;
                }
            }
            return $resultdata;
        } else {
            return array();
        }
    }
    private function getresult($data,$errcount,$redis,$link){
    	$types=strtolower($data['type']);
        $tmppdata=array();
        switch ($types) {
            case 'get':
                $tmppdata=$this->GetData($data['url']);
                if(isset($tmppdata)){
                	$resdata=$this->rinse($tmppdata);
                    $tmpcontent=preg_replace('/\s/','',strip_tags($resdata['content']));
                    $resdata['catid']=$this->phpml($tmpcontent,$redis);
                    $resdata['isred']=0;
                    $this->insertdata($resdata,$link);
                }else{
                     $log = array(
                                    'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                                    'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:未获取到数据\r\n"
                                );
                                $this->log($log);
                }
                //print_r($resultdata);
                // $this->insertdata($resultdata); 
                break;
            case 'post':
                $tmppdata=$this->PostData($tmp_data['url'],$tmp_data['data']);
                break;
            default:
                //写一个通用的处理
                //type用post-, get- 区分
                // if(strpos($tmp_data['type'], 'get-') !== false){
                //  $res = $this->GetData($tmp_data['url'].$tmp_data['data']);
                // }else if(strpos($tmp_data['type'], 'post-') !== false){
                //  $res = $this->PostData($tmp_data['url'], $tmp_data['data'], true, $tmp_data['token']);
                // }
                break;
        }
    }


    private function rinse($data){
    	$resdata=array();
    	$rkdata=array();
        $this->spiderdom = str_get_html($data);
		//找到所有的数据
		 foreach($this->spiderdom->find('.text-header h1') as $element){
		   if($element->innertext){
		      $resdata['title']=trim($element->innertext);
		    }
		 }
		 //找到所有的数据
		 foreach($this->spiderdom->find('.time span') as $element){
		    if($element->innertext){
		    	$element->innertext=trim($element->innertext);
              		$element->innertext =str_replace('月','-',$element->innertext);
              		$element->innertext =str_replace('日','',$element->innertext);
              		$resdata['publishDate']=strtotime('2018-'. $element->innertext .' 00:00:00');
		    }
		       
		 }
		 //找到所有的数据
		 foreach($this->spiderdom->find('.author span') as $element){
		    if($element->innertext){
		      $resdata['posterScreenName']=trim($element->innertext);
		    }
		       
		 }
		 foreach($this->spiderdom->find('#detailsSynopsis h2') as $element){
		    if($element->innertext){
		      $resdata['abstract']=trim($element->innertext);
		    }
		       
		 }

		 foreach($this->spiderdom->find('.keyword a') as $element){
		    if($element->innertext){
		      $rkdata[]=$element->innertext;
		    }   
		 }
		if(count($rkdata)>1){
		  $resdata['keyword']=implode(" ",$rkdata);
		}else{
		  $resdata['keyword']=$rkdata[0];
		}

		//找到所有的数据
		 foreach($this->spiderdom->find('.detail-text-cont') as $element){
		 		if($element->outertext){
		 			$resdata['content']=$element->outertext;
		 		}
		       
		 }
 		return $resdata;
    }

    private function insertdata($data,$link){
        $sql_insert="";
        $sql_insert_data="";
                    $sql_selects = "select * from  v9_news where title='".$data['title']."'";//后期会改成查redis
                    if ($result = $link->query($sql_selects)){
                        if($result->num_rows>0){
                        }else{
                                $link->autocommit(FALSE);
                                $sql_insert ="insert into v9_news(catid,typeid,title,keywords,description,url,status,sysadd,username,inputtime,updatetime)values(".$data['catid'].",0,'".$data['title']."','".$data['keyword']."','".$data['abstract']."','"."/api"."',"."1".",1,'".$data['posterScreenName']."',".$data['publishDate'].",".$data['publishDate'].")";
				$sql1=$link->query($sql_insert);
                                $finished=0;
                                $ii=0;
                                while($finished ==0){
                                    $sqlid=$link->insert_id;
                                    $log = array(
                                        'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                                        'content' => "时间：".date('Ymd-H:i:s',time())."\r\ninfo:sqlid为:".$sqlid."\r\n"
                                    );
                                    $this->log($log);
                                    if($sqlid){
                                        $finished = 1;
                                        $sql_insert_update="update v9_news set url='/content/index/show.html?catid=".$data['catid']."&id=".$sqlid."' where id=".$sqlid;
                                        $sql2=$link->query($sql_insert_update);
                                        $sql_insert_data ="insert into v9_news_data(id,content,groupids_view,paginationtype,maxcharperpage,template,isred)values(".$sqlid.",'".$data['content']."','',0,10000,'','".$data['isred']."')";
                                        $sql3=$link->query($sql_insert_data);
                                        $sql_hit="insert into v9_hits(hitsid,catid,updatetime)values('c-1-".$sqlid."','".$data['catid']."','".$data['publishDate']."')";
                                        $sql4=$link->query($sql_hit);
                                        if($sql3){
                                            $link->commit(); //提交事务
                                            $link->autocommit(TRUE);//开启自动提交功能
                                        }else{
                                            $link->rollback();
                                        }
                                        $ii=0;
                                        break;
                                    }else{
                                        if($ii<=$this->iderrorcount){
                                            $ii++;
                                        }else{
                                             $finished = 1;
                                             $ii=0;
                                             $log = array(
                                                'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                                                'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:".$sql_insert."插入数据库后获取insert_id失败\r\n"
                                             );
                                             $this->log($log);
                                             $link->rollback();
                                             break;
                                        }
                                        sleep(2);
                                        continue;
                                    }
                                
                            }
                            if($sql1){
                            }else{
                                $log = array(
                                    'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                                    'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:事物提交失败！\r\n"
                                );
                                $this->log($log);
                            }
                    }
        }
        // $res = $link->multi_query($sql_insert);
        // if($res){
        //     while ($link->more_results() && $link->next_result());
        //     $ress = $link->multi_query($sql_insert_data);
        //     if($ress){
        //          $log = array(
        //         'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
        //         'content' => "时间：".date('Ymd-H:i:s',time())."\r\ninfo:插入成功\r\n"
        //         );
        //         $this->log($log);
        //     }
        // }else{
        //     $log = array(
        //         'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
        //         'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:".$link->error."\r\n"
        //         );
        //         $this->log($log);
        // }
    }
    private function phpscws($text){
        $sh = scws_open();
        scws_set_charset($sh, 'utf8');
        scws_set_dict($sh, '/usr/local/scws/etc/dict.utf8.xdb');
        scws_set_rule($sh, '/usr/local/scws/etc/rules.utf8.ini');
        scws_send_text($sh, $text);
        $top = scws_get_tops($sh,5);
        return $top;
    }

    private function getkeyword($data){
        $tmpjieba=jieba($data,0,10);
        return implode(' ',$tmpjieba);
    }

    private function phpml($data,$redis){
            // $tmpscws=$this->phpscws($data);
            // foreach ($tmpscws as $kk => $vv) {
            //    $tmpdata[]=sprintf("%u",crc32($vv['word']));
            //    $tmpdata[]=$vv['times'];
            //    $tmpdata[]=(int)$vv['weight'];
            //    $classdata[]=$this->classifier->predict($tmpdata);
            //    unset($tmpdata);
            // }
            $tmpjieba=jieba($data,0,10);
            if($redis->PING()=='+PONG'){
                foreach ($tmpjieba as $kk => $vv) {
                    $redisdata=$redis->get(sprintf("%u",crc32($vv)));
                    if($redisdata){
                        $tmpdata=json_decode($redisdata,true);
                        $classdata[]=$this->classifier->predict($tmpdata);
                    }else{
                        $cidata=$this->distance($vv);
                        if($cidata){
                            $tmpdata[]=$cidata[1]*1000000;
                            $tmpdata[]=1;
                            $classdata[]=$this->classifier->predict($tmpdata);
                        }else{
                            $tmpdata[]=1*1000000;//默认未找到的词向量，会给一个默认分类
                            $tmpdata[]=1;
                            $classdata[]=$this->classifier->predict($tmpdata);
                            $log = array(
                                'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                                'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:该词".$vv."未找到词向量，请加大训练词汇量\r\n"
                            );
                            $this->log($log);
                        }
                        $inredis=json_encode($tmpdata,true);
                        $redis->set(sprintf("%u",crc32($vv)),$inredis);
                    }
                    unset($tmpdata);
                }

            }else{
                foreach ($tmpjieba as $kk => $vv) {
                    $cidata=$this->distance($vv);
                    if($cidata){
                        $tmpdata[]=$cidata[1]*1000000;
                        $tmpdata[]=1;
                        $classdata[]=$this->classifier->predict($tmpdata);
                    }else{
                        $tmpdata[]=1*1000000;//默认未找到的词向量，会给一个默认分类
                        $tmpdata[]=1;
                        $classdata[]=$this->classifier->predict($tmpdata);
                        $log = array(
                            'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                            'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:该词".$vv."未找到词向量，请加大训练词汇量\r\n"
                        );
                    $this->log($log);
                }
                    unset($tmpdata);
                }
                $log = array(
                            'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                            'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:RedisServer  connect() failed: Connection refused \r\n"
                        );
                $this->log($log);
            }
            
            return $this->getstatistic($classdata);
    }

    private function getstatistic($data){
        $tmpcdata=array_count_values($data);
        if(count($tmpcdata)>1){
            foreach ($tmpcdata as $ks => $vs) {
                $tmpccdata[]=$vs;
            }
            arsort($tmpccdata);
            foreach ($tmpccdata as $k => $v) {
                foreach ($tmpcdata as $kk => $vv) {
                    if($v==$vv){
                        $reskey=$kk;
                        break;
                    }
                }
            }
        }else{
            foreach ($tmpcdata as $m => $n) {
                $reskey=$m;
            }
        }
        unset($tmpcdata);
        unset($tmpccdata);
        return $reskey;
    }


    /**
     * 名称:  请求接口获取数据
     * 参数:  string $key     接口地址
     * 返回值: array   数据;
     */

    private function GetData($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1) ;
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);   //只需要设置一个秒的数量就可以
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true) ;
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.2) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13 ');
        $output = curl_exec($ch);
        if($output === false)  //超时处理
        { 
            if(curl_errno($ch) == CURLE_OPERATION_TIMEDOUT)  
            {  
                $log = array(
                            'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                            'content' => "时间：".date('Ymd-H:i:s',time())."\r\n错误内容为：curl通过get方式请求{$url}的连接超时\r\n"
                );
                $this->log($log);
            }else{
                $log = array(
                            'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                            'content' => "时间：".date('Ymd-H:i:s',time())."\r\nerror:curl请求错误码:".curl_errno($ch)."\r\n"
                        );
                $this->log($log);
            }
        }
        curl_close($ch);
        if (empty($output)) {
            return ;
        }
        return $output;
        // $result = json_decode($output, true);
        // return $result;
    }

    /**
     * 名称:  请求接口提交数据
     * 参数:  string $key     接口地址
     * 参数:  array $data     提交数据
       参数： bool  $json    是否json提交
     * 参数： bool  $token     token值
     * 返回值: array   数据;
     */

    private function PostData($url, $data, $json = false, $token = false)
    {
        $datastring = $json ? json_encode($data) : http_build_query($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url) ;
        curl_setopt($ch, CURLOPT_POST, 1) ;
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);   //只需要设置一个秒的数量就可以
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $datastring);
        if ($json) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($datastring))
            );
        }
        if ($token) {
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($datastring),
                    'Authorization:'.$token
                )
            );
        }
        $output=curl_exec($ch);
        if($output === false)  //超时处理
        { 
            if(curl_errno($ch) == CURLE_OPERATION_TIMEDOUT)  
            {  
                $log = array(
                            'path' => dirname(__DIR__) .'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log', 
                            'content' => "时间：".date('Ymd-H:i:s',time())."\r\n错误内容为：curl通过post方式请求{$url}的连接超时\r\n请求数据打印：".$datastring."\r\n"
                );
                $this->log($log);
            }  
        }
        curl_close($ch) ;
        if (empty($output)) {
            return ;
        }
        $result = json_decode($output, true);
        return $result;
    }

    private function getfriend($uid){
        ob_start();
        $this->application->execute(array('swoole_model','getfriend'),$uid);
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
     //包装数据
    public function packmes($data, $format = '\r\n\r\n', $preformat = '######')
    {
        //return $preformat . json_encode($data, true) . $format;
        return pack('N', strlen($data)) . $data;
    }
 
    //解包装数据
    public function unpackmes($data, $format = '\r\n\r\n', $preformat = '######')
    {
        
        $resultdata = substr($data, 4);
        return $resultdata;
    }

    private function log($data)
    {
        if (!file_put_contents( $this->exitdir($data['path']), $data['content']."\r\n", FILE_APPEND )) {
            return false;
        }
        return true;
    }

    private function exitdir($dir)
    {
        $dirarr=pathinfo($dir);
        if (!is_dir( $dirarr['dirname'] )) {
            mkdir( $dirarr['dirname'], 0777, true);
        }
        return $dir;
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new SpiderServer;
        }
        return self::$instance;
    }
}

SpiderServer::getInstance();
