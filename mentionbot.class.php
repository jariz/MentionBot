<?php

class MentionBot {

    private $log;
    private $config;
    private $reddit;
    private $db;

    public function __construct() {
        require_once("config.php");
        $this->config = new Config();
        $this->debug("MentionBot is bootstrapping...");
        $_SESSION["p"] = array();
        require_once("api/Reddit.php");
        $this->log = fopen("bot.log", "a+");
        $this->reddit = new \RedditApiClient\Reddit();
        if(!$this->reddit->login($this->config->username, $this->config->password)) {
            $this->log("MentionBot was unable to login to reddit");
            $this->Kill(true);
        }

        $this->debug("MentionBot was successfully initialized!\n----------------------------------------------------------\n\n");
    }

    public function Monitor() {
        $last = "";
        while(true) {
            $b4 = microtime(true);
            $result = $this->reddit->getComments("r/all/comments", 100, $last);

            //if our filter didn't return any results, start back from scratch again, might need to find a more elegant way to do this, and need to find out what causes this.
            if(count($result) == 0) {
                $this->debug("WARNING: Filter didn't return anything, removing filter, trying again!");
                $last = "";
                continue;
            }

            foreach($result as $comment) {
                $this->scan($comment);
                $last = $comment->getThingId();
            }

            //'On average, we should see no more than one request every two seconds from you.'
            $spend = 2000 - (microtime(true) - $b4);
            if(!$this->config->ignore_rules && $spend > 0) {
                usleep($spend*1000);
                $this->debug("Done. Sleeping ".(int)$spend." ms before making another request [RULES]");
            } else
                $this->debug("Done. Starting new request [NORULES]");
        }

    }

    function populateTemplate(\RedditApiClient\Comment $comment) {
        $template = $this->config->template;
        $template = str_replace("{user}", $comment->getAuthorName(), $template);
        $template = str_replace("{thread_title}", $comment->getLinkTitle(), $template);
        $template = str_replace("{threadurl}", "http://reddit.com/comments/".substr($comment->getLinkId(), 3)."/MentionBot/{$comment->getId()}", $template);
        $template = str_replace("{message}", ">".str_replace("\n", "\n>", $comment->getBody()), $template);
        return $template;
    }

    public function scan(\RedditApiClient\Comment $comment) {

        //check if we've already processed this and it somehow got into the queue
        if(isset($_SESSION["p"][$comment->getThingId()])) {
            $this->debug("WARNING: Skipping (what appears to be) a message we've already processed");
            return;
        }

        //run regex
        $match = array();
        $res = preg_match_all ("/\\/u\\/((?:[a-z][a-z]+))/is",$comment->getBody(), $match);
        //$this->debug($comment->getBody());
        if($res == false || $res == 0) return;

        //for loop for each user mentioned in this post
        for($i=0;$i<count($match[1]);$i++) {

            $mentioned_user = @$match[1][$i];

            if($mentioned_user == null) {
                $this->debug("WARNING: Failed to get mentioned user from regex result (?) Aborting...");
                break;
            }

            //reddit wrapper doesn't even PM, bro
            $resp = $this->reddit->sendRequest("POST", "http://www.reddit.com/api/compose.json",
                array(
                    "api_type" => "json",
                    "subject" => $comment->getAuthorName()." mentioned you in a comment.",
                    "text" => $this->populateTemplate($comment),
                    //"to" => "MoederPoeder",
                    "to" => $mentioned_user,
                    "uh" => $this->reddit->modHash
                )
            );

            //mark comment as processed
            $_SESSION["p"][$comment->getThingId()] = "";

            if($resp != null && count($resp["json"]["errors"]) == 0) {
                //xdebug_break();
                $this->debug("Successfully send a message to ".$mentioned_user);
            } else
            {
                $this->log("PM failed to '{$mentioned_user}'!!!! Error details:");
                if($resp == null) $this->log("> Response is null (which means a network error, or a invalid status code from reddit");
                else $this->log("JSON dump of error(s): ".json_encode($resp["json"]["errors"]));
                $this->debug("Failed to send a message to ".$mentioned_user);
            }
        }
    }
    //log function, only used for errors (to prevent a 100GB logfile)
    public function log($msg) {
        fwrite($this->log, sprintf("[%s] %s\n", date("r"), $msg));
        if($this->config->debug) printf("[%s] [ERROR] %s\n", date("r"), $msg);
    }

    //debug function, printed to screen (never enable on cronjob!)
    public function debug($msg) {
        if($this->config->debug) printf("[%s] %s\n", date("r"), $msg);
    }

    public function Kill($exit=false) {
        fclose($this->log);
        $this->db->close();
        if($exit) exit;
    }
}