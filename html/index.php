<?php
  $title = "Счетчики";
  $js = "counters.js";
  $ajax = "ajax.php";

  $local_config = "local.php";

  if(preg_match('/devel\.php/', __FILE__)) {
    $js="counters_devel.js";
    $ajax = "ajax_devel.php";
    $local_config = "local_devel.php";
  };

  require($local_config);

?>
<!DOCTYPE html>
<HTML>
<HEAD>
<TITLE><?php echo $title; ?></TITLE>
<link rel="icon" href="data:;base64,iVBORw0KGgo=">
<link href="/jq-ui/jquery-ui.css" rel="stylesheet\">
<SCRIPT type="text/javascript" src="/jq.js"></script>
<SCRIPT type="text/javascript" src="/jq-ui/jquery-ui.js"></script>
<SCRIPT type="text/javascript">
var AJAX = "<?php echo $ajax; ?>";
</script>
<SCRIPT type="text/javascript" src="/mylib/js/myjslib.js"></script>
<SCRIPT type="text/javascript" src="<?php echo $js; ?>"></script>
<STYLE>
.ns {
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
.sel {
    -webkit-touch-callout: initial;
    -webkit-user-select: text;
    -khtml-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
    user-select: text;
}
</STYLE>
</HEAD>
<BODY>
</BODY>
</HTML>
