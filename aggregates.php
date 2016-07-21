<?php
include_once('functions.php');

$start = null;
if(isset($_GET['duration'])) {
    $start = $_GET['duration'];
}
?>


<html>
<head>
<title>FITB - Aggregates</title>
<link rel="stylesheet" href="fitb.css" type="text/css" media="screen" /> 
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
</head>

<body>
<?php include_once('header.php'); # includes the <div> for the header ?>
<div id="wrap">
    <?php include_once('side.php'); # includes the <div> for the side bar ?>
    <div id="main">
        <h2>View Aggregates</h2>
            <?php
            if ($link=connectToDB()) {
                $result = mysqli_query($link, 'SELECT aggregate_id FROM aggregates ORDER BY friendlytitle'); // depending on the number of aggregates we have, we may want to have some filtering

                if (mysqli_num_rows($result) > 0) {
                    echo '<ul id="aggregate-graphs">';
                    while ($row = mysqli_fetch_assoc($result)) {
                        $aggregate_id = $row['aggregate_id'];
                        
                        $url = "graph.php?aggregate_id={$aggregate_id}&height=100&width=400";
                        if (!is_null($start)) {
                            $url .= '&duration='.$start;
                        }

                        echo '<li>';
                        echo '<a href="viewaggregate.php?aggregate_id=' . $row['aggregate_id'] . '">';
                        echo '<img src="' . $url . '" alt="">';
                        echo '</a>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    echo '<p class="help-text">* Shift-click a graph to start building a new aggregate.</p>';
                    echo '<br style="clear:both">';

                } else {
                    echo "No aggregates were found. Shift-click a graph to start building an aggregate.";
                }
            } else {
                echo "<br />Connection to FITB database failed, have you set up the database and specified the correct connection parameters in config.php?";
            }
        ?>

    <div>
</div>


</body>
</html>
