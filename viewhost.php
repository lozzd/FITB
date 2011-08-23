<?php
include_once('functions.php');
$viewhost = $_GET['host'];
$viewtype = @$_GET['type'];

$start = "";
if(isset($_GET['duration'])) {
    $start = "&start=" . $_GET['duration'];
}


?>


<html>
<head>
<title>FITB - View host - <?php echo $viewhost ?></title>
<link rel="stylesheet" href="fitb.css" type="text/css" media="screen" /> 
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
<script type="text/javascript" src="fitb.js"></script>
<meta http-equiv="refresh" content="60">
</head>

<body>
<?php include_once('header.php'); # includes the <div> for the header ?>
<div id="wrap">
    <?php include_once('side.php'); # includes the <div> for the side bar ?>
    <div id="main">
        <h2>View host - <?php echo $viewhost; if ($viewtype!="") echo " - ". $viewtype  ?></h2>
            <?php
            # Lets find some graphs! Connect to the database, select all the ports for this host, and this graphtype (Empty wildcard makes sure all graphs appear if none set)
            if (connectToDB()) {
                $result = mysql_query('SELECT * FROM ports WHERE host like "%' . mysql_real_escape_string($viewhost). '%" AND graphtype like "%' . mysql_real_escape_string($viewtype) . '%" ORDER BY lastpoll DESC, safename ASC');

                if(mysql_num_rows($result) > 0) {

                   while ($row = mysql_fetch_assoc($result)) {
                        $staletag = "";
                        if ((time() - $row['lastpoll']) > $staleage) {
                            $staletag = "STALE: ";
                        }
                        $friendlytitle = urlencode("{$staletag}{$viewhost} - {$row['name']} ({$row['alias']})");
                        $basegraphurl = "graph.php?host={$viewhost}&rrdname={$viewhost}-{$row['safename']}&type={$row['graphtype']}{$start}";
                        echo '<a href="view' . $basegraphurl . '">';
                        echo '<img src="' . $basegraphurl . '&height=100&width=400&friendlytitle=' . $friendlytitle . '" alt="'.$row['alias'].'"></a>';
                    }
                } else {
                    echo "This host does not exist, or it has not been polled yet. ";
                }
            } else {
                echo "<br />Connection to FITB database failed, have you set up the database and specified the correct connection parameters in config.php?";
            }
        ?>

    <div>
</div>


</body>
</html>
