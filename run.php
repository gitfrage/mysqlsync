<?php

require 'Timer.php';   // https://github.com/sebastianbergmann/php-timer
require 'Sync.php';    // https://github.com/gitfrage/mysqlsync
require 'Connection.php';

$path = '/tmp/'.time();
PHP_Timer::start();


echo 'starting Diff Sync - require table dbpush_queries & triggers '."\n";
try {
    $s = new DiffSync($source, $path);

    foreach ($dest as $d) {
        $diff = $s->prepare($d);
        echo 'compare '.print_r($diff)."\n".PHP_Timer::resourceUsage()."\n";
    }
    $s->restore($dest);
    echo 'restore '.PHP_Timer::resourceUsage()."\n";

    foreach ($dest as $d) {
        $diff = $s->verify($d);
        echo 'verify '.print_r($diff)."\n".PHP_Timer::resourceUsage()."\n";
    }
} catch (Exception $e) {
    print_r($e);
}

if ($diff > 1) {
    echo 'starting FullSync - regular mysqldump & restore '."\n";
    try {
        foreach ($dest as $d) {
            $fs = new FullSync($source, $path);
            $fs->tables = $diff;  // only dump/restore not synced tables

            $fs->dump();
            echo 'dump '.PHP_Timer::resourceUsage()."\n";
            $fs->restore($d);
            echo 'restore '.PHP_Timer::resourceUsage()."\n";

            $diff = $s->verify($d);
            echo 'verify '.print_r($diff)."\n".PHP_Timer::resourceUsage()."\n";
        }
    } catch (Exception $e) {
        print_r($e);
    }
}

if ($diff > 1) {
    $headers   = array();
    $headers[] = "CC: <matthias.bayer@myracloud.com>";
    mail('nikolas.shewlakow@soprado.de', 'major fuckup', 'pls run full db push', implode("\r\n", $headers));
}
