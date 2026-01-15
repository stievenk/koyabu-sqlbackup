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
      if ($this->config['keepFile_count'] < 3) {
         $this->config['keepFile_count'] = 3;
      }
      // $this->createDir();
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
      $exec .= " {$Database} > {$fileoutput}.sql";
      
      echo $exec.PHP_EOL;
      exec($exec,$retr);
      echo implode("\n",$retr).PHP_EOL;
      return "{$fileoutput}.sql";
   }

   function run($file_prefix = '', $file_sufix = '' ,$debug = false) {
      $limit = $this->config['keepFile_count'] ?? 3;
      $dbCount = 0; $cloudSync = []; $rm = 0; $filesize=0; $dbs = [];
      foreach($this->DB_Backup as $Database) {
         if (in_array($Database, $this->skipDB)) {
            continue;
         }
         $savePath = rtrim($this->BASE_DIR,'/') .'/'. $Database .'/';
         if (!file_exists($savePath)) {
            mkdir($savePath,0777,true);
         }
         if (file_exists($savePath.'backup.json')) {
            $cfg = json_decode(file_get_contents($savePath.'backup.json'),true);
         } else {
            $cfg = [
               'created_at' => date("Y-m-d H:i:s")
            ];
         }
         $dbCount++;
         $dbs[] = $Database;
         $file = $this->dump($Database,$savePath.$file_prefix.$Database.$file_sufix);
         if ($this->config['zip']['compress'] == true) {
            if (!empty($this->config['zip']['exec'])) {
               exec($this->config['zip']['exec'].' '.$file);
               echo $res ? $res.PHP_EOL : '';
               $file = $file.'.bz2';
            }
         }
         $filesize += filesize($file);
         $cfg['filename'][] = basename($file);
         $cfg['last_update'] = date("Y-m-d H:i:s");
         $cfg['filename'] = array_unique($cfg['filename']);
         echo $savePath.PHP_EOL;
         file_put_contents($savePath.'backup.json',json_encode($cfg,JSON_PRETTY_PRINT));
         $rm += $this->removeFile($Database,$limit);
         $cs = $this->cloudSync($Database,$file);
         $cloudSync['dbx_sync'] += $cs['dbx_sync'];
         $cloudSync['dbx_remove'] += $cs['dbx_remove'];
         $cloudSync['gdrive_sync'] += $cs['gdrive_sync'];
         $cloudSync['gdrive_remove'] += $cs['gdrive_remove'];
      }

      return [
         'cloud' => $cloudSync,
         'db' => $dbCount,
         'local_remove' => $rm,
         'total_size' => round($filesize / 1024 / 1024, 2) . ' MB',
         'dbs' => $dbs
      ];
   }

   function removeFile($Database,$limit = 3) {
      $savePath = trim($this->BASE_DIR,'/') .'/'. $Database .'/';
      $rm = 0;
      if (!file_exists($savePath.'backup.json')) {
         return false;
      }
      $cfg = json_decode(file_get_contents($savePath.'backup.json'),true);
      if (count($cfg['filename']) > $limit) {
         $saveFile = [];
         for($i = count($cfg['filename']) - $limit; $i < count($cfg['filename']); $i++) {
            $saveFile[] = $cfg['filename'][$i];
         }
         for ($i = 0; $i < count($cfg['filename']) - $limit; $i++) {
            echo "REMOVE: ".$savePath . $cfg['filename'][$i].PHP_EOL;
            unlink($savePath . $cfg['filename'][$i]);
            $rm++;
         }
         print_r($saveFile);
         $cfg['filename'] = $saveFile;
      }
      $cfg['filename'] = array_unique($cfg['filename']);
      $cfg['last_update'] = date("Y-m-d H:i:s");
      file_put_contents($savePath.'backup.json',json_encode($cfg,JSON_PRETTY_PRINT));
      return $rm;
   }

   public function cloudSync($Database,$file) {
      $limit = $this->config['keepFile_count'] ?? 3;
      $savePath = trim($this->BASE_DIR,'/') .'/'. $Database .'/';
      $cfg = json_decode(file_get_contents($savePath.'backup.json'),true);

      $data = [];

      if (!file_exists($savePath.'backup.json')) {
         return false;
      }
      if ($this->config['dropbox']['sync'] === true) {
         $res = $this->DropboxSync($file);
         if (!empty($res) and is_array($res)) {
            print_r($res);
            $cfg['dropbox']['last_update'] = date("Y-m-d H:i:s");
            $cfg['dropbox']['files'][] = $res;
            $data['dbx_sync']++;
         }
      }
      // Remove Old File Dropbox
      if (count($cfg['dropbox']['files']) > $limit) {
         for($i = 0; $i < count($cfg['dropbox']['files']) - $limit; $i++) {
            $r = $this->DropboxDelete($cfg['dropbox']['files'][$i]['path_lower']);
            print_r($r);
            echo "REMOVE: ".$cfg['dropbox']['files'][$i]['path_lower'].PHP_EOL;
            $data['dbx_remove']++;
         }
         $cfg['dropbox']['files'] = array_slice($cfg['dropbox']['files'],-$limit);
      }

      if ($this->config['gdrive']['sync'] === true) {
         $res = $this->GoogleDriveSync($file);
         $cfg['gdrive']['last_update'] = date("Y-m-d H:i:s");
         $cfg['gdrive']['files'][] = $res;
         $data['gdrive_sync']++;
      }

      // Remove Old File Google Drive
      if (count($cfg['gdrive']['files']) > $limit) {
         for($i = 0; $i < count($cfg['gdrive']['files']) - $limit; $i++) {
            $r = $this->GoogleDriveDelete($cfg['gdrive']['files'][$i]['id']);
            print_r($r);
            echo "REMOVE: ".$cfg['gdrive']['files'][$i]['name'].PHP_EOL;
            $data['gdrive_remove']++;
         }
         $cfg['gdrive']['files'] = array_slice($cfg['gdrive']['files'],-$limit);
      }

      file_put_contents($savePath.'backup.json',json_encode($cfg,JSON_PRETTY_PRINT));
      return $data;
   }

   public function DropboxSync($filename) {
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
      if($res = $DBX->upload($filename,$this->config['dropbox']['home_dir'])) {
         return $res;
      } else {
         echo "DBX Error:". $DBX->error.PHP_EOL;
      }
   }

   public function DropboxDelete($filename) {
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
      echo "Dropbox DELETE\n";
      $DBX = new Dropbox($this->config['dropbox']);
      if($res = $DBX->delete($filename)) {
         return $res;
      } else {
         echo "DBX Error:". $DBX->error.PHP_EOL;
      }
   }

   public function GoogleDriveSync($filename) {
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
      file_put_contents($gDriveFile,json_encode($cfg,JSON_PRETTY_PRINT));
      $res = $drive->uploadFile($filename, basename($filename),$cfg['path']['id']);
      if (!empty($res)) {
         $drive->createShareLink($res->id, 'reader', 'anyone');
         $info = $drive->fileInfo($res->id);
         if ($info) {
            return $info;
         } else {
            return [
               'id' => $res->id,
               'name' => basename($filename)
            ];
         }
      }
   }

   public function GoogleDriveDelete($id) {
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
      echo "Google Drive: ";
      $drive = new GoogleDriveClient($this->config['gdrive']);
      echo "Remove file {$id} from Google Drive\n";
      $r = $drive->delete($id);
      return $r;
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