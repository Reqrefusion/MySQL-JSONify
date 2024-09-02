<?php
require(__DIR__ . '/../headerToken.php');

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
    public $privateKey;
    public $publicKey;
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
     * @var true
     */
    public bool $external_auth = false;
    /**
     * @var mixed
     */
    private $bypassHeaderWithHeader = null;


    /**
     * Constructor
     */
    public
    function __construct($obj, $connect)
    {
        $url = '';
        if (array_key_exists('HTTPS', $_SERVER) && strtolower($_SERVER['HTTPS']) == 'on')
            $url = "https://";
        else
            $url = "http://";
        // Get the full URL and Valid paths and Method, such as POST, GET, PUT, DELETE
        $url .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $this->path = basename(parse_url($url, PHP_URL_PATH));
        $this->validPaths = array_keys($obj["paths"]);
        $this->method = $_SERVER['REQUEST_METHOD'];
        if (strtolower($this->method) == "options")
            throw new Exception('OPTIONS request unsupported', 1001);
        debug($this->method . ' ' . $url);
        $this->serverKey = $obj["serverKey"];
        if (array_key_exists('headerPrivateToken', $obj))
            $this->privateKey = $obj['headerPrivateToken'];
        if (array_key_exists('headerPublicToken', $obj))
            $this->publicKey = $obj['headerPublicToken'];
        if (array_key_exists('login', $obj))
            $this->login = $obj["login"];
        else
            $this->external_auth = true;
        if (strtolower(@$_SERVER['CONTENT_TYPE']) == 'application/json') {
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
        $this->validParams[] = $this->idCol;

        // Get the GETS in the $this->validParams
        $this->params = array_combine($this->validParams, array_map("getGet", $this->validParams));
        // Get posts, Only receives data sent
        $this->posts = array_combine($this->tableRows, array_map("getPOST", $this->tableRows));
        if (strtolower($this->method) == 'post' || strtolower($this->method) == 'put') {
            debug('$this->' . strtolower($this->method), $this->posts);
        }

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
            debug("putParams", $putParams);
            debug("this->putParams", $this->putParams);
            debug("this->posts", $this->posts);
        }

        $this->sqlParams = array(':' . $this->idCol => $this->params[$this->idCol]); //No need to escape it

        debug("params", $this->params);
        if ($this->params["token"]) {
            try {
                $this->loginInfo['login'] = JWT::decode($this->params["token"], $this->serverKey, array('HS256'));

                if (is_null(@$this->tableProperty[$this->params["statement"]]) or $this->tableProperty[$this->params["statement"]] >= $this->loginInfo['login']->authorityLevel) {
                    $this->statementPass = true;
                } else {
                    $this->statementPass = false;
                    $this->loginInfo['login']->error = "Authority level not enough for " . $this->params["statement"];
                }
            } catch (Exception $e) {
                $this->statementPass = false;
                $this->loginInfo['login']['error'] = $e->getMessage();
            }
        } else
            $this->statementPass = true;

        if (!empty($this->privateKey) && (empty($_SERVER['AUTH_TYPE']) || empty($_SERVER['REMOTE_USER']))) {
            debug('HeaderToken verification');
            // a hash is provided into header
            $ht = new HeaderToken();
            if (array_key_exists($this->publicKey, $_SERVER) && array_key_exists($this->privateKey, $_SERVER)) {
                if (!$ht->verifyToken(@$_SERVER[$this->publicKey], @$_SERVER[$this->privateKey], $this->serverKey)) {
                    $this->statementPass = false;
                    $this->loginInfo['login']['error'] = 'invalid token';
                } else
                    $this->statementPass = true;
            } else {
                debug('$_SERVER', $_SERVER);
                $this->statementPass = false;
                $this->loginInfo['login']['error'] = 'missing token';
            }
        } else {
            debug('no header token verification');
            $this->statementPass = true;
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
