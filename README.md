# mysqlsync
Sync multiple destination dbs with a source db

1. class FullSync    - mysqldump & mysql restore
2. class OutfileSync - faster full sync, with LOAD DATA INFILE (script must run on destination machine)
3. class DiffSync    - Diff Sync (extra table and triggers required)

```php

<?php

require('Timer.php');       // https://github.com/sebastianbergmann/php-timer
require('Sync.php');        // https://github.com/gitfrage/mysqlsync
require('Connection.php');  // https://github.com/gitfrage/mysqlsync
$path   = '/tmp/' . time();
PHP_Timer::start();

try {
    $s = new FullSync($source, $path);
    $diff = $s->prepare($dest['0']);
    $s->dump();
    $s->restore($dest['0']);
    $s->restore($dest['1']);
    $diff = $s->verify($dest['0']);
    $diff = $s->verify($dest['1']);
} catch (Exception $e) {
    print_r($e);
}

```