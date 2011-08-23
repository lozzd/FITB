<?php
# Functions that help us work! 
# Anything required to get the job done goes in here. 

# include our config file
include_once('config.php');

function getGraphDefinition($graphtype) {
    switch($graphtype) {
    case "bits":
        $graphdefinition["rrddef"] = "DS:traffic_in:COUNTER:120:0:40000000000 DS:traffic_out:COUNTER:120:0:40000000000";
        $graphdefinition["datasources"] = array("inoctets", "outoctets");
        $graphdefinition["filesuffix"] = "bits";
        break;
    case "errors":
        $graphdefinition["rrddef"] = "DS:errors_in:COUNTER:120:0:10000000 DS:errors_out:COUNTER:120:0:10000000 DS:discards_in:COUNTER:120:0:10000000 DS:discards_out:COUNTER:120:0:10000000";
        $graphdefinition["datasources"] = array("inerrors", "outerrors", "indiscards", "outdiscards");
        $graphdefinition["filesuffix"] = "errors";
        break; 
    case "ucastpkts":
        $graphdefinition["rrddef"] = "DS:unicast_in:COUNTER:120:0:1000000000 DS:unicast_out:COUNTER:120:0:1000000000";
        $graphdefinition["datasources"] = array("inucastpkts", "outucastpkts");
        $graphdefinition["filesuffix"] = "ucastpkts";
        break;
    case "mcastpkts":
        $graphdefinition["rrddef"] = "DS:multicast_in:COUNTER:120:0:1000000000 DS:multicast_out:COUNTER:120:0:1000000000";
        $graphdefinition["datasources"] = array("inmulticast", "outmulticast");
        $graphdefinition["filesuffix"] = "mcastpkts";
        break;
    case "bcastpkts":
        $graphdefinition["rrddef"] = "DS:broadcast_in:COUNTER:120:0:1000000000 DS:broadcast_out:COUNTER:120:0:1000000000";
        $graphdefinition["datasources"] = array("inbroadcast", "outbroadcast");
        $graphdefinition["filesuffix"] = "bcastpkts";
        break;
    default:
        echo "FATAL ERROR: Graph type {$graphtype} is not understood";
        return false;
    }
    return $graphdefinition;

}

function findOrCreateRRD($rrdname, $rrdfolder, $datasources) {
    global $path_rrdtool, $path_rrd;

    # Feel free to adjust these RRA definitions. We're storing 2 weeks at 60 second accuracy. 
    $RRAdef = "RRA:AVERAGE:0.5:1:1209600 RRA:AVERAGE:0.5:24:244 RRA:AVERAGE:0.5:168:244 RRA:AVERAGE:0.5:672:244 RRA:AVERAGE:0.5:5760:1827 ";
    $RRAdef .= "RRA:MAX:0.5:1:1209600 RRA:MAX:0.5:24:244 RRA:MAX:0.5:168:244 RRA:MAX:0.5:672:244 RRA:MAXx:0.5:5760:1827";

    if (!file_exists($path_rrd . $rrdfolder . "/" .  $rrdname)) {
        # RRD file does not exist, we need to send a create command
        # Check the containing folder exists first
        if (!is_dir($path_rrd . $rrdfolder)) {
            if(!mkdir($path_rrd . $rrdfolder, 0777, true)) {
                echo "Making RRD path {$path_rrd}{$rrdfolder} failed!";
                return false;
            }
        }
        $oneminuteago = time() - 60;
        exec("{$path_rrdtool} create {$path_rrd}{$rrdfolder}/{$rrdname} --step 60 --start {$oneminuteago} {$datasources} {$RRAdef}", $rrdoutput, $rrdreturn);
        if ($rrdreturn != 0) {
            echo "RRD failed: " . print_r($rrdoutput) . "\n";
            return false;
        } else {
            return true;
        }
    } else { 
        # File exists already, return true
        return true;
    }
}

function updateRRD($rrdname, $rrdfolder, $timestamp, $value) {
    global $path_rrdtool, $path_rrd;

    exec("{$path_rrdtool} update {$path_rrd}{$rrdfolder}/{$rrdname} {$timestamp}:{$value}", $rrdoutput, $rrdreturn);
    if ($rrdreturn != 0) {
        echo "RRD failed: " . print_r($rrdoutput) . "\n";
        return false;
    } else {
        return true;
    } 

}

function printRRDgraphcmd($rrdname, $rrdfolder, $type = "bits", $start = "-86400", $end = "-60", $height = "120", $width = "500", $friendlytitle) {
    global $path_rrdtool, $path_rrd;

    $buildname = "{$path_rrd}{$rrdfolder}/{$rrdname}_{$type}.rrd";

    if ($friendlytitle == "") {
        $titlename = basename($rrdname);
    } else {
        $titlename = $friendlytitle;
    }

    switch($type) {
        case "bits":
            # bits/sec
            $rrdcmd = "{$path_rrdtool} graph - --imgformat=PNG --font TITLE:8: --start={$start} --end={$end} --title=\"{$titlename} - bits/sec\" ";
            $rrdcmd .= "--rigid --vertical-label='bits per second' --slope-mode --height={$height} --width={$width} --lower-limit=0 ";
            $rrdcmd .= "DEF:a='{$buildname}':traffic_in:AVERAGE DEF:b='{$buildname}':traffic_out:AVERAGE CDEF:cdefa=a,8,* CDEF:cdefb=b,8,* ";
            $rrdcmd .= 'AREA:cdefa#00CF00FF:"Inbound" GPRINT:cdefa:LAST:" Curr\:%8.2lf %s" GPRINT:cdefa:AVERAGE:"Ave\:%8.2lf %s" GPRINT:cdefa:MIN:"Min\:%8.2lf %s" GPRINT:cdefa:MAX:"Max\:%8.2lf %s\n" ';
            $rrdcmd .= 'LINE1:cdefb#002A97FF:"Outbound" GPRINT:cdefb:LAST:"Curr\:%8.2lf %s" GPRINT:cdefb:AVERAGE:"Ave\:%8.2lf %s" GPRINT:cdefb:MIN:"Min\:%8.2lf %s" GPRINT:cdefb:MAX:"Max\:%8.2lf %s\n"';
            break;
        case "ucastpkts":
            # Unicast packets
            $rrdcmd = "{$path_rrdtool} graph - --imgformat=PNG --font TITLE:8: --start={$start} --end={$end} --title=\"{$titlename} - unicast packets/sec\" ";
            $rrdcmd .= "--rigid --vertical-label='packets per second' --slope-mode --height={$height} --width={$width} --lower-limit=0 ";
            $rrdcmd .= "DEF:a='{$buildname}':unicast_in:AVERAGE DEF:b='{$buildname}':unicast_out:AVERAGE ";
            $rrdcmd .= 'AREA:a#FFF200FF:"Unicast In" GPRINT:a:LAST:" Curr\:%8.2lf %s" GPRINT:a:AVERAGE:"Ave\:%8.2lf %s" GPRINT:a:MAX:"Max\:%8.2lf %s\n" ';
            $rrdcmd .= 'LINE1:b#00234BFF:"Unicast Out" GPRINT:b:LAST:"Curr\:%8.2lf %s" GPRINT:b:AVERAGE:"Ave\:%8.2lf %s" GPRINT:b:MAX:"Max\:%8.2lf %s\n"';
            break;
        case "errors":
            # Errors in/out and discards/in/out
            $rrdcmd = "{$path_rrdtool} graph - --imgformat=PNG --font TITLE:8: --start={$start} --end={$end} --title=\"{$titlename} - Errors/sec\" ";
            $rrdcmd .= "--rigid --vertical-label='errors per second' --slope-mode --height={$height} --width={$width} --lower-limit=0 ";
            $rrdcmd .= "DEF:a='{$buildname}':discards_in:AVERAGE DEF:b='{$buildname}':errors_in:AVERAGE DEF:c='{$buildname}':discards_out:AVERAGE DEF:d='{$buildname}':errors_out:AVERAGE ";
            $rrdcmd .= 'LINE1:a#FFAB00FF:"Discards In" GPRINT:a:LAST:" Curr\:%8.2lf %s" GPRINT:a:AVERAGE:"Ave\:%8.2lf %s" GPRINT:a:MAX:"Max\:%8.2lf %s\n" ';
            $rrdcmd .= 'LINE1:b#F51D30FF:"Errors In" GPRINT:b:LAST:"   Curr\:%8.2lf %s" GPRINT:b:AVERAGE:"Ave\:%8.2lf %s" GPRINT:b:MAX:"Max\:%8.2lf %s\n" ';
            $rrdcmd .= 'LINE1:c#C4FD3DFF:"Discards Out" GPRINT:c:LAST:"Curr\:%8.2lf %s" GPRINT:c:AVERAGE:"Ave\:%8.2lf %s" GPRINT:c:MAX:"Max\:%8.2lf %s\n" ';
            $rrdcmd .= 'LINE1:d#00694AFF:"Errors Out" GPRINT:d:LAST:"  Curr\:%8.2lf %s" GPRINT:d:AVERAGE:"Ave\:%8.2lf %s" GPRINT:d:MAX:"Max\:%8.2lf %s\n" ';
            break;
        case "mcastpkts":
            # Multicast packets
            $rrdcmd = "{$path_rrdtool} graph - --imgformat=PNG --font TITLE:8: --start={$start} --end={$end} --title=\"{$titlename} - multicast packets/sec\" ";
            $rrdcmd .= "--rigid --vertical-label='packets per second' --slope-mode --height={$height} --width={$width} --lower-limit=0 ";
            $rrdcmd .= "DEF:a='{$buildname}':multicast_in:AVERAGE DEF:b='{$buildname}':multicast_out:AVERAGE ";
            $rrdcmd .= 'AREA:a#FFF200FF:"Multicast In" GPRINT:a:LAST:" Cur\:%8.2lf %s" GPRINT:a:AVERAGE:"Ave\:%8.2lf %s" GPRINT:a:MAX:"Max\:%8.2lf %s\n" ';
            $rrdcmd .= 'LINE1:b#00234BFF:"Multicast Out" GPRINT:b:LAST:"Cur\:%8.2lf %s" GPRINT:b:AVERAGE:"Ave\:%8.2lf %s" GPRINT:b:MAX:"Max\:%8.2lf %s\n"';
            break;
        case "bcastpkts":
            # Broadcast packets
            $rrdcmd = "{$path_rrdtool} graph - --imgformat=PNG --font TITLE:8: --start={$start} --end={$end} --title=\"{$titlename} - broadcast packets/sec\" ";
            $rrdcmd .= "--rigid --vertical-label='packets per second' --slope-mode --height={$height} --width={$width} --lower-limit=0 ";
            $rrdcmd .= "DEF:a='{$buildname}':broadcast_in:AVERAGE DEF:b='{$buildname}':broadcast_out:AVERAGE ";
            $rrdcmd .= 'AREA:a#99B898FF:"Broadcast In" GPRINT:a:LAST:" Cur\:%8.2lf %s" GPRINT:a:AVERAGE:"Ave\:%8.2lf %s" GPRINT:a:MAX:"Max\:%8.2lf %s\n" ';
            $rrdcmd .= 'LINE1:b#00234BFF:"Broadcast Out" GPRINT:b:LAST:"Cur\:%8.2lf %s" GPRINT:b:AVERAGE:"Ave\:%8.2lf %s" GPRINT:b:MAX:"Max\:%8.2lf %s\n"';
            break;
    }

    return $rrdcmd;
}

function purgeOld($loglevel) {
    global $purgeage, $path_rrd;

    if ($purgeage == 0) {
        logline("PURGER: Purge has been disabled in the config. Quitting now. ", 0, $loglevel);
        return false;
    }
    $unixpurgeage = time() - $purgeage;
    logline("PURGER: Beginning purge of old RRD files that have since gone down", 0, $loglevel);
    logline("PURGER: Your purge age is set to delete any RRDs/ports older than " . _ago($unixpurgeage), 1, $loglevel);

    connectToDB();
    $findold = "SELECT * FROM ports WHERE lastpoll < {$unixpurgeage}";
    $results = mysql_query($findold);

    $numresults = mysql_num_rows($results);
    if($numresults > 0) {
        logline("PURGER: Found {$numresults} candidate old RRDs/ports for deletion (older than " . _ago($unixpurgeage) . ")", 0, $loglevel);
         while ($row = mysql_fetch_assoc($results)) {
            logline("PURGER: Deleting '{$row['name']}' from host '{$row['host']}' and graphtype '{$row['graphtype']}' from the database", 2, $loglevel);
            $filetodelete = "{$path_rrd}{$row['host']}/{$row['host']}-{$row['safename']}_{$row['graphtype']}.rrd";
            logline("PURGER: Deleting '{$filetodelete}'. ", 2, $loglevel);
            unlink($filetodelete);
            logline("PURGER: Deleting row for '{$row['name']}' from the database", 2, $loglevel);
            $deleterow = 'DELETE FROM ports WHERE host="' . $row['host']. '" AND safename="'. $row['safename'] .'" AND graphtype="' . $row['graphtype']. '"';
            mysql_query($deleterow);
            logline("PURGER: Done deleting '{$row['name']}' ", 1, $loglevel);
         }
    } else {
        logline("PURGER: No RRDs/ports were older than " . _ago($unixpurgeage), 0, $loglevel);
    }
    return true;

}

function getAllGraphTypesInUse() {
    global $pollhosts;

    $graphsarray = array();
    foreach($pollhosts as $thishost) {
        $graphsarray = array_merge($thishost['graphtypes'], $graphsarray);
    }
    $graphsarray = array_unique($graphsarray);
    return $graphsarray;
}

function logline($message, $messverbose, $reqverbose) {
    # Prints a log message if the message verbosity is in the requested range
    if ($reqverbose >= $messverbose) {
        echo date(DATE_RFC822) . " " . $message . "\n";
    }
}

function connectToDB() {
    global $mysql_host, $mysql_user, $mysql_pass, $mysql_db;
    if(function_exists(mysql_connect)) {
        if(!mysql_connect($mysql_host, $mysql_user, $mysql_pass)) {
            return false;
        }
        if(!mysql_select_db($mysql_db)) {
            return false;
        }
        return true;
    } else {
        echo "MySQL support is required";
        return false;
    }
}

function htmlHostsInConfig() {
    global $pollhosts;

    # Prints HTML output of all the switches in the config
    echo "<ul id=\"navlinks\">";
    foreach ($pollhosts as $thishost) {
        if($thishost['showoninterface'] == true) {
            echo '<li><a href="viewhost.php?host=' . $thishost['prettyname'] . '">' . $thishost['prettyname'] . '</a>';
            foreach ($thishost['graphtypes'] as $thistype) {
                echo '<ul>';
                echo '<li><a href="viewhost.php?host=' . $thishost['prettyname'] . '&type=' . $thistype . '">' . $thistype . '</a></li>';
                echo '</ul>';
            }
            echo '</li>';
        }
    }
    echo "</ul>";

}

function htmlLastPollerTime() {
    global $path_rrd;
    
    if (is_readable($path_rrd . "lastpolltime.txt")) { 
        $lastpolltime = file_get_contents($path_rrd . "lastpolltime.txt");
        if ((time() - $lastpolltime) > 400) {
            $color = '<span class="red">'; 
        } else {
            $color = '<span>';
        }
        echo "Last poll time: {$color}" . _ago($lastpolltime) . " ago</span>";
    } else {
        echo '<span class="red">Never polled or permissions issue</span>';
    }
}

function htmlNumberOfGraphs() {
    if(connectToDB()) {
        $results = mysql_query("SELECT COUNT(*) from ports;");
        $ports = mysql_result($results, 0);
        echo "{$ports} graphs";
    } else {
        echo " Connect to database failed, are your MySQL details correct? ";
    }
}

function htmlNumberOfHosts() {
    if(connectToDB()) {
        $results = mysql_query("SELECT COUNT(DISTINCT(host)) from ports;");
        $hosts = mysql_result($results, 0);
        echo "{$hosts} hosts";
    }
}

function _ago($tm,$rcs = 0) {
   $cur_tm = time(); $dif = $cur_tm-$tm;
   $pds = array('second','minute','hour','day','week','month','year','decade');
   $lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);
   for($v = sizeof($lngh)-1; ($v >= 0)&&(($no = $dif/$lngh[$v])<=1); $v--); if($v < 0) $v = 0; $_tm = $cur_tm-($dif%$lngh[$v]);

   $no = floor($no); if($no <> 1) $pds[$v] .='s'; $x=sprintf("%d %s ",$no,$pds[$v]);
   if(($rcs == 1)&&($v >= 1)&&(($cur_tm-$_tm) > 0)) $x .= time_ago($_tm);
   return $x;
}

function snmptable($host, $community, $oid) {
    # This handy function was bought to you by scot at indievisible dot org
    # Found on the PHP.net documentation page for snmprealwalk.

    # The important thing about this function is that it fills in the blanks.
    # Regular SNMP walks leave out items so you can't blindly prod things into arrays any more. 

    snmp_set_oid_numeric_print(TRUE);
    snmp_set_quick_print(TRUE);
    snmp_set_enum_print(TRUE);

    $retval = array();
    if(!$raw = snmp2_real_walk($host, $community, $oid)) {
        return false;
    }
    if (count($raw) == 0) return false; // no data

    $prefix_length = 0;
    $largest = 0;
    foreach ($raw as $key => $value) {
        if ($prefix_length == 0) {
            // don't just use $oid's length since it may be non-numeric
            $prefix_elements = count(explode('.',$oid));
            $tmp = '.' . strtok($key, '.');
            while ($prefix_elements > 1) {
                $tmp .= '.' . strtok('.');
                $prefix_elements--;
            }
            $tmp .= '.';
            $prefix_length = strlen($tmp);
        }
        $key = substr($key, $prefix_length);
        $index = explode('.', $key, 2);
        isset($retval[$index[1]]) or $retval[$index[1]] = array();
        if ($largest < $index[0]) $largest = $index[0];
        $retval[$index[1]][$index[0]] = $value;
    }

    if (count($retval) == 0) return false; // no data

    // fill in holes and blanks the agent may "give" you
    foreach($retval as $k => $x) {
        for ($i = 1; $i <= $largest; $i++) {
        if (! isset($retval[$k][$i])) {
                $retval[$k][$i] = '';
            }
        }
        ksort($retval[$k]);
    }
    return($retval);
}
