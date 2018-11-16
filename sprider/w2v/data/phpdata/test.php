<?php
$dsn='mysql:host=localhost;dbname=v9';
$user='root';
$password='51cto';
function characet($data){
  if( !empty($data) ){
    $fileType = mb_detect_encoding($data , array('UTF-8','GBK','LATIN1','BIG5')) ;
    if( $fileType != 'UTF-8'){
      $data = mb_convert_encoding($data ,'utf-8' , $fileType);
    }
  }
  return $data;
}
try {
    $sql='select * from v9_news_data';
    $dbh=new PDO($dsn,$user,$password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); 
    $dbh->exec("set names 'utf8'");
    $stmt=$dbh->prepare($sql);
   // $stmt->bindParam(':status',$status);
    $stmt->execute();
    //返回插入、更新、删除的受影响行数
    // echo $stmt->rowCount();
    //返回最后插入的id
    // echo 'ID of last insert:'.$dbh->lastInsertId();
    while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['id'].'</br>';
	$file_content = strip_tags(str_replace('&nbsp;','',$row['content']));
	//echo $file_content;
	//$file_content = iconv("gbk", "utf-8", $file_content);
	if(trim($file_content)!=''){
	file_put_contents("log.txt", "<content>".$file_content."</content>\r\n", FILE_APPEND);
	}
    }
} catch (PDOException $e) {
    echo 'SQL Query:'.$sql.'</br>';
    echo 'Connection failed:'.$e->getMessage();
}

?>
