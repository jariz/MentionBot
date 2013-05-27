<?php

class LinkFixerFixer {

    private $log;
    private $config;
    private $reddit;
    private $db;

    public function __construct() {
        require_once("config.php");
        $this->config = new Config();
        $this->debug("LinkFixerFixer is bootstrapping...");
        require_once("api/Reddit.php");
        $this->log = fopen("linkfixerfixer.log", "a+");
        $this->reddit = new \RedditApiClient\Reddit();
        if(!$this->reddit->login($this->config->username, $this->config->password)) {
            $this->log("LinkFixerFixer was unable to login to reddit");
            $this->Kill(true);
        }
        $this->db = new mysqli($this->config->db_host, $this->config->db_username, $this->config->db_password, $this->config->db_database);
        if ($this->db->connect_errno) {
            $this->log("LinkFixerFixer failed to connect to MySQL: (" . $this->db->connect_errno . ") " . $this->db->connect_error);
            $this->Kill(true);
        }
        $this->db->query("UPDATE info SET lastran = '".date("r")."'");
        $this->debug("LinkFixerFixer was successfully initialized!\n----------------------------------------------------------\n\n");
    }

    public function getLinkFixerComments() {
        //get last comment we responded on (if any)
        $q = $this->db->query("SELECT thing FROM comments ORDER BY id DESC LIMIT 0,1");
        if($q != false && $q->num_rows != 1) $thing = "";
        else $thing = $q->fetch_object()->thing;

        $result = $this->reddit->getCommentsByUsername("LinkFixerBot", $thing);

        //did the result return nothing? (comment deleted or w/e) Try again without filter.
        //(shit gets filtered by stalk function anyway)
        if(count($result) == 0) $result = $this->reddit->getCommentsByUsername("LinkFixerBot");
        return $result;
    }

    public function stalk(\RedditApiClient\Comment $comment) {

        //make sure that we didn't already comment on this one, or else it's gonna be really annoying
        if($this->db->query("SELECT COUNT(*) as count FROM comments WHERE thing = '{$comment->getThingId()}'")->fetch_object()->count == 1) return;

        $x = preg_replace_callback ("/(\\/)(r)(\\/)((?:[a-z][a-z]+))/is", array($this, "replacereddit"), $comment->getBody());
        $x = preg_replace_callback ("/(\\/)(u)(\\/)((?:[a-z][a-z]+))/is", array($this, "replaceuser"), $x);
        $success = $comment->reply($x);
        if($success != true) $this->log($success == false ? "FAILED" : "FAILED: $success");
        $this->db->query("INSERT INTO comments VALUES (NULL, '{$comment->getThingId()}', '{$x}', ".($success == true ? 1 : 0).")");
    }

    function replacereddit($matches) {
        if(isset($matches[4]))
            return "r/".$matches[4];
        return "";
    }

    function replaceuser($matches) {
        if(isset($matches[4]))
            return "u/".$matches[4];
        return "";
    }

    //log function, only used for errors (to prevent a 100GB logfile)
    public function log($msg) {
        fwrite($this->log, sprintf("[%s] %s", date("r"), $msg));
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