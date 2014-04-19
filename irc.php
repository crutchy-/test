<?php

# gpl2
# by crutchy
# 19-april-2014

define("NICK","coffee"); # bacon/coffee/mother/weather/IRCiv
define("PASSWORD",file_get_contents("../pwd/test"));
define("LOG_FILE","log");
define("EXEC_FILE","exec");
define("EXEC_DELIM","|");
define("TERM_PRIVMSG","privmsg");
define("CMD_ABOUT","~");
define("CMD_QUIT","~q");
define("CMD_JOIN","~join");
define("CMD_PART","~part");
define("CMD_RELOADEXEC","~reload");
define("CHAN_LIST","#test");
define("VALID_UPPERCASE","ABCDEFGHIJKLMNOPQRSTUVWXYZ");
define("VALID_LOWERCASE","abcdefghijklmnopqrstuvwxyz");
define("VALID_NUMERIC","0123456789");
define("VALID_SPECIAL_CHAN","#~");
define("VALID_SPECIAL_NICK","|_");
define("VALID_SPECIAL_MSG"," .,#_'");
define("VALID_SPECIAL_ALIAS","-");
define("VALID_NICK",VALID_UPPERCASE.VALID_LOWERCASE.VALID_NUMERIC.VALID_SPECIAL_NICK);
define("VALID_CHAN",VALID_UPPERCASE.VALID_LOWERCASE.VALID_NUMERIC.VALID_SPECIAL_CHAN);
define("VALID_MSG",VALID_UPPERCASE.VALID_LOWERCASE.VALID_NUMERIC.VALID_SPECIAL_MSG);
define("VALID_ALIAS",VALID_LOWERCASE.VALID_SPECIAL_ALIAS);
define("TEMPLATE_DELIM","%%");
define("TEMPLATE_MSG","msg");
define("TEMPLATE_NICK","nick");
define("TEMPLATE_CHAN","chan");
define("TEMPLATE_START","start");
define("TEMPLATE_ALIAS","alias");
define("MAX_PRIVMSG_LENGTH",500);
define("START_TIME",microtime(True));
set_time_limit(0);
ini_set("display_errors","on");
date_default_timezone_set("UTC");
$admin_nicks=array("crutchy");
$exec_list=array();
if (exec_load($exec_list)==False)
{
  term_echo("error loading exec");
  return;
}
$fp=fsockopen("irc.sylnt.us",6667);
fputs($fp,"NICK ".NICK."\n");
fputs($fp,"USER ".NICK." hostname servername :".NICK."\n");
$handles=array();
while (feof($fp)===False)
{
  $n=count($handles);
  for ($i=0;$i<$n;$i++)
  {
    $terminated=False;
    $start=microtime(True);
    $timeout=$handles[$i]["timeout"];
    while (True)
    {
      $buf=fgets($handles[$i]["pipe_stdout"]);
      if ($buf!==False)
      {
        if (trim($buf)<>"")
        {
          $pre=substr(strtolower($buf),0,strlen(TERM_PRIVMSG)+1);
          if (($pre==(TERM_PRIVMSG." ")) or ($handles[$i]["auto_privmsg"]==1))
          {
            $msg=rtrim($buf);
            if ($pre==(TERM_PRIVMSG." "))
            {
              $msg=substr($msg,strlen(TERM_PRIVMSG)+1);
            }
            if (substr($handles[$i]["chan"],0,1)=="#")
            {
              privmsg($handles[$i]["chan"],$msg);
            }
            else
            {
              privmsg($handles[$i]["nick"],$msg);
            }
          }
          else
          {
            term_echo(rtrim($buf));
          }
        }
      }
      else
      {
        $proc_info=proc_get_status($handles[$i]["process"]);
        if ($proc_info["running"]==False)
        {
          $terminated=True;
          $return_value=proc_close($handles[$i]["process"]);
          if ($handles[$i]["alias"]<>"*")
          {
            term_echo("process terminated normally");
          }
          break;
        }
        if ((microtime(True)-$start)>$timeout)
        {
          $terminated=True;
          $return_value=proc_close($handles[$i]["process"]);
          privmsg($handles[$i]["chan"],"error: command timed out");
          break;
        }
      }
    }
    if ($terminated==False)
    {
      while (feof($handles[$i]["pipe_stderr"])==False)
      {
        $buf=fgets($handles[$i]["pipe_stderr"]);
        if ($buf!==False)
        {
          term_echo(rtrim($buf));
        }
        sleep(1);
      }
      $proc_info=proc_get_status($handles[$i]["process"]);
      if ($proc_info["running"]==False)
      {
        $return_value=proc_close($handles[$i]["process"]);
      }
    }
    unset($handles[$i]);
  }
  $handles=array_values($handles);
  $data=fgets($fp);
  if ($data===False)
  {
    continue;
  }
  if (pingpong($fp,$data)==True)
  {
    continue;
  }
  echo $data;
  $items=parse_data($data);
  if ($items!==False)
  {
    append_log($items);
    $params=explode(" ",$items["msg"]);
    switch (strtolower($params[0]))
    {
      case CMD_ABOUT:
        privmsg($items["chan"],"IRC SCRIPT EXECUTIVE");
        privmsg($items["chan"],"  by crutchy: https://github.com/crutchy-/test/blob/master/irc.php");
        privmsg($items["chan"],"  visit http://wiki.soylentnews.org/wiki/IRC#bacon.2Fcoffee.2Fmother for more info");
        break;
      case CMD_QUIT:
        if (in_array($items["nick"],$admin_nicks)==True)
        {
          doquit($fp);
          return;
        }
        else
        {
          privmsg($items["chan"],"command not permitted by nick \"".$items["nick"]."\"");
        }
        break;
      case CMD_PART:
        if (in_array($items["nick"],$admin_nicks)==True)
        {
          fputs($fp,"PART ".$items["chan"]."\n");
        }
        else
        {
          privmsg($items["chan"],"command not permitted by nick \"".$items["nick"]."\"");
        }
        break;
      case CMD_JOIN:
        if (in_array($items["nick"],$admin_nicks)==True)
        {
          array_shift($params);
          dojoin($fp,implode(" ",$params));
        }
        else
        {
          privmsg($items["chan"],"command not permitted by nick \"".$items["nick"]."\"");
        }
        break;
      case CMD_RELOADEXEC:
        if (in_array($items["nick"],$admin_nicks)==True)
        {
          if (exec_load($exec_list)==True)
          {
            privmsg($items["chan"],"successfully reloaded exec");
          }
          else
          {
            privmsg($items["chan"],"error reloading exec");
          }
        }
        else
        {
          privmsg($items["chan"],"quit command not permitted by nick \"".$items["nick"]."\"");
        }
        break;
      default:
        process_scripts($items);
        process_scripts($items,True);
    }
  }
  if (strpos($data,"End of /MOTD command")!==False)
  {
    dojoin($fp,CHAN_LIST);
  }
  if (strpos($data,"You have 60 seconds to identify to your nickname before it is changed.")!==False)
  {
    fputs($fp,"NICKSERV identify ".PASSWORD."\n");
  }
}

function exec_load(&$exec_list)
{
  $exec_list=array();
  $data=file_get_contents(EXEC_FILE);
  if ($data===False)
  {
    return False;
  }
  $data=explode("\n",$data);
  for ($i=0;$i<count($data);$i++)
  {
    $line=trim($data[$i]);
    if ($line=="")
    {
      continue;
    }
    if (substr($line,0,1)=="#")
    {
      continue;
    }
    $timeout="";
    $auto="";
    $empty="";
    $alias="";
    $cmd="";
    if (parse_exec($line,$timeout,$auto,$empty,$alias,$cmd)==True)
    {
      $exec_list[$alias]["timeout"]=$timeout;
      $exec_list[$alias]["auto"]=$auto;
      $exec_list[$alias]["empty"]=$empty;
      $exec_list[$alias]["cmd"]=$cmd;
    }
  }
  return True;
}

function doquit($fp)
{
  global $handles;
  $n=count($handles);
  for ($i=0;$i<$n;$i++)
  {
    $proc_info=proc_get_status($handles[$i]["process"]);
    if ($proc_info["running"]==False)
    {
      $return_value=proc_close($handles[$i]["process"]);
    }
  }
  fputs($fp,": QUIT\n");
  fclose($fp);
  term_echo("QUITTING SCRIPT");
}

function dojoin($fp,$chanlist)
{
  fputs($fp,"JOIN $chanlist\n");
}

function pingpong($fp,$data)
{
  $parts=explode(" ",$data);
  if (count($parts)>1)
  {
    if ($parts[0]=="PING")
    {
      fputs($fp,"PONG ".$parts[1]."\n");
      return True;
    }
  }
  return False;
}

function parse_exec($line,&$timeout,&$auto,&$empty,&$alias,&$cmd)
{
  $parts=explode(EXEC_DELIM,$line);
  if (count($parts)<>5)
  {
    return False;
  }
  if ((($parts[1]<>"0") and ($parts[1]<>"1")) or (($parts[2]<>"0") and ($parts[2]<>"1")) or ($parts[3]=="") or ($parts[4]==""))
  {
    return False;
  }
  $timeout=$parts[0]; # seconds
  $auto=$parts[1];
  $empty=$parts[2];
  $alias=$parts[3];
  $cmd=$parts[4];
  return True;
}

function append_log($items)
{
  $data=serialize($items);
  if ($data===False)
  {
    term_echo("Error serializing log items.");
    return;
  }
  if (file_put_contents(LOG_FILE,$data."\n",FILE_APPEND)===False)
  {
    term_echo("Error appending log file \"".LOG_FILE."\".");
  }
}

function term_echo($msg)
{
  echo "\033[1;31m$msg\033[0m\n";
}

function parse_data($data)
{
  # :nick!addr PRIVMSG chan :msg
  $result=array();
  if ($data=="")
  {
    return False;
  }
  if ($data[0]<>":")
  {
    return False;
  }
  $i=strpos($data," :");
  $result["msg"]=trim(substr($data,$i+2));
  if ($result["msg"]=="")
  {
    return False;
  }
  $sub=substr($data,1,$i-1);
  $i=strpos($sub,"!");
  $result["nick"]=substr($sub,0,$i);
  if (($result["nick"]=="") or ($result["nick"]==NICK))
  {
    return False;
  }
  $sub=substr($sub,$i+1);
  $i=strpos($sub," ");
  $result["addr"]=substr($sub,0,$i);
  if ($result["addr"]=="")
  {
    return False;
  }
  $sub=substr($sub,$i+1);
  $i=strpos($sub," ");
  $cmd=substr($sub,0,$i);
  if ($cmd<>"PRIVMSG")
  {
    return False;
  }
  $result["chan"]=substr($sub,$i+1);
  if ($result["chan"]=="")
  {
    return False;
  }
  $result["microtime"]=microtime(True);
  $result["time"]=date("Y-m-d H:i:s",$result["microtime"]);
  return $result;
}

function privmsg($chan,$msg)
{
  global $fp;
  if ($chan=="")
  {
    term_echo("Channel not specified.");
    return;
  }
  if ($msg=="")
  {
    term_echo("No text to send.");
    return;
  }
  $msg=substr($msg,0,MAX_PRIVMSG_LENGTH);
  fputs($fp,":".NICK." PRIVMSG $chan :$msg\r\n");
  term_echo($msg);
}

function process_scripts($items,$doall=False)
{
  global $handles;
  global $exec_list;
  if ($doall==False)
  {
    $parts=explode(" ",$items["msg"]);
    $alias=filter_alias(trim($parts[0]));
    if (isset($exec_list[$alias])==False)
    {
      return;
    }
    array_shift($parts);
    $msg=filter_msg(trim(implode(" ",$parts)));
  }
  else
  {
    $alias="*";
    $msg=$items["msg"];
  }
  $nick=filter_nick(trim($items["nick"]));
  $chan=filter_chan(trim($items["chan"]));
  if ($nick<>$items["nick"])
  {
    privmsg($items["chan"],"nick contains illegal chars");
    return;
  }
  if ($chan<>$items["chan"])
  {
    privmsg($items["chan"],"chan contains illegal chars");
    return;
  }
  if (($exec_list[$alias]["empty"]==0) and ($msg==""))
  {
    privmsg($items["chan"],"alias requires additional argument");
    return;
  }
  $template=$exec_list[$alias]["cmd"];
  $template=str_replace(TEMPLATE_DELIM.TEMPLATE_MSG.TEMPLATE_DELIM,escapeshellarg($msg),$template);
  $template=str_replace(TEMPLATE_DELIM.TEMPLATE_NICK.TEMPLATE_DELIM,escapeshellarg($nick),$template);
  $template=str_replace(TEMPLATE_DELIM.TEMPLATE_CHAN.TEMPLATE_DELIM,escapeshellarg($chan),$template);
  $template=str_replace(TEMPLATE_DELIM.TEMPLATE_START.TEMPLATE_DELIM,escapeshellarg(START_TIME),$template);
  $template=str_replace(TEMPLATE_DELIM.TEMPLATE_ALIAS.TEMPLATE_DELIM,escapeshellarg($alias),$template);
  $command="exec ".$template;
  $command=$template;
  $cwd=NULL;
  $env=NULL;
  $descriptorspec=array(0=>array("pipe","r"),1=>array("pipe","w"),2=>array("pipe","w"));
  if ($alias<>"*")
  {
    term_echo($command);
  }
  $process=proc_open($command,$descriptorspec,$pipes,$cwd,$env);
  stream_set_blocking($pipes[1],0);
  $handles[]=array("process"=>$process,"command"=>$command,"pipe_stdin"=>$pipes[0],"pipe_stdout"=>$pipes[1],"pipe_stderr"=>$pipes[2],"alias"=>$alias,"template"=>$exec_list[$alias]["cmd"],"allow_empty"=>$exec_list[$alias]["empty"],"timeout"=>$exec_list[$alias]["timeout"],"auto_privmsg"=>$exec_list[$alias]["auto"],"nick"=>$items["nick"],"chan"=>$items["chan"]);
}

function filter_nick($nick)
{
  return filter($nick,VALID_NICK);
}

function filter_chan($chan)
{
  return filter($chan,VALID_CHAN);
}

function filter_msg($msg)
{
  return filter($msg,VALID_MSG);
}

function filter_alias($alias)
{
  return filter($alias,VALID_ALIAS);
}

function filter($msg,$whitelist)
{
  $result="";
  for ($i=0;$i<strlen($msg);$i++)
  {
    if (strpos($whitelist,$msg[$i])!==False)
    {
      $result=$result.$msg[$i];
    }
  }
  return $result;
}

?>
