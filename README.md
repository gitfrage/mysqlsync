# mysqlsync
Fast Sync (LOAD DATA INFILE) multiple destination dbs with a source db

<?php

require('Timer.php');   // https://github.com/sebastianbergmann/php-timer
require('Sync.php');    // https://github.com/gitfrage/mysqlsync

$source = array( 'h' => 'localhost', 'u' => 'root', 'd' => 'sourcedb');
$dest[] = array( 'h' => 'localhost', 'u' => 'root', 'd' => 'destdb1');
$dest[] = array( 'h' => 'localhost', 'u' => 'root', 'd' => 'destdb2');
$path   = '/tmp/' . time();

PHP_Timer::start();

try {

    $s = new OutfileSync($source, $path);

    $diff = $s->prepare($dest['0']); echo 'compare ' . print_r($diff) . "\n" . PHP_Timer::resourceUsage() . "\n";
    $s->dump();                      echo 'dump '    . PHP_Timer::resourceUsage() . "\n";
    $s->restore($dest['0']);         echo 'restore ' . PHP_Timer::resourceUsage() . "\n";
    $diff = $s->verify($dest['0']);  echo 'verify '  . print_r($diff) . "\n" . PHP_Timer::resourceUsage() . "\n";

    $s->restore($dest['1']);
    $s->restore($dest['2']);
    $diff = $s->verify($dest['1']);
    $diff = $s->verify($dest['2']);

} catch (Exception $e) {
    print_r($e);
}
