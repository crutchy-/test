<?php

#####################################################################################################

/*
exec:~wiki|40|0|0|0|*||||php scripts/wiki.php %%trailing%% %%dest%% %%nick%% %%alias%%
exec:~wiki-privmsg|40|0|0|0|crutchy,mrcoolbp||||php scripts/wiki.php %%trailing%% %%dest%% %%nick%% %%alias%%
init:~wiki register-events
*/

#####################################################################################################

# http://www.mediawiki.org/wiki/Manual:Bots
# http://en.wikipedia.org/wiki/Wikipedia:Creating_a_bot

# ~wiki edit title|section|text
# ~wiki edit title|section| (deletes section)

# instead of "~wiki login" & "~wiki get page|section" you just type [[page#section]] to get the page/section

#####################################################################################################

require_once("lib.php");
require_once("wiki_lib.php");

$trailing=trim(strip_tags($argv[1]));
$dest=$argv[2];
$nick=$argv[3];
$alias=$argv[4];

if ($trailing=="register-events")
{
  register_event_handler("PRIVMSG",":%%nick%% INTERNAL %%dest%% :~wiki-privmsg %%trailing%%");
  return;
}

if ($alias=="~wiki-privmsg")
{
  $spamctl=".spamctl";
  if (strtolower(substr($trailing,0,strlen($spamctl)))==$spamctl)
  {
    wiki_spamctl($nick,$trailing);
    return;
  }
  if ($dest=="#wiki")
  {
    return;
  }
  $delim1="[[";
  $delim2="]]";
  $parts=explode($delim1,$trailing);
  array_shift($parts);
  for ($i=0;$i<count($parts);$i++)
  {
    $subparts=explode($delim2,$parts[$i]);
    $linkstr=$subparts[0];
    $section="";
    $params=explode("|",$linkstr);
    $title=$params[0];
    $section="";
    if (count($params)==2)
    {
      $section=$params[1];
    }
    else
    {
      $params=explode("#",$linkstr);
      if (count($params)==2)
      {
        $title=$params[0];
        $section=$params[1];
      }
    }
    $result=get_text($title,$section,True,True);
    if ($result!==False)
    {
      if (count($result)>=2)
      {
        privmsg("  ".chr(3)."03".$linkstr.chr(3)." => ".chr(3)."13".$result[0]);
        privmsg("  └─ ".chr(3)."02".$result[count($result)-1]);
      }
    }
  }
  return;
}

$login=get_bucket("wiki_login_cookieprefix");

if (strtolower($trailing)=="login")
{
  login();
}
elseif ($login<>"")
{
  $parts=explode(" ",$trailing);
  $action=$parts[0];
  switch (strtolower($action))
  {
    case "edit":
      array_shift($parts);
      $trailing=implode(" ",$parts);
      $parts=explode("|",$trailing);
      if (count($parts)<>3)
      {
        privmsg("syntax: ~wiki title|section|text");
        return;
      }
      $title=$parts[0];
      $section=$parts[1];
      $text=$parts[2];
      edit($title,$section,$text);
      break;
    case "get":
      array_shift($parts);
      $trailing=implode(" ",$parts);
      $parts=explode("|",$trailing);
      if ((count($parts)<>1) and (count($parts)<>2))
      {
        privmsg("syntax: ~wiki get title[|section]");
        return;
      }
      $title=$parts[0];
      $section="";
      if (isset($parts[1])==True)
      {
        $section=$parts[1];
      }
      get_text($title,$section);
      break;
    case "logout":
      logout();
      break;
    default:
      privmsg("wiki: no action specified");
  }
}
else
{
  privmsg("wiki: not logged in");
}

#####################################################################################################

?>
