<?php

class MentionBot {

    private $log;
    private $config;
    private $reddit;
    private $blacklist;

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
        $this->blacklist = new Blacklist("black.list");
        $this->authorblacklist = new Blacklist("author.black.list");

        $this->debug("MentionBot was successfully initialized!\n----------------------------------------------------------\n\n");
    }

    public function Monitor() {
        $last = "";
        $last_pm_check = 0;
        while(true) {

            //check every minute
            if((time() - $last_pm_check) > 60) {
                $this->debug("Reading PM's....");

                $inbox = $this->reddit->sendRequest("GET", "http://www.reddit.com/message/unread.json?limit=100");
                if(!isset($inbox["data"]["children"])) {
                    $this->debug("Failed to read inbox");
                } else {
                    foreach($inbox["data"]["children"] as $message_) {
                        $message = (object)$message_["data"];
                        if(strtolower($message->subject) == "unsubscribe") {
                            $this->blacklist->add($message->author);
                            $this->pm("You have been unsubscribed from my notifications", $this->config->unsub_template, $message->author);
                        } else if(strtolower($message->subject) == "subscribe") {
                            $this->blacklist->remove($message->author);
                            $this->pm("You have been subscribed to my notifications", $this->config->sub_template, $message->author);
                        }

                        //mark as read
                        $this->reddit->sendRequest("POST", "http://www.reddit.com/api/read_message", array("id" => $message->name, "uh" => $this->reddit->modHash));
                    }
                }

                $last_pm_check = time();
            }

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
                $this->debug("Fetched ".count($result)." results. Sleeping ".(int)$spend." ms to complete 2s [RULES]");
            } else
                $this->debug("Fetched ".count($result)." results. Starting new request [NORULES]");
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

        //is author added to our 'do not send mentions from' blacklist?
        if($this->authorblacklist->isBlackListed($comment->getAuthorName())) {
            $this->debug("WARNING: Tried to send a mention from a blacklisted author");
            return;
        }

        //run regex
        $match = array();
        $res = preg_match_all ("/\\/u\\/((?:[a-z0-9_]{5,20}+))/is",$comment->getBody(), $match);
        //$this->debug($comment->getBody());
        if($res == false || $res == 0) return;

        $mentioned = array();

        //for loop for each user mentioned in this post
        for($i=0;$i<count($match[1]);$i++) {

            $mentioned_user = @$match[1][$i];

            //regex error?
            if($mentioned_user == null) {
                $this->debug("WARNING: Failed to get mentioned user from regex result (?) Aborting...");
                continue;
            }

            //did the user mention himself?
            if(strtolower($mentioned_user) == strtolower($comment->getAuthorName())) {
                $this->debug("User ".$mentioned_user." tried to mention himself, ignoring...");
                continue;
            }

            //we don't want to send the notification twice for the same message, if we've already seen this user mentioned, continue
            if(isset($mentioned[$mentioned_user])) continue;
            $mentioned[$mentioned_user] = "";

            if($this->blacklist->isBlackListed($mentioned_user)) {
                $this->debug("WARNING: Tried to message a user ({$mentioned_user}) that has blacklisted himself");
                continue;
            }

            //now for the famous reddit gold check
            $mentioned_user_account = $this->reddit->getAccountByUsername($mentioned_user);

            //does user even exist at all?
            if($mentioned_user_account == null) {
                $this->debug("WARNING: Tried to look up a user ({$mentioned_user}) that doesn't exist, aborting");
                continue;
            }
            //okay so he exists, does he have gold?
            if($mentioned_user_account->isGold()) {
                $this->debug("WARNING: Tried to send a message to a user ({$mentioned_user}) that has gold, aborting!");
                continue;
            }

            //We've gone trough all checks, send notification
            $this->pm(
                $comment->getAuthorName()." mentioned you in a comment.",
                $this->populateTemplate($comment),
                $mentioned_user
                //$mentioned_user
            );
        }

        //mark comment as processed
        $_SESSION["p"][$comment->getThingId()] = "";
    }

    public function pm($subject, $text, $to) {
        //reddit wrapper doesn't even PM, bro
        $resp = $this->reddit->sendRequest("POST", "http://www.reddit.com/api/compose.json",
            array(
                "api_type" => "json",
                "subject" => $subject,
                "text" => $text,
                "to" => $to,
                "uh" => $this->reddit->modHash
            )
        );

        if($resp != null && count($resp["json"]["errors"]) == 0) {
            $this->debug("Successfully send a message to ".$to);
        } else
        {
            $this->log("PM failed to '{$to}'!!!! Error details:");
            if($resp == null) $this->log("> Response is null (which means a network error, or a invalid status code from reddit");
            else $this->log(">JSON dump of error(s): ".json_encode($resp["json"]["errors"]));
            $this->debug("Failed to send a message to ".$to);
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
        if($exit) exit;
    }
}

//Lightweight user list class that does most if its work with a 'cache' but also saves things into a file called 'black.list'

class Blacklist {

    private $cache;
    private $fname;
    public function __construct($fname) {
        $this->fname = $fname;
        $this->refresh();
    }

    //update cache with file
    function refresh() {
        if(!file_exists($this->fname)) {
            $f = fopen($this->fname, "w+");
            fclose($f);
        }
        $file = fopen($this->fname, "r");
        $fsize = filesize($this->fname);
        if($fsize == 0) {
            //black.list not initialized?
            $this->cache = array();
        } else
            $this->cache = json_decode(fread($file, $fsize), true);
        fclose($file);
        $this->sync();
    }

    //update file with cache
    function sync() {
        $file = fopen($this->fname, "w");
        fwrite($file, json_encode($this->cache));
        fclose($file);
    }

    public function add($username) {
        $this->cache[strtolower($username)] = "";
        $this->sync();
    }

    public function remove($username) {
        unset($this->cache[strtolower($username)]);
        $this->sync();
    }

    public function isBlackListed($username) {
        return isset($this->cache[strtolower($username)]);
    }
}