<?php

//ini_set('display_errors', '1');
//error_reporting(E_ALL | E_STRICT);

class BD
{
    
    public static $conexao = null;
    public static $ready = false;
    public static $statementsDone = array();
    
    //put your code here
    public static function init($dbname = "", $user = "", $password = "", $host = "", $port = "") {
        self::$conexao = self::abrirConexao($dbname, $user, $password, $host, $port);
        
        self::$ready = true;
    }
    
    private static function abrirConexao($dbname, $user, $password, $host, $port) {
        $conn = pg_connect("dbname=$dbname user=$user password=$password host=$host port=$port");
        if (!$conn) {
            echo "Not connected : " . pg_last_error();
            exit;
        }
        return $conn;
    }
    
    private static function prepareSelect($tablename) {
        $i = 1;
        $columns = self::getTableInfo($tablename);
        
        $sql_select = [];
        $primary_keys = [];
        while ($column = pg_fetch_array($columns)) {
            if ($column["primary_key"] === "t") {
                array_push($primary_keys, $column["name"] . " = $" . $i);
            }
            
            if (strpos($column["typ"], "geometry") !== false) {
                array_push($sql_select, "ST_AsText(" . $column["name"] . ") as " . $column["name"]);
            } else {
                array_push($sql_select, $column["name"]);
            }
            
            $i++;
        }
        $concat_primary_keys = implode(" AND ", $primary_keys);
        
        //prepare statement to check if record exists
        $sql = "SELECT " . implode(",", $sql_select) . " FROM $tablename WHERE $concat_primary_keys";
        
        pg_prepare(self::$conexao, $tablename . "_select", $sql);
        
        self::$statementsDone[$tablename . '_select'] = true;
    }
    
    private static function prepareSelectGeoData($tablename) {
        $i = 1;
        $columns = self::getTableInfo($tablename);
        
        $sql_select = [];
        
        while ($column = pg_fetch_array($columns)) {
            if (strpos($column["typ"], "geometry") !== false) {
                array_push($sql_select, "ST_AsText(" . $column["name"] . ") as " . $column["name"]);
                $sql_selectGeo = $column["name"];
            } else {
                array_push($sql_select, $column["name"]);
            }
            
            $i++;
        }
        
        if ($sql_selectGeo !== "") {
            $sql = "SELECT " . implode(",", $sql_select) . ", st_asgeojson($sql_selectGeo) as geojson FROM " . $tablename . " WHERE 1=$1";
            
            pg_prepare(self::$conexao, $tablename . "_selectGeoData", $sql);
            
            self::$statementsDone[$tablename . '_selectGeoData'] = true;
        }
    }
    
    private static function prepareInsert($tablename) {
        $i = 1;
        $columns = self::getTableInfo($tablename);
        
        $sql_insert1 = "INSERT INTO $tablename (";
        $sql_insert2 = [];
        $sql_insert3 = [];
        
        while ($column = pg_fetch_assoc($columns)) {
            if ($column['primary_key'] === 't' && $column["default"] !== '') {
                continue;
            }
            array_push($sql_insert2, $column["name"]);
            
            if (strpos($column["typ"], "geometry") !== false) {
                array_push($sql_insert3, " ST_SetSRID(ST_GeomFromText($" . $i . "),4326)");
            } else {
                array_push($sql_insert3, " $" . $i);
            }
            
            $i++;
        }
        
        // prepare statement to insert data
        $sql = "$sql_insert1 " . implode(",", $sql_insert2) . ") VALUES (" . implode(",", $sql_insert3) . ")";
        
        //            echo "<p>" . $sql . "</p>";
        pg_prepare(self::$conexao, $tablename . "_insert", " " . $sql);
        
        self::$statementsDone[$tablename . '_insert'] = true;
    }
    
    private static function prepareUpdate($tablename) {
        $i = 1;
        $columns = self::getTableInfo($tablename);
        
        $sql_update1 = "UPDATE " . $tablename . " ";
        $sql_update2 = [];
        
        $primary_keys = [];
        
        while ($column = pg_fetch_assoc($columns)) {
            if ($column["primary_key"] === "t") {
                array_push($primary_keys, $column["name"] . " = $" . $i);
            }
            
            if (strpos($column["typ"], "geometry") !== false) {
                array_push($sql_update2, $column["name"] . " = ST_SetSRID(ST_GeomFromText($" . ($i + 1) . "),4326)");
            } else {
                array_push($sql_update2, $column["name"] . " = $" . ($i + 1));
            }
            
            $i++;
        }
        $concat_primary_keys = implode(" AND ", $primary_keys);
        
        // prepare statement to update data
        $sql = "$sql_update1 SET " . implode(",", $sql_update2) . " WHERE $concat_primary_keys";
        
        // echo "<p>" . $sql . "</p>";
        pg_prepare(self::$conexao, $tablename . "_update", $sql);
        
        self::$statementsDone[$tablename . '_update'] = true;
    }
    
    private static function prepareDelete($tablename) {
        $i = 1;
        $columns = self::getTableInfo($tablename);
        
        $sql_delete1 = "DELETE FROM $tablename WHERE ";
        
        $primary_keys = [];
        
        while ($column = pg_fetch_assoc($columns)) {
            if ($column["primary_key"] === "t") {
                array_push($primary_keys, $column["name"] . " = $" . $i);
            }
            
            $i++;
        }
        $concat_primary_keys = implode(" AND ", $primary_keys);
        
        // prepare statement to delete data
        $sql = $sql_delete1 . $concat_primary_keys;
        
        // echo "<p>" . $sql . "</p>";
        pg_prepare(self::$conexao, $tablename . "_delete", $sql);
        
        self::$statementsDone[$tablename . '_delete'] = true;
    }
    
    private static function prepareTableInfo($tablename) {
        if (!isset(self::$conexao)) {
            self::init();
        }
        pg_prepare(self::$conexao, $tablename . "_getColumns", "SELECT * FROM table_columns WHERE relname = $1");
        
        self::$statementsDone[$tablename . '_getColumns'] = true;
    }
    
    private static function prepareGetTables() {
    }
    
    //##############################################################
    
    public static function getTable($tablename) {
        return $tables = pg_query(self::$conexao, "SELECT * FROM database_tables");
    }
    
    public static function getTableInfo($tablename) {
        
        //        echo self::$statementsDone[$tablename . "_getColumns"];
        if (!isset(self::$statementsDone[$tablename . "_getColumns"])) {
            self::prepareTableInfo($tablename);
        }
        return pg_execute(self::$conexao, $tablename . "_getColumns", array($tablename));
    }
    
    public static function selectGeoData($tablename) {
        if (!isset(self::$statementsDone[$tablename . "_selectGeoData"])) {
            self::prepareSelectGeoData($tablename);
        }
        return pg_execute(self::$conexao, $tablename . "_selectGeoData", (array)1);
    }
    
    public static function select($tablename, $args) {
        if (!isset(self::$statementsDone[$tablename . "_select"])) {
            self::prepareSelect($tablename);
        }
        return pg_execute(self::$conexao, $tablename . "_select", (array)$args);
    }
    
    public static function exists($tablename, $args) {
        return pg_num_rows(self::select($tablename, $args));
    }
    
    public static function insert($tablename, $args) {
        if (!isset(self::$statementsDone[$tablename . "_insert"])) {
            self::prepareInsert($tablename);
        }
        
        //        var_dump($args);
        return pg_execute(self::$conexao, $tablename . "_insert", (array)$args);
    }
    
    public static function update($tablename, $pkeys, $args) {
        if (!isset(self::$statementsDone[$tablename . "_update"])) {
            self::prepareUpdate($tablename);
        }
        return pg_execute(self::$conexao, $tablename . "_update", array_merge((array)$pkeys, (array)$args));
    }
    
    public static function delete($tablename, $args) {
        if (!isset(self::$statementsDone[$tablename . "_delete"])) {
            self::prepareDelete($tablename);
        }
        return pg_execute(self::$conexao, $tablename . "_delete", (array)$args);
    }
    
    function __destruct() {
        pg_close(self::$conexao);
    }
}

if (!BD::$ready) {
    BD::init();
}
