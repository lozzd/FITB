<?php

include_once('functions.php');

if(!defined('STDIN') ) {
    echo "Must only be run from the command line";
    die();
}

if(!isset($argv[1])) {
    echo "This tool is only supposed to be run by the poller master. \n";
    echo "If you must run it by hand it requires the host from the config to poll\n";
    die();
}

$pollhost = $argv[1];

if(!isset($pollhosts[$pollhost]["prettyname"])) {
    echo "Something went really wrong: Poll host requested not found in config!\n";
    die();
}

# Fetch the details required for polling from the config
$pollprettyhost = $pollhosts[$pollhost]["prettyname"];
$pollip = $pollhosts[$pollhost]["ip"];
$pollsnmpcomm = $pollhosts[$pollhost]["snmpcommunity"];
$pollgraphs = $pollhosts[$pollhost]["graphtypes"];

logline("{$pollprettyhost} - Starting poller run for this host. ", 0, $verbose);

$timestamp = time();
logline("{$pollprettyhost} - Beginning SNMP poll", 1, $verbose);
$ifEntry = snmptable($pollip, $pollsnmpcomm, "1.3.6.1.2.1.2.2.1");
$ifXEntry = snmptable($pollip, $pollsnmpcomm, "1.3.6.1.2.1.31.1.1.1");
logline("{$pollprettyhost} - SNMP poll complete", 1, $verbose);

# Here comes the fun. For every interface, we need to create every graph type that we decided we would graph in the config

if (!$ifEntry) {
    logline("{$pollprettyhost} - SNMP Failed! Either no results, no response or timeout. ", 0, $verbose);
    exit();
}

foreach($ifEntry as $intid => $thisint) {
    logline("{$pollprettyhost} - Starting interface loop for interface index {$intid} ({$thisint[2]})", 1, $verbose);

    # Check if the interface is up. No point graphing down interfaces. 
    if (($thisint[7] == "1") && ($thisint['8'] == "1")) {

        # Assign the values to an array with names for easier referencing
        $thisint['inoctets'] = $ifXEntry[$intid][6];
        $thisint['outoctets'] = $ifXEntry[$intid][10];
        $thisint['inucastpkts'] = $ifXEntry[$intid][7];
        $thisint['outucastpkts'] = $ifXEntry[$intid][11];
        $thisint['indiscards'] = $thisint[13];
        $thisint['outdiscards'] = $thisint[19];
        $thisint['inerrors'] = $thisint[14];
        $thisint['outerrors'] = $thisint[20];
        $thisint['inmulticast'] = $ifXEntry[$intid][8];
        $thisint['outmulticast'] = $ifXEntry[$intid][12];
        $thisint['inbroadcast'] = $ifXEntry[$intid][9];
        $thisint['outbroadcast'] = $ifXEntry[$intid][13];
        $thisint['alias'] = $ifXEntry[$intid][18]; 
    
        # Sanitise the name
        $intname = str_replace("/", "-", $thisint[2]); 
        $intname = str_replace(" ", "-", $intname); 
        $intname = str_replace(":", "-", $intname); 
        $intname = str_replace('"', "", $intname); 
        $thisint['name'] = $intname;

        # Sanitise the alias
        $thisint['alias'] = str_replace('"', "", $thisint['alias']);
       
        
        logline("{$pollprettyhost} - {$intname} - Description for {$intname} is {$thisint['alias']}.", 2, $verbose);
        
        # This loop is going to run a lot. For every interface, create every graph. 
        foreach($pollgraphs as $thisgraph) {

            logline("{$pollprettyhost} - {$intname} - Starting loop for interface {$intname} and graph type {$thisgraph}", 2, $verbose);
            $thisgraphdef = getGraphDefinition($thisgraph);

            $genrrdname = "{$pollprettyhost}-{$intname}_{$thisgraphdef['filesuffix']}.rrd"; 

            logline("{$pollprettyhost} - {$intname} - Starting find or create RRD for graphtype {$thisgraph} and interface {$thisint['name']}... ", 2, $verbose);
            if (!findOrCreateRRD($genrrdname, $pollprettyhost, $thisgraphdef['rrddef'])) {
                logline("{$pollprettyhost} - {$intname} - findOrCreateRRD returned false! Could not find or create the RRD file, check your permissions", 0, $verbose);
                return false;
            }
            logline("{$pollprettyhost} - {$intname} - Find or create rrd done", 2, $verbose);

            # This ugly little loop is neccesery to collect up all the data sources, get the right numbers and add a colon in the middle only.
            $insertvalues = "";
            $i = 0;
            $numberofds = count($thisgraphdef['datasources']);
            foreach($thisgraphdef['datasources'] as $thisds)  {
                $insertvalues .= $thisint[$thisds];
                if($i < $numberofds - 1) {
                    $insertvalues .= ":";
                }
                $i++;
            }

            logline("{$pollprettyhost} - {$intname} - Going to update RRD {$genrrdname} with data {$insertvalues}", 2, $verbose);
            updateRRD($genrrdname, $pollprettyhost, $timestamp, $insertvalues);
            logline("{$pollprettyhost} - {$intname} - Update RRD done", 1, $verbose);

            logline("{$pollprettyhost} - {$intname} - Updating database", 2, $verbose);
            # Insert the details of this graph/port into the database for future reference
            connectToDB();
            # first, delete the previous row if it exists
            mysql_query('DELETE FROM ports where host="' . $pollprettyhost . '" AND safename="' . $thisint['name']. '" AND graphtype="' . $thisgraph . '"');
            # Now insert the values
            mysql_query('INSERT INTO ports (host, name, safename, filename, alias, graphtype, lastpoll)
                VALUES ("'.$pollprettyhost.'", "'.$thisint[2].'", "'.$thisint['name'].'", "'.$genrrdname.'", "'.$thisint['alias'].'", "'.$thisgraph.'", "'. $timestamp .'")');

            logline("{$pollprettyhost} - {$intname} - Done Updating database", 2, $verbose);
            logline("{$pollprettyhost} - {$intname} - Done loop for interface {$thisint['name']} and graph type {$thisgraph}", 2, $verbose);
        }
    } else {
        logline("{$pollprettyhost} - Interface {$thisint['name']} was either admin down or oper down, not polling this run", 1, $verbose);
    }
    logline("{$pollprettyhost} - {$intname} - Loop for interface {$thisint['name']} complete", 1, $verbose);
}

logline("{$pollprettyhost} - Poller has completed it's run for this host. ", 0, $verbose);


