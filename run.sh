echo "loading..."

pid=`pidof live`

if ps  `pidof live` > /dev/null

then
    echo $pid
    #kill -USR1 $pid
    #kill -KILL $pid
    kill -9 $pid

fi

/www/server/php/81/bin/php sw.php
echo "loading success"
