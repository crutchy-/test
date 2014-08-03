<?php

# gpl2
# by crutchy
# 3-aug-2014

#####################################################################################################

require_once("lib.php");
require_once("weather_lib.php");
require_once("time_lib.php");
require_once("switches.php");

$nick=$argv[1];
$dest=$argv[2];
$alias=$argv[3];
$trailing=$argv[4];
$msg="";
$flag=handle_switch($alias,$dest,$nick,$trailing,"<<EXEC_WELCOME_CHANNELS>>","~welcome","~welcome-internal",$msg);
switch ($flag)
{
  case 1:
    privmsg("welcome enabled for ".chr(3)."10$dest");
    return;
  case 2:
    privmsg("welcome already enabled for ".chr(3)."10$dest");
    return;
  case 3:
    privmsg("welcome disabled for ".chr(3)."10$dest");
    return;
  case 4:
    privmsg("welcome already disabled for ".chr(3)."10$dest");
    return;
  case 9:
    show_welcome($nick);
    return;
}

#####################################################################################################

function show_welcome($nick)
{
  $location=get_location($nick);
  if ($location===False)
  {
    return;
  }
  $time=get_time($location);
  if ($time=="")
  {
    return;
  }
  $arr=convert_google_location_time($time);
  $data=process_weather($location);
  if (is_array($data)==False)
  {
    return;
  }
  privmsg("$nick, ".$arr["location"].", ".$data["temp"].", ".date("h:i:s A",$arr["timestamp"])." ".$arr["timezone"].", ".date("l, j F Y",$arr["timestamp"]));
}

#####################################################################################################

?>
