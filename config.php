<?php
/**
 * Here you can configure LinkFixerFixer, simply change the values.
 */

class Config {
    //Reddit login info
    public $username = "MyLinkFixerFixerBot";
    public $password = "";

    //MySQL login info
    public $db_username = "root";
    public $db_password = "";
    public $db_database = "linkfixerfixer";
    public $db_host = "127.0.0.1";

    //Bot settings
    public $debug = true; //never enable when cronjobbing
    public $stalking = "LinkFixerBot"; //who are we stalking?
}

?>