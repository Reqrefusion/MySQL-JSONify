<?php

class DataHandler
{
    // Table, method Data
    public string $path;
    public $method;
    public $table;
    public $tableProperty;

    // Rows data
    public $params;
    public $posts;
    public $putParams;

    // Acceptable data
    public $tableRows; // Acceptable WHERE'S and SELECT's (tables columns)
    private array $validParams = array("select", "order", "where", "id", "filter", "limit", "offset", "page", "statement", "token");
    private array $validPaths;
    public $idCol;
    public $loginInfo;
    public $statementPass;
    /**
     * @var mixed
     */
    public $serverKey;
    /**
     * @var mixed
     */
    public $login;
    /**
     * @var mixed
     */
    public $primaryTableRows;
    public array $sqlParams;


    /**
     * Constructor
     */
    public
    function __construct($obj, $connect)
    {
        // Get the full URL and Valid paths and Method, such as POST, GET, PUT, DELETE
        $url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $this->path = basename(parse_url($url, PHP_URL_PATH));
        $this->validPaths = array_keys($obj["paths"]);
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->serverKey = $obj["serverKey"];
        if (array_key_exists('login', $obj))
            $this->login = $obj["login"];
        if (@$_SERVER['CONTENT_TYPE'] == 'application/json') {
            // read and decode json data
            $json = file_get_contents('php://input');
            // Converts it into a PHP object
            $data = json_decode($json);
            // inject data into $_POST
            foreach ($data as $k => $v) {
                $_POST[$k] = $v;
            }
        }
        // controlPath then get table rows
        $this->controlPath();
        $this->controlParams();

        $this->table = $obj["paths"][$this->path]["name"];
        $this->tableProperty = $obj["paths"][$this->path];
        $this->tableRows = $connect->fetchArray("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$this->table'");
        //Added due to the possibility of id being in another name
        $this->primaryTableRows = $connect->fetchArray("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.key_column_usage WHERE TABLE_NAME='$this->table' AND CONSTRAINT_NAME = 'PRIMARY'");
        if (empty($this->primaryTableRows)) {
            if (in_array('id', $this->tableRows))
                $this->idCol = 'id';
            elseif (array_key_exists('key', $obj["paths"][$this->path]))
                $this->idCol = $obj["paths"][$this->path]['key']; // allow force id by hand in details.php
            else
                error_log('unset id column for table ' . $this->table);
        } else
            $this->idCol = $this->primaryTableRows[0];
        // Get the GETS in the $this->validParams
        $this->params = array_combine($this->validParams, array_map("getGet", $this->validParams));
        // Get posts, Only receives data sent
        $this->posts = array_combine($this->tableRows, array_map("getPOST", $this->tableRows));

        // Get paths based on colum names and move id last ALWAYS.
        if (strtolower($this->method) == 'put') {
            $putParams = array();
            if (isset($_POST[$this->idCol])) {
                $putParams = array_combine($this->tableRows, array_map("getPOST", $this->tableRows));
            } else {
                $putParams = array_combine($this->tableRows, array_map("getGet", $this->tableRows));
            }

            $putParams = array_filter($putParams, function ($v, $k) {
                return !is_null($v);
            }, ARRAY_FILTER_USE_BOTH);
            $this->posts = @moveIndexEnd($putParams, $this->idCol);
            $this->putParams = array_values(@moveIndexEnd($putParams, $this->idCol));
            error_log('putParams => ' . print_r($putParams, true));
            error_log('this->putParams, after filter, ' . $this->idCol . ' as end => ' . print_r($this->putParams, true));
            error_log('this->posts    , after filter, ' . $this->idCol . ' as end => ' . print_r($this->posts, true));
        }

        $this->sqlParams = array(':id' => $this->params["id"]); //No need to escape it

        if ($this->params["token"]) {

            try {
                $this->loginInfo['login'] = JWT::decode($this->params["token"], $this->serverKey, array('HS256'));

                if (is_null(@$this->tableProperty[$this->params["statement"]]) or $this->tableProperty[$this->params["statement"]] >= $this->loginInfo['login']->authorityLevel) {
                    //$connect -> execute($sql -> sql['POST']);
                    //For the update process
                    /*
                        if (isset($this -> params["statement"]) and $this -> params["statement"] === "update" and isset($this -> posts[$this -> idCol])) {

                        }
                        */
                    $this->statementPass = true;
                } else {
                    $this->statementPass = false;
                    $this->loginInfo['login']->error = "Authority level not enough for " . $this->params["statement"];
                }
            } catch (Exception $e) {
                $this->statementPass = false;
                $this->loginInfo['login']['error'] = $e->getMessage();
            }
            //return $connect -> jsonResponse($sql -> sql['GET'], $data -> sqlParams, returnInfo($data, $sql, $this), $data);
        }
    }

    /**
     * errorHandler, echos ERROR JSON-response and it ends here
     */
    public
    function errorHandler()
    {
        $errorPath = array('validPaths' => $this->validPaths, 'givenPath' => $this->path);
        $errorParam = array('validSelects' => "ALL table columns, $this->path", 'validParams' => $this->validParams, 'givenParams' => $this->params);
        $error = array('Path' => $errorPath, 'Param' => $errorParam);
        echo json_encode($error, JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * Token generator to Post Method
     */
    public
    function loginJwt($connect): array
    {
        $username = getPOST('username');
        $password = getPOST('password');
        $executeArray = array(':username' => $username, ':password' => $password);
        $query = $connect->fetchOneRow("SELECT * FROM " . $this->login['table'] .
            " WHERE " . $this->login['username'] .
            "=:username && " . $this->login['password'] .
            "=:password", $executeArray);
        if ($query) {
            $nbf = strtotime("now");
            $exp = strtotime($this->login['expirationRemainingHours'] .
                ' hour');
            // create a token
            $payloadArray = array();
            $payloadArray['userId'] = $query[$this->login['userId']];
            $payloadArray['username'] = $query[$this->login['username']];
            $payloadArray['authorityLevel'] = $query[$this->login['authorityLevel']];
            if ($nbf !== false) {
                $payloadArray['nbf'] = $nbf;
            }
            if ($exp !== false) {
                $payloadArray['exp'] = $exp;
            }
            $token = JWT::encode($payloadArray, $this->serverKey);

            // return to caller
            $returnArray['token'] = $token;

        } else {

            $returnArray['error'] = 'Invalid user ID or password.';
        }
        return $returnArray;
    }

    /**
     * @return true if valid, else false
     */
    public
    function controlPath()
    {
        if (!in_array($this->path, $this->validPaths)) {
            $this->errorHandler();
        }
        return $this->table;
    }

    public
    function controlParams()
    {
        // ---------------------------------------------------
        // Control Select
        // Incoming matches valid value sets
        if (isset($this->params) && $this->params["select"]) {
            $selectParams = explode(",", $this->params["select"]);
            foreach ($selectParams as $selectParam) {
                if (!in_array($selectParam, $this->tableRows) && $selectParam) {
                    $this->errorHandler($this);
                }
            }
        }
        // ---------------------------------------------------
        // Only these values are valid
        if (isset($this->params) && !is_numeric($this->params["offset"]) && $this->params["offset"]) {
            $this->errorHandler();
        }

        if (isset($this->params) && !is_numeric($this->params["limit"]) && $this->params["limit"]) {
            $this->errorHandler();
        }

        if (isset($this->params) && !is_numeric($this->params["page"]) && $this->params["page"]) {
            $this->errorHandler();
        }
    }
}
