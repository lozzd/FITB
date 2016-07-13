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
# alt graphs are non interface graphs
$pollaltgraphs = $pollhosts[$pollhost]["altgraphtypes"];
# platform is the definition of what kind of hardware it is, ie: c3560e or c3064nex
$pollplatform = $pollhosts[$pollhost]["platform"];
logline( " the platform for this switch is $pollplatform. ", 1, $verbose);

# Connect carbon.
if (isset($carbon_host,$carbon_port,$graphite_prefix,$graphite_metrics,$graphite_datacenter)) {
    $carbon = fsockopen($carbon_host, $carbon_port, $errno, $errstr, 5);
    if (!$carbon) {
        logline("{$pollprettyhost} - Connect to carbon failed.", 0, $verbose);
    }
}

logline("{$pollprettyhost} - Starting poller run for this host. ", 0, $verbose);

$timestamp = time();
logline("{$pollprettyhost} - Beginning SNMP poll", 1, $verbose);
$ifEntry    = snmptable($pollip, $pollsnmpcomm, "1.3.6.1.2.1.2.2.1");
$ifXEntry   = snmptable($pollip, $pollsnmpcomm, "1.3.6.1.2.1.31.1.1.1");

# It would be better if oid, graph defs, colors, types, etc were all taken care of modularly to make adding new things like intake temp a breeze
# Also, this seems like a good place to inject common platform elements from the config.php to make the config simpler?
if( $pollplatform == 'c3560e' || $pollplatform == 'c3560x' ) {
	$temp       = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.9.13.1.3.1.3.1006");
	$cpu1minrev = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.9.109.1.1.1.1.7.1");
	$memfree    = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.2.1.8.0");
} elseif($pollplatform == 'nexus5000') {
	$temp       = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.9.91.1.1.1.1.4.21598");
        $cpu1minrev = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.9.109.1.1.1.1.7.1");
        $memfree    = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.2.1.8.0");  
} elseif($pollplatform == 'nexus7000') {
        # the 7k has a million temp sensors, this one is the cpu on CMP in slot5
	# Note: these work on the base vdc
	$temp       = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.9.91.1.1.1.1.4.21850");
        $cpu1minrev = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.9.109.1.1.1.1.7.1");
        $memfree    = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.9.109.1.1.1.1.13.1");  
} elseif($pollplatform == 'c4000') {
	#air outlet temp
        $temp       = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.9.13.1.3.1.3.6");
        $cpu1minrev = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.2.1.57.0");
        $memfree    = snmp2_get($pollip, $pollsnmpcomm, "1.3.6.1.4.1.9.2.1.8.0");
}

# print arrays to get debug info
logline("$ifEntry" , 2, $verbose);
logline("$ifXEntry", 2, $verbose);
logline("snmp2_get returned temp as temperature {$temp}" ,2 ,$verbose);
logline("snmp2_get returned cpu as cpu {$cpu1minrev}" ,2,$verbose);
logline("snmp2_get returned memfree as memfree {$memfree}" ,2 ,$verbose);

logline("{$pollprettyhost} - SNMP poll complete", 1, $verbose);

# Here comes the fun. For every interface, we need to create every graph type that we decided we would graph in the config
if (!$ifEntry) {
    logline("{$pollprettyhost} - SNMP Failed! Either no results, no response or timeout. ", 0, $verbose);
    exit();
}

foreach($ifEntry as $intid => $thisint) {
    logline("{$pollprettyhost} - Starting interface loop for interface index {$intid} int name ({$thisint[2]})", 1, $verbose);

    #logline("is up/down $thisint[7] , $thisint[8].");
    # Check if the interface is up. No point graphing down interfaces. 
    ##  this IF is FAILING
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
 
        # Send data to carbon.
        if ($carbon) {
            foreach($graphite_metrics as $metric) {
                fwrite($carbon, "{$graphite_prefix}.{$graphite_datacenter}-{$pollprettyhost}.{$thisint['name']}.{$metric} {$thisint[$metric]} {$timestamp}\n");
            }
        }
       
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

##################################################################################################################################

# This loop is going to run for each altgraph defined per host
if (!$pollaltgraphs) {
	logline("Alternate graphs not configured for host {$prettypollhost}.", 1, $verbose);
	exit();
} else {
	#logline(" pollaltgraphs is {$pollaltgraphs[0]} , second one is {$pollaltgraphs[1]} , third one is {$pollaltgraphs[2]} ", 1, $verbose);
	foreach($pollaltgraphs as $thisgraph) {

            logline("{$pollprettyhost} - Starting loop for graph type {$thisgraph}", 2, $verbose);
            $thisgraphdef = getGraphDefinition($thisgraph);
	    #error_log("poller_child: thisgraphdef is {$thisgraphdef['rrddef']} and data is {$thisgraphdef['datasources']} and file is {$thisgraphdef['filesuffix']}", 1, $verbose);
            $genrrdname = "{$pollprettyhost}-{$thisgraphdef['filesuffix']}.rrd";
  #logline("poller_child: the genrrdname is $genrrdname", 1 ,$verbose);

            logline("{$pollprettyhost} - Starting find or create RRD for graphtype {$thisgraph} ... ", 2, $verbose);
            if (!findOrCreateRRD($genrrdname, $pollprettyhost, $thisgraphdef['rrddef'])) {
                logline("{$pollprettyhost} - {$intname} - findOrCreateRRD returned false! Could not find or create the RRD file, check your permissions", 0, $verbose);
                return false;
		echo "failed to find or create rrd file($genrrdname, $pollprettyhost, {$thisgraphdef['rrddef']})";
		logline("{$pollprettyhost} - {$intname} - FAILED to find or create rrd file for {$genrrdname} - {$pollprettyhost} - {$thisgraphdef['rrddef']}", 1, $verbose);
            }
            logline("{$pollprettyhost} - Find or create rrd done", 2, $verbose);

	    # alt graphs are all single value so far and have nothing to do with interfaces, so leaving this here for the future?
            $insertvalues = "";
	    logline("the datasources is {$thisgraphdef['datasources']} " ,1 ,$verbose);
	    if($thisgraphdef['datasources'] == 'temp') {
                   #$insertvalues = ${$thisgraphdef['datasources']};
                $insertvalues .= $temp;
                logline("{$pollprettyhost} - {$thisgraph} - Going to update RRD {$genrrdname} with data {$insertvalues} which should equal $temp", 1, $verbose);
                updateRRD($genrrdname, $pollprettyhost, $timestamp, $insertvalues);
                logline("{$pollprettyhost} - {$thisgraph} - Update RRD done", 1, $verbose);
	    } elseif($thisgraphdef['datasources'] == 'cpu1minrev') {
                $insertvalues .= $cpu1minrev;
		logline("{$pollprettyhost} - {$thisgraph} - Going to update RRD {$genrrdname} with data {$insertvalues} which should equal $cpu1minrev", 1, $verbose);
                updateRRD($genrrdname, $pollprettyhost, $timestamp, $insertvalues);
                logline("{$pollprettyhost} - {$thisgraph} - Update RRD done", 1, $verbose);
            } elseif($thisgraphdef['datasources'] == 'memfree') {
                $insertvalues .= $memfree;
                logline("{$pollprettyhost} - {$thisgraph} - Going to update RRD {$genrrdname} with data {$insertvalues} which should equal $memfree", 1, $verbose);
                updateRRD($genrrdname, $pollprettyhost, $timestamp, $insertvalues);
                logline("{$pollprettyhost} - {$thisgraph} - Update RRD done", 1, $verbose);
            }            

 
            logline("{$pollprettyhost} - {$thisgraph} - Updating database", 2, $verbose);
            # Insert the details of this metric into the database for future reference
            connectToDB();
            # first, delete the previous row if it exists, WHY ARE WE DELETING THIS?
            mysql_query('DELETE FROM altgraphs where host="' . $pollprettyhost . '" AND safename="' . $thisgraph['name']. '" AND graphtype="' . $thisgraph . '"');
            # Now insert the values
            mysql_query('INSERT INTO altgraphs (host, name, safename, filename, alias, graphtype, lastpoll)
                VALUES ("'.$pollprettyhost.'", "'.$thisgraph.'", "'.$thisgraph['name'].'", "'.$genrrdname.'", "'.$thisgraph.'", "'.$thisgraph.'", "'. $timestamp .'")');

            logline("{$pollprettyhost} - {$thisgraph} - Done Updating database", 2, $verbose);
            logline("{$pollprettyhost} - {$intgraph} - Done loop for {$thisgraph} ", 2, $verbose);
        }
}
logline("{$pollprettyhost} - Poller has completed it's run for this host. ", 0, $verbose);

# Disconnect carbon.
if ($carbon) {
    fclose($carbon);
}
