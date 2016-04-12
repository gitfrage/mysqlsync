# mysqlsync
Sync multiple destination dbs with a source db

1. class FullSync    - mysqldump & mysql restore
2. class OutfileSync - faster full sync, with LOAD DATA INFILE (script must run on destination machine)
3. class DiffSync    - Diff Sync (extra table and triggers required)
