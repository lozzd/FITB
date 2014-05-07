<?php
include_once('functions.php');
$viewhost = $_GET['host'];
$viewtype = @$_GET['type'];
$viewport = @$_GET['rrdname'];

# Remove the extra host bit from the rrdname
$viewport = str_replace($viewhost. "-", "", $viewport);


$start = "";
if(isset($_GET['duration'])) {
    $start = "&duration=" . $_GET['duration'];
}


?>


<html>
<head>
<title>FITB - View graph - <?php echo "$viewhost - $viewport - $viewtype" ?></title>
<link rel="stylesheet" href="fitb.css" type="text/css" media="screen" /> 
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
</head>

<body>
<?php include_once('header.php'); # includes the <div> for the header ?>
<div id="wrap">
    <?php include_once('side.php'); # includes the <div> for the side bar ?>
    <div id="main">
        <h2>View graph - <?php echo "$viewhost - $viewport - $viewtype" ?></h2>
            <?php
	    if ( $viewtype == 'temp' || $viewtype == 'cpu1minrev' || $viewtype == 'memfree' ) {
		#echo "entering temp if, viewtype is $viewtype and viewhost is $viewhost.\n ";
		connectToDB();
                $result = mysql_query(' SELECT * FROM altgraphs WHERE host="' . mysql_real_escape_string($viewhost). '" AND graphtype="' . mysql_real_escape_string($viewtype). '" '); 
		if(mysql_num_rows($result) > 0) {
		    #echo " entering internal if for more than 0 rows \n";
                    $row = mysql_fetch_assoc($result);
		    #var_dump($row);
                    echo "<div>";
                    echo "<p>Graph Name: <a href=\"viewhost.php?host={$row['host']}&port={$row['safename']}\">{$row['name']}</a> </p>";
                    echo "<p>Graph Alias: <pre>{$row['alias']}</pre></p>";
                    echo '<p>On host: <a href="viewhost.php?host=' . $row['host'] . '">' . $row['host'] . '</a>';
                    echo "<p>Status: ";
                    if ((time() - $row['lastpoll']) > $staleage) {
                        echo '<span class="red">STALE</span></p>';
                    } else {
                        echo '<span class="green">OK</span></p>';
                }
                echo "<p>Last polled: " . _ago($row['lastpoll']) .  " ago </p>";

                # Insert the graph
                $friendlytitle = urlencode("{$viewhost} - {$row['name']} ({$row['alias']})");
                $basegraphurl = "graph.php?host={$viewhost}&rrdname={$viewhost}-{$viewtype}&type={$row['graphtype']}{$start}&friendlytitle={$friendlytitle}";
		#echo "friendlytitle is $friendlytitle <br> and basegraphurl is $basegraphurl. <br>";
                    echo '<img class="graph-img" src="' . $basegraphurl . '&height=300&width=800" alt="'.$row['alias'].'">';
                } else {
                    echo "<br>Host/type: $viewhost/$viewtype :  combination not found, is this supposed to be a port graph?<br>";
            	}
	    }
	# lets assume its a port if its not and alt type of graph
	 if ( $viewtype != 'temp' && $viewtype != 'cpu1minrev' && $viewtype != 'memfree' ) {
            connectToDB();
            $result = mysql_query('SELECT * FROM ports WHERE host="' . mysql_real_escape_string($viewhost). '" AND graphtype="' . mysql_real_escape_string($viewtype) . '" AND safename="' . mysql_real_escape_string($viewport) . '" ');

            if(mysql_num_rows($result) > 0) {
                    $row = mysql_fetch_assoc($result);
                    echo "<div>";
                    echo "<p>Port Name: <a href=\"viewhost.php?host={$row['host']}&port={$row['safename']}\">{$row['name']}</a> </p>";
                    echo "<p>Port Alias: <pre>{$row['alias']}</pre></p>";
                    echo '<p>On host: <a href="viewhost.php?host=' . $row['host'] . '">' . $row['host'] . '</a>';
                    echo "<p>Status: "; 
                    if ((time() - $row['lastpoll']) > $staleage) {
                        echo '<span class="red">STALE</span></p>';
                    } else {
                        echo '<span class="green">OK</span></p>';
                    }
                    echo "<p>Last polled: " . _ago($row['lastpoll']) .  " ago </p>";
                
                    # Insert the graph
                    $friendlytitle = urlencode("{$viewhost} - {$row['name']} ({$row['alias']})");
                    $basegraphurl = "graph.php?host={$viewhost}&rrdname={$viewhost}-{$row['safename']}&type={$row['graphtype']}{$start}&friendlytitle={$friendlytitle}";
                    echo '<img class="graph-img" src="' . $basegraphurl . '&height=300&width=800" alt="'.$row['alias'].'">';
            } else {
                   echo "Port/host/type combination not found! Was it polled yet?";
            }
	  }
        ?>

    <div>
</div>

<?php include('agg_builder_template.php'); ?>
<script type="text/javascript" src="fitb.js"></script>
<script type="text/javascript">
GraphManager.HOSTS = <?php echo json_encode(getAllEnabledHosts()); ?>;
jQuery(function($) {
    var gm = new GraphManager();
    gm.init('#main img.graph-img');
}(jQuery));
</script>

</body>
</html>
