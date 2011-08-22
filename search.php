<?php
include_once('functions.php');
$searchquery = $_GET['query'];

$start = "";
if(isset($_GET['duration'])) {
    $start = "&start=" . $_GET['duration'];
}
if((isset($_GET['type'])) && ($_GET['type'] != "")) {
    $type = $_GET['type'];
}

if((isset($_GET['host'])) && ($_GET['host'] != "")) {
    $host = $_GET['host'];
}

?>


<html>
<head>
<title>FITB - Search Results for <?php echo $searchquery ?></title>
<link rel="stylesheet" href="fitb.css" type="text/css" media="screen" /> 
<meta http-equiv="refresh" content="60">
</head>

<body>
<?php include_once('header.php'); # includes the <div> for the header ?>
<div id="wrap">
    <?php include_once('side.php'); # includes the <div> for the side bar ?>
    <div id="main">
        <?php
            if (isset($host)) {
                echo "<h2>Search Results for \"{$searchquery}\" on host {$host}</h2>";
            } elseif (isset($type)) {
                echo "<h2>Search Results for \"{$searchquery}\", type {$type}</h2>";
            } else {
                echo "<h2>Search Results for \"{$searchquery}\"</h2>";
            }
        ?>
            <?php
            connectToDB();
            $searchquery = mysql_real_escape_string($searchquery);

            if (isset($host)) {
                $host = mysql_real_escape_string($host);
                $type = mysql_real_escape_string($type);
                $result = mysql_query('SELECT * FROM ports WHERE (name like "%' . $searchquery . '%" OR alias like "%' . $searchquery . '%") 
                                            AND graphtype like "%' . $type . '%" AND host="' . $host . '" ORDER BY lastpoll DESC, safename ASC');
            } elseif (isset($type)) {
                $type = mysql_real_escape_string($type);
                $result = mysql_query('SELECT * FROM ports WHERE (name like "%' . $searchquery . '%" OR alias like "%' . $searchquery . '%") 
                                            AND graphtype="' . $type . '" ORDER BY lastpoll DESC, safename ASC');
            } else {
                $result = mysql_query('SELECT * FROM ports WHERE (name like "%' . $searchquery . '%" OR alias like "%' . $searchquery . '%")
                                            ORDER BY lastpoll DESC, safename ASC');
            }
            if(mysql_num_rows($result) > 0) {
                $numresults = mysql_num_rows($result);
                echo "<div class=\"small\">{$numresults} results found</div>";
                while ($row = mysql_fetch_assoc($result)) {
                    $friendlytitle = urlencode("{$row['host']} - {$row['name']} ({$row['alias']})");
                    $basegraphurl = "graph.php?host={$row['host']}&rrdname={$row['host']}-{$row['safename']}&type={$row['graphtype']}{$start}&friendlytitle={$friendlytitle}";
                    echo '<a href="view' . $basegraphurl . '">';
                    echo '<img src="' . $basegraphurl . '&height=100&width=400" alt="'.$row['alias'].'"></a>';
                }
            } else {
                echo "No results :( ";
            }
        ?>

    <div>
</div>


</body>
</html>
