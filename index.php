<?php
include_once('functions.php');
?>


<html>
<head>
<title>FITB - Welcome</title>
<link rel="stylesheet" href="fitb.css" type="text/css" media="screen" /> 
<meta http-equiv="refresh" content="60">
</head>

<body>
<?php include_once('header.php'); # includes the <div> for the header ?>
<div id="wrap">
    <?php include_once('side.php'); # includes the <div> for the side bar ?>
    <div id="main">
        <h2>Welcome to FITB</h2>
        <p>FITB is a automatic, RRDTool based graphing product that leaves no port untouched.</p>
        <p>Select a host from the left.</p>
        <?php 
        if (!connectToDB()) {
            echo '<p><span class="red">FITB is having trouble connecting to your database. Have you set up MySQL with the FITB database and specified 
                the correct connection parameters in config.php?</span></p>';
        }

        ?>
    <div>
</div>


</body>
</html>
