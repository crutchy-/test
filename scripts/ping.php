<?php

# gpl2
# by crutchy
# 19-july-2014

#####################################################################################################

ini_set("display_errors","on");
date_default_timezone_set("UTC");
require_once("lib.php");
$trailing=trim($argv[1]);
define("BUCKET_PING_LAG","<<PING_LAG>>");
pm("#","ping test");
$t=time();
if ($trailing<>"")
{
  $ping_lag=get_bucket(BUCKET_PING_LAG);
  $delta=$t-$ping_lag;
  if ($delta>20)
  {
    term_echo("==================== PING TIMEOUT DETECTED ====================");
    echo "/INTERNAL ~restart\n";
  }
}
else
{
  set_bucket(BUCKET_PING_LAG,$t);
  rawmsg("PING $t");
}

#####################################################################################################

?>
