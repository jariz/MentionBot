<?php

//force xdebug to gtfo
if(function_exists("xdebug_is_enabled") && xdebug_is_enabled()) xdebug_disable();

require_once("linkfixerfixer.class.php");
$linkfixerfixer = new LinkFixerFixer();
foreach($linkfixerfixer->getLinkFixerComments() as $comment) {
    $linkfixerfixer->stalk($comment);
}
$linkfixerfixer->Kill();