<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
ini_set('display_startup_errors',1);

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");



const R_SUPER="super";

$rights_list[R_SUPER]="Супр";

$MAX_SESSION_AGE=36000;

$time=time();
$version="";

if(preg_match("/_devel/", __FILE__)) {
  $version="_devel";
}

require("local".$version.".php");
require($_SERVER['DOCUMENT_ROOT']."/mylib/php/myphplib.php");

function error_exit($error) {
  close_db(FALSE);
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
  unset($_SESSION['pc']);
  unset($_SESSION['time']);
#error_exit("Boo at ".__LINE__);
  $check_login=$q['login'];

  $query="SELECT * FROM users WHERE user_login=".mq($check_login)." AND user_md5_password=MD5(".mq($q['password']).") AND user_deleted=0";
  $user_row=return_one($query,TRUE,"Неверно указан логин или пароль");

  if($user_row['user_blocked'] != 0) { error_exit("Пользователь заблокирован"); };

  run_query("UPDATE users SET user_last_login=$time WHERE user_id=".mq($user_row['user_id']));

  $_SESSION['login']=$check_login;
  $_SESSION['pc']=$user_row['user_password_count'];
  $_SESSION['time']=time();

  ok_exit("ok");
};

if($q['action'] == 'logout') {

  unset($_SESSION['login']);
  unset($_SESSION['pc']);
  unset($_SESSION['time']);

  ok_exit("ok");
};

if(isset($_SESSION['login']) && isset($_SESSION['pc']) && isset($_SESSION['time']) && (time() - $_SESSION['time']) < $MAX_SESSION_AGE) {
  $check_login=$_SESSION['login'];
  $check_pc=$_SESSION['pc'];
} else {
  error_exit("no_auth");
};

$query="SELECT * FROM users WHERE user_login=".mq($check_login)." AND user_password_count=".mq($check_pc)." AND user_deleted=0";
$user_row=return_one($query,TRUE,"no_auth");
if($user_row['user_blocked'] != 0) { error_exit("Пользователь заблокирован"); };

run_query("UPDATE users SET user_last_activity=$time WHERE user_id=".mq($user_row['user_id']));

$_SESSION['time']=time();
$user_self_id=$user_row['user_id'];
$user_self_login=$user_row['user_login'];
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
  require_right(R_SUPER);

  require_p('c_id', '/^\d+$/');
  $act_id = $q['c_id'];

  require_p('c_connect', '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+(?:\/\d+)?$/');
  require_p('c_type', '/\S/');
  if(!isset($types[ $q['c_type'] ])) { error_exit("Unknown c_type"); };
  require_p('c_serial');
  require_p('c_descr', '/\S/');
  require_p('c_coords', '/^(?:-?\d{1,3}(?:\.\d+)?, ?-?\d{1,3}(?:\.\d+)?|)$/');
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

  $prev_row=return_one('SELECT * FROM cs WHERE c_id='.mq($act_id), TRUE);

  trans_start();

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
  $query .= ",c_number=".mq($q['c_number']);
  $query .= ",change_by=".mq($user_self_id);
  $query .= ",ts=$time";

  $query .= " WHERE c_id=".mq($act_id);

  run_query($query);

  run_query("DELETE FROM crs WHERE cr_fk_c_id=".mq($act_id));

  
  if(isset($types[ $q['c_type'] ]['reads'])) {
    foreach($types[ $q['c_type'] ]['reads'] as $read) {
      $var_name=$read['var'];
      $var_value=$q['crs'][ $read['var'] ];
      
      $query = "INSERT INTO crs SET";
      $query .= " cr_fk_c_id=".mq($act_id);
      $query .= ",cr_name=".mq($var_name);
      $query .= ",cr_value=".mq($var_value);
      $query .= ",change_by=".mq($user_self_id);
      $query .= ",ts=$time";

      run_query($query);
    };
  };

  $ret=return_one('SELECT * FROM cs WHERE c_id='.mq($act_id), TRUE);
  $ds = return_query("SELECT * FROM ds WHERE d_fk_c_id=".mq($act_id), 'd_name');
  if(count($ds) > 0) {
    $ret['ds'] = $ds;
  };
  $ret['crs'] = return_query("SELECT * FROM crs WHERE cr_fk_c_id=".mq($act_id), 'cr_name');
  ok_exit($ret);
} else if($q['action'] == 'add_counter') {
  require_right(R_SUPER);

  require_p('c_connect', '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+(?:\/\d+)?$/');
  require_p('c_type', '/\S/');
  if(!isset($types[ $q['c_type'] ])) { error_exit("Unknown c_type"); };
  require_p('c_serial');
  require_p('c_descr', '/\S/');
  require_p('c_coords', '/^(?:-?\d{1,3}(?:\.\d+)?, ?-?\d{1,3}(?:\.\d+)?|)$/');
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

  trans_start();

  $query="INSERT INTO cs SET";
  $query .= " c_connect=".mq($q['c_connect']);
  $query .= ",c_type=".mq($q['c_type']);
  $query .= ",c_serial=".mq($q['c_serial']);
  $query .= ",c_descr=".mq($q['c_descr']);
  $query .= ",c_coords=".mq($q['c_coords']);
  $query .= ",c_location=".mq($q['c_location']);
  $query .= ",c_tz=".mq($q['c_tz']);
  if($q['c_paused'] != 0) {
    $query .= ",c_paused=".mq(time());
  } else {
    $query .= ",c_paused=0";
  };
  $query .= ",c_comment=".mq($q['c_comment']);
  $query .= ",c_fk_s_id=".mq($q['c_fk_s_id']);
  $query .= ",c_number=".mq($q['c_number']);
  $query .= ",change_by=".mq($user_self_id);
  $query .= ",ts=$time";

  run_query($query);

  $act_id = insert_id();
  
  if(isset($types[ $q['c_type'] ]['reads'])) {
    foreach($types[ $q['c_type'] ]['reads'] as $read) {
      $var_name=$read['var'];
      $var_value=$q['crs'][ $read['var'] ];
      
      $query = "INSERT INTO crs SET";
      $query .= " cr_fk_c_id=".mq($act_id);
      $query .= ",cr_name=".mq($var_name);
      $query .= ",cr_value=".mq($var_value);
      $query .= ",change_by=".mq($user_self_id);
      $query .= ",ts=$time";

      run_query($query);
    };
  };

  $ret=return_one('SELECT * FROM cs WHERE c_id='.mq($act_id), TRUE);
  $ret['crs'] = return_query("SELECT * FROM crs WHERE cr_fk_c_id=".mq($act_id), 'cr_name');
  ok_exit($ret);
} else if($q['action'] == 'delete_counter') {
  require_right(R_SUPER);

  require_p('c_id', '/^\d+$/');
  $act_id = $q['c_id'];

  trans_start();

  $data_count=return_single("SELECT COUNT(*) FROM ds WHERE d_fk_c_id=".mq($act_id), TRUE);
  $reads_count=return_single("SELECT COUNT(*) FROM crs WHERE cr_fk_c_id=".mq($act_id), TRUE);

  if(($data_count + $reads_count) > 0) {
    $query = "UPDATE cs SET";
    $query .= " c_deleted=$time";
    $query .= ",change_by=".mq($user_self_id);
    $query .= ",ts=$time";

    $query .= " WHERE c_id=".mq($act_id);

    run_query($query);
  } else {
    run_query("DELETE FROM crs WHERE cr_fk_c_id=".mq($act_id));
    run_query("DELETE FROM cs WHERE c_id=".mq($act_id));
  };

  ok_exit("done");
} else if($q['action'] == 'list_suppliers') {
  $ret=return_query("SELECT ss.*,(SELECT COUNT(*) FROM cs WHERE c_deleted=0 AND c_fk_s_id=s_id) as used_in FROM ss WHERE s_deleted=0", "s_id");
  ok_exit($ret);
} else if($q['action'] == 'add_supplier') {
  require_right(R_SUPER);

  require_p('s_short_name', '/\S/');
  require_p('s_full_name');
  require_p('s_contacts');

  $query="INSERT INTO ss SET";
  $query .= " s_short_name=".mq($q['s_short_name']);
  $query .= ",s_full_name=".mq($q['s_full_name']);
  $query .= ",s_contacts=".mq($q['s_contacts']);
  $query .= ",change_by=".mq($user_self_id);
  $query .= ",ts=$time";

  trans_start();

  run_query($query);

  $act_id = insert_id();
  
  $ret=return_one('SELECT ss.*,(SELECT COUNT(*) FROM cs WHERE c_deleted=0 AND c_fk_s_id=s_id) as used_in FROM ss WHERE s_id='.mq($act_id), TRUE);
  ok_exit($ret);
} else if($q['action'] == 'edit_supplier') {
  require_right(R_SUPER);

  require_p('s_id', '/^\d+$/');
  $act_id=$q['s_id'];
  require_p('s_short_name', '/\S/');
  require_p('s_full_name');
  require_p('s_contacts');

  $query="UPDATE ss SET";
  $query .= " s_short_name=".mq($q['s_short_name']);
  $query .= ",s_full_name=".mq($q['s_full_name']);
  $query .= ",s_contacts=".mq($q['s_contacts']);
  $query .= ",change_by=".mq($user_self_id);
  $query .= ",ts=$time";
  $query .= " WHERE s_id=".mq($act_id);

  trans_start();

  run_query($query);
  
  $ret=return_one('SELECT ss.*,(SELECT COUNT(*) FROM cs WHERE c_deleted=0 AND c_fk_s_id=s_id) as used_in FROM ss WHERE s_id='.mq($act_id), TRUE);
  ok_exit($ret);
} else if($q['action'] == 'delete_supplier') {
  require_right(R_SUPER);

  require_p('s_id', '/^\d+$/');
  $act_id=$q['s_id'];

  $c=return_single("SELECT COUNT(*) FROM cs WHERE c_deleted=0 AND c_fk_s_id=".mq($act_id), TRUE);

  if($c > 0) { error_exit("Поставщик выбран в $c счетчиках, удаление невозможно"); };

  $c=return_single("SELECT COUNT(*) FROM cs WHERE c_fk_s_id=".mq($act_id), TRUE);
  if($c == 0) {
    run_query('DELETE FROM ss WHERE s_id='.mq($act_id));
  } else {
    run_query('UPDATE ss SET s_deleted=$time WHERE s_id='.mq($act_id));
  };

  ok_exit("done");
} else if($q['action'] == 'list_users') {
  require_right(R_SUPER);

  $ret=return_query("SELECT user_id, user_login,user_rights,user_name,user_last_login,user_last_activity,user_blocked,user_block_reason,ts,change_by"
                   ." FROM users WHERE user_deleted=0", "user_id");
  ok_exit($ret);
} else if($q['action'] == 'add_user') {
  require_right(R_SUPER);

  require_p('user_login', '/^[a-zA-Z0-9_\-\.\@]+$/');
  require_p('user_name', '/\S/');
  require_p('user_pass', '/\S/');
  require_p('user_blocked', '/^(?:0|1)$/');
  require_p('user_block_reason');
  require_p('user_rights', '/^ *(?:[a-zA-Z0-9_]+(?: *, *[a-zA-Z0-9_]+)*)? *?$/');

  $rs=trim($q['user_rights']);
  if($rs != "") {
    $ra=explode(",", $rs);
    foreach($ra as $r) {
      $right=trim($r);
      if(!isset($rights_list[$right])) { error_exit("Unknown right ".$right); };
      if(!has_right($right)) { error_exit("Нельзя делегировать права, которых у Вас нет"); };
    };
  };

  $query="INSERT INTO users SET";
  $query .= " user_login=".mq(strtolower($q['user_login']));
  $query .= ",user_name=".mq($q['user_name']);
  $query .= ",user_rights=".mq($rs);
  $query .= ",user_md5_password=MD5(".mq($q['user_pass']).")";
  if($q['user_blocked'] != 0) { $q['user_blocked'] = $time; };
  $query .= ",user_blocked=".mq($q['user_blocked']);
  $query .= ",user_block_reason=".mq($q['user_block_reason']);
  $query .= ",change_by=".mq($user_self_id);
  $query .= ",ts=$time";

  trans_start();

  run_query($query);

  $act_id = insert_id();
  
  $ret=return_one("SELECT user_id, user_login,user_rights,user_name,user_last_login,user_last_activity,user_blocked,user_block_reason,ts,change_by"
                 ." FROM users"
                 ." WHERE user_id=".mq($act_id), TRUE);
  ok_exit($ret);
} else if($q['action'] == 'edit_user') {
  require_right(R_SUPER);
  require_p('user_id', '/^\d+$/');
  $act_id=$q['user_id'];

  require_p('user_login', '/^[a-zA-Z0-9_\-\.\@]+$/');
  require_p('user_name', '/\S/');
  require_p('user_blocked', '/^(?:0|1)$/');
  require_p('user_block_reason');
  require_p('user_rights', '/^ *(?:[a-zA-Z0-9_]+(?: *, *[a-zA-Z0-9_]+)*)? *?$/');

  if(isset($q['user_pass'])) {
    require_p('user_pass', '/\S/');
  };

  $prev_row=return_one("SELECT * FROM users WHERE user_id=".mq($act_id), TRUE);

  $rs=trim($q['user_rights']);
  if($rs != "") {
    $ra=explode(",", $rs);
    foreach($ra as $r) {
      $right=trim($r);
      if(!isset($rights_list[$right])) { error_exit("Unknown right ".$right); };

      if(!has_right($right) && !has_right($right, $prev_row['user_rights'])) { error_exit("Нельзя делегировать права, которых у Вас нет"); };
    };
  };

  $prev_rs=trim($prev_row['user_rights']);
  if($prev_rs != "") {
    foreach(explode(",", $prev_rs) as $r) {
      $right=trim($r);
      if(!has_right($right) && !has_right($right, $rs)) { error_exit("Нельзя забрать права, которых у Вас нет"); };
    };
  };

  $query="UPDATE users SET";
  $query .= " user_login=".mq(strtolower($q['user_login']));
  $query .= ",user_name=".mq($q['user_name']);
  $query .= ",user_rights=".mq($rs);
  if(isset($q['user_pass'])) {
    $query .= ",user_md5_password=MD5(".mq($q['user_pass']).")";
    $query .= ",user_password_count=user_password_count+1";
  };
  if($q['user_blocked'] != 0 && $prev_row['user_blocked'] == 0) {
    $query .= ",user_blocked=".mq($time);
  } else if($q['user_blocked'] == 0) {
    $query .= ",user_blocked=0";
  };
  $query .= ",user_block_reason=".mq($q['user_block_reason']);
  $query .= ",change_by=".mq($user_self_id);
  $query .= ",ts=$time";

  $query .= " WHERE user_id=".mq($act_id);

  trans_start();

  run_query($query);
  
  $ret=return_one("SELECT user_id, user_login,user_rights,user_name,user_last_login,user_last_activity,user_blocked,user_block_reason,ts,change_by"
                 ." FROM users"
                 ." WHERE user_id=".mq($act_id), TRUE);
  ok_exit($ret);
} else if($q['action'] == 'delete_user') {
  require_right(R_SUPER);
  require_p('user_id', '/^\d+$/');
  $act_id=$q['user_id'];

  $prev_row=return_one("SELECT * FROM users WHERE user_id=".mq($act_id), TRUE);

  if($act_id == $user_self_id) { error_exit("Нельзя удалить собственную учетную запись"); };

  $query="UPDATE users SET";
  $query .= " user_deleted=".mq($time);
  $query .= " WHERE user_id=".mq($act_id);

  run_query($query);

  ok_exit("done");

} else if($q['action'] == 'get_counter_info') {
  require_p('c_id', '/^\d+$/');
  $act_id=$q['c_id'];

  $query="SELECT * FROM cs WHERE c_id=".mq($act_id);

  $ret=return_one($query, TRUE);

  $query="SELECT * FROM ds WHERE d_fk_c_id=".mq($act_id);


  $ret['ds']=return_query($query, "d_name");

  $crs=return_query("SELECT * FROM crs WHERE cr_fk_c_id=".mq($act_id), "cr_name");
  if(count($crs) > 0) {
    $ret['crs']=$crs;
  };

  $ret['user']=return_one("SELECT user_login, user_name, user_deleted FROM users WHERE user_id=".mq($ret['change_by']), TRUE);

  $ret['ss']=return_one("SELECT * FROM ss WHERE s_id=".mq($ret['c_fk_s_id']), TRUE);

  ok_exit($ret);
};

error_exit("Unknown action: ".$q['action']);

?>
