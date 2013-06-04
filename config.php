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
    public $template = "You were mentioned by /u/{user} in the thread ['{thread_title}']({threadurl}).

{message}

######[`Don't respond to me, I won't reply back`] [[`Unsubscribe`](http://www.reddit.com/message/compose/?to=MentionBot&subject=Unsubscribe&message=Just+hit+send+to+unsubscribe+from+MentionBot+notifications)] [[`What am I?`](http://reddit.com/r/MentionBot)] [[`I am opensource`](http://github.com/jariz/MentionBot)]"; //message template
    public $unsub_template = "Hi, I've received your request to unsubscribe from my notifications.  
You now won't be notified when someone mentions you.  
Please tell us why you unsubscribed at /r/MentionBot and **if you ever change your mind**, [you can subscribe back by PMing 'subscribe'](http://www.reddit.com/message/compose/?to=MentionBot&subject=Subscribe&message=Just+hit+send+to+subscribe+to+MentionBot+notifications+:D)  
Farewell :(";
    public $sub_template = "Hi, I've received your request to subscribe to my notifications.  
You will be notified again when somebody mentions you.  
Glad to have you back :)";
}

?>