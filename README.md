# Install [![Latest Stable Version](https://poser.pugx.org/koyabu/mysqlbackup/downloads)](<[https://poser.pugx.org/koyabu/mysqlbackup/downloads](https://packagist.org/packages/koyabu/webapi)>)

```
composer require koyabu/mysqlbackup
```

## Change log

- Sync to Dropbox

## Requirement

- MySQL with mysqldump set to Global
- PHP 8+ and composer
- PHP MySQLi enable
- Cron job or Task Scheduler

### Feature

- Backup Daily
- Backup Monthly
- Backup Weekly
- Backup All (with exeption filter)
- Save to Dropbox (for next version)
- Save to Google Drive (for next version)
- Auto Delete old file (for next version)

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
$config = array();
$config['mysql']['host']="localhost";
$config['mysql']['user']="root";
$config['mysql']['pass']="";
$config['mysql']['port']="3306";
$config['mysql']['data']="test";

$config['dropbox']['app_key'] = '';
$config['dropbox']['app_secret'] = '';
$config['dropbox']['refresh_token'] = '';
$config['dropbox']['access_token'] = '';

$config['dropbox']['home_dir'] = '/SQL-Backup/';

$config['dropbox']['sync'] = false; // set true to Sync with dropbox, make sure refresh_token already set

include 'vendor/autoload.php';

$Backup = new Backup($config);
$Backup->skipAlways(['performance_schema','mysql','test']);

/*
Set Dir where file backup will be saved
*/
$Backup->setDir('./data/sqlbackup/');

// Backup Daily for specific database
$Backup->Daily(['aknj']);
// if parameter = null -> this will backup all database; alias $Backup->All();

// Backup Monthly specific database every date 28 of month
$Backup->Monthly(['wagw'],28);

// Backup Every week on Thursday (0 = Sunday, 6 = Saturday)
$Backup->Weekly(['hwm'],4);

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
