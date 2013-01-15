<?php
include_once('functions.php');
$aggregate_id = $_GET['aggregate_id'];

$url = null;
$data = getAggregateData($aggregate_id);
if (!is_null($data)) {
    $graphs_array = $data['graphs_array'];
    $meta = $data['meta'];
    $meta['height'] = 300;
    $meta['width'] = 800;

    $url = "graph.php?aggregate_id={$aggregate_id}&height=300&width=800";

    if(isset($_GET['duration'])) {
        $url .= "&duration=" . $_GET['duration'];
    }
    $titleinfo = ' - '.($meta['friendlytitle'] ? $meta['friendlytitle'].' - ' : '').$meta['type'].' ('.count($graphs_array).' graphs)';
}


?>


<html>
<head>
<title>FITB <?php echo $titleinfo ?></title>
<link rel="stylesheet" href="fitb.css" type="text/css" media="screen" /> 
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
</head>

<body>
<?php include_once('header.php'); # includes the <div> for the header ?>
<div id="wrap">
    <?php include_once('side.php'); # includes the <div> for the side bar ?>
    <div id="main">
        <h2>View aggregate <?php echo $titleinfo ?></h2>
            <?php
            if (is_null($data)) {
                echo 'Aggregate not found.';
            } else {
                echo '<img src="' . $url . '">';
                foreach ($graphs_array as $g) {
                    $host = $g['rrdfolder'];
                    $port = str_replace($host.'-', '', $g['rrdname']);
                    echo "<p>Host/Port: <a href=\"viewhost.php?host={$host}\">{$host}</a>/<a href=\"viewhost.php?host={$host}&port={$port}\">{$port}</a></p>";
                }
                echo '<p><a class="delete-link" href="ajax/deleteAggregate.php?aggregate_id='.$aggregate_id.'">Delete this aggregate graph</a></p>';
            }
            echo '<p class="help-text">* Shift-click a graph to start building a new aggregate.</p>';
            ?>
    <div>
</div>

<script>
    jQuery(function($) {
        $('a.delete-link').click(function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            var res = window.confirm('Are you sure you want to delete this aggregate graph?');
            if (res) {
                $.post(url).done(function() {
                    window.location = 'aggregates.php';
                });
            }
        });
    }(jQuery));
</script>

</body>
</html>
