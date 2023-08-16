<?php

class Connect {
    protected $db;
    public $connectInfo;
    public $obj;
    /**
     * Constructor
     * @param $dsn string The dsn to the database-file
     * @return void
     */
    public
    function __construct($obj) {
        // Server
        $this -> obj = $obj;
        $databaseConfig = array(
            "dsn" => "mysql:host=$obj[host];dbname=$obj[dbname]",
            "login" => "$obj[username]",
            "password" => "$obj[password]",
            "options" => array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"),
        );

        try {
            $db = new PDO(...array_values($databaseConfig));
            $db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this -> db = $db;
        } catch (PDOException $e) {
            print "Error!: ".$e -> getMessage().
            "<br/>";
            throw new PDOException("Could not connect to database, hiding details.");
        }
    }
    public
    function startResponse($data, $sql) {
        // Based on method, do GET, POST, PUT, DELETE
        switch ($data -> method) {
            case 'GET':
                return $this -> jsonResponse($sql -> sql, $data -> sqlParams, returnInfo($data, $sql, $this), $data);
            case 'POST':
                if ($data -> params["statement"] and $data -> params["statement"] === "login"
                    and getPOST('username') and getPOST('password')) {
                    $this -> connectInfo['login'] = $data -> loginJwt($this);
                    return $this -> jsonResponse($sql -> sql['GET'], $data -> sqlParams, returnInfo($data, $sql, $this), $data);
                }
                elseif($data -> params["token"]) {
                    if ($data -> statementPass) {
                        $this -> connectInfo['login'] = $data -> loginInfo['login'];
                        $this -> execute($sql -> sql['POST']);
                    }
                    $this -> connectInfo['login'] = $data -> loginInfo['login'];
                    return $this -> jsonResponse($sql -> sql['GET'], $data -> sqlParams, returnInfo($data, $sql, $this), $data);

                }
            case 'PUT':
                $this -> execute($sql -> sql, $data -> putParams);
                return $this -> jsonResponse("SELECT * FROM `$data->table` WHERE $data->idCol=".end($data -> putParams));
            case 'DELETE':
                $this -> execute($sql -> sql, $data -> sqlParams);
                return $this -> jsonResponse("SELECT * FROM `$data->table`");
        }
    }
    public
    function execute($sql, $sqlParams = null) {
        try {
            $stmt = $this -> db -> prepare($sql);
            $stmt -> execute($sqlParams);
            $this -> connectInfo['executeStatus'] = "Successful";
        } catch (PDOException $e) {
            $this -> connectInfo['executeStatus'] = $e -> getMessage();
        } catch (Exception $e) {
            $this -> connectInfo['executeStatus'] = $e -> getMessage();
        }
    }
    public
    function rowCount($sql) {
            $stmt = $this -> db -> prepare($sql);
            $stmt -> execute();
            return $stmt -> rowCount();
        }
        // Fetches from MySQL DB and returns as JSON
    public
    function jsonResponse($sql, $sqlParams = null, $info = null, $data = null) {

        if (@is_null(@$data -> tableProperty["select"]) or
	    (!isset($this->connectInfo) && isset($data) && !isset($data->login)) or
	    (@$this -> connectInfo['login'] and @$data -> tableProperty['select'] >= @$this -> connectInfo['login'] -> authorityLevel)) {
            $stmt = $this -> db -> prepare($sql);
            $stmt -> execute($sqlParams);
            //info added page nummer, id etc.
            return json_encode(array('info' => $info, 'data' => $stmt -> fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
        } else {
            $info['error'] = "Authority level not enough to get answer";
            return json_encode(array('info' => $info));
        }

        /*

        $stmt = $this -> db -> prepare($sql);
        $stmt -> execute($sqlParams);
        //info added page nummer, id etc.
        return json_encode(array('info' => $info, 'data' => $stmt -> fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
		*/
    }

    // get res with one fetch
    public
    function fetchArray($sql) {
            $stmt = $this -> db -> prepare($sql);
            $stmt -> execute();
            return $stmt -> fetchAll(PDO::FETCH_COLUMN);
        }
        // get res with one fetch
    public
    function fetchOneRow($sql, $executeArray = null) {
        $stmt = $this -> db -> prepare($sql);
        $stmt -> execute($executeArray);
        return $stmt -> fetch(PDO::FETCH_ASSOC);
    }
}
