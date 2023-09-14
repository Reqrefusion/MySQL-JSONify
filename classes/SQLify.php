<?php

class SQLify
{
    public $select;
    public $where;
    public $sql;
    public string $paginationSQL;

    /**
     * Constructor
     */
    public
    function __construct($data)
    {
        $this->select = $this->getSQLPart($data, "select");
        $this->where = $this->getSQLPart($data, "where");
        $this->sql = $this->createSQL($data);
        $this->paginationSQL = $this->getSQL($data, "pagination");
    }

    // Return part (select, where) if exists, else "*"
    public
    function getSQLPart($data, $part)
    {
        if ($data->params[$part]) {
            return $data->params[$part];
        }
        return "*";
    }

    /** @noinspection PhpInconsistentReturnPointsInspection */
    public
    function createSQL($data)
    {
        // Based on method, create SQL
        switch ($data->method) {
            case 'GET':
                return $this->getSQL($data);
            case 'POST':
                return $this->postSQL($data);
            case 'PUT':
                return $this->putSQL($data);
            case 'DELETE':
                return $this->deleteSQL($data);
        }
    }

    // Create statement and return it
    public
    function putSQL($data): string
    {
        // error_log('data->posts => ' . print_r($data->posts, true));
        $set = arrayKeyRemove(array_keys($data->posts), $data->idCol);
        $set = join(', ', array_map(function ($v) {
                return "`$v` = ?";
            }, $set)) .
            " WHERE $data->idCol = ?";
        // error_log('PutSQL: ' . $set);
        return "UPDATE `$data->table` SET $set";
    }

    public
    function deleteSQL($data): string
    {
        debug("DELETE FROM `$data->table` WHERE $data->idCol = :", $data->idCol);
        return "DELETE FROM `$data->table` WHERE $data->idCol = :" . $data->idCol;
    }

    // Create statement and return it
    public
    function getSQL($data, $purpose = null): string
    {
        $params = $data->params;
        // If parameter exists, then make string else empty
        $id = "";
        if ($params["id"]) {
            $id = "WHERE $data->idCol = " . firsNumberFinder($params["id"]);
        }
        //Select if
        $select = $this->select;
        if ($params["select"]) {
            $select = $params["select"];
        }
        //Order if
        $order = "";
        if ($params["order"]) {
            $order = "ORDER BY " . orderOrganizer($params["order"], $data->tableRows, $data->idCol);
        }
        //Limit if
        $limit = "LIMIT 18446744073709551610";

        if ($params["limit"] > 0) {
            $limit = "LIMIT $params[limit]";
        }
        //Page params change but it is not organize will be change
        $offset = "";
        if ($params["offset"] > 0) {
            $offset = "OFFSET $params[offset]";
        } elseif ($params["page"] > 0 and $params["limit"] > 0) {
            $offset = "OFFSET " . ($params["page"] - 1) * $params["limit"];
        }
        //filter is back thanks for filterOrganizer function
        $filter = "";
        if ($params["filter"]) {
            $filter = " WHERE " . filterOrganizer($params["filter"], $data->tableRows);
        }
        //creates the required sql to create the total number of pages required for pagination
        $stack = array($id, $filter, $order, $limit, $offset);
        if ($purpose == "pagination") {
            $stack = array($id, $filter, $order);
        }
        return 'SELECT ' . $select . ' FROM `' . $data->table . '` ' . implode(" ", array_filter($stack));
    }

    // Create post method sql
    public
    function postSQL($data): array
    {
        debug("[sqlify POST] data->params", $data->params);
        debug("[sqlify POST] data->posts", $data->posts);
        $params = $data->params;
        $SQLs['GET'] = $this->getSQL($data);
        debug("SQL['GET']", $SQLs['GET']);
        if (isset($params["statement"]) and $params["statement"] === "update"
            and isset($data->posts[$data->idCol])) {
            if (array_key_exists('notUpdate', $data->tableProperty)) {
                foreach ($data->tableProperty["notUpdate"] as $key => $value) {
                    if ($value <= $data->loginInfo['login']->authorityLevel) {
                        unset($data->posts[$key]);
                        $data->loginInfo['login']->notUpdate = "Authority level not enough update for " . $key;

                    }
                }
            }

            if (array_key_exists('ifUpdate', $data->tableProperty)) {
                foreach ($data->tableProperty["ifUpdate"] as $key => $value) {
                    if ($value <= $data->loginInfo['login']->authorityLevel) {
                        $data->loginInfo['login']->ifUpdate = "Authority level not enough for direct update";

                    }
                }
            }

            //$SQLs['POST'] = updateOrganizer($data -> table, $data -> posts, $data -> idCol, $data -> tableProperty);
            $SQLs['POST'] = updateOrganizer($data->table, $data->posts, $data->idCol, $data->tableProperty, $data->loginInfo['login']);
        } elseif (isset($params["statement"]) and $params["statement"] === "delete"
            and isset($data->posts[$data->idCol])) {
            $SQLs['POST'] = "DELETE FROM `" . $data->table .
                "` WHERE " .
                $data->idCol .
                "=" . addStartEndSingleQuote($data->posts[$data->idCol]);
        } elseif ((isset($params["statement"]) and $params["statement"] === "insert") || !isset($params["statement"])) {
            // unset col id on insert
            if (array_key_exists($data->idCol, $data->posts))
                unset($data->posts[$data->idCol]);

            $SQLs['POST'] = "INSERT INTO `" . $data->table . "` (" .
                implode(",", arrayBackTick(array_keys($data->posts))) .
                ") VALUES (" . implode(",", array_map("addStartEndSingleQuote", array_map("sqlStringEscaper", $data->posts))) .
                ")";
        }
        debug('params["statement"]', $params["statement"]);
        debug("SQL['POST']", $SQLs['POST']);
        return $SQLs;
    }


}