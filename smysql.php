<?php
  /**
   * Version for PHP 5.4 and later versions.
  **/
  class Smysql {
    private $connect;
    private $result;
    protected $host;
    protected $user;
    protected $password;
    protected $db;
    public function __construct($host = NULL, $user = NULL, $password = NULL, $database = NULL) {
      if(empty($host) && empty($user) && empty($password) && empty($database)) {
        if(!empty($this->host)) {
          $host = $this->host;
          $user = $this->user;
          $password = $this->password;
          $database = $this->db;
        }
        else {
          $host = ini_get("mysqli.default_host");
          $user = ini_get("mysqli.default_user");
          $password = ini_get("mysqli.default_pw");
        };
      };
      $this->host = $host;
      $this->user = $user;
      $this->password = $password;
      $this->db = $database;
      $this->connect = new mysqli($host, $user, $password);
      if($this->connect->connect_errno || !$this->connect)
        die("Simon's MySQL error <strong>(__construct):</strong> Can't connect to MySQL: " . $this->connect->connect_error);
      if(!empty($database)) {
        if(!$this->connect->select_db($database)) {
          if(str_replace("create[", NULL, $database)!=$database && end(str_split($databese))=="]") {
            $new = str_replace("]", NULL, str_replace("create[", NULL, $database));
            if(!$this->query("
              CREATE DATABASE $new
            ", "__construct"))
            die("Simon's MySQL error <strong>(__construct):</strong> Can't create database " . $new);
            $this->connect->close();
          }
          else {
            die("Simon's MySQL error <strong>(__construct):</strong> Can't select database MySQL");
            $this->connect->close();
          };
        };
      };
      $this->charset("utf8");
    }
    
    public function escapeString($string) {
      if(is_array($string)) {
        foreach($string as $k => $v) {
          $r[$k] = $this->escapeString($v);
        };
        return $r;
      };
      return $this->connect->real_escape_string($string);
    }
    
    public function reload() {
      $this->connect = new mysqli($this->host, $this->user, $this->password, true);
    }
    
    public function query($query, $fnc = "Query") {
      if(empty($this->db))
        die("Simon's MySQL error <strong>(" . $fnc . "):</strong> No database selected");
      $this->result = $this->connect->query($query);
      if($this->connect->errno)
        die("Simon's MySQL error <strong>(" . $fnc . "):</strong> Error in MySQL: " . $this->connect->error);
      return $this->result;
    }
    
    public function result() {
      return $this->result;
    }
    
    public function charset($charset) {
      $this->connect->set_charset($charset);
      return $charset;
    }
    
    public function fetch() {
      return $this->result->fetch_object();
    }
    
    public function fetchArray() {
      return $this->result->fetch_array();
    }
    
    public function deleteDB($db, $close = false) {
      if($db==$this->db)
        die("Simon's MySQL error (deleteDB): You can't delete current database");
      if($this->query("DROP DATABASE $db")) {
        return true;
      }
      else {
        die("Simon's MySQL error (deleteDB): Can't delete database " . $db);
        return false;
      };
    }
    
    public function changeDB($newDB) {
      $this->__destruct();
      $this->__construct($this->host, $this->user, $this->password, $newDB);
    }
    
    public function fetchAll() {
      $return = [];
      while($row = $this->fetch()) {
        $return[] = $row;
      };
      return $return;
    }
    
    public function select($table, $order = NULL, $orderType = "ASC", $cols = ["*"]) {
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY '" . $order . "' $orderType";
      return $this->query("
        SELECT $colsValue FROM `$table` $order
      ", "select");
    }
    
    public function selectWhere($table, $array, $all = true, $order = NULL, $orderType = "ASC", $cols = ["*"], $exists = false) {
      $bool = $this->getBool($array, $all);
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY '" . $order . "' $orderType";
      return $this->query("
        SELECT $colsValue FROM `$table` WHERE $bool $order
      ", $exists ? "exists" : "selectWhere");
    }
    
    public function selectJoin($table, $join, $array, $all = true, $joinType = 0, $order = NULL, $orderType = "ASC", $cols = ["*"]) {
      switch($joinType) {
        case 0: $jt = "INNER"; break;
        case 1: $jt = "LEFT"; break;
        default: $jt = "RIGHT"; break;
      };
      $bool = $this->getBool($array, $all, true);
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY '" . $order . "' $orderType";
      return $this->query("
        SELECT $colsValue
        FROM `$table`
        $jt JOIN $join ON $bool
        $order
      ", "selectJoin");
    }
    
    public function exists($table, $array, $all = true) {
      $this->selectWhere($table, $array, $all, NULL, "ASC", ["*"], true);
      $noFetch = !$this->fetch();
      return !$noFetch;
    }
    
    public function truncate($table) {
      return $this->query("
        TRUNCATE `$table`
      ", "truncate");
    }
    
    public function insert($table, $values, $cols = [NULL], $retId = false) {
      if($cols==[NULL])
        $colString = NULL;
      else {
        $colString = " (";
        foreach($cols as $key => $value) {
          if($key!=0) $colString.= ", ";
          $colString.= "'" . $this->escapeString($value) . "'";
        };
        $colString.= ")";
      };
      $valueString = NULL;
      foreach($values as $key => $value) {
        if($key!=array_keys($values, array_values($values)[0])[0]) $valueString.= ", ";
        $valueString.= "'" . $this->escapeString($value) . "'";
      };
      $r = $this->query("
        INSERT INTO $table$colString VALUES ($valueString)
      ", "insert");
      return ($retId ? $this->connect->insert_id() : $r);
    }
    
    public function delete($table, $array, $all = true) {
      $bool = $this->getBool($array, $all);
      return $this->query("
        DELETE FROM `$table` WHERE $bool
      ", "delete");
    }
    
    public function update($table, $arr, $array, $all = true) {
      $bool = $this->getBool($arr, $all);
      $string = NULL;
      foreach($array as $key => $value) {
        if($string!=NULL) 
          $string.= ", ";
        $string.= $key . "='" . $this->escapeString($value) . "'";
      };
      return $this->query("
        UPDATE `$table` SET $string WHERE $bool
      ", "update");
    }
    
    public function add($table, $name, $type, $lenth, $null, $where, $key, $data = NULL) {
      if(!empty($data))
        $data = " " . $data;
      $type = strtoupper($type);
      $where = strtoupper($where);
      return $this->query("
        ALTER TABLE `$table` ADD '$name' $type($lenth) " . ($null ? "NULL" : "NOT NULL") . "$data $where '$key'
      ", "drop");
    }
    
    public function drop($table, $name) {
      return $this->query("
        ALTER TABLE `$table` DROP '$name'
      ", "drop");
    }
    
    public function change($table, $name, $newname, $type, $lenth, $null, $data = NULL) {
      if(!empty($data))
        $data = " " . $data;
      $type = strtoupper($type);
      $where = strtoupper($where);
      return $this->query("
        ALTER TABLE `$table` CHANGE '$name' $newname $type($lenth) " . ($null ? "NULL" : "NOT NULL") . $data
      , "change");
    }
    
    public function getDetails($table, $columnNm) {
      if(empty($this->db))
        die("Simon's MySQL error <strong>(getDetails):</strong> No database selected");
      $result = $this->result;
      $this->query("
        SHOW COLUMNS FROM `$table`
      ");
      $column = $this->connect->query("SELECT $columnNm FROM `$table`")->fetch_field()->name;
      $columnType = $this->connect->query("SELECT $columnNm FROM $table")->fetch_field()->type;
      $columnRealType = $this->fetchArray()[$columnNm+1];
      $result = @$this->connect->query("
        SELECT $column AS 'name', MIN($column) AS 'firstValue', MAX($column) AS 'lastValue', COUNT($column) AS 'count', SUM($column) AS 'suma', '$columnType' AS 'dataType', '$columnRealType' AS 'extras' FROM $table
      ");
      if(!$result)
        die("Simon's MySQL error: <strong>(getDetails):</strong> Error in MySQL: " . $this->connect->error);
      return $result->fetch_object();
    }
    
    public function createTable($table, $names, $types, $lenghts, $nulls, $others = []) {
      $parameters = $this->getParameters($names, $types, $lengths, $nulls, $others);
      $valueString = implode(",\n", $parameters);
      return $this->query("
        CREATE TABLE `$table` ($valueString)
      ");
    }
    
    public function renameTable($table, $newname) {
      return $this->query("
        ALTER TABLE `$table` RENAME TO $newname
      ", "renameTable");
    }
        
    public function deleteTable($table) {
      return $this->query("
        DROP TABLE `$table`
      ", "deleteTable");
    }
    
    private function getParameters($names, $types, $lengths, $nulls, $others = []) {
      if(count($names)==count($types) && count($names)==count($nulls)) {
        if(count($names)==count($others)) {
          foreach($names as $k => $v) {
            $t = $types[$k];
            $l = $lenghts[$k];
            $n = $nulls[$k] ? "NULL" : "NOT NULL";
            $o = $others[$k];
            if(empty($l))
              $r[] = "$v $t $n $o";
            else
              $r[] = "$v $t($v) $n $o";
          };
          return $r;
        }
        elseif($others==[]) {
          foreach($names as $k => $v) {
            $t = $types[$k];
            $l = $lenghts[$k];
            $n = $nulls[$k] ? "NULL" : "NOT NULL";
            if(empty($l))
              $r[] = "$v $t $n";
            else
              $r[] = "$v $t($v) $n";
          };
          return $r;
        };
      };
      return false;
    }
    
    private function getBool($a, $and, $join = false) {
      if(is_array($a)) {
        $r = NULL;
        foreach($a as $k => $v) {
          if(is_array($v)) {
            foreach($v as $k2 => $v2) {
              if($v2[0]=="`" && end(str_split($v2))=="`")
                $col = true;
              $v3 = $this->escapeString($v2);
              $r.= "`" . $this->escapeString($k) . "`";
              if(is_numeric($v3)) {
                $r.= " = ";
                $v3 = intval($v3);
              }
              else
                $r.= " LIKE ";
              $r.= ($join || $col) ? "`$v3`" : "'$v3'";
              $r.= $and ? " AND " : " OR ";
            };
            return rtrim($r, $and ? " AND " : " OR ");
          }
          else {
            $col = false;
            if($v[0]=="`" && end(str_split($v))=="`")
              $col = true;
            $v = $this->escapeString($v);
            $r.= "`" . $this->escapeString($k) . "`";
            if(is_numeric($v)) {
              $r.= " = ";
              $v = (int) $v;
            }
            else
              $r.= " LIKE ";
            $r.= ($join || $col) ? "`$v`" : "'$v'";
            $r.= $and ? " AND " : " OR ";
          };
          return rtrim($r, $and ? " AND " : " OR ");
        }
      }
      else
        return $a;
    }
    
    public function __wakeup() {
      $this->__construct($this->host, $this->user, $this->password, $this->db);
    }
    
    public function __destruct() {
      @$this->result->free();
      @$this->connect->close();
    }
  };
  function Smysql($host, $user, $password, $db, &$object = "return") {
    if($object=="return")
      return new Smysql($host, $user, $password, $db);
    else
      $object = new Smysql($host, $user, $password, $db);
  };
?>
