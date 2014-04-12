<?php

# gpl2
# by crutchy
# 10-april-2014

$pwd=file_get_contents("weather.pwd");
define("NICK","weather");
define("PASSWORD",$pwd);
unset($pwd);
define("LOG_FILE","weather.log");
define("CMD_QUIT","~q");
define("CMD_WEATHER","weather");
#define("CHAN_LIST","#test,##");
define("CHAN_LIST","##");
define("CHAN_TERM","##");
set_time_limit(0);
ini_set("display_errors","on");
$fp=fsockopen("irc.sylnt.us",6667);
stream_set_blocking($fp,False);
fputs($fp,"NICK ".NICK."\n");
fputs($fp,"USER ".NICK." * ".NICK." :".NICK."\n");
while (feof($fp)===False)
{
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
      case CMD_QUIT:
        fputs($fp,": QUIT\n");
        fclose($fp);
        term_echo("QUITTING SCRIPT");
        return;
      case CMD_WEATHER:
        unset($params[0]);
        $location=trim(implode(" ",$params));
        if ($location<>"")
        {
          process_weather($location,$items["chan"]);
        }
        break;
      default:
        {
        }
    }
  }
  if (strpos($data,"End of /MOTD command")!==False)
  {
    fputs($fp,"JOIN ".CHAN_LIST."\n");
  }
  if (strpos($data,"You have 60 seconds to identify to your nickname before it is changed.")!==False)
  {
    fputs($fp,"NICKSERV identify ".PASSWORD."\n");
  }
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
  fputs($fp,":".NICK." PRIVMSG $chan :$msg\r\n");
  term_echo($msg);
}

function wget($host,$uri,$port)
{
  $fp=fsockopen($host,$port);
  if ($fp===False)
  {
    term_echo("Error connecting to \"$host\".");
    return;
  }
  fwrite($fp,"GET $uri HTTP/1.0\r\nHost: $host\r\nConnection: Close\r\n\r\n");
  $response="";
  while (!feof($fp))
  {
    $response=$response.fgets($fp,1024);
  }
  fclose($fp);
  return $response;
}

function process_weather($location,$chan)
{
  # http://weather.gladstonefamily.net/site/search?site=melbourne&search=Search
  $search=wget("weather.gladstonefamily.net","/site/search?site=".urlencode($location)."&search=Search",80);
  $parts=explode("<li>",$search);
  $delim1="/site/";
  $delim2="\">";
  for ($i=0;$i<count($parts);$i++)
  {
    if ((strpos($parts[$i],"/site/")!==False) and (strpos($parts[$i],"[no data]")===False) and (strpos($parts[$i],"[inactive]")===False))
    {
      term_echo($parts[$i]);
      $j=strpos($parts[$i],$delim1);
      $k=strpos($parts[$i],$delim2);
      if (($j!==False) and ($k!==False))
      {
        $name=substr($parts[$i],$k+strlen($delim2),strlen($parts[$i])-$k-strlen($delim2)-strlen("</a>"));
        $station=substr($parts[$i],$j+strlen($delim1),$k-$j-strlen($delim1));
        # http://weather.gladstonefamily.net/cgi-bin/wxobservations.pl?site=94868&days=7
        $csv=trim(wget("weather.gladstonefamily.net","/cgi-bin/wxobservations.pl?site=".urlencode($station)."&days=7",80));
        $lines=explode("\n",$csv);
        $last=$lines[count($lines)-1];
        term_echo($last);
        $data=explode(",",$last);
        $tempF=round($data[2],1);
        $tempC=round(($tempF-32)*5/9,1);
        privmsg($chan,"Weather for $name at ".$data[0]." (UTC):");
        privmsg($chan,"Temperature = ".$tempF."°F (".$tempC."°C)");
        privmsg($chan,"Barometric pressure = ".round($data[1],1)." mb");
        break;
      }
    }
  }
}

?>
