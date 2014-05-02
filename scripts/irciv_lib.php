<?php

# gpl2
# by crutchy
# 02-may-2014

# irciv_lib.php

require_once("lib.php");

define("GAME_NAME","IRCiv");
define("GAME_CHAN","#civ");
define("BUCKET_PREFIX",GAME_NAME."_".GAME_CHAN."_");

#####################################################################################################

function irciv__term_echo($msg)
{
  term_echo(GAME_NAME.": $msg");
}

#####################################################################################################

function irciv__privmsg($msg)
{
  privmsg(GAME_NAME.": $msg");
}

#####################################################################################################

function irciv__err($msg)
{
  err(GAME_NAME." error: $msg");
}

#####################################################################################################

function irciv__get_bucket($suffix)
{
  return get_bucket(BUCKET_PREFIX.$suffix);
}

#####################################################################################################

function irciv__set_bucket($suffix,$data)
{
  set_bucket(BUCKET_PREFIX.$suffix,$data);
}

#####################################################################################################

function map_coord($cols,$x,$y)
{
  return ($x+$y*$cols);
}

#####################################################################################################

?>
