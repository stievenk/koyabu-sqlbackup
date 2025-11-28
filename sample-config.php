<?php
/**
 * This sample using config.json:
 * ---- 
 {
    "daily"     : "all",
    "weekly"    : "all",
    "monthly"   : "all",
    "monthly_start" : 1,
    "weekly_start"  : 0,
    "removeOldFile" : 10,
    "save_dir"  : "~/Database-Backup/"
 }
 * ---
 * Run your php file with Cronjob or Task Scheduler
 * Linux:
 * /usr/bin/php sample-config.php > /dev/null 2>&1 &
 */
use Koyabu\MysqlBackup\Backup;
use Koyabu\DropboxApi\Dropbox;
use Koyabu\Googledriveapi\GoogleDriveClient;
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_PARSE);
ini_set('date.timezone','Asia/Makassar');
set_time_limit(0);

include 'vendor/autoload.php';

$config = [
    'mysql' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'port' => '3306',
        'data' => 'test'
    ],

    'dropbox' => [
        'sync'              => false,
        'app_key'           => '',
        'app_secret'        => '',
        'access_token'      => '',
        'refresh_token'      => '',
        'auto_refresh'      => true,
        'home_dir'          => '/SQL-Backup/'
    ],

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

$Backup = new Backup($config);
$Backup->loadConfig('config.json');
$file_prefix = 'server.hostname.com-';
$file_sufix = '_'.date("Y-m-d");
$Backup->run($file_prefix, $file_sufix, true);
?>