<?php

class Connect
{
    protected $db;
    public $connectInfo;
    public $obj;
    private $array;

    /**
     * Constructor
     * @param $dsn string The dsn to the database-file
     * @return void
     */
    public
    function __construct($obj)
    {
        // Server
        $this->obj = $obj;
        $this->array = array(
            "dsn" => "mysql:host=$obj[host];dbname=$obj[dbname]",
            "login" => "$obj[username]",
            "password" => "$obj[password]",
            "options" => array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"),
        );
        $databaseConfig = $this->array;

        try {
            $db = new PDO(...array_values($databaseConfig));
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db = $db;
        } catch (PDOException $e) {
            print "Error!: " . $e->getMessage() .
                "<br/>";
            throw new PDOException("Could not connect to database, hiding details.");
        }
    }

    public
    function startResponse($data, $sql)
    {
        if (!is_object($data)) return;
        // Based on method, do GET, POST, PUT, DELETE
        switch ($data->method) {
            case 'GET':
                if (!$data->statementPass) {
                    return json_encode(array('info' => $data->loginInfo['login']['error']));
                }
                return $this->jsonResponse($sql->sql, $data->sqlParams, returnInfo($data, $sql, $this), $data);
            case 'POST':
                if ($data->params["statement"] and $data->params["statement"] === "login"
                    and getPOST('username') and getPOST('password')) {
                    $this->connectInfo['login'] = $data->loginJwt($this);
                    return $this->jsonResponse($sql->sql['GET'], $data->sqlParams, returnInfo($data, $sql, $this), $data);
                } elseif (isset($data->params["token"])) {
                    if ($data->statementPass) {
                        $this->connectInfo['login'] = $data->loginInfo['login'];
                        $this->execute($sql->sql['POST']);
                    }
                    $this->connectInfo['login'] = $data->loginInfo['login'];
                    return $this->jsonResponse($sql->sql['GET'], $data->sqlParams, returnInfo($data, $sql, $this), $data);
                } elseif ($data->external_auth) {
                    debug('external auth, executing sql', $sql);
                    $this->execute($sql->sql['POST']);
                    if (isset($this->connectInfo['lastInsertId']))
                        return $this->jsonResponse("SELECT * FROM `$data->table` WHERE $data->idCol=" . $this->connectInfo['lastInsertId']);
                    else {
                        debug('last insert: ', $this->connectInfo);
                        return $this->jsonResponse("SELECT NOW()");
                    }
                }
                break;
            case 'PUT':
                $data->putParams = array_values(array_filter($data->putParams, function ($v) {
                    return !is_null($v);
                }));
                $this->execute($sql->sql, $data->putParams);
                // error_log($this->connectInfo['executeStatus']);
                return $this->jsonResponse("SELECT * FROM `$data->table` WHERE $data->idCol=" . end($data->putParams));
            case 'DELETE':
                debug(print_r($data->sqlParams, true));
                $this->execute($sql->sql, $data->sqlParams);
                if ("Successful" !== $this->connectInfo['executeStatus']) {
                    http_response_code(599 /* custom */);
                    return json_encode(array('info' => $this->connectInfo));
                } else
                    return $this->jsonResponse("SELECT * FROM `$data->table`");
        }
    }

    public
    function execute($sql, $sqlParams = null)
    {
        try {
            debug('[execute] ', $sql);
            debug('[execute] ', $sqlParams);
            $stmt = $this->db->prepare($sql);
            if ($stmt === false) {
                debug('error on sql query', $stmt->errorInfo());
                $this->connectInfo['executeStatus'] = $stmt->errorCode();
            }
            if ($stmt->execute($sqlParams)) {
                debug($sql . ' <== SUCCESS');
                $this->connectInfo['executeStatus'] = "Successful";
                $this->connectInfo['lastInsertId'] = $this->db->lastInsertId();
            } else {
                debug('error on sql query', $stmt->errorInfo());
                $this->connectInfo['executeStatus'] = $stmt->errorCode();
            }
        } catch (PDOException $e) {
            debug('Exception SQL', $e->getMessage());
            $this->connectInfo['executeStatus'] = 'Error';
            $this->connectInfo['executeMessage'] = $e->getMessage();
        } catch (Exception $e) {
            $this->connectInfo['executeStatus'] = $e->getMessage();
        }
    }

    public
    function rowCount($sql)
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    // Fetches from MySQL DB and returns as JSON
    public
    function jsonResponse($sql, $sqlParams = null, $info = null, $data = null)
    {

        if (@is_null(@$data->tableProperty["select"]) or
            (!isset($this->connectInfo) && isset($data) && !isset($data->login)) or
            (@$this->connectInfo['login'] and @$data->tableProperty['select'] >= @$this->connectInfo['login']->authorityLevel)) {
            $stmt = $this->db->prepare($sql);
            try {
                $stmt->execute($sqlParams);
            } catch (PDOException $e) {
                return json_encode(array('info' => $e->getMessage(), 'stack' => $e->getTraceAsString(), 'request' => $sql));
            }
            //info added page number, id etc.
            return json_encode(array('info' => $info, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT)
            );
        } else {
            $info['error'] = "Authority level not enough to get answer";
            return json_encode(array('info' => $info));
        }
    }

    // get res with one fetch
    public
    function fetchArray($sql)
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // get res with one fetch
    public
    function fetchOneRow($sql, $executeArray = null)
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($executeArray);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
