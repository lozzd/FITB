<?php
# this puts the other non port graphs into the side panel to show things like cpu and memory

# turn on facist debugging
ini_set('display_errors', 'On');
error_reporting(E_ALL);
                       

include_once('functions.php');
$viewhost = $_GET['host'];
$viewtype = @$_GET['type'];
#$viewport = @$_GET['port'];

$start = "";
if(isset($_GET['duration'])) {
    $start = "&duration=" . $_GET['duration'];
}

$title_info = 'View ' . ' - '.$viewhost.($viewtype ? " - ". $viewtype : '');

?>


<html>
<head>
<title>FITB - <?php echo $title_info; ?></title>
<link rel="stylesheet" href="fitb.css" type="text/css" media="screen" /> 
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
</head>

<body>
<?php include_once('header.php'); # includes the <div> for the header ?>
<div id="wrap">
    <?php include_once('side.php'); # includes the <div> for the side bar ?>
    <div id="main">
        <h2><?php echo $title_info;  ?></h2>
            <?php
            # Lets find some graphs! Connect to the database, select the ONE graph for this host, and this graphtype 
            if (connectToDB()) {
                $result = mysql_query('SELECT * FROM altgraphs WHERE host like "%' . mysql_real_escape_string($viewhost). '%" AND graphtype like "%' . mysql_real_escape_string($viewtype). '%"'); 
		#error_log("viewhostalt.php host is {$viewhost} and type is {$viewtype} and result is mysql_fetch_assoc{$result} ");
                if(mysql_num_rows($result) > 0) {
                    echo '<ul id="host-graphs">';
                    while ($row = mysql_fetch_assoc($result)) {
			#error_log("viewhostalt.php line 46: host is {$row['host']} and type is {$row['graphtype']} and filename is {$row['filename']} ");
                        $staletag = "";
                        if ((time() - $row['lastpoll']) > $staleage) {
                            $staletag = "STALE: ";
                        }
                        $friendlytitle = urlencode("{$staletag}{$viewhost} - {$row['name']} ");
                        $basegraphurl = "graph.php?host={$viewhost}&rrdname={$row['host']}-{$row['graphtype']}&type={$row['graphtype']}{$start}";
                        echo '<li>';
                        echo '<a href="view' . $basegraphurl . '">';
                        echo '<img src="' . $basegraphurl . '&height=100&width=400&friendlytitle=' . $friendlytitle . '" alt="'.$row['alias'].'"></a>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    echo '<br style="clear:both">';
                } else {
                    echo "This host does not exist, or it has not been polled yet. ";
                }
            } else {
                echo "<br />Connection to FITB database failed, have you set up the database and specified the correct connection parameters in config.php?";
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
    gm.init('#host-graphs li img');

    $('#host-graphs').sortable({
        distance: 10,
        cursor: 'move',
        placeholder: 'sortable-placeholder',
        forcePlaceholderSize: true,
        revert: true,
        scrollSensitivity: 20
    }).disableSelection();
}(jQuery));
</script>


</body>
</html>
