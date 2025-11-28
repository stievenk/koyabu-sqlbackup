# Install [![Latest Stable Version](https://poser.pugx.org/koyabu/mysqlbackup/downloads)](<[https://poser.pugx.org/koyabu/mysqlbackup/downloads](https://packagist.org/packages/koyabu/webapi)>)

```
composer require koyabu/mysqlbackup
```

## Change log

- Sync to Dropbox (https://github.com/stievenk/DropboxAPIClient)
- Sync to Google Drive (https://github.com/stievenk/GoogleDriveAPI)

## Requirement

- MySQL with mysqldump set to Global
- PHP 8+ and composer
- PHP MySQLi enable
- Cron job or Task Scheduler
- bzip2 (optional, only if compress set = true)

### Feature

- Backup Daily
- Backup Monthly
- Backup Weekly
- Backup All (with exeption filter)
- Save to Dropbox
- Save to Google Drive
- Auto Delete old file
- Log File: /your-backup-dir/data.json
- Log File: /your-backup-dir/backup.json >> backup log
- Don't remove file /your-backup-dir/gdrive.json
  if you sync to Google Drive it will create new folder if folder_id not found in gdrive.json

### Sample Code

```
<?php
use Koyabu\MysqlBackup\Backup;

/**
 * Note:
 * You need to set your `mysqldump` execution to Global
 * or you can set mysqldump path location
 * Linux Example:
 * $Backup->setMysqlDumpPath('/usr/bin/');
 * Windows Example:
 * $Backup->setMysqlDumpPath('C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\');
 *
 * You can run this script with cron job or task scheduler
 * set to run everyday (see cron manual or task scheluder manual):
 * /usr/bin/php sqlbackup.php > /dev/null 2>&1 &
 *
 * For Windows recommended use .bat file
 */
$config = [
    'mysql' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'port' => '3306',
        'data' => 'test'
    ],
    /**
     * Dropbox Sync
     * please read >> https://github.com/stievenk/DropboxAPIClient
    */
    'dropbox' => [
        'sync'              => false,
        'app_key'           => '',
        'app_secret'        => '',
        'access_token'      => '',
        'refresh_token'      => '',
        'auto_refresh'      => true,
        'home_dir'          => '/SQL-Backup/'
    ],
    /**
     * Google Drive Sync
    */
    'gdrive' => [
        'sync'              => false,
        'client_id'         => '',
        'client_secret'     => '',
        'access_token'      => '',
        'refresh_token'     => '',
        'redirect_uri'      => 'http://localhost',
        'folder'            => 'SQL-backup'
    ],

    'zip' => [
        'compress'          => false,
        'exec'              => 'bzip2 -z --force'
    ]
];

include 'vendor/autoload.php';

$Backup = new Backup($config);

// $Backup->removeOldFile($num_file_to_keep,$boolean_remove_remote_file_dropbox_gdrive);
// Remove Old File and keep latest 10
$Backup->removeOldFile(10,true);

// Database to Skip backup
$Backup->skipAlways(['performance_schema','mysql','test']);

/*
Set Dir where file backup will be saved
*/
$Backup->setDir('./data/sqlbackup/');

// Backup Daily for specific database
$Backup->Daily(['db1']);
// if parameter = null -> this will backup all database; alias $Backup->All();

// Backup Monthly specific database every date 28 of month
$Backup->Monthly(['db2'],28);

// Backup Every week on Thursday (0 = Sunday, 6 = Saturday)
$Backup->Weekly(['db3'],4);

/*
* Example: All DB Backup
* $Backup->All();
* this is Alias for $Backup->Daily(); without parameter
*
* or you can backup all database with filter parameter:
* $Backup->All($arrayDB_toSkip);
*
* Example: All DB Backup except aknj and wagw
* $Backup->All(['aknj','wagw']);
*/
//

$file_prefix = '';
$file_sufix = '_'.date("Y-m-d");
$Backup->run($file_prefix, $file_sufix, true);
?>
```

## Contact Me

If you want request some feature please contact me at <stieven.kalengkian@gmail.com>
