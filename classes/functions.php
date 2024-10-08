<?php
// Moves the element to the end index and return array
function moveIndexEnd($array, $key)
{
    $newArray = $array;
    if (array_key_exists($key, $newArray)) {
        unset($newArray[$key]);
        $newArray[$key] = $array[$key];
    }
    return $newArray;
}

// Returns array without given $key, array_values for re-index array
function arrayKeyRemove($arr, $key): array
{
    return array_values(array_filter($arr, function ($k, $v) use ($key) {
        return $k != $key;
    }, ARRAY_FILTER_USE_BOTH));
}

// The function returns the GET based on key.
function getGet($key, $default = null)
{
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    } else {
        return $default;
    }
}

// The function returns the POST based on key.
function getPOST($key, $default = null)
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    } else {
        return $default;
    }
}

function addStartEndSingleQuote($string): string
{
    return "'" . $string .
        "'";
}

function sqlOperator($params): string
{
    $operator = "$params[filterOperator] $params[where] LIKE ";
    // create array with the sent in string
    $pieces = explode(",", $params["filter"]);
    // prepare SQL statement again with wildcards
    foreach ($pieces as $key => $value) {
        $pieces[$key] = " '%" . $value .
            "%' ";
    }
    // return string with operator for multiple "LIKEs" and "ANDs" and "ORs"
    return implode($operator, $pieces);
}

//These two functions are added for ease of writing
function arrayCheck($value, $array)
{
    return $array[array_search(strtolower($value), array_map('strtolower', $array))];
}

function arrayCheckIn($value, $array): bool
{
    return in_array(strtolower($value), array_map('strtolower', $array));
}

function arrayBackTick($arr): array
{
    return array_map(function ($v) {
        return '`' . $v . '`';
    }, $arr);
}

//To avoid injections that can be made for the query
function sqlStringEscaper($value)
{
    return str_replace("'", "\'", str_replace('"', '\"', str_replace('\\\\', "\\", $value)));
}

//To avoid injections that can be made for the query
function firstNumberFinder($value): string
{
    preg_match_all('!\d+!', $value, $matches);
    return $matches[0][0];
}

// multible orderig organizer.
function orderOrganizer($order, $tableRows, $idCol): string
{
    $organizeOrder = "";
    $sortArray = array("ASC", "DESC");
    $slices = explode(";", $order);
    foreach ($slices as $slice) {
        $sorter = "ASC";
        $col = $idCol;
        $parts = explode(",", $slice);
        foreach ($parts as $part) {
            if (arrayCheckIn($part, $sortArray)) {
                $sorter = arrayCheck($part, $sortArray);
            } elseif (arrayCheckIn($part, $tableRows)) {
                $col = arrayCheck($part, $tableRows);
            }
        } //part and

        $organizeOrder .= "`" . $col .
            "` " . $sorter;
        //prevents the comma from finally
        if ($slice !== end($slices)) {
            $organizeOrder .= ",";
        }
    }
    return $organizeOrder;
}


function btComOperatorOrganizer($col, $searchWords, $status = 1): string
{
    asort($searchWords);
    $searchFirst = current($searchWords);
    $searchEnd = end($searchWords);
    if ($status == 0) {
        return "`" . $col . "` NOT BETWEEN '" . $searchFirst . "' AND '" . $searchEnd . "' ";

    } else {
        return "`" . $col . "` BETWEEN '" . $searchFirst . "' AND '" . $searchEnd . "' ";
    }

}

function filterClauseOrganizer($col, $comOperator, $search): string
{
    switch ($comOperator) {
        case "lk"://LIKE
            return "`" . $col . "` LIKE '" . $search . "' ";
        case "nlk"://NOT LIKE
            return "`" . $col . "` NOT LIKE '" . $search . "' ";
        case "cs"://contain string (string contains value)
            return "`" . $col . "` LIKE '%" . $search . "%' ";
        case "ncs"://not contain string (string contains value)
            return "`" . $col . "` NOT LIKE '%" . $search . "%' ";
        case "sw"://start with (string starts with value)
            return "`" . $col . "` LIKE '" . $search . "%' ";
        case "ew"://end with (string end with value)
            return "`" . $col . "` LIKE '%" . $search . "' ";
        case "eq"://equal (string or number matches exactly)
            return "`" . $col . "` = '" . $search . "' ";
        case "neq"://not equal (string or number matches exactly)
            return "`" . $col . "` != '" . $search . "' ";
        case "lt"://lower than (number is lower than value)
            return "`" . $col . "` > '" . $search . "' ";
        case "le"://lower or equal (number is lower than or equal to value)
            return "`" . $col . "` => '" . $search . "' ";
        case "ge"://greater or equal (number is higher than or equal to value)
            return "`" . $col . "` =< '" . $search . "' ";
        case "gt"://greater than (number is higher than value)
            return "`" . $col . "` < '" . $search . "' ";
        case "bt"://between (number is between two comma separated values)
            return btComOperatorOrganizer($col, $search);
        case "nbt"://not between (number is between two comma separated values)
            return btComOperatorOrganizer($col, $search, 0);
        case "in": //in (number or string is in comma separated list of values)
            return "`" . $col . "` IN (" . implode(",", array_map("addStartEndSingleQuote", $search)) . ") ";
        case "nin": //not in (number or string is in comma separated list of values)
            return "`" . $col . "` NOT IN (" . implode(",", array_map("addStartEndSingleQuote", $search)) . ") ";
        case "is"://is null (field contains "NULL" value)
            return "`" . $col . "` IS NULL ";
        case "nis"://is not null (field contains "NULL" value)
            return "`" . $col . "` IS NOT NULL ";
        default: //cace cs(?):
            return "`" . $col . "` LIKE '%" . $search . "%' ";
    }
}

function filterOrganizer($filter, $tableRows): string
{
    $comOperatorsArray = array("lk", "nlk", "cs", "sw", "ew", "eq", "lt", "le", "ge", "gt", "bt", "in", "is", "nin" /* not in */);
    $logOperatorsArray = array("AND", "OR", "||", "&&", "XOR");
    $slices = explode(";", $filter);
    debug("filters (slices)", $slices);
    $organizeFilter = "";

    // Espagne,expert_pays,like,OR,France,expert_pays,like,AND;%C3%82ne,expert_especes,like,OR;Anguille,expert_especes,like
    $i_slice = count($slices);
    foreach ($slices as $slice) {
        $i_slice--;
        $comOperator = "LIKE";
        $logOperator = "OR";
        $search = "";
        $parts = explode(",", $slice);
        debug("parts", $parts);
        $comOperators = array_intersect($parts, $comOperatorsArray);
        $comOperator = current($comOperators);
        $logOperators = array_intersect($parts, $logOperatorsArray);
        $logOperator = current($logOperators);
        $colIntersect = array_intersect($parts, $tableRows);
        debug('$comOperator', $comOperator);
        debug('$logOperators', $logOperators);
        debug('$colIntersect', $colIntersect);
        $comLogOperatorsColDiff = array_diff($parts, array_merge($comOperatorsArray, $logOperatorsArray, $tableRows));
        debug('$comLogOperatorsColDiff', $comLogOperatorsColDiff);
        $searchWords = array_map("sqlStringEscaper", $comLogOperatorsColDiff);
        debug('$searchWords', $searchWords);

        if (empty($colIntersect)) {
            $cols = $tableRows;
        } else {
            $cols = $colIntersect;
        }
        $organizeFilter .= "(";
        $i = count($cols);
        reset($searchWords);
        reset($cols);
        foreach ($cols as $col) {
            $i--;
            debug('$col', $col);
            debug('$searchWords', $searchWords);
            if ($comOperator == 'in' || $comOperator == 'nin')
                $organizeFilter .= filterClauseOrganizer($col, $comOperator, $searchWords);
            else
                $organizeFilter .= filterClauseOrganizer($col, $comOperator, current($searchWords));
            debug('$organizeFilter', $organizeFilter);
            debug('$logOperator', $logOperator);
            if ($i > 0) {
                $organizeFilter .= $logOperator . " ";
                $logOperator = next($logOperators) ?? 'OR';
                debug('next $logOperator', $logOperator);
            } else {
                debug('last inner condition', $logOperators);
            }
            next($searchWords);
        }
        $organizeFilter .= ") ";


        if ($i_slice > 0) {
            if (empty($logOperator)) {
                $logOperator = "OR";
            }
            debug('$logOperator', $logOperator);
            $organizeFilter .= $logOperator . " ";
        }
    }
    debug('build filter', $organizeFilter);
    return $organizeFilter;
}

// Update statement 
function updateOrganizer($table, $posts, $idCol, $tableProperty, $loginInfo): string
{
    //$refinedPosts = array_diff_key($posts, $tableProperty["notUpdate"]);//Galiba bu tam istediğim şey değil bira daha düşünmem lazım
    //$test = print_r($loginInfo);
    $sql = "UPDATE `$table` SET ";
    foreach ($posts as $key => $value) {
        if ($key !== $idCol) {
            $sql = $sql . $key .
                "=" . addStartEndSingleQuote(sqlStringEscaper($value));
            //"=".addStartEndSingleQuote(sqlStringEscaper($test));
            if ($value !== end($posts)) {
                $sql = $sql .
                    ",";
            }
        }
    }
    $sql = $sql .
        " WHERE " . $idCol .
        "=" . $posts[$idCol];
    if (array_key_exists('ifUpdate', $tableProperty)) {
        foreach ($tableProperty["ifUpdate"] as $key => $value) {
            if ($value <= $loginInfo->authorityLevel) {
                $sql = $sql . " AND " . "`" . $key . "`" . " = " . $loginInfo->userId;
            }
        }
    }
    return $sql;

}

// Select statement
function selectOrganizer($selectStr, $tableRows)
{
    $aggFunctions = array("avg", 'count', 'max', 'min', 'sum');
    $scaFunctions = array("ucase", 'lcase', 'mid', 'len', 'clen', 'round');
    $otherFunctions = array('dist');//dist=DISTINCT
}

// Moves the element to the end index and return array
function returnInfo($data, $sql, $connect): array
{
    $params = $data->params;
    $info["rowCount"] = $connect->rowCount($sql->paginationSQL);
    $info["tableRows"] = $data->tableRows;
    if ($params["page"]) {
        $info["page"] = $params["page"];
    }
    if ($params["limit"]) {
        $info["limit"] = $params["limit"];
        $info["numberOfPages"] = ceil($info["rowCount"] / $info["limit"]);
    }
    if ($params["statement"] and $params["statement"] === "login"
        and getPOST('username') and getPOST('password')) {
        $info["login"] = $connect->connectInfo['login'];
    } elseif ($params["token"]) {
        $info["posts"] = $data->posts;
        $info["login"] = $connect->connectInfo['login'];//stdclass
        if (is_array($info["login"])) {
            $info['login']['token'] = $params["token"];
        } else {
            $info['login']->token = $params["token"];
        }
    }
    if (isset($connect->connectInfo['executeStatus'])) {
        $info["executeStatus"] = $connect->connectInfo['executeStatus'];
    }
    $info["id"] = $data->idCol;
    return $info;
}

function debug(...$msg)
{
    if (!file_exists(__DIR__ . '/../.debug'))
        return;
    $prefix = '[debug] ';
    if (function_exists("xdebug_call_function")) {
        $prefix = sprintf("[%s-%s:%d] ", xdebug_call_file(), xdebug_call_function(), xdebug_call_line());
    }
    $i = 0;
    foreach ($msg as $s) {
        switch (gettype($s)) {
            case "array":
            case "object":
            case "resource":
            case "unknown type":
            case "resource (closed)":
                error_log($prefix . '(' . gettype($s) . ') ' . print_r($s, true));
                break;
            case "boolean":
                error_log($prefix . $s ? 'true' : 'false');
                break;
            case "NULL":
                error_log($prefix . "(null)");
                break;
            default:
                error_log($prefix . '(' . gettype($s) . ') ' . $s);
                break;
        }
        $i++;
    }
}
