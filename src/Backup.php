<?php
namespace Koyabu\mysqlbackup;
use Koyabu\Dropbox\Init as DBX;

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
      if (count($databases) > 0) {
         $this->DB_Backup = array_merge($this->DB_Backup,$databases);
         $this->DB_Backup = array_unique($this->DB_Backup);
         sort($this->DB_Backup);
      } else {
         $this->DB_Backup = $this->Databases;
      }
   }

   function Weekly($databases = [], $weekday = 1) {
      if (date("w") == $weekday) {
         $this->DB_Backup = array_merge($this->DB_Backup,$databases);
         $this->DB_Backup = array_unique($this->DB_Backup);
         sort($this->DB_Backup);
      }
   }

   function Monthly($databases = [], $montlhyday = 1) {
      if (date("d") == $montlhyday) {
         $this->DB_Backup = array_merge($this->DB_Backup,$databases);
         $this->DB_Backup = array_unique($this->DB_Backup);
         sort($this->DB_Backup);
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

   function run($file_prefix = '', $file_sufix ,$debug = false) {
      for ($i = 0; $i < count($this->DB_Backup); $i++) {
         if (in_array($this->DB_Backup[$i], $this->skipDB)) {
            continue;
         }
         $file = $this->dump($this->DB_Backup[$i],$file_prefix.$this->DB_Backup[$i].$file_sufix,$debug);
         if ($this->config['dropbox']['sync'] == true and $this->config['dropbox']['refresh_token']) {
            echo "Dropbox Backup Sync {$file}\n";
            $r = $this->dbx_Sync($file);
            if ($debug == true) {
               print_r($r);
            }
         }
      }
   }

   function dbx_Sync($filename) {
      $DBX = new DBX();
      return $DBX->upload($filename,'overwrite',$this->config['dropbox']['home_dir']);
   }
}
?>