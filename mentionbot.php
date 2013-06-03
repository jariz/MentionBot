<?php

require_once("mentionbot.class.php");

//keep monitoring till the end of time
$mentionbot = new MentionBot();
$mentionbot->Monitor();