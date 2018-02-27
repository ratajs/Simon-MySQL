<?php
  class SmysqlException extends Exception {
    function __construct($e, $code = 0, Exception $previous = NULL) {
      $this->message = "<strong>Simon's MySQL error</strong> " . $e . " in <strong>" . end(parent::getTrace())['file'] . "</strong> on line <strong>" . end(parent::getTrace())['line'] . "</strong>";
    }
    function __toString() {
      trigger_error($this->message, E_USER_ERROR);
      return $this->message;
    }
  };
  class Smysql {
    protected $connect;
    protected $result;
    protected $host;
    protected $user;
    protected $password;
    protected $db;
    protected $fncs = [];
    const ORDER_ASC = 1;
    const ORDER_DESC = 2;
    const JOIN_INNER = 4;
    const JOIN_LEFT = 8;
    const JOIN_RIGHT = 16;
    const JOIN_FULL = 32;
    const INSERT_RETURN_ID = 64;
    const QUERY_ALL = 128;
    const FETCH_OBJECT = 256;
    const FETCH_ARRAY = 512;
    const FETCH_ALL = 1024;
    const ALWAYS_ARRAY = 2048;
    public function __construct($host = NULL, $user = NULL, $password = NULL, $database = NULL) {
      if(empty($host) && empty($user) && empty($password) && empty($database)) {
        if(!empty($this->host)) {
          $host = $this->host;
          $user = $this->user;
          $password = $this->password;
          $database = $this->db;
        };
        if(empty($host) || empty($user) || empty($password)) {
          throw new SmysqlException("(__construct): Host, user and password parameters are required");
        };
      };
      $this->host = $host;
      $this->user = $user;
      $this->password = $password;
      $this->db = $database;
      if(str_replace("create[", NULL, $database)!=$database && end(str_split($databese))=="]") {
        $new = str_replace("]", NULL, str_replace("create[", NULL, $database));
        $this->connect = @new PDO("mysql:host=" . $host, $user, $password);
        if(!$this->query("
          CREATE DATABASE $new
          ", "__construct"))
          throw new SmysqlException("(__construct): Can't create database " . $new);
        $this->connect = NULL;
      };
      try {
        $this->connect = @new PDO("mysql:" . (empty($database) ? NULL : "dbname=" . $database . ";") . "host=" . $host . ";charset=utf8", $user, $password);
      } catch(PDOException $e) {
        throw new SmysqlException("(__construct): Can't connect to MySQL: " . $e->getMessage());
      }
      if($this->connect->errorCode() && $this->connect) {
        throw new SmysqlException("(__construct): Can't select database MySQL (" . $this->connect->errorInfo()[2] . ")");
        $this->connect->close();
      };
    }

    public function escape($string) {
      if(is_array($string)) {
        foreach($string as $k => $v) {
          $r[$k] = $this->escape($v);
        };
        return $r;
      };
      $quote = $this->connect->quote($string);
      $quoteA = str_split($quote);
      unset($quoteA[0]);
      unset($quoteA[count($quoteA)]);
      $quote = NULL;
      foreach($quoteA as $k => $v) {
        $quote.= $v;
      };
      return $quote;
    }

    public function reload() {
      $this->__construct();
    }

    public function query($query, $fnc = "Query") {
      if(empty($this->db) && !in_array($fnc, ["__construct", "changeDB", "dbList"]))
        throw new SmysqlException("(" . $fnc . "): No database selected");
      $this->result = $this->connect->query($query);
      if(!$this->result)
        throw new SmysqlException("(" . $fnc . "): Error in MySQL: " . $this->connect->errorInfo()[2] . " <strong>SQL command:</strong> " . $query);
      return $this->result;
    }

    public function queryf($q, $a, $fnc = "Queryf") {
      foreach($a as $k => $v) {
        $q = str_replace("%" . $k, $this->escape($v), $q);
      };
      return $this->query($q, $fnc);
    }

    public function __set($name, $query) {
      $this->fncs[$name] = $query;
    }

    public function __get($name) {
      return $this->fncs[$name];
    }

    public function __call($name, $params) {
      if(isset($params[0]) && is_array($params[0]))
        $this->execFnc($name, $params[0]);
      else
        $this->execFnc($name, $params);
    }

    public function __isset($name) {
      return isset($this->fncs[$name]);
    }

    public function __unset($name) {
      if(isset($this->fncs[$name]))
        unset($this->fncs[$name]);
      return true;
    }

    public function setFnc($name, $query) {
      $this->fncs[$name] = $query;
    }

    public function execFnc($name, $params = []) {
      if(isset($this->fncs[$name]))
        $this->queryf($this->fncs[$name], $params, $name);
      else
        throw new SmysqlException("(" . $name . "): This function isn't defined");
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
      while($f = $this->fetch(FETCH_ARRAY)) {
        $r[] = $f[0];
      };
      $this->result = $result;
      return $r;
    }

    public function result() {
      return $this->result;
    }

    public function charset($charset) {
      $this->connect->exec("SET NAMES " . $charset);
      return $charset;
    }

    public function fetch($flags = 256, $id = false) {
      if($id===false)
        $id = $this->result;
      if(boolval($flags & self::FETCH_OBJECT))
        $return =  $id->fetchObject();
      elseif(boolval($flags & self::FETCH_ARRAY))
        $return =  $id->fetch();
      elseif(boolval($flags & self::FETCH_ALL)) {
        $return = [];
        while($row = $this->fetch(self::FETCH_OBJECT, $id)) {
          $return[] = $row;
        };
      };
      return $return;
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
      if($this->result instanceof PDOStatement)
        $this->result->free();
      $this->connect->close();
      $this->__construct($this->host, $this->user, $this->password, $newDB);
    }

    public function select($table, $order = NULL, $cols = ["*"], $flags = 129) {
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY '" . $order . "'" . (boolval($orderType & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue FROM `$table` $order
      ", "select");
    }

    public function selectWhere($table, $array, $order = NULL, $cols = ["*"], $flags = 129, $name = "selectWhere") {
      $all = boolval($flags & self::QUERY_ALL);
      $bool = $this->getBool($array, $all);
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY '" . $order . "'" . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue FROM `$table` WHERE $bool $order
      ", $name);
    }

    public function selectJoin($table, $join, $array, $order = NULL, $cols = ["*"], $flags = 133) {
      $all = boolval($flags & self::QUERY_ALL);
      switch(true) {
        case boolval($joinType & self::JOIN_INNER): $jt = "INNER"; break;
        case boolval($joinType & self::JOIN_LEFT): $jt = "LEFT OUTER"; break;
        case boolval($joinType & self::JOIN_RIGHT): $jt = "RIGHT OUTER"; break;
        case boolval($joinType & self::JOIN_FULL): $jt = "FULL OUTER"; break;
        default: $jt = "INNER";
      };
      $bool = $this->getBool($array, $all, true);
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY '" . $order . "' " . (boolval($orderType & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue
        FROM `$table`
        $jt JOIN $join ON $bool
        $order
      ", "selectJoin");
    }

    public function exists($table, $array, $flags = 129, $name = "exists") {
      $all = boolval($flags & self::QUERY_ALL);
      $this->selectWhere($table, $array, NULL, ["*"], $flags, $name);
      $noFetch = !$this->fetch();
      return !$noFetch;
    }

    public function truncate($table) {
      return $this->query("
        TRUNCATE `$table`
      ", "truncate");
    }

    public function insert($table, $values, $cols = [NULL], $flags = 0) {
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
      return (boolval($flags & 64) ? $this->connect->insert_id : $r);
    }

    public function delete($table, $array, $flags = 128) {
      $all = boolval($flags & self::QUERY_ALL);
      $bool = $this->getBool($array, $all);
      return $this->query("
        DELETE FROM `$table` WHERE $bool
      ", "delete");
    }

    public function update($table, $arr, $array, $flags = 128) {
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
      $f = $this->fetch(self::FETCH_ALL);
      $this->result = $r;
      return $f;
    }

    public function fetchWhere($table, $bool, $flags = 129) {
      $all = boolval($flags & self::QUERY_ALL);
      $r = $this->result;
      $this->selectWhere($table, $bool, $flags);
      $f = $this->fetch();
      $this->result = $r;
      return $f;
    }

    public function read($table, $bool = [], $flags = 129) {
      $all = boolval($flags & self::QUERY_ALL);
      $r = $this->result;
      $f = new stdClass();
      $f->someKey = "nonfalse";
      if($bool===[]) {
        $f = $this->selectAll($table);
        $this->result = $r;
      }
      elseif(!$this->exists($table, $bool, $flags, "read"))
        if(boolval($flags & self::ALWAYS_ARRAY))
          return [];
        else
          return false;
      else {
        $this->selectWhere($table, $bool, $all);
        $f = $this->fetch(self::FETCH_ALL);
      };
      if($f===new stdClass() && !boolval($flags & self::ALWAYS_ARRAY))
        return false;
      $this->result = $r;
      if(count($f)==1 && !boolval($flags & self::ALWAYS_ARRAY))
        return $f[0];
      return $f;
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
            if($v2[0]=="`" && end(str_split($v2))=="`") {
              $va = str_split($v);
              unset($va[0]);
              unset($va[count($va-1)]);
              $col = true;
            };
            if(!is_numeric($v2))
              $v3 = $this->escape($v2);
            $r.= "`" . $this->escape($k) . "`";
            if(is_numeric($v3)) {
              $r.= " = ";
              $v3 = intval($v3);
            }
            else
              $r.= " LIKE ";
            if(is_numeric($v3))
              $r.= $v;
            else
              $r.= ($join || $col) ? "`$v3`" : "'$v3'";
            $r.= $and ? " AND " : " OR ";
          };
          return rtrim($r, $and ? " AND " : " OR ");
        }
        else {
          $col = false;
          if($v[0]=="`" && end(str_split($v))=="`") {
            $va = str_split($v);
            unset($va[0]);
            unset($va[count($va)]);
            $col = true;
          };
          if(!is_numeric($v))
            $v = $this->escape($v);
          $r.= "`" . $this->escape($k) . "`";
          if(is_numeric($v)) {
            $r.= " = ";
            $v = intval($v);
          }
          else
            $r.= " LIKE ";
          if(is_numeric($v))
            $r.= $v;
          else
            $r.= ($join || $col) ? "`$v`" : "'$v'";
          $r.= $and ? " AND " : " OR ";
        };
      };
      return rtrim($r, $and ? " AND " : " OR ");
    }
    public function __sleep() {
      if($this->result instanceof PDOStatement)
        $this->result->free();
      $this->connect->close();
    }

    public function __wakeup() {
      $this->__construct($this->host, $this->user, $this->password, $this->db);
    }

    public function __destruct() {
      if($this->result instanceof PDOStatement)
        $this->result->free();
      $this->connect = NULL;
    }
  };
  function Smysql($host, $user, $password, $db, &$object = "return") {
    if($object=="return")
      return new Smysql($host, $user, $password, $db);
    else
      $object = new Smysql($host, $user, $password, $db);
  };

  if(!function_exists("mysql_connect")) {
    function mysql_connect($h, $u, $p, $db = NULL) {
      return new Smysql($h, $u, $p, $db);
    };
    function mysql_select_db($c, $db) {
      if($c instanceof Smysql)
        $c->changeDB($db);
      else
        trigger_error("Invalid connection ID");
    };
    function mysql_query($q, $c) {
      if($c instanceof Smysql)
        return $c->query($q);
      else
        trigger_error("Invalid connection ID");
    };
    function mysql_real_escape_string($s, $c) {
      if($c instanceof Smysql)
        $c->escape($s);
      else
        trigger_error("Invalid connection ID");
    };
    function mysql_fetch_object($r, $c) {
      if($c instanceof Smysql)
        return $c->fetch($r);
      else
        trigger_error("Invalid connection ID");
    };
    function mysql_fetch_array($r, $c) {
      if($c instanceof Smysql)
        return $c->fetchArray($r);
      else
        trigger_error("Invalid connection ID");
    };
    function mysql_close($c) {
      if($c instanceof Smysql)
        $c->__destruct();
      else
        trigger_error("Invalid connection ID");
    };
  };
?>
