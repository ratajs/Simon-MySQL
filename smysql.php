<?php
  /**
   * Version for PHP 5.4 and later versions.
  **/
 class SmysqlException extends Exception {};
  set_exception_handler(function($e) {
    if($e instanceof SmysqlException) {
      trigger_error("<strong>Simon's MySQL error</strong> " . $e->getMessage());
    }
    else
      throw new Exception($e->getMessage());
  });
  class Smysql {
    protected $connect;
    protected $result;
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
      $this->connect = @new mysqli($host, $user, $password);
      if($this->connect->connect_errno || !$this->connect)
        throw new SmysqlException("(__construct): Can't connect to MySQL: " . $this->connect->connect_error);
      if(!empty($database)) {
        if(!$this->connect->select_db($database)) {
          if(str_replace("create[", NULL, $database)!=$database && end(str_split($databese))=="]") {
            $new = str_replace("]", NULL, str_replace("create[", NULL, $database));
            if(!$this->query("
              CREATE DATABASE $new
            ", "__construct"))
              throw new SmysqlException("(__construct): Can't create database " . $new);
          }
          else {
            throw new SmysqlException("(__construct): Can't select database MySQL");
            $this->connect->close();
          };
        };
      };
      $this->charset("utf8");
    }
    
    public function escape($string) {
      if(is_array($string)) {
        foreach($string as $k => $v) {
          $r[$k] = $this->escape($v);
        };
        return $r;
      };
      return $this->connect->real_escape_string($string);
    }
    
    public function reload() {
      $this->__construct();
    }
    
    public function query($query, $fnc = "Query") {
      if(empty($this->db) && !in_array($fnc, ["__construct", "changeDB", "dbList"]))
        throw new SmysqlException("(" . $fnc . "): No database selected");
      $this->result = $this->connect->query($query);
      if($this->connect->errno)
        throw new SmysqlException("(" . $fnc . "): Error in MySQL: " . $this->connect->error . " <strong>SQL command:</strong> " . $query);
      return $this->result;
    }
    
    public function queryf($q, $a) {
      foreach($a as $k => $v) {
        $q = str_replace("%" . $k, $this->escape($v), $q);
      };
      return $this->query($q, "Queryf");
    }
    
    public function dbList() {
      $result = $this->result;
      $this->query("SHOW DATABASES", "dbList");
      $r = [];
      while($f = $this->fetch()) {
        $r[] = $f->Database;
      };
      $this->result = $result;
      return $r;
    }
    
    public function tableList() {
      if(empty($this->db))
        throw new SmysqlException("(tableList): No database selected");
      $result = $this->result;
      $this->query("SHOW TABLES FROM $this->db", "tableList");
      $r = [];
      while($f = $this->fetchArray()) {
        $r[] = $f[0];
      };
      $this->result = $result;
      return $r;
    }
    
    public function result() {
      return $this->result;
    }
    
    public function charset($charset) {
      $this->connect->set_charset($charset);
      return $charset;
    }
    
    public function fetch($id = false) {
      if($id===false)
        return $this->result->fetch_object();
      return $id->fetch_object();
    }
    
    public function fetchArray($id = false) {
      if($id===false)
        return $this->result->fetch_array();
      return $id->fetch_array();
    }
    
    public function deleteDB($db, $close = false) {
      if($db==$this->db)
        throw new SmysqlException("(deleteDB): You can't delete current database");
      if($this->query("DROP DATABASE $db")) {
        return true;
      }
      else {
        throw new SmysqlException("(deleteDB): Can't delete database " . $db);
        return false;
      };
    }
    
    public function changeDB($newDB) {
      if($this->result instanceof mysqli_result)
        $this->result->free();
      $this->connect->close();
      $this->__construct($this->host, $this->user, $this->password, $newDB);
    }
    
    public function fetchAll($id = false) {
      $return = [];
      while($row = $this->fetch($id)) {
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
        case 1: $jt = "LEFT OUTER"; break;
        case 2: $jt = "RIGHT OUTER"; break;
        default: $jt = "FULL OUTER"; break;
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
          $colString.= "'" . $this->escape($value) . "'";
        };
        $colString.= ")";
      };
      $valueString = NULL;
      foreach($values as $key => $value) {
        if($key!=array_keys($values, array_values($values)[0])[0]) $valueString.= ", ";
        $valueString.= "'" . $this->escape($value) . "'";
      };
      $r = $this->query("
        INSERT INTO $table$colString VALUES ($valueString)
      ", "insert");
      return ($retId ? $this->connect->insert_id : $r);
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
        $string.= "`" . $key . "`='" . $this->escape($value) . "'";
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
    
    public function selectAll($table) {
      $r = $this->result;
      $this->select($table);
      $f = $this->fetchAll();
      $this->result = $r;
      return $f;
    }
    
    public function fetchWhere($table, $bool, $all = true) {
      $r = $this->result;
      $this->selectWhere($table, $bool, $all);
      $f = $this->fetch();
      $this->result = $r;
      return $f;
    }
    
    public function read($table, $bool = [], $all = true) {
      $r = $this->result;
      $f = new stdClass();
      $f->someKey = "nonfalse";
      if($bool===[]) {
        $f = $this->selectAll($table);
        $this->result = $r;  
      }
      elseif(!$this->exists($table, $bool, $all))
        return false;
      else {
        $this->selectWhere($table, $bool, $all);
        $f = $this->fetchAll();
      };
      if($f===new stdClass())
        return false;
      $this->result = $r;
      if(count($f)==1)
        return $f[0];
      return $f;
    }
    
    public function getDetails($table, $columnNm) {
      $r = $this->result;
      if(empty($this->db))
        throw new SmysqlException("(getDetails): No database selected");
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
        throw new SmysqlException("Simon's MySQL error: (getDetails): Error in MySQL: " . $this->connect->error);
      $this->result = $r;
      return $result->fetch_object();
    }
    
    public function createTable($table, $names, $types, $lenghts, $nulls, $primary = NULL, $uniques, $others = []) {
      $parameters = $this->getParameters($names, $types, $lengths, $nulls, $uniques, $others);
      $valueString = implode(",\n", $parameters);
      return $this->query("
        CREATE TABLE `$table` ($valueString)
        " . empty($primary) ? NULL : ", PRIMARY KEY ($primary)" . "
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
              $r[] = "$v $t $n" . (in_array($v, $uniques) ? NULL : " UNIQUE") . " $o";
            else
              $r[] = "$v $t($l) $n" . (in_array($v, $uniques) ? NULL : " UNIQUE") . " $o";
          };
          return $r;
        };
      };
      return false;
    }
    
    private function getBool($a, $and, $join = false) {
      if(!is_array($a))
        return $a;
      $r = NULL;
      foreach($a as $k => $v) {
        if(is_array($v)) {
          foreach($v as $k2 => $v2) {
            $col = false;
            if($v2[0]=="`" && end(str_split($v2))=="`")
              $col = true;
            $v3 = $this->escape($v2);
            $r.= "`" . $this->escape($k) . "`";
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
          $v = $this->escape($v);
          $r.= "`" . $this->escape($k) . "`";
          if(is_numeric($v)) {
            $r.= " = ";
            $v = (int) $v;
          }
          else
            $r.= " LIKE ";
          $r.= ($join || $col) ? "`$v`" : "'$v'";
          $r.= $and ? " AND " : " OR ";
        };
      };
      return rtrim($r, $and ? " AND " : " OR ");
    }
    
    public function __wakeup() {
      $this->__construct($this->host, $this->user, $this->password, $this->db);
    }
    
    public function __destruct() {
      if($this->result instanceof mysqli_result)
        $this->result->free();
      $this->connect->close();
    }
  };
  function Smysql($host, $user, $password, $db, &$object = "return") {
    if($object=="return")
      return new Smysql($host, $user, $password, $db);
    else
      $object = new Smysql($host, $user, $password, $db);
  };
?>
