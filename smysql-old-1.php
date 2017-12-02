<?php
  /**
   * Version for PHP 5.3 and earlier versions.
  **/
  class Smysql {
    protected $connect;
    protected $result;
    protected $host;
    protected $user;
    protected $password;
    protected $db;
    protected $fncs = [];
    public function __construct($host = NULL, $user = NULL, $password = NULL, $database = NULL) {
      if(empty($host) && empty($user) && empty($password) && empty($database)) {
        if(!empty($this->host)) {
          $host = $this->host;
          $user = $this->user;
          $password = $this->password;
          $database = $this->db;
        }
        else {
          $host = ini_get("mysql.default_host");
          $user = ini_get("mysql.default_user");
          $password = ini_get("mysql.default_pw");
        };
      };
      $this->host = $host;
      $this->user = $user;
      $this->password = $password;
      $this->db = $database;
      $this->connect = @mysql_connect($host, $user, $password, true);
      if(!$this->connect)
        trigger_error("Can't connect to MySQL");
      if(!@mysql_select_db($database, $this->connect)) {
        if(str_replace("create[", NULL, $database)!=$database && end(str_split($databese))=="]") {
          $new = str_replace("]", NULL, str_replace("create[", NULL, $database));
          if(!mysql_query("
            CREATE DATABASE $new
          ", $this->connect))
          trigger_error("Can't create database " . $new);
          mysql_select_db($new, $this->connect);
        }
        else {
          trigger_error("Can't select database MySQL");
          mysql_close($this->connect);
        };
      };
      $this->charset("utf8");
    }
    
    public function escape($string) {
      if(is_array($string)) {
        $r = array();
        foreach($string as $k => $v) {
          $r[$k] = $this->escape($v);
        };
        return $r;
      };
      return mysql_real_escape_string($string, $this->connect);
    }
    
    public function reload() {
      $this->__construct();
    }
        
    public function query($query, $debug = false) {
      if(empty($this->db) && !in_array($fnc, new array("__construct", "changeDB")))
        trigger_error("Simon's MySQL error <strong>(" . $fnc . "):</strong> No database selected");
      $this->result = mysql_query($query, $this->connect);
      if(!$this->result)
        trigger_error("Simon's MySQL error <strong>(" . $fnc . "):</strong> Error in MySQL: " . mysql_error());
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
      $ld = mysql_list_dbs($this->connect);
      $r = new array();
      while($row = mysql_fetch_object($ld)) {
        array_push($r, $row->Database);
      };
      $this->result = $result;
      return $r;
    }
    
    public function tableList() {
      if(empty($this->db))
        trigger_error("Simon's MySQL error <strong>(tableList):</strong> No database selected");
      $result = $this->result;
      $this->query("SHOW TABLES FROM $this->db", "tableList");
      $r = new array();
      while($f = $this->fetchArray()) {
        array_push($r, $f[0]);
      };
      $this->result = $result;
      return $r;
    }
    
    public function result() {
      return $this->result;
    }
    
    public function charset($charset) {
      mysql_set_charset($charset);
    }
    
    public function fetch($id = false) {
      if($id===false)
        return mysql_fetch_object($this->returnReturn());
      return mysql_fetch_object($id);
    }
    
    public function fetchArray($id = false) {
      if($id===false)
        return mysql_fetch_array($this->returnReturn());
      return mysql_fetch_array($id);
    }
    
    public function deleteDB($db, $close = false) {
      if($db==$this->db)
        trigger_error("You can't delete current database, you must change database before deleting this database!");
      if(!$this->query("
        DROP DATABASE $db
      ")) {
        trigger_error("Can't delete database " . $db);
        return false;
      }
      else {
        if(!$newDB) {
          mysql_close($this->connect);
          return true;
        };
        return true;
      };
    }
    
    public function changeDB($newDB) {
      $this->__destruct();
      $this->__construct($this->host, $this->user, $this->password);
    }
    
    public function fetchAll($id = false) {
      $return = array();
      while($row = $this->fetch($id)) {
        array_push($return, $row);
      };
      return $return;
    }
    
    public function select($table, $order = NULL, $orderType = "ASC", $cols = array("*")) {
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY '" . $order . "' $orderType";
      return $this->query("
        SELECT $colsValue FROM $table $order
      ");
    }
    
    public function selectWhere($table, $array, $all = true, $order = NULL, $orderType = "ASC", $cols = array("*")) {
      $bool = $this->getBool($array, $all);
      $colsValue = implode(", ", $cols);
      if($order!=NULL)
        $order = "ORDER BY '" . $order . "' $orderType";
      return $this->query("
        SELECT $colsValue FROM $table WHERE $bool $order
      ");
    }
    
    public function selectJoin($table, $join, $array, $all = true, $joinType = 0, $order = NULL, $orderType = "ASC", $cols = array("*")) {
      switch($joinType) {
        case 0: $jt = "INNER"; break;
        case 1: $jt = "LEFT OUTER"; break;
        case 2: $jt = "RIGHT OUTER"; break;
        default: $jt = "FULL OUTER"; break;
      };
      $bool = $this->getBool($array, $all);
      $colsValue = implode(", ", $cols);
      if($order!=NULL)
        $order = "ORDER BY '" . $order . "' $orderType";
      return $this->query("
        SELECT $colsValue
        FROM $table
        $jt JOIN $join ON $bool
        $order
      ");
    }
    
    public function exists($table, $array, $all = true) {
      $bool = $this->getBool($array, $all);
      $this->selectWhere($table, $bool);
      $noFetch = !$this->fetch();
      return !$noFetch;
    }
    
    public function truncate($table) {
      return $this->query("
        TRUNCATE $table
      ");
    }
    
    public function insert($table, $values, $cols = array(), $retId = false) {
      if($cols==[NULL])
        $colString = NULL;
      else {
        $colString = " (";
        foreach($cols as $key => $value) {
          if($key!=0) $valueString.= ", ";
          $colString.= "'" . $this->escape($value) . "'";
        };
        $colString.= ")";
      };
      $valueString = NULL;
      foreach($values as $key => $value) {
        $av = array_values($values);
        $ak = array_keys($values, $av[0]);
        if($key!=$ak[0]) $valueString.= ", ";
        $valueString.= "'" . $this->escape($value) . "'";
      };
      $r = $this->query("
        INSERT INTO $table$colString VALUES ($valueString)
      ");
      return ($retId ? mysql_insert_id($this->connect) : $r);      
    }
    
    public function delete($table, $bool) {
      return $this->query("
        DELETE FROM $table WHERE $bool
      ");
    }
    
    public function update($table, $arr, $array, $all = true) {
      $bool = $this->getBool($arr, $all);
      $string = NULL;
      foreach($array as $key => $value) {
        if($string!=NULL) 
          $string.= ", ";
        $string.= $key . "='" . $this->escape($value, $this->connect) . "'";
      };
      return $this->query("
        UPDATE $table SET $string WHERE $bool
      ");
    }
    
    public function add($table, $name, $type, $lenth, $null, $where, $key, $data = NULL) {
      if(!empty($data))
        $data = " " . $data;
      $type = strtoupper($type);
      $where = strtoupper($where);
      return $this->query("
        ALTER TABLE '$table' ADD '$name' $type($lenth) " . ($null ? "NULL" : "NOT NULL") . "$data $where '$key'
      ");
    }
    
    public function drop($table, $name) {
      return $this->query("
        ALTER TABLE '$table' DROP '$name'
      ");
    }
    
    public function change($table, $name, $newname, $type, $lenth, $null, $data = NULL) {
      if(!empty($data))
        $data = " " . $data;
      $type = strtoupper($type);
      $where = strtoupper($where);
      return $this->query("
        ALTER TABLE '$table' CHANGE '$name' $newname $type($lenth) " . ($null ? "NULL" : "NOT NULL") . $data
      );
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
    
    public function read($table, $bool = array(), $all = true) {
      $r = $this->result;
      if($bool===array() {
      $this->result = $r;
        return $this->selectAll($table);
      };
      $this->selectWhere($table, $bool, $all);
      $f = $this->fetchAll();
      $this->result = $r;
      if($f===array())
        return false;
      if(count($f)==1)
        return $f[0];
      return $f;
    }
    
    public function getDetails($table, $columnNm) {
      $r = $this->result;
      if(empty($this->db))
        trigger_error("Simon's MySQL error <strong>(getDetails):</strong> No database selected");
      $result = $this->result;
      $this->query("
        SHOW COLUMNS FROM $table
      ");
      $column = mysql_field_name(mysql_query("SELECT * FROM $table"), $columnNm);
      $columnType = @mysql_field_type(mysql_query("SELECT * FROM $table"), $columnNm);
      $mfa = @mysql_fetch_array($this->result);
      $columnRealType = $mfa[$columnNm+1];
      $result = @mysql_query("
        SELECT '$column' AS 'name', MIN($column) AS 'firstValue', MAX($column) AS 'lastValue', COUNT($column) AS 'count', SUM($column) AS 'suma', '$columnType' AS 'dataType', '$columnRealType' AS 'extras' FROM $table
      ");
      if(!$result)
        trigger_error("Simon's MySQL error <strong>(getDetails):</strong> Error in MySQL: " . mysql_error());
      $this->result = $r;
      return mysql_fetch_object($result);
    }
    
    public function createTable($table, $names, $types, $lenghts, $nulls, $primary = NULL, $uniques = [], $others = []) {
      $parameters = $this->getParameters($names, $types, $lengths, $nulls, $uniques, $others);
      $valueString = implode(",\n", $parameters);
      return $this->query("
        CREATE TABLE $table ($valueString)
      " . empty($primary) ? NULL : ", PRIMARY KEY ($primary)");
    }
    
    public function renameTable($table, $newname) {
      return $this->query("
        ALTER TABLE $table rename to $newname
      ");
    }
        
    public function deleteTable($table) {
      return $this->query("
        DROP TABLE $table
      ");
    }
    
    private function getParameters($names, $types, $lengths, $nulls, $uniques, $others = array()) {
      if(count($names)==count($types) && count($names)==count($nulls)) {
        if(count($names)==count($others)) {
          $r = [];
          foreach($names as $k => $v) {
            $t = $types[$k];
            $l = $lenghts[$k];
            $n = $nulls[$k] ? "NULL" : "NOT NULL";
            $o = $others[$k];
            if(empty($l))
              array_push($r, "$v $t $n" . (in_array($v, $uniques) ? NULL : " UNIQUE") . " $o");
            else
              array_push($r, "$v $t($l) $n" . (in_array($v, $uniques) ? NULL : " UNIQUE") . " $o");
          };
          return $r;
        }
        elseif($others==array()) {
          $r = array();
          foreach($names as $k => $v) {
            $t = $types[$k];
            $l = $lenghts[$k];
            $n = $nulls[$k] ? "NULL" : "NOT NULL";
            if(empty($l))
              array_push($r, "$v $t $n");
            else
              array_push($r, "$v $t($v) $n)";
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
            $r = rtrim($r, " AND ");
            return rtrim($r, " OR ");
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
          $r = rtrim($r, " AND ");
          return rtrim($r, " OR ");
        }
      }
      else
        return $a;
    }
        
    public function __destruct() {
      @mysql_free_result($this->result);
      mysql_close($this->connect);
    }
  };
  function Smysql($host, $user, $password, $db, &$object = "return") {
    if($object=="return")
      return new Smysql($host, $user, $password, $db);
    else
      $object = new Smysql($host, $user, $password, $db);
  };
?>
