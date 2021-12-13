<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 'On');
  require_once './include/db_handler.php';
  require_once './include/config.php';

 if($_SERVER['REQUEST_METHOD']=='POST') {
 $headers =  getallheaders();
 $file_upload_url = 'http://' . HOST . '/socialapp-api/';
 $db  = new DbHandler();
 $api = $headers['Authorization'];
 $user = $db->getUserId($api);
 if ($db->checkApi($api) && $user["isDisabled"] != 1) {
 $file_name = $_FILES['myFile']['name'];
 $temp_name = $_FILES['myFile']['tmp_name'];
 $file_type = $headers['fileType'];

 $location = "uploads/videos/";
 $video_path = ($location . $user['username'] . '-' . rand(1, 10000) . '-' . rand(10, 50000) . '-' . $user['id'] . $file_type);
 move_uploaded_file($temp_name, $video_path);
 echo ($file_upload_url.$video_path);
 } else {
 echo '400';
 }
} else {
  echo '404';
}
 ?>
