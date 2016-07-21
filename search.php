<?php
include_once('functions.php');
$searchquery = $_GET['query'];

$start = "";
if(isset($_GET['duration'])) {
    $start = "&duration=" . $_GET['duration'];
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
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
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
            $link=connectToDB();
            $searchquery = mysqli_real_escape_string($link, $searchquery);

            if (isset($host)) {
                $host = mysqli_real_escape_string($link, $host);
                $type = mysqli_real_escape_string($link, $type);
                $result = mysqli_query($link, 'SELECT * FROM ports WHERE (name like "%' . $searchquery . '%" OR alias like "%' . $searchquery . '%") 
                                            AND graphtype like "%' . $type . '%" AND host="' . $host . '" ORDER BY lastpoll DESC, safename ASC');
            } elseif (isset($type)) {
                $type = mysqli_real_escape_string($link, $type);
                $result = mysqli_query($link, 'SELECT * FROM ports WHERE (name like "%' . $searchquery . '%" OR alias like "%' . $searchquery . '%") 
                                            AND graphtype="' . $type . '" ORDER BY lastpoll DESC, safename ASC');
            } else {
                $result = mysqli_query($link, 'SELECT * FROM ports WHERE (name like "%' . $searchquery . '%" OR alias like "%' . $searchquery . '%")
                                            ORDER BY lastpoll DESC, safename ASC');
            }
            if(mysqli_num_rows($result) > 0) {
                $numresults = mysqli_num_rows($result);
                echo "<div class=\"small\">{$numresults} results found</div>";
                while ($row = mysqli_fetch_assoc($result)) {
                    $staletag = "";
                    if ((time() - $row['lastpoll']) > $staleage) {
                        $staletag = "STALE: ";
                    }
                    $friendlytitle = urlencode("{$staletag}{$row['host']} - {$row['name']} ({$row['alias']})");
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
