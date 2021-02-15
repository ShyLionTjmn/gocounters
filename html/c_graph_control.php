<?php
ini_set("memory_limit", "512M");

function head($title) {
?>
<!DOCTYPE html>
<HTML>
<HEAD>
<link rel="stylesheet" href="/jq-ui/jquery-ui.css">
<script src="/jq.js"></script>
<script src="/jq-ui/jquery-ui.js"></script>
<script src="gc.js"></script>
<script src="graph_control.js"></script>
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

if(!isset($_REQUEST['type'])) {
  head("Error");
  error_exit("No type specified");
};

$type=$_REQUEST['type'];

if(!isset($_REQUEST['dev_id'])) {
  head("Error");
  error_exit("No dev_id");
};

$dev_id=$_REQUEST['dev_id'];

if(!preg_match('/^[0-9a-zA-Z\-_\.]+$/', $dev_id)) {
  head("Error");
  error_exit("Bad dev_id");
};

if($type == 'int_io' || $type == 'int_pkts' || $type == 'opt_power') {
  if(!isset($_REQUEST['int'])) {
    head("Error");
   error_exit("No int");
  };
  $int=$_REQUEST['int'];
  if(!preg_match('/^[0-9a-zA-Z\-_\.<>]+$/', $int)) {
    head("Error");
    error_exit("Bad int");
  };
};

$file_data=@file_get_contents("http://localhost:8181/?safe_dev_id=".urlencode($dev_id));
if($file_data === FALSE) {
  head("Error");
  error_exit("Cannot load JSON data");
};

$fdata=json_decode($file_data, true);

if($fdata === NULL) {
  head("Error");
  error_exit("Cannot decode JSON data");
};

if(!isset($fdata['devs'])) {
  head("Error");
  error_exit("No devs section in JSON data");
};

$dev_name;
$int_name;
$int_alias="";
$max;

$cpus;
$memoryUsed;

foreach($fdata['devs'] as $dev => $dev_data) {

  $safe_dev=preg_replace('/\//', 's', $dev);
  $safe_dev=preg_replace('/:/', 'c', $safe_dev);
  $safe_dev=preg_replace('/\s/', '_', $safe_dev);

  if($safe_dev == $dev_id) {
    $dev_name=$dev_data['short_name'];
    if($type == 'int_io' || $type == 'int_pkts' || $type == 'opt_power') {
      if(isset($dev_data['interfaces'])) {
        foreach($dev_data['interfaces'] as $i => $idata) {
          $safe_int=preg_replace('/\//', 's', $i);
          $safe_int=preg_replace('/:/', 'c', $safe_int);
          $safe_int=preg_replace('/\s/', '_', $safe_int);
//          $safe_int=preg_replace('/>/', '_', $safe_int);
//          $safe_int=preg_replace('/</', '_', $safe_int);

          if($safe_int == $int) {
            $int_name=$i;
            $int_alias=$idata['ifAlias'];
            $ifspeed=1000000000;
            if(isset($idata['ifSpeed']) && $idata['ifSpeed'] > 0) {
              $ifspeed=$idata['ifSpeed'];
            };
            if($type == 'int_io') {
              $max=$ifspeed;
            } else if($type == 'int_pkts') {
              $max=round($ifspeed/12000);
            };
            break;
          }; 
        };
      };
    } else if($type == "cpu") {
      if(isset($dev_data['CPUs'])) {
        foreach($dev_data['CPUs'] as $cpu => $cpu_data) {
          $cpu_name=preg_replace('/[`\'\\%]/', '_', $dev_data['CPUs'][$cpu]['name']);
          $cpu_name=preg_replace('/^SCE8000 traffic processor /', 'Traff CPU', $cpu_name);
          $cpu_name=preg_replace('/^SCE8000 control processor/', 'Ctrl CPU', $cpu_name);
          $cpus[$cpu]=$cpu_name;
        };
      };
      $max=100;
    } else if($type == "mem") {
      if(isset($dev_data['memorySize'])) {
        $max=$dev_data['memorySize'];
      };
      if(isset($dev_data['memoryUsed'])) {
        $memoryUsed=$dev_data['memoryUsed'];
      };
    };
    break;
  };
};

if(!isset($dev_name)) {
  head("Error");
  error_exit("No such device");
};

if( ($type == 'int_io' || $type == 'int_pkts' || $type == 'opt_power') &&
    !isset($int_name)
) {
  head("Error");
  error_exit("No such interface");
};

if( $type == 'cpu' && !isset($cpus)) {
  head("Error");
  error_exit("No CPUs data");
};

if( $type == 'mem' && !isset($memoryUsed)) {
  head("Error");
  error_exit("No memory data");
};


if($type == "int_io") {
  $title="Input/Output of $int_name".($int_alias != "" ? " ($int_alias)" : "")." on $dev_name";
} else if($type == "int_pkts") {
  $title="Packet statistics of $int_name".($int_alias != "" ? " ($int_alias)" : "")." on $dev_name";
} else if($type == "opt_power") {
  $title="Optical power on $int_name".($int_alias != "" ? " ($int_alias)" : "")." on $dev_name";
} else if($type == "cpu") {
  $title="CPU load on $dev_name";
} else if($type == "mem") {
  $title="Memory usage on $dev_name";
} else {
  head("Error");
  error_exit("should not get here at ".__LINE__);
};

head($title);
?>
<SCRIPT>
  var gc_type=<?php echo json_encode($type); ?>;
  var gc_dev_id=<?php echo json_encode($dev_id); ?>;
  var gc_dev_name=<?php echo json_encode($dev_name); ?>;
  var gc_int<?php if(isset($int)) { echo "=".json_encode($int); } ?>;
  var gc_int_name<?php if(isset($int_name)) { echo "=".json_encode($int_name); } ?>;
  var gc_int_alias<?php if(isset($int_alias)) { echo "=".json_encode($int_alias); }; ?>;
  var gc_max<?php if(isset($max)) { echo "=".json_encode($max); }; ?>;
  var gc_cpus<?php if(isset($cpus)) { echo "=\"".addslashes(json_encode($cpus))."\""; }; ?>;
  var gc_cpus_a<?php if(isset($cpus)) { echo "=\"".addslashes(json_encode($dev_data['CPUs']))."\""; }; ?>;
</SCRIPT>
<?php
?>

</BODY>
</HTML>
