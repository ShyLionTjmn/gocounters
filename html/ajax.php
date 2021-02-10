<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
ini_set('display_startup_errors',1);

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");



const R_SUPER="super";
$MAX_SESSION_AGE=36000;

$time=time();
$version="";

if(preg_match("/_devel/", __FILE__)) {
  $version="_devel";
}

require("local".$version.".php");
require($_SERVER['DOCUMENT_ROOT']."/mylib/php/myphplib.php");

function error_exit($error) {
  close_db();
  echo JSON_encode(array("error" => $error));
  exit;
};

function ok_exit($result) {
  close_db(TRUE);
  echo JSON_encode(array("ok" => $result));
  exit;
};

function audit($line, $act, $table, $q) {
  global $db;
  global $user_self_id;
  global $time;
  global $user_name;
  global $user_rights;

  $user_type=0; # system user
  $script_mtime=0;
  if(($st=@stat(__FILE__)) !== FALSE) {
    $script_mtime=$st['mtime'];
  };

  $query="INSERT INTO audit_log(time,user_id,user_ip,user_name,user_rights,user_type,action,script_mtime,code_line,table_name,query) VALUES(";
  $query .= $time;
  $query .= ", ".$user_self_id;
  $query .= ", ".mq($_SERVER['REMOTE_ADDR']);
  $query .= ", ".mq($user_name);
  $query .= ", ".mq($user_rights);
  $query .= ", ".$user_type;
  $query .= ", ".mq($act);
  $query .= ", ".$script_mtime;
  $query .= ", ".$line;
  $query .= ", ".mq($table);
  $query .= ", ".mq($q);
  $query .= ")";
  run_query($query);
};

$json=@file_get_contents("types.json");
if($json === false) {
  error_exit("Cannot read types.json");
};

$types=json_decode($json, true);
if($types === NULL) {
  error_exit("Bad types.json file");
};

$json=file_get_contents("php://input");

$q = json_decode($json, true);
if($q === NULL) {
  error_exit("Bad JSON input: $json");
};

if(!isset($q['action'])) {
  error_exit("No action in JSON");
};

$db=mysqli_connect("localhost", $DB_USER, $DB_PASS, $DB_NAME);
if(!$db) {
  error_exit("Db connect error at ".__LINE__);
};

if (!mysqli_set_charset($db, "utf8")) {
  error_exit("UTF-8 set charset error at ".__LINE__);
};

session_name("counters$version");
session_start();

if($q['action'] == 'login') {
  require_p('login');
  require_p('password');

  unset($_SESSION['login']);
  unset($_SESSION['password']);
  unset($_SESSION['time']);
#error_exit("Boo at ".__LINE__);
  $check_login=$q['login'];
  $check_password=$q['password'];

  $query="SELECT * FROM users WHERE user_login='".mysqli_real_escape_string($db,$check_login)."' AND user_md5_password=MD5('".mysqli_real_escape_string($db,$check_password)."')";
  $user_row=return_one($query,TRUE,"Неверно указан логин или пароль");

  $_SESSION['login']=$check_login;
  $_SESSION['password']=$check_password;
  $_SESSION['time']=time();

  ok_exit("ok");
};

if($q['action'] == 'logout') {

  unset($_SESSION['login']);
  unset($_SESSION['password']);
  unset($_SESSION['time']);

  ok_exit("ok");
};

if(isset($_SESSION['login']) && isset($_SESSION['password']) && isset($_SESSION['time']) && (time() - $_SESSION['time']) < $MAX_SESSION_AGE) {
  $check_login=$_SESSION['login'];
  $check_password=$_SESSION['password'];
} else {
  error_exit("no_auth");
};

$query="SELECT * FROM users WHERE user_login='".mysqli_real_escape_string($db,$check_login)."' AND user_md5_password=MD5('".mysqli_real_escape_string($db,$check_password)."')";
$user_row=return_one($query,TRUE,"no_auth");


$_SESSION['time']=time();
$user_self_id=$user_row['user_id'];
$user_rights=$user_row['user_rights'];
$user_name=$user_row['user_name'];

function has_right($right, $rights_string=NULL) {
  global $user_rights;
  if(!isset($right) || $right===NULL || $right == "") {
    return true;
  };
  if(!isset($rights_string)) {
    $rights_string=$user_rights;
  };
  if(preg_match("/(?:^|,) *super *(?:,|$)/i", $rights_string)) {
    return true;
  };
  if(preg_match("/(?:^|,) *".preg_quote($right, "/")." *(?:,|$)/i", $rights_string)) {
    return true;
  };
  return false;
};

function require_right($right, $rights_string=NULL) {
  if(!has_right($right, $rights_string)){
    error_exit("У вас нет прав $right на совершение операции.");
  };
};

if($q['action'] == 'user_check') {
  ok_exit(Array("user_self_id" => $user_self_id,
    "user_rights" => $user_rights, "user_name" => $user_name, "user_login" => $check_login
  ));
} else if($q['action'] == 'list_counters') {
  $ret=Array();

  $ret['ss'] = return_query("SELECT * FROM ss", "s_id");
  $ret['cs'] = return_query("SELECT * FROM cs WHERE c_deleted=0");
  $ret['ds'] = return_query("SELECT ds.* FROM ds INNER JOIN cs ON c_id=d_fk_c_id WHERE c_deleted=0");
  $ret['crs'] = return_query("SELECT * FROM crs INNER JOIN cs ON c_id=cr_fk_c_id WHERE c_deleted=0");

  ok_exit($ret);
} else if($q['action'] == 'edit_counter') {
  require_p('c_id', '/^\d+$/');
  require_p('c_connect', '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+(?:\/\d+)?$/');
  require_p('c_type', '/\S/');
  if(!isset($types[ $q['c_type'] ])) { error_exit("Unknown c_type"); };
  require_p('c_serial');
  require_p('c_descr', '/\S/');
  require_p('c_coords', '/^(?:-?\d{1,3}\.\d{9,}, ?-?\d{1,3}\.\d{9,}|)$/');
  require_p('c_location', '/\S/');
  require_p('c_tz', '/\S/');
  if(@timezone_open($q['c_tz']) === false) { error_exit("Bad timezone ".$q['c_tz']); };
  require_p('c_paused', '/^(?:0|1)$/');
  require_p('c_comment');
  require_p('c_fk_s_id', '/^\d+$/');
  require_p('c_number');
  require_list('crs');

  if(isset($types[ $q['c_type'] ]['reads'])) {
    foreach($types[ $q['c_type'] ]['reads'] as $read) {
      if(!isset( $q['crs'][ $read['var'] ])) { error_exit("No ".$read['var']." var in crs"); };
      if(!preg_match('/^\d+(?:\.\d+)?$/', $q['crs'][ $read['var'] ])) {
        error_exit('crs value not a number');
      };
    };
  };

  $prev_row=return_one('SELECT * FROM cs WHERE c_id='.mq($q['c_id']));

  trans_start();

  run_query("DELETE FROM crs WHERE cr_fk_c_id=".mq($q['c_id']));

  $query="UPDATE cs SET";
  $query .= " c_connect=".mq($q['c_connect']);
  $query .= ",c_type=".mq($q['c_type']);
  $query .= ",c_serial=".mq($q['c_serial']);
  $query .= ",c_descr=".mq($q['c_descr']);
  $query .= ",c_coords=".mq($q['c_coords']);
  $query .= ",c_location=".mq($q['c_location']);
  $query .= ",c_tz=".mq($q['c_tz']);
  if($prev_row['c_paused'] == 0 && $q['c_paused'] != 0) {
    $query .= ",c_paused=".mq(time());
  } else if($prev_row['c_paused'] != 0 && $q['c_paused'] == 0) {
    $query .= ",c_paused=0";
  };
  $query .= ",c_comment=".mq($q['c_comment']);
  $query .= ",c_fk_s_id=".mq($q['c_fk_s_id']);

};

error_exit("Unknown action: ".$q['action']);

?>
