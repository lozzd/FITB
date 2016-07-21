<?php
include_once('functions.php');
$viewhost = $_GET['host'];
$viewtype = @$_GET['type'];
$viewport = @$_GET['port'];

$start = "";
if(isset($_GET['duration'])) {
    $start = "&duration=" . $_GET['duration'];
}

$title_info = 'View ' . ($viewport ? 'port' : 'host') . ' - '.$viewhost.($viewport ? ' - '.$viewport : '').($viewtype ? " - ". $viewtype : '');

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
            # Lets find some graphs! Connect to the database, select all the ports for this host, and this graphtype (Empty wildcard makes sure all graphs appear if none set)
            if ($link=connectToDB()) {
                $port_clause = $viewport ? ' AND safename="' . mysqli_real_escape_string($link, $viewport) . '" ' : '';
                $result = mysqli_query($link, 'SELECT * FROM ports WHERE host = "' . mysqli_real_escape_string($link, $viewhost). '" AND graphtype like "%' . mysqli_real_escape_string($link, $viewtype) . '%" '. $port_clause .' ORDER BY lastpoll DESC, safename ASC');

                if(mysqli_num_rows($result) > 0) {
                    echo '<ul id="host-graphs">';
                    while ($row = mysqli_fetch_assoc($result)) {
                        $staletag = "";
                        if ((time() - $row['lastpoll']) > $staleage) {
                            $staletag = "STALE: ";
                        }
                        $friendlytitle = urlencode("{$staletag}{$viewhost} - {$row['name']} ({$row['alias']})");
                        $basegraphurl = "graph.php?host={$viewhost}&rrdname={$viewhost}-{$row['safename']}&type={$row['graphtype']}{$start}";
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
