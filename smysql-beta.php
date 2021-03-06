<?php
  define("SQ_ORDER_ASC", 1);
  define("SQ_ORDER_DESC", 2);
  define("SQ_JOIN_INNER", 4);
  define("SQ_JOIN_LEFT", 8);
  define("SQ_JOIN_RIGHT", 16);
  define("SQ_JOIN_FULL", 32);
  define("SQ_INSERT_RETURN_ID", 64);
  define("SQ_QUERY_ALL", 128);
  define("SQ_FETCH_OBJECT", 256);
  define("SQ_FETCH_ARRAY", 512);
  define("SQ_FETCH_ALL", 1024);
  define("SQ_ALWAYS_ARRAY", 2048);
  define("SQ_NO_ERROR", 4096);
  class SmysqlException extends Exception {
    function __construct($e, $code = 0, Exception $previous = NULL) {
      $gt = parent::getTrace();
      $egt = end($gt);
      $this->message = "<strong>Simon's MySQL error</strong> " . $e . " in <strong>" . $egt['file'] . "</strong> on line <strong>" . $egt['line'] . "</strong>";
    }
    function __toString() {
      trigger_error($this->message, E_USER_ERROR);
      return $this->message;
    }
  };
  abstract class SMQ {
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
    const NO_ERROR = 4096;

    static function open($host = "", $user = "", $password = "", $db = "", &$object = "return") {
      if($object=="return")
        return new Smysql($host, $user, $password, $db);
      else
        $object = new Smysql($host, $user, $password, $db);
    }
  };
  class Smysql extends SMQ {
    protected $connect;
    protected $result;
    protected $host;
    protected $user;
    protected $password;
    protected $db;
    protected $fncs = [];
    public function __construct($host = "", $user = "", $password = "", $database = "") {
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
      if(str_replace("create[", "", $database)!=$database && end(str_split($database))=="]") {
        $new = str_replace("]", "", str_replace("create[", "", $database));
        $this->connect = @new PDO("mysql:host=" . $host, $user, $password);
        if(!$this->query("
          CREATE DATABASE $new
          ", "__construct"))
          throw new SmysqlException("(__construct): Can't create database " . $new);
        $this->connect = NULL;
      };
      try {
        $this->connect = @new PDO("mysql:" . (empty($database) ? "" : "dbname=" . $database . ";") . "host=" . $host . ";charset=utf8", $user, $password);
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
      $quote = "";
      foreach($quoteA as $k => $v) {
        $quote.= $v;
      };
      return $quote;
    }

    public function reload() {
      $this->__construct();
    }

    public function query($query, $flags = 0, $fnc = "Query") {
      if(empty($this->db) && !in_array($fnc, ["__construct", "changeDB", "dbList"]))
        throw new SmysqlException("(" . $fnc . "): No database selected");
      $qr = $this->connect->query($query);
      if(!$qr && !($flags & self::NO_ERROR))
        throw new SmysqlException("(" . $fnc . "): Error in MySQL: " . $this->connect->errorInfo()[2] . " <strong>SQL command:</strong> " . (empty($query) ? "<em>Missing</em>" : $query));
      $this->result = new SmysqlResult($qr);
      return $this->result;
    }

    public function queryf($q, $a, $flags = 0, $fnc = "Queryf") {
      foreach($a as $k => $v) {
        $q = str_replace("%" . $k, $this->escape($v), $q);
      };
      return $this->query($q, $flags, $fnc);
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

    public function execFnc($name, $params = [], $flags = 0) {
      if(isset($this->fncs[$name]))
        $this->queryf($this->fncs[$name], $params, $flags, $name);
      else
        throw new SmysqlException("(" . $name . "): This function isn't defined");
    }

    public function dbList($flags = 0) {
      $result = $this->result;
      $this->query("SHOW DATABASES", $flags, "dbList");
      $r = [];
      while($f = $this->fetch()) {
        $r[] = $f->Database;
      };
      $this->result = $result;
      return $r;
    }

    public function tableList($flags = 0) {
      if(empty($this->db))
        throw new SmysqlException("(tableList): No database selected");
      $result = $this->result;
      $this->query("SHOW TABLES FROM `$this->db`", "tableList", $flags);
      $r = [];
      while($f = $this->fetch(SMQ::FETCH_ARRAY)) {
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

    public function fetch($flags = 256) {
      $id = $this->result;
      return $id->fetch($flags);
    }

    public function deleteDB($db, $flags) {
      if($db==$this->db)
        throw new SmysqlException("(deleteDB): You can't delete current database");
      if($this->query("DROP DATABASE `$db`", $flags, "deleteDB")) {
        return true;
      }
      else {
        throw new SmysqlException("(deleteDB): Can't delete database " . $db);
        return false;
      };
    }

    public function changeDB($newDB) {
      if($this->result instanceof SmysqlResult)
        $this->result->__destruct();
      $this->connect = NULL;
      $this->db = $newDB;
      $this->reload();
    }

    public function select($table, $order = "", $cols = ["*"], $flags = 129) {
			foreach($cols as $k => $v) {
				if(!is_numeric($k))
					$cols[$k] = $v . " AS " . $k;
			};
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY `" . $order . "`" . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue FROM `$table` $order
      ", $flags, "select");
    }

    public function selectWhere($table, $array, $order = "", $cols = ["*"], $flags = 129, $name = "selectWhere") {
      $all = boolval($flags & self::QUERY_ALL);
      $bool = $this->getBool($array, $all);
			foreach($cols as $k => $v) {
				if(!is_numeric($k))
					$cols[$k] = $v . " AS " . $k;
			};
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY `" . $order . "`" . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue FROM `$table` WHERE $bool $order
      ", $flags, $name);
    }

    public function selectJoin($table, $join, $array, $order = "", $cols = ["*"], $flags = 133) {
      $all = boolval($flags & self::QUERY_ALL);
      switch(true) {
        case boolval($flags & self::JOIN_INNER): $jt = "INNER"; break;
        case boolval($flags & self::JOIN_LEFT): $jt = "LEFT OUTER"; break;
        case boolval($flags & self::JOIN_RIGHT): $jt = "RIGHT OUTER"; break;
        case boolval($flags & self::JOIN_FULL): $jt = "FULL OUTER"; break;
        default: $jt = "INNER";
      };
      $bool = $this->getBool($array, $all, true);
			foreach($cols as $k => $v) {
				if(!is_numeric($k))
					$cols[$k] = $v . " AS " . $k;
			};
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY `" . $order . "` " . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue
        FROM `$table`
        $jt JOIN `$join` ON $bool
        $order
      ", $flags, "selectJoin");
    }

    public function selectJoinWhere($table, $join, $array, $where, $order = "", $cols = ["*"], $flags = 133) {
      $all = boolval($flags & self::QUERY_ALL);
      switch(true) {
        case boolval($flags & self::JOIN_INNER): $jt = "INNER"; break;
        case boolval($flags & self::JOIN_LEFT): $jt = "LEFT OUTER"; break;
        case boolval($flags & self::JOIN_RIGHT): $jt = "RIGHT OUTER"; break;
        case boolval($flags & self::JOIN_FULL): $jt = "FULL OUTER"; break;
        default: $jt = "INNER";
      };
      $bool = $this->getBool($array, $all, true);
      $whereBool = $this->getBool($where, $all);
			foreach($cols as $k => $v) {
				if(!is_numeric($k))
					$cols[$k] = $v . " AS " . $k;
			};
      $colsValue = implode(", ", $cols);
      if(!empty($order))
        $order = "ORDER BY `" . $order . "` " . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue
        FROM `$table`
        $jt JOIN `$join` ON $bool
        WHERE $whereBool
        $order
      ", $flags, "selectJoinWhere");
    }

    public function exists($table, $array, $flags = 129, $name = "exists") {
      $all = boolval($flags & self::QUERY_ALL);
      $this->selectWhere($table, $array, "", ["*"], $flags, $name);
      $noFetch = !$this->fetch();
      return !$noFetch;
    }

    public function truncate($table, $flags = 0) {
      return $this->query("
        TRUNCATE `$table`
      ", $flags, "truncate");
    }

    public function insert($table, $values, $cols = [""], $flags = 0) {
      if($cols==[""] || $cols=="")
        $colString = "";
      else {
        $colString = " (";
        foreach($cols as $key => $value) {
          if($key!=0) $colString.= ", ";
          $colString.= "'" . $this->escape($value) . "'";
        };
        $colString.= ")";
      };
      $valueString = "";
      foreach($values as $key => $value) {
        if($key!=array_keys($values, array_values($values)[0])[0]) $valueString.= ", ";
        $valueString.= $value===NULL ? "NULL" : ("'" . $this->escape($value) . "'");
      };
      $r = $this->query("
        INSERT INTO $table$colString VALUES ($valueString)
      ", $flags, "insert");
      return (boolval($flags & self::INSERT_RETURN_ID) ? $this->connect->lastInsertId() : $r);
    }

    public function delete($table, $array, $flags = 128) {
      $all = boolval($flags & self::QUERY_ALL);
      $bool = $this->getBool($array, $all);
      return $this->query("
        DELETE FROM `$table` WHERE $bool
      ", $flags, "delete");
    }

    public function update($table, $arr, $array, $flags = 128) {
      $bool = $this->getBool($arr, $flags & 128);
      $string = "";
      foreach($array as $key => $value) {
        if($string!="")
          $string.= ", ";
        $string.= "`" . $key . "`=" . ($value===NULL ? "NULL" : ("'" . $this->escape($value) . "'"));
      };
      return $this->query("
        UPDATE `$table` SET $string WHERE $bool
      ", $flags, "update");
    }

    public function add($table, $name, $type, $lenth, $null, $where, $key, $data = "") {
      if(!empty($data))
        $data = " " . $data;
      $type = strtoupper($type);
      $where = strtoupper($where);
      return $this->query("
        ALTER TABLE `$table` ADD '$name' $type($lenth) " . ($null ? "NULL" : "NOT NULL") . "$data $where '$key'
      ", $flags, "drop");
    }

    public function drop($table, $name) {
      return $this->query("
        ALTER TABLE `$table` DROP '$name'
      ", $flags, "drop");
    }

    public function change($table, $name, $newname, $type, $lenth, $null, $data = "", $flags = 0) {
      if(!empty($data))
        $data = " " . $data;
      $type = strtoupper($type);
      $where = strtoupper($where);
      return $this->query("
        ALTER TABLE `$table` CHANGE '$name' $newname $type($lenth) " . ($null ? "NULL" : "NOT NULL") . $data
      , $flags, "change");
    }

    public function selectAll($table, $flags = 129) {
      $r = $this->result;
      $this->select($table, "", ["*"], $flags);
      $f = $this->fetch($flags | self::FETCH_ALL);
      $this->result = $r;
      return $f;
    }

    public function fetchWhere($table, $bool, $flags = 129) {
      $r = $this->result;
      $this->selectWhere($table, $bool, $flags);
      $f = $this->fetch();
      $this->result = $r;
      return $f;
    }

    public function read($table, $bool = [], $flags = 129) {
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
        $this->selectWhere($table, $bool, "", ["*"], $flags);
        $f = $this->fetch(self::FETCH_ALL);
      };
      if($f===new stdClass() && !boolval($flags & self::ALWAYS_ARRAY))
        return false;
      $this->result = $r;
      if(count($f)==1 && !boolval($flags & self::ALWAYS_ARRAY))
        return $f[0];
      return $f;
    }

    public function createTable($table, $names, $types, $lengths, $nulls, $primary = "", $uniques, $others = [], $flags = 0) {
      $parameters = $this->getParameters($names, $types, $lengths, $nulls, $uniques, $others);
      $valueString = implode(",\n", $parameters);
      return $this->query("
        CREATE TABLE `$table` ($valueString)
        " . empty($primary) ? "" : ", PRIMARY KEY ($primary)" . "
      ", $flags);
    }

    public function renameTable($table, $newname, $flags = 0) {
      return $this->query("
        ALTER TABLE `$table` RENAME TO $newname
      ", $flags, "renameTable");
    }

    public function deleteTable($table, $flags = 0) {
      return $this->query("
        DROP TABLE `$table`
      ", $flags, "deleteTable");
    }

    private function getParameters($names, $types, $lengths, $nulls, $others = []) {
      if(count($names)==count($types) && count($names)==count($nulls)) {
        if(count($names)==count($others)) {
          foreach($names as $k => $v) {
            $t = $types[$k];
            $l = $lengths[$k];
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
            $l = $lengths[$k];
            $n = $nulls[$k] ? "NULL" : "NOT NULL";
            if(empty($l))
              $r[] = "$v $t $n" . (in_array($v, $uniques) ? "" : " UNIQUE") . " $o";
            else
              $r[] = "$v $t($l) $n" . (in_array($v, $uniques) ? "" : " UNIQUE") . " $o";
          };
          return $r;
        };
      };
      return false;
    }

    public function q($q, string ...$a) {
      if($a==[])
        return $this->query($q);
      else
        return $this->queryf($q, $a);
    }

    public function qs(string ...$qs) {
      $r = [];
      foreach($qs as $k => $v) {
        $r[] = $this->query($v);
      };
      if(count($r)==1)
        $r = $r[0];
      return $r;
    }

    public function p($q) {
      return new SmysqlQuery($this, $q);
    }

    private function getBool($a, $and, $join = false) {
      if(!is_array($a))
        return $a;
      $r = "";
      foreach($a as $k => $v) {
        if(is_array($v)) {
          foreach($v as $k2 => $v2) {
            $col = false;
            if(str_split($v2)[0]=="`" && end(str_split($v2))=="`") {
              $va = str_split($v);
              unset($va[0]);
              unset($va[count($va-1)]);
              $col = true;
            };
            if(!is_numeric($v2))
              $v3 = $this->escape($v2);
            $r.= "`" . $this->escape($k) . "`";
            $r.= " = ";
            if(is_numeric($v3))
              $v3 = intval($v3);
            if($v3===NULL)
              $r.= "IS NULL";
            elseif(is_numeric($v3))
              $r.= $v;
            else
              $r.= ($join || $col) ? "`$v3`" : "'$v3'";
            $r.= $and ? " AND " : " OR ";
          };
          return rtrim($r, $and ? " AND " : " OR ");
        }
        else {
          $col = false;
          if(str_split($v)[0]=="`" && end(str_split($v))=="`") {
            $va = str_split($v);
            unset($va[0]);
            unset($va[count($va)]);
            $col = true;
          };
          if(!is_numeric($v))
            $v = $this->escape($v);
          $r.= "`" . $this->escape($k) . "`";
          $r.= " = ";
          if(is_numeric($v))
            $v = intval($v);
          if(is_numeric($v))
            $r.= $v;
          else
            $r.= ($join || $col) ? "`$v`" : "'$v'";
          $r.= $and ? " AND " : " OR ";
        };
      };
      return rtrim($r, $and ? " AND " : " OR ");
    }
    public function __debugInfo() {
      return ['host' => $this->host, 'user' => $this->user, 'password' => preg_replace("/./", "*", $this->password), 'database' => $this->db, 'lastError' => $this->connect->errorInfo()];
    }
    public function __sleep() {
      if($this->result instanceof PDOStatement)
        $this->result->__destruct();
      $this->connect = NULL;
    }

    public function __wakeup() {
      $this->__construct($this->host, $this->user, $this->password, $this->db);
    }

    public function __destruct() {
      if($this->result instanceof PDOStatement)
        $this->result->__destruct();
      $this->connect = NULL;
    }
  };
  class SmysqlResult {
    private $pdos;
    public function __construct($pdos) {
      $this->pdos = $pdos;
    }
    public function fetch($flags = 256) {
      $id = $this->pdos;
      if(boolval($flags & SMQ::FETCH_OBJECT))
        $return =  $id->fetchObject();
      elseif(boolval($flags & SMQ::FETCH_ARRAY))
        $return =  $id->fetch();
      elseif(boolval($flags & SMQ::FETCH_ALL)) {
        $return = [];
        while($row = $id->fetchObject()) {
          $return[] = $row;
        };
      };
      return $return;
    }
    public function dump($print = true) {
      $h = "";
      $f = $this->fetch(SQ_FETCH_ALL);
      $h.= "<table style=\"border-collapse: collapse; margin: 10px;\">";
      $cc = $this->pdos->columnCount();
      if($cc>0) {
        $h.= "<tr>";
        $i = 0;
        while($i++<$cc) {
          $h.= "<th style=\"border: 1px solid black; background-color: #ccf; padding: 5px;\">";
          $h.= $this->pdos->getColumnMeta(bcsub($i, 1))['name'];
          $h.= "</th>";
        };
        $h.= "</tr>";
      };
      if(count($f)==0 && $cc>0)
        $h.= "<tr><td colspan=\"" . $cc . "\" style=\"border: 1px solid black; padding: 5px;\"><em>No rows returned</em></td></tr>";
      elseif($cc>0) {
        foreach($f as $k => $v) {
          $h.= "<tr>";
          foreach($v as $val) {
            $h.= "<td style=\"border: 1px solid black; padding: 5px; text-align: center;\">" . ($val===NULL ? "<em>NULL</em>" : ("<pre>" . str_replace(["\n", "\r"], "", nl2br(htmlspecialchars($val, ENT_QUOTES, "UTF-8"))) . "</pre>")) . "</td>";
          };
          $h.= "<tr>";
        };
      }
      else
        $h = "<em>Empty response</em>";
      if(str_replace("<table", "", $h)!=$h)
        $h.= "</table>";
      if($print)
        print $h;
      return $h;
    }
    public function __toString() {
      return $this->dump(false);
    }
    public function __destruct() {
      $this->pdos = NULL;
    }
  };

  class SmysqlQuery {
    private $c;
    private $q = "";
    private $p = [];
    public function __construct($c, $q) {
      $this->c = $c;
      $this->q = $q;
    }
    public function pair($k, $v) {
      $this->p[$k] = $v;
      return $this;
    }
    public function set($p) {
      foreach($p as $k => $v) {
        $this->pair($k, $v);
      };
      return $this;
    }
    public function __set($k, $v) {
      $this->pair($k, $v);
    }
    public function __get($k) {
      return $this->p[$k];
    }
    public function __unset($k) {
      unset($this->p[$k]);
    }
    public function __isset($k) {
      isset($this->p[$k]);
    }
    public function s(string ...$qs) {
      $this->set($qs);
      return $this;
    }
    public function run(string ...$qs) {
      $this->set($qs);
      return $this->c->queryf($this->q, $this->p);
    }
  };


  function Smysql($host, $user, $password, $db, &$object = "return") {
    if($object=="return")
      return new Smysql($host, $user, $password, $db);
    else
      $object = new Smysql($host, $user, $password, $db);
  };

  if(!function_exists("mysql_connect")) {
    function mysql_connect($h, $u, $p, $db = "") {
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
        return $c->escape($s);
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
        return $c->fetch($r, SMQ::FETCH_ARRAY);
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
