<?php
$lines = file(__DIR__.'/.env');
foreach($lines as $l){
    if(preg_match('/^(N8N|EVOLUTION|APP_URL|BROADCAST|QUEUE|DB_CONNECTION|CACHE|SESSION)/', $l)) echo $l;
}
