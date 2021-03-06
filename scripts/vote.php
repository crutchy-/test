<?php

#####################################################################################################

/*
exec:~vote|10|0|0|1|*||||php scripts/vote.php %%trailing%% %%dest%% %%nick%% %%alias%%
exec:~quickpoll|10|0|0|1|*||||php scripts/vote.php %%trailing%% %%dest%% %%nick%% %%alias%%
*/

#####################################################################################################

# TODO: saving vote bucket to file and loading on startup
# TODO: colors

#####################################################################################################

require_once("lib.php");

$trailing=trim($argv[1]);
$dest=strtolower(trim($argv[2]));
$nick=strtolower(trim($argv[3]));
$alias=$argv[3];

if ($alias=="~quickpoll")
{
  # TODO: ~quickpoll description wth spaces [yes|no|maybe|light it on fire]
  # TODO: ~quickpoll maybe
  # TODO: quickpoll is automatically opened for 1 minute and then outputs results and then deletes
  return;
}

if (($trailing=="") or ($trailing=="?") or (strtolower($trailing)=="help"))
{
  notice($nick,"  to vote: ~vote poll_id option_id");
  notice($nick,"  example: ~vote beverage coffee");
  notice($nick,"  http://sylnt.us/vote");
  return;
}

$founders=array(
  "crutchy",
  "chromas",
  "bytram",
  "mrcoolbp",
  "juggs",
  "themightybuzzard",
  "arti",
  "paulej72");

$parts=explode(" ",$trailing);
delete_empty_elements($parts);

$data=get_array_bucket("<<IRC_VOTE_DATA>>");

$id=strtolower(filter($parts[0],VALID_UPPERCASE.VALID_LOWERCASE.VALID_NUMERIC."_-.?"));

array_shift($parts);
$trailing=implode(" ",$parts);

$commands=array(
  "list","l",
  "register",
  "unregister",
  "breakdown","b",
  "add-option","ao","oa",
  "del-option","do","od",
  "add-admin",
  "del-admin",
  "list-admin",
  "open",
  "close",
  "sort");

switch ($id)
{
  case "list":
  case "l":
    if (count($data)==0)
    {
      notice($nick,"  no polls registered");
    }
    else
    {
      foreach ($data as $poll_id => $poll_data)
      {
        if (isset($data[strtolower($trailing)])==True)
        {
          if ($poll_id<>strtolower($trailing))
          {
            continue;
          }
        }
        $n=count($poll_data["options"]);
        $suffix="";
        if ($poll_data["description"]<>"")
        {
          $suffix=": ".$poll_data["description"];
        }
        if ($n==0)
        {
          notice($nick,"  ".$poll_id.$suffix);
          #notice($nick,"    [no poll options]");
          continue;
        }
        else
        {
          notice($nick,"  ".$poll_id.$suffix);
        }
      }
    }
    return;
}
$action="";
if (isset($parts[0])==True)
{
  $action=strtolower($parts[0]);
  array_shift($parts);
}
if ($action=="register")
{
  if ($id=="")
  {
    notice($nick,"  you must specify a poll id");
  }
  if (in_array($id,$commands)==True)
  {
    notice($nick,"  invalid poll id");
  }
  else
  {
    $account=users_get_account($nick);
    if (in_array($account,$founders)==False)
    {
      notice($nick,"  account \"$account\" not in founders list - please contact crutchy if you would like to be added");
      return;
    }
    $data[$id]=array();
    $data[$id]["description"]=implode(" ",$parts);
    $data[$id]["founder"]=$account;
    $data[$id]["status"]="closed";
    $data[$id]["options"]=array();
    $data[$id]["votes"]=array();
    $data[$id]["admins"]=array($account);
    set_array_bucket($data,"<<IRC_VOTE_DATA>>");
    $suffix="";
    if ($data[$id]["description"]<>"")
    {
      $suffix=" [".$data[$id]["description"]."]";
    }
    notice($nick,"  poll \"$id\"$suffix registered");
  }
}
elseif (isset($data[$id])==True)
{
  switch ($action)
  {
    case "sort":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      ksort($data[$id]["options"]);
      set_array_bucket($data,"<<IRC_VOTE_DATA>>");
      notice($nick,"  options for poll \"$id\"$suffix are now alphabetically sorted");
      return;
    case "list":
    case "l":
      $poll_data=$data[$id];
      $n=count($poll_data["options"]);
      $suffix="";
      if ($poll_data["description"]<>"")
      {
        $suffix=": ".$poll_data["description"];
      }
      if ($n==0)
      {
        notice($nick,"  ".$id.$suffix);
        notice($nick,"    [no poll options]");
        continue;
      }
      else
      {
        notice($nick,"  ".$id.$suffix);
      }
      $i=0;
      foreach ($poll_data["options"] as $option_id => $option_description)
      {
        $suffix="";
        if ($option_description<>"")
        {
          $suffix=": ".$option_description;
        }
        if ($i==($n-1))
        {
          notice($nick,"  └─".$option_id.$suffix);
        }
        else
        {
          notice($nick,"  ├─".$option_id.$suffix);
        }
        $i++;
      }
      return;
    case "unregister":
      $account=users_get_account($nick);
      if ($data[$id]["founder"]<>$account)
      {
        notice($nick,"  only the poll founder can unregister the poll");
        return;
      }
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      notice($nick,"  poll \"$id\"$suffix unregistered");
      unset($data[$id]);
      set_array_bucket($data,"<<IRC_VOTE_DATA>>");
      return;
    case "breakdown":
    case "b":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      $account=users_get_account($nick);
      if (in_array($account,$data[$id]["admins"])==False)
      {
        notice($nick,"  only a poll admin may output the vote breakdown for a poll");
        return;
      }
      $n=count($data[$id]["votes"]);
      if ($n==0)
      {
        notice($nick,"  no votes for poll \"$id\"$suffix are registered");
        return;
      }
      notice($nick,"  voting breakdown for poll \"$id\"$suffix:");
      $i=0;
      foreach ($data[$id]["votes"] as $account => $option_id)
      {
        $suffix="";
        if ($data[$id]["options"][$option_id]<>"")
        {
          $suffix=": ".$data[$id]["options"][$option_id];
        }
        if ($i==($n-1))
        {
          notice($nick,"  └─$account => ".$option_id.$suffix);
        }
        else
        {
          notice($nick,"  ├─$account => ".$option_id.$suffix);
        }
        $i++;
      }
      return;
    case "add-option":
    case "ao":
    case "oa":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      $account=users_get_account($nick);
      if (in_array($account,$data[$id]["admins"])==False)
      {
        notice($nick,"  only a poll admin may add an option for a poll");
        return;
      }
      $option_id=strtolower($parts[0]);
      array_shift($parts);
      if (isset($data[$id]["options"][$option_id])==False)
      {
        $description=implode(" ",$parts);
        $data[$id]["options"][$option_id]=$description;
        set_array_bucket($data,"<<IRC_VOTE_DATA>>");
        $opt_suffix="";
        if ($description<>"")
        {
          $opt_suffix=" [$description]";
        }
        notice($nick,"  option \"$option_id\"$opt_suffix added for poll \"$id\"$suffix");
      }
      else
      {
        $opt_suffix="";
        if ($data[$id]["options"][$option_id]<>"")
        {
          $opt_suffix=" [".$data[$id]["options"][$option_id]."]";
        }
        notice($nick,"  option \"$option_id\"$opt_suffix already exists for poll \"$id\"$suffix");
      }
      return;
    case "del-option":
    case "do":
    case "od":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      $account=users_get_account($nick);
      if (in_array($account,$data[$id]["admins"])==False)
      {
        notice($nick,"  only a poll admin may delete an option for a poll");
        return;
      }
      $option_id=strtolower($parts[0]);
      if (isset($data[$id]["options"][$option_id])==True)
      {
        $opt_suffix="";
        if ($data[$id]["options"][$option_id]<>"")
        {
          $opt_suffix=" [".$data[$id]["options"][$option_id]."]";
        }
        notice($nick,"  option \"$option_id\"$opt_suffix deleted for poll \"$id\"$suffix");
        unset($data[$id]["options"][$option_id]);
        set_array_bucket($data,"<<IRC_VOTE_DATA>>");
      }
      else
      {
        notice($nick,"  option \"$option_id\" not found for poll \"$id\"$suffix");
      }
      return;
    case "add-admin":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      $account=users_get_account($nick);
      if ($data[$id]["founder"]<>$account)
      {
        notice($nick,"  only the poll founder can add poll admins");
        return;
      }
      $admin_account=users_get_account(strtolower($parts[0]));
      if ($admin_account=="")
      {
        notice($nick,"  invalid admin");
      }
      if (in_array($admin_account,$data[$id]["admins"])==False)
      {
        $data[$id]["admins"][]=$admin_account;
        set_array_bucket($data,"<<IRC_VOTE_DATA>>");
        notice($nick,"  account \"$admin_account\" added as admin for poll \"$id\"$suffix");
      }
      else
      {
        notice($nick,"  admin \"$admin_account\" already exists for poll \"$id\"$suffix");
      }
      return;
    case "del-admin":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      $account=users_get_account($nick);
      if ($data[$id]["founder"]<>$account)
      {
        notice($nick,"  only the poll founder can delete poll admins");
        return;
      }
      $admin_account=strtolower($parts[0]);
      if ($account==$admin_account)
      {
        notice($nick,"  founder cannot be deleted from poll admins");
        return;
      }
      $index=array_search($admin_account,$data[$id]["admins"]);
      if ($index!==False)
      {
        unset($data[$id]["admins"][$index]);
        $data[$id]["admins"]=array_values($data[$id]["admins"]);
        set_array_bucket($data,"<<IRC_VOTE_DATA>>");
        notice($nick,"  account \"$admin_account\" deleted froms admins for poll \"$id\"$suffix");
      }
      else
      {
        notice($nick,"  admin \"$admin_account\" not found in admins for poll \"$id\"$suffix");
      }
      return;
    case "list-admin":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      notice($nick,"  admin accounts for poll \"$id\"$suffix:");
      $n=count($data[$id]["admins"]);
      for ($i=0;$i<$n;$i++)
      {
        $founder_suffix="";
        if ($data[$id]["admins"][$i]==$data[$id]["founder"])
        {
          $founder_suffix=" (poll founder)";
        }
        if ($i==($n-1))
        {
          notice($nick,"  └─".$data[$id]["admins"][$i].$founder_suffix);
        }
        else
        {
          notice($nick,"  ├─".$data[$id]["admins"][$i].$founder_suffix);
        }
      }
      return;
    case "open":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      $account=users_get_account($nick);
      if (in_array($account,$data[$id]["admins"])==False)
      {
        notice($nick,"  only a poll admin may open the poll for voting");
        return;
      }
      $data[$id]["status"]="open";
      set_array_bucket($data,"<<IRC_VOTE_DATA>>");
      notice($nick,"  poll \"$id\"$suffix opened for voting");
      return;
    case "close":
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      $account=users_get_account($nick);
      if (in_array($account,$data[$id]["admins"])==False)
      {
        notice($nick,"  only a poll admin may close the poll for voting");
        return;
      }
      $data[$id]["status"]="closed";
      set_array_bucket($data,"<<IRC_VOTE_DATA>>");
      notice($nick,"  poll \"$id\"$suffix closed for voting");
      return;
    default:
      $suffix="";
      if ($data[$id]["description"]<>"")
      {
        $suffix=" [".$data[$id]["description"]."]";
      }
      if ($action=="")
      {
        $n=count($data[$id]["votes"]);
        if ($n==0)
        {
          notice($nick,"  no votes for poll \"$id\"$suffix are registered");
          return;
        }
        $tally=array();
        foreach ($data[$id]["options"] as $option_id => $option_description)
        {
          $tally[$option_id]=0;
        }
        foreach ($data[$id]["votes"] as $account => $option_id)
        {
          $tally[$option_id]=$tally[$option_id]+1;
        }
        notice($nick,"  voting result for poll \"$id\"$suffix:");
        $n=count($tally);
        $i=0;
        foreach ($tally as $option_id => $result)
        {
          $suffix="";
          if ($data[$id]["options"][$option_id]<>"")
          {
            $suffix=": ".$data[$id]["options"][$option_id];
          }
          if ($i==($n-1))
          {
            notice($nick,"  └─".$option_id.$suffix." => $result");
          }
          else
          {
            notice($nick,"  ├─".$option_id.$suffix." => $result");
          }
          $i++;
        }
        return;
      }
      if ($data[$id]["status"]<>"open")
      {
        notice($nick,"  poll \"$id\"$suffix is not currently open for voting");
        return;
      }
      $account=users_get_account($nick);
      if ($account=="")
      {
        return;
      }
      if (isset($data[$id]["options"][$action])==False)
      {
        notice($nick,"  invalid option for poll \"$id\"$suffix");
        return;
      }
      $data[$id]["votes"][$account]=$action;
      set_array_bucket($data,"<<IRC_VOTE_DATA>>");
      $opt_suffix="";
      if ($data[$id]["options"][$action]<>"")
      {
        $opt_suffix=" [".$data[$id]["options"][$action]."]";
      }
      notice($nick,"  vote registered by account \"$account\" with option \"$action\"$opt_suffix for poll \"$id\"$suffix");
      return;
    }
  }

#####################################################################################################

?>
