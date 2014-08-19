<?php

# gpl2
# by crutchy
# 19-aug-2014

#####################################################################################################

require_once("users_lib.php");

$trailing=trim($argv[1]);
$nick=trim($argv[2]);
$dest=trim($argv[3]);
$alias=trim($argv[4]);

$parts=explode(" ",$trailing);
delete_empty_elements($parts);
$cmd=strtoupper($parts[0]);
array_shift($parts);
$trailing=trim(implode(" ",$parts));
unset($parts);

switch ($cmd)
{
  case "BUILD": # TODO: call on startup
    term_echo("*** BUILDING CHANNEL/NICK REGISTER ***");
    users_build();
    break;
  case "LIST-CHANNELS":
    $channels=get_array_bucket(BUCKET_CHANNELS);
    privmsg("*** channels: ".implode(", ",$channels));
    break;
  case "LIST-NICKS":
    $nicks=get_array_bucket(BUCKET_NICKS);
    #privmsg("*** nicks: ".implode(", ",$nicks));
    var_dump($nicks);
    break;
  case "COUNT-CHANNELS":
    $channels=get_array_bucket(BUCKET_CHANNELS);
    privmsg("*** ".count($channels)." channels registered");
    break;
  case "COUNT-NICKS":
    $nicks=get_array_bucket(BUCKET_NICKS);
    privmsg("*** ".count($nicks)." nicks registered");
    break;
  case "322": # trailing = <calling_nick> <channel> <nick_count>
    handle_322($trailing);
    break;
  case "354": # trailing = <calling_nick> 152 <channel> <nick> <mode_info>
    handle_354($trailing);
    break;
  case "330": # trailing = <calling_nick> <nick> <account>
    handle_330($trailing);
    break;
  case "JOIN": # trailing = <channel>
    handle_join($nick,$trailing);
    break;
  case "KICK": # trailing = <channel> <kicked_nick>
    handle_kick($nick,$trailing);
    break;
  case "NICK": # trailing = <new_nick>
    handle_nick($nick,$trailing);
    break;
  case "PART": # trailing = <channel>
    handle_part($nick,$trailing);
    break;
  case "QUIT":
    handle_quit($nick);
    break;
}

#####################################################################################################

?>