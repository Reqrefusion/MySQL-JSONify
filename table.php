﻿<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Table</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
</head>
<body>
<style>
    ol#pagination li {
        display: inline;
        margin-right: 5px;
    }
</style>
<?php
// GET details
$obj = (include 'details.php');
?>
<select id="path">
    <?php
    foreach ($obj["paths"] as $key => $value) {
        echo '<option value="' . $key . '">' . $key . '</option>';
    }
    ?>
</select>
<input type="text" id="filter" placeholder="filter" value="">
<input type="number" id="offset" placeholder="offset" value="">
<input type="number" id="limit" placeholder="limit" value="">
<p id="linkName"></p>
<p id="info"></p>
<ol id="pagination">
</ol>
<div id="checboxes">
</div>
<table id="table" align="center" border="1px">
    <thead id="tablehead"></thead>
    <tbody id="tablebody"></tbody>
</table>
<script>

    function getPages(currentPage, visiblePages, totalPages) {
        var pages = [];

        var half = Math.floor(visiblePages / 2);
        var start = currentPage - half + 1 - visiblePages % 2;
        var end = currentPage + half;

        if (visiblePages > totalPages) {
            visiblePages = totalPages;
        }
        if (start <= 0) {
            start = 1;
            end = visiblePages;
        }
        if (end > totalPages) {
            start = totalPages - visiblePages + 1;
            end = totalPages;
        }

        var itPage = start;
        while (itPage <= end) {
            pages.push(itPage);
            itPage++;
        }

        return pages;
    }


    function $_GET(param) {
        var vars = {};
        window.location.href.replace(location.hash, '').replace(
            /[?&]+([^=&]+)=?([^&]*)?/gi, // regexp
            function (m, key, value) { // callback
                vars[key] = value !== undefined ? value : '';
            }
        );

        if (param) {
            return vars[param] ? vars[param] : null;
        }
        return vars;
    }


    var currentPage = "";
    var selectCols = [];
    var orderCols = {};
    var orderCol = "";
    var selectColChecked = "";
    var orderColed = '';
    var visiblePages = 3;
    /*Select Module*/
    $("#checboxes").click(function () {
        selectCols = [];
        $.each($("input[name='column']:checked"), function () {
            selectCols.push($(this).val());
        });
        selectColChecked = selectCols.join(",");

    });
    /*Order Module*/
    $("table").click(function (event) {
        orderCol = event.target.getAttribute("data-col");
        if (orderCol !== null) {
            if (orderCols[orderCol] == 'asc') {
                orderCols[orderCol] = 'desc';
            } else if (orderCols[orderCol] == 'desc') {
                orderCols[orderCol] = 'asc';
            } else {
                if (!(event.shiftKey)) {
                    orderCols = {};
                }
                //First click
                orderCols[orderCol] = 'asc';
            }
        }
        $("#info").html(JSON.stringify(orderCols));
        orderColed = '';
        $.each(orderCols, function (keyOr, valOr) {
            orderColed += '' + keyOr + ',' + valOr + ';';
        });
    });
    /*Pagination Module*/
    $("#pagination").click(function (event) {
        if (event.target.getAttribute("data-page") !== null) {
            currentPage = event.target.getAttribute("data-page");
        }
    });
    /*Table Module*/
    $("#path, #selectCol, #filter, #order, #offset, #page, #limit, #pagination, #checboxes, table")
        .on('keyup change click', function (event) {
            var path = document.getElementById("path");
            var pathValue = path.value;
            var filter = document.getElementById("filter");
            var filterValue = filter.value;
            var offset = document.getElementById("offset");
            var offsetValue = offset.value;
            var limit = document.getElementById("limit");
            var limitValue = limit.value;
            var linkJson = "api.php/" + path.value + "?select=" + selectColChecked + "&filter=" + filter.value + "&order=" + orderColed + "&offset=" + offset.value + "&page=" + currentPage + "&limit=" + limit.value + "";
            $("#linkName").html(linkJson);
            history.pushState(null, null, "?select=" + selectColChecked + "&filter=" + filter.value + "&order=" + orderColed + "&offset=" + offset.value + "&page=" + currentPage + "&limit=" + limit.value + "");
            //Json System
            $.getJSON(linkJson, function (data) {
                //Pagination system
                var pagination = "";
                if (1 < data.info.numberOfPages) {
                    if (data.info.page) {
                        var currentPage = parseInt(data.info.page);
                    } else if ($_GET('page')) {
                        var currentPage = parseInt($_GET('page'));
                    } else {
                        var currentPage = 1;
                    }
                    var showPages = getPages(currentPage, visiblePages, data.info.numberOfPages);
                    if (currentPage == 1) {
                        pagination += '<li class="page" data-page="' + 1 + '">' + 'First' + '</li>';
                        pagination += '<li class="page" data-page="' + (currentPage - 1) + '">' + '<' + '</li>';
                    } else {
                        pagination += '<a href="javascript:void(0)"><li class="page" data-page="' + 1 + '">' + 'First' + '</li></a>';
                        pagination += '<a href="javascript:void(0)"><li class="page" data-page="' + (currentPage - 1) + '">' + '<' + '</li></a>';
                    }
                    for (i = showPages[0]; i <= showPages[showPages.length - 1]; i++) {
                        if (currentPage == i) {
                            pagination += '<li class="page" data-page="' + i + '">' + i + '</li>';
                        } else {
                            pagination += '<a href="javascript:void(0)"><li class="page" data-page="' + i + '">' + i + '</li></a>';
                        }
                    }
                    if (currentPage == data.info.numberOfPages) {
                        pagination += '<li class="page" data-page="' + (currentPage - -1) + '">' + '>' + '</li>';
                        pagination += '<li class="page" data-page="' + data.info.numberOfPages + '">' + 'Last' + '</li>';
                    } else {
                        pagination += '<a href="javascript:void(0)"><li class="page" data-page="' + (currentPage - -1) + '">' + '>' + '</li></a>';
                        pagination += '<a href="javascript:void(0)"><li class="page" data-page="' + data.info.numberOfPages + '">' + 'Last' + '</li></a>';
                    }
                }
                $("#pagination").html(pagination);
                //Select System
                var checboxes = "";
                $.each(data.info.tableRows, function (keyCh, valCh) {
                    if (jQuery.inArray(valCh, selectCols) !== -1) {
                        checboxes += '<label><input type="checkbox" value="' + valCh + '" name="column" checked>' + valCh + '</label>';
                    } else {
                        checboxes += '<label><input type="checkbox" value="' + valCh + '" name="column">' + valCh + '</label>';
                    }
                });
                $("#checboxes").html(checboxes);
                //Table system
                //Thead system start
                $("#tablehead").html("");
                $("#tablebody").html("");
                var theader = '<tr>';
                $.each(data.data[0], function (keyTh, valTh) {
                    theader += '<th data-col="' + keyTh + '">' + keyTh + '</th>';
                });
                theader += "</tr>";
                $("#tablehead").html(theader);
                //Thead system end
                //Tbody system start

                $.each(data.data, function (key, val) {
                    var tbody = "";
                    tbody += "<tr>";
                    $.each(val, function (keyTr, valTr) {
                        tbody += '<td>' + valTr + '</td>';
                    });
                    tbody += "</tr>";
                    $("#tablebody").append(tbody);
                });
                //Tbody system end
            });
        })
        .click();
</script>
</body>
</html>