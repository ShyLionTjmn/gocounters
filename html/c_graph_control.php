<?php
ini_set("memory_limit", "512M");

$MAX_SESSION_AGE=36000;

$time=time();

session_name("counters");
session_start();

if(!isset($_SESSION['login']) || !isset($_SESSION['pc']) || !isset($_SESSION['time']) || (time() - $_SESSION['time']) >= $MAX_SESSION_AGE) {
  head("Error");
  error_exit("No auth");
};

$_SESSION['time']=time();


function head($title) {
?>
<!DOCTYPE html>
<HTML>
<HEAD>
<link rel="stylesheet" href="/jq-ui/jquery-ui.css">
<script src="/jq.js"></script>
<script src="/jq-ui/jquery-ui.js"></script>
<script src="c_gc.js"></script>
<script src="c_graph_control.js"></script>
<TITLE><?php echo isset($title)?htmlentities($title, ENT_HTML5, 'UTF-8'):"Graph" ?></TITLE>
</HEAD>
<BODY>
<?php
};
function dumper($var) {
  ob_start();
  var_dump($var);
  $dump_str=ob_get_contents();
  ob_end_clean();
  return $dump_str;
};

function error_exit($text) {
  echo "<SPAN style=\"color: red\">$text</SPAN>";
  echo "</BODY></HTML>";
  exit;
};

if(!isset($_REQUEST['title'])) {
  head("Error");
  error_exit("No title specified");
};

if(!isset($_REQUEST['var']) || !preg_match('/^[a-zA-Z_0-9]+$/', $_REQUEST['var'])) {
  head("Error");
  error_exit("No or bad var specified");
};

if(!isset($_REQUEST['c_id']) || !preg_match('/^[0-9]+$/', $_REQUEST['c_id'])) {
  head("Error");
  error_exit("No or bad c_id specified");
};

if(!isset($_REQUEST['unit'])) {
  head("Error");
  error_exit("No unit specified");
};

$corr=0.00;

if(isset($_REQUEST['corr'])) {
  if(!preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $_REQUEST['corr'])) {
    head("Error");
    error_exit("Bad corr specified");
  };
  $corr=$_REQUEST['corr'];
};


$c_id=$_REQUEST['c_id'];
$var=$_REQUEST['var'];
$title=$_REQUEST['title'];
$unit=$_REQUEST['unit'];


head($title);
?>
<SCRIPT>
  var gc_var=<?php echo json_encode($var); ?>;
  var gc_c_id=<?php echo json_encode($c_id); ?>;
  var gc_title=<?php echo json_encode($title); ?>;
  var gc_unit=<?php echo json_encode($unit); ?>;
  var gc_corr=<?php echo json_encode($corr); ?>;
  var gc_max<?php if(isset($max)) { echo "=".json_encode($max); }; ?>;
</SCRIPT>
<?php
?>

</BODY>
</HTML>
