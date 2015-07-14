<?php

interface iSync
{
    public function prepare($dest);
    public function dump();
    public function restore($dest);
    public function verify($dest);
}

class OutfileSync extends aSync implements iSync
{
    /*
    * first dump stricture sql only
    * second - data txt
    */
    public function dump()
    {
        $options = $this->dumpOpts . '--no-data  --result-file=' . $this->path . 'dump.sql';
        $this->exportDump($options);

        $options = $this->dumpOpts . '--tab=' . $this->path ;
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
        $options = $this->dumpOpts . '--result-file=' . $this->path . 'dump.sql';
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
    public function prepare($dest){

    }
    public function dump(){

    }
    public function restore($dest, $mode = false) {

    }
    public function verify($dest) {

    }
}

class aSync {

    protected $source;      // array mysql options (mysql --help)
    protected $path;        // path to dump data
    protected $tables;      // array of source tables to push

    protected $dest;        // array mysql options (mysql --help)
    protected $checksum;    // array of source tables with checksums
    protected $dumpOpts;    // basic options (mysqldump --help)

    public function __construct(
        $source,
        $path               = '/tmp/',
        $comentedTables     = 'PUSH_TO_LIVE',
        $dumpOpts           = '--no-create-db --allow-keywords --skip-triggers --skip-tz-utc --hex-blob --order-by-primary '
    ) {
        $this->source       = $source;
        $this->path         = $path;
        $this->tables       = $this->selectTablesForSync($comentedTables);
        $this->dumpOpts     = $dumpOpts;
    }

    public function prepare($dest)
    {
        $this->checksum = $this->getTablesChecksum($this->source);
        $this->tables = $this->verify($dest);

        if (!$this->tables) {
            throw new \Exception("Nothing to do - same chechsum on source and dest");
        }
        return $this->tables;
    }

    public function verify($dest)
    {
        if (!$this->checksum) {
            throw new \Exception("con not verify whitout checksum option");
        }

        $checksum  = $this->getTablesChecksum($dest);

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
        $sql = "SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_COMMENT = " . escapeshellarg($comment) . "
            AND TABLE_SCHEMA = " . escapeshellarg($this->source['d']);

        $tables = $this->run_cmd("mysql -e \"$sql\"", $this->source);

        if ($tables == false) {
            throw new \Exception("Nothing to push in:" . $this->source['d']);
        }

        // remove first element 'TABLE_NAME'
        array_shift($tables);

        return $tables;
    }

    protected function getTablesChecksum($options) {

        foreach ($this->tables as $table) {
            $out = $this->run_cmd("mysql -e 'checksum table `$table`'", $options);
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
        $options  = $options;
        $options .= ' --databases ' . $this->source['d'];
        $options .= ' --tables ' . implode(" ", $this->tables);

        $source = $this->source; unset($source['d']);
        $structure = $this->run_cmd("mysqldump $options", $source);
    }

    protected function importDump($dest)
    {
        $file = $this->path . 'dump.sql';
        $this->run_cmd("mysql < $file", $dest);
    }

    protected function loadData($dest, $table)
    {
        $file = $this->path . $table . '.txt';

        $sql = array(
            "SET FOREIGN_KEY_CHECKS=0",
            "SET UNIQUE_CHECKS=0",
            "ALTER TABLE $table DISABLE KEYS",
            "LOAD DATA INFILE '$file' INTO TABLE $table CHARACTER SET utf8",
            "ALTER TABLE $table ENABLE KEYS",
        );

        $sql = escapeshellarg(implode(";", $sql));
        $this->run_cmd("mysql -e $sql", $dest);
    }

    protected function run_cmd($cmd, $options) {

        # ToDo: $sourceArgs = "--defaults-extra-file=(printf "[client]\nuser = %s\npassword = %s\database = %s" "user" "pass" "db")";

        if (isset($options['h'])) {
            $cmd .= " --host " . escapeshellarg($options['h']);
        }
        if (isset($options['u'])) {
            $cmd .= " --user " . escapeshellarg($options['u']);
        }
        if (isset($options['P'])) {
            $cmd .= " --port " . intval($options['P']);
        }
        if (isset($options[ "p" ])) {
            $cmd .= " -p" . escapeshellarg($options['p']);
        }
        if (isset($options['d'])) {
            $cmd .= " " . escapeshellarg($options['d']);
        }

        $cmd .= " ";
        exec($cmd, $output, $return);
        if ($return) {
            throw new \Exception("Command executed with code $return: $cmd");
        }
        return $output;
    }
}