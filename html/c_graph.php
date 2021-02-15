<?php

$cache_off=false;
if(isset($_REQUEST['no_cache'])) {
  $cache_off=true;
};
function dumper($var) {
  ob_start();
  var_dump($var);
  $dump_str=ob_get_contents();
  ob_end_clean();
  return $dump_str;
};

$error_png_dir="/var/www/html";

function error_exit($error) {
  global $error_png_dir;
  
  if(!isset($error)) {
    $error=$error_png_dir."/error.png";
  } else {
    $error=$error_png_dir."/".$error.".png";
  };
  $size = getimagesize($error);
  header('Content-type: '.$size['mime']);
  header('Cache-Control: no-cache, must-revalidate');
  header('Pragma: no-cache');
  header('Cache-Control: no-store');
  readfile($error);
  exit;
};

function error_text($text) {
  header('Content-type: text/plain');
  echo $text;
  exit;
};

function num_str_compare($a,$b) {
  $ar_a=preg_split('/([0-9]+)/', $a, -1, PREG_SPLIT_DELIM_CAPTURE);
  $ar_b=preg_split('/([0-9]+)/', $b, -1, PREG_SPLIT_DELIM_CAPTURE);
  while(1) {
    $aa_a=array_shift($ar_a);
    $aa_b=array_shift($ar_b);
    if(isset($aa_a) && isset($aa_b)) {
      if($aa_a !== $aa_b) {
        if(is_numeric($aa_a) && is_numeric($aa_b)) {
          return $aa_a - $aa_b;
        } else {
          return strcmp($aa_a, $aa_b);
        };
      };
    } else if(isset($aa_a)) {
      return 1;
    } else if(isset($aa_b)) {
      return -1;
    } else {
      return 0;
    };
  };
  return 0;
};

$comp=false;

$rrd_root="/ssd/rrd/counters/";
$png_cache="/var/counters_png_cache/";

$cmd="/usr/local/bin/rrdtool graph \"PNG_PLACE_HOLDER\" --daemon /var/run/rrdcached.sock --slope-mode";

$png_end="";

if(isset($_REQUEST['start']) && preg_match('/^[0-9+\-a-zA-Z \:\.\/]+$/', $_REQUEST['start'])) {
  $cmd .= " --start \"".$_REQUEST['start']."\"";
  $png_end .= "_start_".$_REQUEST['start'];
} else {
  $cmd .= " --start end-1h";
};

if(isset($_REQUEST['end']) && preg_match('/^[0-9+\-a-zA-Z \:\.\/]+$/', $_REQUEST['end'])) {
  $cmd .= " --end \"".$_REQUEST['end']."\"";
  $png_end .= "_end_".$_REQUEST['end'];
} else {
  $cmd .= " --end now-1min";
};

if(isset($_REQUEST['max']) && preg_match('/^-?[0-9]+$/', $_REQUEST['max'])) {
  $cmd .= " --upper-limit ".$_REQUEST['max'];
  $png_end .= "_max_".$_REQUEST['max'];
};

if(isset($_REQUEST['min']) && preg_match('/^-?[0-9]+$/', $_REQUEST['min'])) {
  $cmd .= " --lower-limit ".$_REQUEST['min'];
  $png_end .= "_min_".$_REQUEST['min'];
};

if(isset($_REQUEST['compact']) && !isset($_REQUEST['small'])) {
  $comp=true;
  $png_end .= "_compact";
};

if(isset($_REQUEST['small'])) {
  $width=60;
  $height=30;
  $cmd .= " --only-graph";
  $png_end .= "_small";
} else {
  $width=400;
  $height=100;
};

$exact=false;

if(isset($_REQUEST['exact'])) {
  $exact=true;
  $png_end .= "_exact";
};


if(isset($_REQUEST['width']) && preg_match('/^[0-9]+$/', $_REQUEST['width'])) {
  $width=$_REQUEST['width'];
};


if(isset($_REQUEST['height']) && preg_match('/^[0-9]+$/', $_REQUEST['height'])) {
  $height=$_REQUEST['height'];
};

$cmd .= " -w $width";
$cmd .= " -h $height";

$png_end .= "_${width}x${height}";

if(!isset($_REQUEST['var']) || !preg_match('/^[a-zA-Z_0-9]+$/', $_REQUEST['var'])) {
  if(isset($_REQUEST['debug'])) { error_text(__LINE__); };
  error_exit(null);
};

if(!isset($_REQUEST['c_id']) || !preg_match('/^[0-9]+$/', $_REQUEST['c_id'])) {
  if(isset($_REQUEST['debug'])) { error_text(__LINE__); };
  error_exit(null);
};

$c_id=$_REQUEST['c_id'];
$var=$_REQUEST['var'];

$total=0;

$png=$png_cache.sprintf("%08d", $c_id)."_".$var.$png_end.".png";
$rrd=$rrd_root.sprintf("%08d", $c_id)."_".$var.".rrd";
  
if(is_file($rrd)) {

  $cmd .= " DEF:val=$rrd:$var:AVERAGE";
  if(!$comp) {
    $cmd .= " LINE:val#009900:";
    $cmd .= " VDEF:valmin=val,MINIMUM";
    $cmd .= " VDEF:valavg=val,AVERAGE";
    $cmd .= " VDEF:valmax=val,MAXIMUM";
    $cmd .= " VDEF:vallast=val,LAST";
    $cmd .= " COMMENT:'           Min       Avg        Max       Last\\n'";
    $cmd .= " COMMENT:'КВт/ч\\t'";
    $cmd .= " GPRINT:valmin:'%.1lf".($exact?"":"%s")."\\t'";
    $cmd .= " GPRINT:valavg:'%.1lf".($exact?"":"%s")."\\t'";
    $cmd .= " GPRINT:valmax:'%.1lf".($exact?"":"%s")."\\t'";
    $cmd .= " GPRINT:vallast:'%.1lf".($exact?"":"%s")."\\n'";
  } else {
    $cmd .= " LINE:val#009900:";
    $cmd .= " COMMENT:''";
  };

  if(!$comp) {
  } else {
  };
  $cmd .= " VDEF:gStart=val,FIRST";
  $cmd .= " VDEF:gEnd=val,LAST";
  if($comp) {
    $cmd .= " COMMENT:'\\n'";
  };
  $cmd .= " GPRINT:gStart:'Start\: %H\:%M\:%S %d/%m/%Y\\t':strftime";
  $cmd .= " GPRINT:gEnd:'End\: %H\:%M\:%S %d/%m/%Y':strftime";
} else {
  error_exit("wait");
};

$cmd=preg_replace('/PNG_PLACE_HOLDER/', $png, $cmd);
$cmd .= " 2>&1";

$png_stat=@stat($png);
if($cache_off || $png_stat === FALSE || ((time() - $png_stat['mtime']) >= 60)) {
  $output=array();
  if(isset($_REQUEST['debug'])) {
    error_text($cmd."\ncache_off: $cache_off\npng_stat: ".($png_stat === FALSE ? "FALSE":"ARRAY")."\nmtime: ".($png_stat !== FALSE ? $png_stat['mtime'] : "no data"));
  } else {
    exec($cmd, $output, $ret);
  };
  if(isset($_REQUEST['debug_exec'])) {
    $output_text="";
    foreach($output as $line) {
     $output_text .= $line;
    };
    error_text("exec returned code: $ret\ntext: $output_text");
  };
  if($ret != 0) {
    $png=$error_png_dir."/error.png";
  };
} else if(isset($_REQUEST['debug'])) {
  error_text("Cache hit\npng_stat: ".($png_stat === FALSE ? "FALSE":"ARRAY")."\nmtime: ".($png_stat !== FALSE ? $png_stat['mtime'] : "no data"));
};
$size = getimagesize($png);
header('Content-type: '.$size['mime']);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($png);
?>
