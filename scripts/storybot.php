<?php

#####################################################################################################

/*
exec:add ~storybot
exec:edit ~storybot timeout 1800
exec:edit ~storybot repeat 3600
exec:edit ~storybot accounts cmn32480,crutchy,martyb,themightybuzzard,chromas
#exec:edit ~storybot cmd { php scripts/storybot.php ; PYTHONIOENCODING=utf_8 ; export PYTHONIOENCODING ; cd ~/Downloads/Exec/storybot ; python storybot.py ; } 2>&1
exec:edit ~storybot cmd { php scripts/storybot.php ; PYTHONIOENCODING=utf_8 ; export PYTHONIOENCODING ; cd /home/jared/git/storybot ; python storybot.py ; } 2>&1
exec:enable ~storybot
help:~storybot arthur
*/

#####################################################################################################

require_once("lib.php");

pm("#exec","arthur is processing stories");

#####################################################################################################

?>
