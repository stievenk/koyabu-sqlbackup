<?php
use Koyabu\MysqlBackup\Backup;
use Koyabu\DropboxApi\Dropbox;
use Koyabu\Googledriveapi\GoogleDriveClient;
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_PARSE);
ini_set('date.timezone','Asia/Makassar');
set_time_limit(0);
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

// not available for version v1.0.1 ->removeOldFile(10,true);
// Now file will auto remove and keep 3 file for default
// you can change keepFile_count number (min: 3)
$config['keepFile_count'] = 3;

$config['mysql']['host']="localhost";
$config['mysql']['user']="root";
$config['mysql']['pass']="";
$config['mysql']['port']="3306";
$config['mysql']['data']="test";

$config['mysqldump_path'] = '';


// DROPBOX CONFIGURATION
$config['dropbox']['app_key'] = 'YOUR_APP_KEY';
$config['dropbox']['app_secret'] = 'YOUR_APP_SECRET';
/** 
 * Refresh Token Require for dropbox sync
 * please read >> https://github.com/stievenk/DropboxAPIClient
 * */ 
$config['dropbox']['refresh_token'] = ''; 
$config['dropbox']['access_token'] = '';
$config['dropbox']['auto_refresh'] = true;

$config['dropbox']['home_dir'] = '/SQL-Backup/';

$config['dropbox']['sync'] = true; // set true to Sync with dropbox, make sure refresh_token already set

$config['gdrive']['sync'] = true;
$config['gdrive']['client_id'] = '';
$config['gdrive']['client_secret'] = '';
$config['gdrive']['access_token'] = '';
$config['gdrive']['refresh_token'] = '';
$config['gdrive']['redirect_uri']  = 'https://localhost';

$config['zip']['compress'] = true;
$config['zip']['exec'] = 'bzip2 -z --force';

include 'vendor/autoload.php';

$Backup = new Backup($config);
if ($config['mysqldump_path']) {
    $Backup->setMysqlDumpPath($config['mysqldump_path']);
}
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