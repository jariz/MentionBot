<?php
/**
 * Here you can configure MentionBot, simply change the values.
 */

class Config {
    //Reddit login info
    public $username = "MyMentionBot";
    public $password = "";

    //Bot settings
    public $debug = false; //never enable when cronjobbing
    public $ignore_rules = false; //Ignore reddit API rules to make at max 1 request per 2 seconds. Use at your own risk.
    public $template = "You were mentioned by /u/{user} in the thread ['{thread_title}']({threadurl}):

{message}

######[`Don't respond to me, I won't reply back`] [[`What am I?`](http://reddit.com/r/MentionBot)] [[`I am opensource`](http://github.com/jariz/MentionBot)]"; //message template
}

?>