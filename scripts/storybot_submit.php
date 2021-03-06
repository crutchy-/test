<?php

#####################################################################################################

/*
exec:add ~arthur
exec:edit ~arthur timeout 600
exec:edit ~arthur repeat 3600
#exec:edit ~arthur repeat 120
exec:edit ~arthur accounts_wildcard *
exec:edit ~arthur cmd php scripts/storybot_submit.php %%trailing%% %%dest%% %%nick%% %%alias%% %%cmd%%
exec:enable ~arthur
*/

#####################################################################################################

date_default_timezone_set("UTC");

require_once("lib.php");
require_once(__DIR__."/wiki/sn_wiki.php");

$trailing=trim($argv[1]);
$dest=$argv[2];
$nick=$argv[3];
$alias=$argv[4];
$cmd=$argv[5];

#pm("#crutchy","testing arthur");

$allowed=array("crutchy","cmn32480","chromas","themightybuzzard","bytram");

#$submit_host="dev.soylentnews.org";
$submit_host="soylentnews.org";

$stories_path="/home/jared/git/storybot/Stories/";
$stories_path_filename=DATA_PATH."storybot_path.txt";

$blockquote_delim="<p>--- --- --- --- --- --- --- Entire Story Below --- --- --- --- --- --- --- <br><br></p>".PHP_EOL;

if (file_exists($stories_path)==False)
{
  if (file_exists($stories_path_filename)==False)
  {
    privmsg("stories path not found, and stories path filename file not found: \"$stories_path_filename\"");
    return;
  }
  $stories_path_test=file_get_contents($stories_path_filename);
  if ($stories_path_test===False)
  {
    privmsg("error reading stories path file: \"$stories_path_filename\"");
    return;
  }
  $stories_path_test=trim($stories_path_test);
  $stories_path_test=rtrim($stories_path_test,"/");
  if (file_exists($stories_path_test)==False)
  {
    privmsg("stories path not found: \"$stories_path_test\"");
    return;
  }
  if (is_dir($stories_path_test)==False)
  {
    privmsg("stories path isn't a directory: \"$stories_path_test\"");
    return;
  }
  $stories_path=$stories_path_test."/";
}

if (file_exists($stories_path)==False)
{
  privmsg("stories path not found: \"$stories_path\"");
  return;
}

$keep_days=3;

if (in_array(strtolower($nick),$allowed)==True)
{
  if ($trailing=="list")
  {
    refresh_list();
    privmsg("http://wiki.soylentnews.org/wiki/Storybot");
    return;
  }
  if ($cmd<>"INTERNAL")
  {
    $last=get_bucket("<<arthur_last_submit_timestamp>>");
    $d=microtime(True)-$last;
    if ($d<60)
    {
      privmsg("it has been only ".round($d)." seconds since the last submission - please wait");
      return;
    }
    submit_story($trailing);
    return;
  }
}

$bot_nick=get_bot_nick();
if (($bot_nick<>"exec") and ($bot_nick<>"x"))
{
  return;
}

delete_old();
refresh_list();

pm("#exec","http://wiki.soylentnews.org/wiki/Storybot has been updated");
pm("#crutchy","http://wiki.soylentnews.org/wiki/Storybot has been updated");

#####################################################################################################

function url_exists(&$story_list,$url)
{
  $result=False;
  for ($i=0;$i<count($story_list);$i++)
  {
    if ($story_list[$i]["url"]===$url)
    {
      $result=True;
      break;
    }
  }
  return $result;
}

#####################################################################################################

function build_story_list()
{
  global $stories_path;
  global $blockquote_delim;
  $file_list=scandir($stories_path);
  $story_list=array();
  for ($i=0;$i<count($file_list);$i++)
  {
    /*if ((($i%300)==0) and ($i<>0))
    {
      privmsg("processing files... $i");
    }*/
    $filename=$file_list[$i];
    if (($filename==".") or ($filename==".."))
    {
      continue;
    }
    $fn=$stories_path.$filename;
    $content=file_get_contents($fn);
    if ($content===False)
    {
      continue;
    }
    $url=extract_text($content,"Original URL: <a href='","'>");
    $title=extract_text($content,"<p>Title: ","</p>");
    if (($url===False) or ($title===False))
    {
      continue;
    }
    if (url_exists($story_list,$url)==True)
    {
      continue;
    }
    $title=clean_title($title);
    $parts=explode($blockquote_delim,$content);
    if (count($parts)<>2)
    {
      continue;
    }
    $summary=$parts[1];
    $record=array();
    $record["filename"]=$filename;
    $record["full_filename"]=$fn;
    $record["url"]=$url;
    $record["title"]=clean_text($title);
    $record["summary"]=clean_text($summary);
    $record["raw_content"]=clean_text($content);
    $record["submit_content"]=$parts[0].$blockquote_delim."<blockquote>".$parts[1]."</blockquote>".PHP_EOL.PHP_EOL."-- submitted from IRC";
    $story_list[]=$record;
  }
  #privmsg(count($story_list)." stories loaded");
  $id_len=6;
  do
  {
    $ids=array();
    for ($i=0;$i<count($story_list);$i++)
    {
      $ids[]=substr(sha1($story_list[$i]["url"].$story_list[$i]["title"]),0,$id_len);
    }
    $id_len++;
  }
  while (count($ids)<>count(array_unique($ids)));
  for ($i=0;$i<count($story_list);$i++)
  {
    $story_list[$i]["id"]=substr(sha1($story_list[$i]["url"].$story_list[$i]["title"]),0,$id_len-1);
  }
  return $story_list;
}

#####################################################################################################

function refresh_list()
{
  $story_list=build_story_list();
  $text="<p>TO SUBMIT ONE OF THESE STORIES, TYPE \"~submit-story #\" IN SOYLENTNEWS IRC, WHERE # IS THE ID FROM THE LIST BELOW.</p>".PHP_EOL;
  $text=$text."{| class=\"wikitable\"".PHP_EOL;
  $text=$text."|-".PHP_EOL;
  $text=$text."! link !! id !! title".PHP_EOL;
  for ($i=0;$i<count($story_list);$i++)
  {
    $text=$text."|-".PHP_EOL;
    # following two lines are to get rid of attempted italics in title, such as "Panasonic wants you to 2wear Li-Ion batteries", which shows up as "Panasonic wants you to �2wear� Li-Ion batteries" in the wiki
    $story_list[$i]["title"]=str_replace("2","",$story_list[$i]["title"]);
    $story_list[$i]["title"]=str_replace("","",$story_list[$i]["title"]);
    $text=$text."| [".$story_list[$i]["url"]." link] || ".$story_list[$i]["id"]." || ".html_decode($story_list[$i]["title"]).PHP_EOL;
  }
  $text=$text."|}".PHP_EOL;
  $text=$text."<p>this page was autogenerated at ".date("H:i:s, j F Y")."by [[IRC:exec|exec]] irc bot. please don't manually edit this page</p>".PHP_EOL;
  $title="Storybot";
  $summary="scheduled page rewrite by exec bot";
  $wiki_result=sn_wiki_rewrite_page($title,$text,$summary);
  if (is_array($wiki_result)==True)
  {
    var_dump($wiki_result);
    for ($i=0;$i<count($wiki_result);$i++)
    {
      for ($j=0;$j<count($story_list);$j++)
      {
        if (strpos($story_list[$i]["filename"],$wiki_result[$i])!==False)
        {
          unlink($story_list[$i]["full_filename"]);
          continue;
        }
        if (strpos($story_list[$i]["url"],$wiki_result[$i])!==False)
        {
          unlink($story_list[$i]["full_filename"]);
          continue;
        }
        if (strpos($story_list[$i]["title"],$wiki_result[$i])!==False)
        {
          unlink($story_list[$i]["full_filename"]);
          continue;
        }
        if (strpos($story_list[$i]["raw_content"],$wiki_result[$i])!==False)
        {
          unlink($story_list[$i]["full_filename"]);
          continue;
        }
      }
    }
    refresh_list();
    return;
  }
}

#####################################################################################################

function delete_old()
{
  global $stories_path;
  global $keep_days;
  $file_list=scandir($stories_path);
  $datum=time();
  for ($i=0;$i<count($file_list);$i++)
  {
    $filename=$stories_path.$file_list[$i];
    $t=filemtime($filename);
    if (($datum-$t)>($keep_days*24*60*60))
    {
      if (@unlink($filename)===False)
      {
        term_echo("storybot: ERROR DELETING OLD FILE \"".$filename."\"");
      }
      else
      {
        term_echo("storybot: deleted old file \"".$filename."\"");
      }
    }
  }
}

#####################################################################################################

function clean_title($title)
{
  $title=str_replace("_"," ",$title);
  $i=strpos($title,"--");
  if ($i!==False)
  {
    $title=trim(substr($title,0,$i));
  }
  $i=strpos($title,"|");
  if ($i!==False)
  {
    $title=trim(substr($title,0,$i));
  }
  $i=strpos($title," - ");
  if ($i!==False)
  {
    $title=trim(substr($title,0,$i));
  }
  $i=strpos($title," : ");
  if ($i!==False)
  {
    $title=trim(substr($title,0,$i));
  }
  $i=strpos($title," — ");
  if ($i!==False)
  {
    $title=trim(substr($title,0,$i));
  }
  $i=strpos($title," • ");
  if ($i!==False)
  {
    $title=trim(substr($title,0,$i));
  }
  return $title;
}

#####################################################################################################

function submit_story($id)
{
  global $submit_host;
  $story_list=build_story_list();
  $index=False;
  for ($i=0;$i<count($story_list);$i++)
  {
    if ($story_list[$i]["id"]===$id)
    {
      $index=$i;
      break;
    }
  }
  if ($index===False)
  {
    # user may have entered url instead of id
    for ($i=0;$i<count($story_list);$i++)
    {
      if ($story_list[$i]["url"]===$id)
      {
        $index=$i;
        break;
      }
    }
  }
  if ($index===False)
  {
    privmsg("story not found");
    return;
  }
  $submit_story=$story_list[$index];
  privmsg("attempting to submit story: \"".$submit_story["title"]."\"");
  $port=443;
  $uri="/submit.pl";
  $response=wget($submit_host,$uri,$port,ICEWEASEL_UA);
  $html=strip_headers($response);
  $reskey=extract_text($html,"<input type=\"hidden\" id=\"reskey\" name=\"reskey\" value=\"","\">");
  if ($reskey===False)
  {
    privmsg("error: unable to extract reskey");
    return;
  }
  sleep(30);
  $params=array();
  $params["reskey"]=$reskey;
  $params["name"]=get_bot_nick();
  $params["email"]="";
  $params["subj"]=trim(substr($submit_story["title"],0,100));
  $params["primaryskid"]="1";
  $params["tid"]="6";
  $params["sub_type"]="plain";
  $params["story"]=$submit_story["submit_content"];
  $params["op"]="SubmitStory";
  $response=wpost($submit_host,$uri,$port,ICEWEASEL_UA,$params);
  $html=strip_headers($response);
  strip_all_tag($html,"head");
  strip_all_tag($html,"script");
  strip_all_tag($html,"style");
  strip_all_tag($html,"a");
  $html=strip_tags($html);
  $html=clean_text($html);
  var_dump($html);
  if (strpos($html,"Perhaps you would like to enter an email address or a URL next time.")!==False)
  {
    privmsg("submission successful - https://$submit_host/submit.pl?op=list");
  }
  else
  {
    privmsg("error: something went wrong with your submission - maybe try again in a minute");
    return;
  }
  unlink($submit_story["full_filename"]);
  set_bucket("<<arthur_last_submit_timestamp>>",microtime(True));
  refresh_list();
}

#####################################################################################################

?>
