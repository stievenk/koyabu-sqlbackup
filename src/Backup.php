<?php
namespace Koyabu\mysqlbackup;
use Koyabu\DropboxApi\Dropbox;
use Koyabu\Googledriveapi\GoogleDriveClient;
use Koyabu\TelegramAPI\Telegram;

class Backup {

   public $BASE_DIR = './data/sqlbackup/';
   private $conn;
   public $Databases;
   public $DB_Backup = [];
   public $mysqldumppath = '';
   public $skipDB = [];
   public $config;


   function __construct($config) {
      $this->config = $config;
      $this->conn = new \mysqli($config['mysql']['host'],$config['mysql']['user'],$config['mysql']['pass'],$config['mysql']['data']);
      $this->getAllDatabases();
      $this->createDir();
   }

   function setDir($dir) {
      $this->BASE_DIR = $dir;
      $this->createDir();
   }

   function createDir($subdir='') {
      if (!file_exists($this->BASE_DIR.$subdir)) {
         mkdir($this->BASE_DIR,0777,true);
      }
   }

   function getAllDatabases() {
      $g = $this->conn->query("show databases;");
      while($t = $g->fetch_assoc()) {
         $this->Databases[] = $t['Database'];
      }
   }

   function Daily($databases = []) {
      if (is_array($databases)) {
         $this->DB_Backup = array_merge($this->DB_Backup,$databases);
         $this->DB_Backup = array_unique($this->DB_Backup);
         sort($this->DB_Backup);
      } else if ($databases == 'all') {
         $this->DB_Backup = $this->Databases;
      }
   }

   function Weekly($databases = [], $weekday = 1) {
      if (date("w") == $weekday) {
         if (is_array($databases)) {
            $this->DB_Backup = array_merge($this->DB_Backup,$databases);
            $this->DB_Backup = array_unique($this->DB_Backup);
            sort($this->DB_Backup);
         } else if ($databases == 'all') {
            $this->DB_Backup = $this->Databases;
         }
      }
   }

   function Monthly($databases = [], $montlhyday = 1) {
      if (date("d") == $montlhyday) {
         if (is_array($databases)) {
            $this->DB_Backup = array_merge($this->DB_Backup,$databases);
            $this->DB_Backup = array_unique($this->DB_Backup);
            sort($this->DB_Backup);
         } else if ($databases == 'all') {
            $this->DB_Backup = $this->Databases;
         }
      }
   }

   function All($filter = []) {
      if (count($filter) > 0) {
         $DBS = [];
         for ($i = 0; $i < count($this->Databases); $i++) {
            if (!in_array($this->Databases[$i], $filter)) {
               $DBS[] = $this->Databases[$i];
            }
         }
         $this->DB_Backup = array_merge($this->DB_Backup,$DBS);
      } else {
         $this->DB_Backup = $this->Databases;
      }
   }

   function skipAlways($db) {
      $this->skipDB = $db;
   }

   function setMysqlDumpPath($path) {
      $this->mysqldumppath = $path;
   }

   function dump($Database,$fileoutput,$debug = false) {
      $exec = "{$this->mysqldumppath}mysqldump";
      if ($this->config['mysql']['user']) {
         $exec .= " -u{$this->config['mysql']['user']}";
      }
      if ($this->config['mysql']['pass']) {
         $exec .= " -p{$this->config['mysql']['pass']}";
      }
      $exec .= " {$Database} > {$this->BASE_DIR}{$fileoutput}.sql";
      
      if ($debug) { echo $exec.PHP_EOL; }
      exec($exec,$retr);
      if ($debug) {
         if ($retr) { print_r($retr); }
      }
      return "{$this->BASE_DIR}{$fileoutput}.sql";
   }

   function run($file_prefix = '', $file_sufix = '' ,$debug = false) {
      $telegram = [];
      for ($i = 0; $i < count($this->DB_Backup); $i++) {
         if (in_array($this->DB_Backup[$i], $this->skipDB)) {
            continue;
         }
         $file = $this->dump($this->DB_Backup[$i],$file_prefix.$this->DB_Backup[$i].$file_sufix,$debug);
         if ($this->config['zip']['compress'] == true) {
            if (!empty($this->config['zip']['exec'])) {
               @exec($this->config['zip']['exec'].' '.$file);
               echo $res ? $res.PHP_EOL : '';
               $file = $file.'.bz2';
            }
         }
         if ($this->config['dropbox']['sync'] == true) {
            $dbx = $this->dbx_sync($file);
         }
         if ($this->config['gdrive']['sync'] == true) {
            $gdx = $this->gdrive_sync($file);
         }
         $filesize = filesize($file);
         $log = [
            'database' => $this->DB_Backup[$i],
            'datetime' => date("Y-m-d H:i:s"),
            'base_dir' => $this->BASE_DIR,
            'filename' => basename($file),
            'filesize' => $filesize,
            'file_md5' => md5_file($file),
            'dropbox' => $dbx ? $dbx : null,
            'gdrive' => $gdx ? $gdx : null
         ];
         $this->json_log($log);
         $this->db_log($this->DB_Backup[$i],$log);
         unset($log['dropbpx'],$log['gdrive']);
         $telegram[$this->DB_Backup[$i]]=[
            'file' => basename($file),
            'size' => round(($filesize / 1024 / 1024),2) .'MB'
         ];
      }
      if (!empty($telegram)) { 
         $this->sendTelegram(json_encode($telegram,JSON_PRETTY_PRINT)); 
      }
   }

   function gdrive_sync($filename) {
      if (!is_array($this->config['gdrive'])) { 
         echo "Invalid config Dropbox".PHP_EOL;
         return false; 
      }
      if ((
         empty($this->config['gdrive']['client_id']) or empty($this->config['gdrive']['client_secret']))
         and empty($this->config['gdrive']['refresh_token'])
         ) {
            echo "Gogole config not found!".PHP_EOL; 
            return false; 
         }
      echo "Google Drive Sync ";
      // print_r($this->config['gdrive']); exit;
      $drive = new GoogleDriveClient($this->config['gdrive']);
      $folder = $this->config['gdrive']['folder'] ?? 'SQL_backup';
      $gDriveFile = $this->BASE_DIR . 'gdrive.json';
      if (!file_exists($gDriveFile)) { file_put_contents($gDriveFile,''); }
      $cfg = json_decode(file_get_contents($gDriveFile),true);
      if (empty($cfg['path'])) {
         // Create Folder
         $result = $drive->createFolder($folder);
         $cfg['path']['id'] = $result->id;
         $cfg['path']['parents'] = $result->parents;
         $cfg['path']['name'] = $folder;
      }
      $res = $drive->uploadFile($filename, basename($filename),$cfg['path']['id']);
      if ($res->id) {
         unset($cfg['file']);
         $drive->createShareLink($res->id, 'reader', 'anyone');
         $info = $drive->fileInfo($res->id);
         if ($info) {
            $cfg['file'] = $info;
         } else {
            $cfg['file']['id'] = $res->id;
            $cfg['file']['name'] = basename($filename);
         }
         file_put_contents($gDriveFile,json_encode($cfg,JSON_PRETTY_PRINT));
         return $cfg;
      } else {
         echo $drive->lastError."\n";
         return false;
      }
   }

   function dbx_sync($filename) {
      if (!is_array($this->config['dropbox'])) { 
         echo "Invalid config Dropbox".PHP_EOL;
         return false; 
      }
      if ((
         empty($this->config['dropbox']['app_key']) or empty($this->config['dropbox']['app_secret']))
         and empty($this->config['dropbox']['refresh_token'])
         ) {
            echo "Dropbox config not found!".PHP_EOL; 
            return false; 
         }
      echo "Dropbox Sync\n";
      $DBX = new Dropbox($this->config['dropbox']);
      if($res = $DBX->upload($filename)) {
         return $res;
      } else {
         echo "DBX Error:". $DBX->error.PHP_EOL;
      }
   }

   function json_log($log) {
      $fjson = $this->BASE_DIR.'backup.json';
      if (!file_exists($fjson)) {
         file_put_contents($fjson, '');
      }

      $data = file_get_contents($fjson);
      $json = json_decode($data, true) ?? [];

      $numericKeys = array_filter(array_keys($json), 'is_numeric');

      if (count($numericKeys) >= 250) {
         sort($numericKeys, SORT_NUMERIC);
         unset($json[$numericKeys[0]]);
      }

      $json[] = $log;

      $json = array_merge(
         array_filter($json, fn($v, $k) => !is_numeric($k), ARRAY_FILTER_USE_BOTH),
         array_values(array_filter($json, fn($v, $k) => is_numeric($k), ARRAY_FILTER_USE_BOTH))
      );

      file_put_contents($fjson, json_encode($json, JSON_PRETTY_PRINT));
   }

   function db_log($dbname,$log) {
      $filelog = $this->BASE_DIR . 'data.json';
      if (!file_exists($filelog)) {
         file_put_contents($filelog,'');
      }
      $json = file_get_contents($filelog);
      $data = json_decode($json,true) ?? [];
      if (empty($data[$dbname])) {
         $logs = $log;
         unset($logs['gdrive'],$logs['dropbox']);
         $data[$dbname] = $logs;
      }
      $data[$dbname]['local'][] = $log['filename'];
      if (!empty($log['dropbox'])) { $data[$dbname]['dropbox'][] = $log['dropbox']; }
      if (!empty($log['gdrive'])) { $data[$dbname]['gdrive'][] = $log['gdrive']; }
      file_put_contents($filelog, json_encode($data, JSON_PRETTY_PRINT));
   }

   function removeOldFile($max = 10, $remote = false) {
      $filelog = $this->BASE_DIR . 'data.json';
      if (file_exists($filelog)) {
         $data = json_decode(file_get_contents($filelog),true) ?? [];
      }
      foreach($data as $k => $v) {
         if (is_array($v['local'])) {
            $keys = array_keys($v['local']);
            $oldData = $v['local'][$keys[0]];
            if (count($keys) > $max) {
               // Remove 1st local
               $fileremove = $this->BASE_DIR . $oldData;
               if (file_exists($fileremove) and is_file($fileremove)) {
                  echo "Delete {$fileremove}\n";
                  @unlink($fileremove);
               }
               unset($data[$k]['local'][$keys[0]]);
            }
         }

         if ($remote == true) {
            if (is_array($v['dropbox'])) {
               $keys = array_keys($v['dropbox']);
               if (count($keys) > $max) {
                  $oldData = $v['dropbox'][$keys[0]];
                  if (!empty($oldData)) {
                     echo "Dropbox Remove Old File:";
                     $DBX = new Dropbox($this->config['dropbox']);
                     $r = $DBX->delete($oldData['path_lower']);
                     print_r($r);
                     echo "\n";
                  }
                  unset($data[$k]['dropbox'][$keys[0]]);
               }
            }
            if (is_array($v['gdrive'])) {
               $keys = array_keys($v['gdrive']);
               if (count($keys) > $max) {
                  $oldData = $v['gdrive'][$keys[0]];
                  $fileId = $oldData['file']['id'];
                  if (!empty($fileId)) {
                     $drive = new GoogleDriveClient($this->config['gdrive']);
                     echo "Remove file {$fileId} from Google Drive\n";
                     $drive->delete($fileId);
                  }
                  unset($data[$k]['gdrive'][$keys[0]]);
               }
            }
         }

         if (is_array($data[$k]['local'])) {
            $data[$k]['local'] = array_merge(
               array_filter($data[$k]['local'], fn($v, $k) => !is_numeric($k), ARRAY_FILTER_USE_BOTH),
               array_values(array_filter($data[$k]['local'], fn($v, $k) => is_numeric($k), ARRAY_FILTER_USE_BOTH))
            );
         }

         if (is_array($data[$k]['dropbox'])) {
            $data[$k]['dropbox'] = array_merge(
               array_filter($data[$k]['dropbox'], fn($v, $k) => !is_numeric($k), ARRAY_FILTER_USE_BOTH),
               array_values(array_filter($data[$k]['dropbox'], fn($v, $k) => is_numeric($k), ARRAY_FILTER_USE_BOTH))
            );
         }

         if (is_array($data[$k]['gdrive'])) {
            $data[$k]['gdrive'] = array_merge(
               array_filter($data[$k]['gdrive'], fn($v, $k) => !is_numeric($k), ARRAY_FILTER_USE_BOTH),
               array_values(array_filter($data[$k]['gdrive'], fn($v, $k) => is_numeric($k), ARRAY_FILTER_USE_BOTH))
            );
         }
      }
      file_put_contents($filelog,json_encode($data,JSON_PRETTY_PRINT));
   }

   function loadConfig($fileConfig) {
      if (!file_exists($fileConfig)) { return false; }
      $cfg = json_decode(file_get_contents($fileConfig),true) ?? [];
      
      if (!empty($cfg['removeOldFile'])) {
         $this->removeOldFile((int) $cfg['removeOldFile'],true);
      }
      
      if ($cfg['mysqldump_path']) {
         $this->setMysqlDumpPath($cfg['mysqldump_path']);
      }

      $this->skipAlways($cfg['always_skip'] ?? ['information_schema','performance_schema','mysql','test','sys']);
      if (!empty($cfg['save_dir'])) {
         $this->setDir($cfg['save_dir']);
      }

      if (!empty($cfg['daily'])) { $this->Daily($cfg['daily']); }
      if (!empty($cfg['monthly'])) { $this->Monthly($cfg['monthly'],$cfg['monthly_start'] ?? 1); }
      if (!empty($cfg['weekly'])) { $this->Weekly($cfg['weekly'],$cfg['weekly_start'] ?? 0); }
   }

   function sendTelegram($text) {
      if (empty($this->config['telegram'])) { return false; }
      if (empty($this->config['telegram']['chat_id'])) { return false; }
      $bot = new Telegram($this->config['telegram']);
      $r = $bot->send($this->config['telegram']['chat_id'],$text);
      print_r($r);
      return $r;
   }
}
?>