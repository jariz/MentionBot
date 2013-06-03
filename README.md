MentionBot
=================

A bot that monitors all comments for mentions (for example /u/alienth) and PM's the user that got mentioned.
Based on [LinkFixerFixerBot](http://github.com/jariz/LinkFixerFixerBot) (may god have mercy on his soul)
Powered by h2s's reddit api wrapper.  

#Setup
1. Make sure you got PHP & MySQL set up. (if you don't know how, please, stahp. go home. this is no place for you.)  
2. Execute the SQL file in your MySQL database  
3. Set up config.php (speaks for it self)  
4. Make sure to run the program from command line and perhaps add it to init.d or whatever
The program will now keep monitoring reddit and PMing users that get mentioned