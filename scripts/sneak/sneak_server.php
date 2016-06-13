<?php

#####################################################################################################

/*
#exec:add ~sneak-server
#exec:edit ~sneak-server timeout 0
#exec:edit ~sneak-server cmd php scripts/sneak/sneak_server.php %%trailing%% %%nick%% %%dest%% %%server%% %%hostname%% %%alias%% %%cmd%%
#exec:enable ~sneak-server
#startup:~join #sneak
#startup:~sneak-server start
*/

# sneak is an irc game where each player aims to increase their kills by moving into the same coordinate as other players

#####################################################################################################

define("APP_NAME","sneak");

require_once("data_server.php");

#####################################################################################################

function server_start_handler(&$server_data,&$server,&$clients,&$connections)
{
  $server_data["app_data"]["moderators"]=array();
  $server_data["app_data"]["moderators"][]=$server_data["server_admin"];
}

#####################################################################################################

function server_stop_handler(&$server_data,&$server,&$clients,&$connections)
{
  # TODO: save and backup game data file as required
}

#####################################################################################################

?>
