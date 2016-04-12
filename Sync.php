<?php

interface iSync
{
    public function prepare($dest);
    public function dump();
    public function restore($dest);
    public function verify($dest);
}

abstract class aSync implements iSync
{
    protected $source;      // array mysql options (mysql --help)
    protected $dest;        // array mysql options (mysql --help)
    protected $dumpOpts;    // basic options (mysqldump --help)

    protected $path;        // path to dump data
    public $tables;      // array of source tables to push
    protected $checksum;    // array of source tables with checksums

    public function __construct(
        $source,
        $path = '/tmp/',
        $comentedTables = 'PUSH_TO_LIVE',
        $dumpOpts = '--no-create-db --allow-keywords --skip-triggers --skip-tz-utc --hex-blob --order-by-primary '
    ) {
        $this->source = $source;
        $this->path = $path;
        $this->tables = $this->selectTablesForSync($comentedTables);
        $this->dumpOpts = $dumpOpts;

    }

    public function prepare($dest)
    {
        $this->checksum = $this->getTablesChecksum($this->source);
        $this->tables = $this->verify($dest);

        if (!$this->tables) {
            throw new \Exception('Nothing to do - same chechsum on source and dest');
        }

        return $this->tables;
    }

    public function verify($dest)
    {
        if (!$this->checksum) {
            throw new \Exception('can not verify whitout checksum option');
        }

        $checksum = $this->getTablesChecksum($dest);

        $diff = '';
        foreach ($this->tables as $table) {
            if ($this->checksum[$table] !== $checksum[$table]) {
                $diff[] = $table;
            }
        }

        return $diff;
    }

    protected function selectTablesForSync($comment = '')
    {
        $sql = 'SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_COMMENT = '.escapeshellarg($comment).'
            AND TABLE_SCHEMA = '.escapeshellarg($this->source['d']);

        $tables = $this->runCmd("mysql -e \"$sql\"", $this->source);

        if ($tables == false) {
            throw new \Exception('Nothing to push in:'.$this->source['d']);
        }

        array_shift($tables); // remove first element 'TABLE_NAME'

        return $tables;
    }

    protected function getTablesChecksum($options)
    {
        foreach ($this->tables as $table) {
            $out = $this->runCmd("mysql -e 'checksum table `$table`'", $options);
            preg_match('/\d+$/', $out[1], $matches);
            $checksum[$table] = $matches;
        }

        if ($checksum == false) {
            throw new \Exception("getTablesChecksum for table $table return noting ");
        }

        return $checksum;
    }

    protected function exportDump($options)
    {
        $options = $options;
        $options .= ' --databases '.$this->source['d'];
        $options .= ' --tables '.implode(' ', $this->tables);

        $source = $this->source;
        unset($source['d']);
        $structure = $this->runCmd("mysqldump $options", $source);
    }

    protected function importDump($dest)
    {
        $file = $this->path.'dump.sql';
        $this->runCmd("mysql < $file", $dest);
    }

    protected function loadData($dest, $table)
    {
        $file = $this->path.$table.'.txt';

        $sql = array(
            'SET FOREIGN_KEY_CHECKS=0',
            'SET UNIQUE_CHECKS=0',
            "ALTER TABLE $table DISABLE KEYS",
            "LOAD DATA INFILE '$file' INTO TABLE $table CHARACTER SET utf8",
            "ALTER TABLE $table ENABLE KEYS",
        );

        $sql = escapeshellarg(implode(';', $sql));
        $this->runCmd("mysql -e $sql", $dest);
    }

    protected function runCmd($cmd, $options)
    {
        # ToDo: $sourceArgs = "--defaults-extra-file=(printf "[client]\nuser = %s\npassword = %s\database = %s" "user" "pass" "db")";
        if (isset($options['h'])) {
            $cmd .= ' --host '.escapeshellarg($options['h']);
        }
        if (isset($options['P'])) {
            $cmd .= ' --port '.intval($options['P']);
        }
        if (isset($options[ 'p' ])) {
            $cmd .= ' -p'.escapeshellarg($options['p']);
        }
        if (isset($options['u'])) {
            $cmd .= ' --user '.escapeshellarg($options['u']);
        }
        if (isset($options['d'])) {
            $cmd .= ' '.escapeshellarg($options['d']);
        }

        $cmd .= ' ';
        exec($cmd, $output, $return);
        if ($return) {
            throw new \Exception("Command executed with code $return: $cmd");
        }

        return $output;
    }

    protected function getHandle($data)
    {
        try {
            $options = array(
                PDO::ATTR_AUTOCOMMIT => false, // PDO::ATTR_PERSISTENT => true,
            );
            $handle = new PDO('mysql:host='.$data['h'].';port='.$data['P'].';dbname='.$data['d'], $data['u'], $data['p'], $options);
            $handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // $handle->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8");
        } catch (Exception $e) {
            exit('Database connection could not be established. '.$e->getMessage());
        }

        return $handle;
    }
}

class OutfileSync extends aSync implements iSync
{
    /*
    * first dump stricture sql only
    * second - data txt
    */
    public function dump()
    {
        $options = $this->dumpOpts.'--no-data  --result-file='.$this->path.'dump.sql';
        $this->exportDump($options);

        $options = $this->dumpOpts.'--tab='.$this->path;
        $this->exportDump($options);
    }

    /*
    * check if data (table.txt) is there
    * first load stricture sql
    * second - load data txt
    */
    public function restore($dest)
    {
        foreach ($this->tables as $table) {
            $txt = glob("$this->path$table.txt");
            if (!$txt) {
                throw new \Exception("file not found for table $table");
            }
        }

        $this->importDump($dest);

        foreach ($this->tables as $table) {
            $this->loadData($dest, $table);
        }
    }
}

class FullSync extends aSync implements iSync
{
    /*
    *  mysqldump definitions and data
    */
    public function dump()
    {
        $options = $this->dumpOpts.'--result-file='.$this->path.'dump.sql';
        $this->exportDump($options);
    }

    /*
    *  import full dump
    */
    public function restore($dest, $mode = false)
    {
        $this->importDump($dest);
    }
}

class DiffSync extends aSync implements iSync
{
    public function dump()
    {
        // no dumps in class DiffSync
    }

    public function restore($dest)
    {
        $conn_source = $this->getHandle($this->source);
        $rows = $conn_source->query('select * from dbpush_queries where pushed = "no"')->fetchAll();

        if (count($rows) == 0) {
            echo 'nothing found in dbpush_queries';
            return;
        } else {
            echo 'found '.count($rows).'rows'."\n";
        }

        $conn_source->exec('BEGIN');
        foreach ($dest as $conn => $value) {
            $handle[$conn] = $this->getHandle($value);
            $handle[$conn]->exec('BEGIN');
            foreach ($rows as $row) {
                try {
                    $handle[$conn]->query($row['original_query']);
                } catch (Exception $e) {
                    echo 'handle exec Failed: '.$e->getMessage();
                }
                $conn_source->query("update dbpush_queries set pushed = 'yes' where id =".$row['id']);
            }
            $handle[$conn]->exec('COMMIT');
        }
        $conn_source->exec('COMMIT');
    }
}
