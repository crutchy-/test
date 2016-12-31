<?php

#####################################################################################################

/*
exec:~ny|20|30|0|1|||||php scripts/ny.php
*/

#####################################################################################################

require_once("lib.php");

date_default_timezone_set("UTC");

$newyear=strtotime("1 January 2017");

$hr=60*60;

$gmt=time();

$diff=$gmt-$newyear;

$tz=-$diff/$hr;

if ($tz>0)
{
  $tz="+".$tz;
}

$out=floor($tz);

$d=$

privmsg("happy new year for timezone GMT".$tz);

#####################################################################################################

?>
