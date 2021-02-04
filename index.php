<?php
  $title = "Счетчики"
  $js = "counters.js"

  $local_config = "local_config.php"

  if(preg_match('/devel\.php/', __FILE__)) {
    $js="devel.js";
    $LS_PREFIX="gomap_devel_";
  };

?>
<!DOCTYPE html>
<HTML>
<HEAD>
<TITLE>
