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
    global $path_rrdtool, $path_rrd, $RRA_average, $RRA_max;

    # The RRA configuration is pulled in from the config.php now, but there are still defaults set 
    # here for people who are using older installations. 
    if (!isset($RRA_average)) {
        $RRA_average = "RRA:AVERAGE:0.5:1:1209600 RRA:AVERAGE:0.5:24:244 RRA:AVERAGE:0.5:168:244 RRA:AVERAGE:0.5:672:244 RRA:AVERAGE:0.5:5760:1827 ";
    }
    if (!isset($RRA_max)) {
        $RRA_max = "RRA:MAX:0.5:1:1209600 RRA:MAX:0.5:24:244 RRA:MAX:0.5:168:244 RRA:MAX:0.5:672:244 RRA:MAXx:0.5:5760:1827";
    }
    $RRAdef = $RRA_average;
    $RRAdef .= $RRA_max;

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
        $cmd = "{$path_rrdtool} create {$path_rrd}{$rrdfolder}/{$rrdname} --step 60 --start {$oneminuteago} {$datasources} {$RRAdef}";
        exec($cmd, $rrdoutput, $rrdreturn);
        if ($rrdreturn != 0) {
            echo "RRD cmd: " . $cmd . " failed: " . print_r($rrdoutput) . "\n";
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

function getSubtypesForGraphType($type) {
    switch($type) {
        case 'bits':
            return array('bits_in', 'bits_out');
        case 'ucastpkts':
            return array('ucastpkts_in', 'ucastpkts_out');
        case 'errors':
            return array('discards_in', 'errors_in', 'discards_out', 'errors_out');
        case 'mcastpkts':
            return array('mcastpkts_in', 'mcastpkts_out');
        case 'bcastpkts':
            return array('bcastpkts_in', 'bcastpkts_out');

    }
    return array($type);
}

function getDefaultSubtypeForGraphType($type) {
    $subtypes = getSubtypesForGraphType($type);
    return $subtypes[0];
}

function getGraphCmd($rrdname, $rrdfolder, $type, $start = "-86400", $end = "-60", $height = "120", $width = "500", $friendlytitle = "") {
    $graphs_array = array();
    $subtypes = getSubtypesForGraphType($type);

    foreach ($subtypes as $subtype) {
        $graphs_array[] = array(
            'rrdname' => $rrdname,
            'rrdfolder' => $rrdfolder,
            'subtype' => $subtype
        );
    }
    return getStackedGraphsCmd($graphs_array, $type, false, $start, $end, $height, $width, $friendlytitle);
}

function getStackedGraphsCmd($graphs_array, $type, $stack = false, $start = "-86400", $end = "-60", $height = "120", $width = "500", $friendlytitle = "") {
    global $path_rrdtool, $path_rrd;

    $type_to_title = array('bits' => 'bits/sec', 'ucastpkts' => 'unicast packets/sec', 'errors' => 'Errors/sec', 'mcastpkts' => 'multicast packets/sec', 'bcastpkts' => 'broadcast packets/sec');
    $type_to_label = array('bits' => 'bits per second', 'ucastpkts' => 'packets per sec', 'errors' => 'errors per sec', 'mcastpkts' => 'packets per sec', 'bcastpkts' => 'packets sec');
    if ($friendlytitle == "") {
        $f = function($graph) { 
            return $graph['rrdname'];
        };
        $titlename = implode(' | ', array_map($f, $graphs_array));
    } else {
        $titlename = $friendlytitle;
    }
    $title = str_replace('"', '\"', $titlename . ' - ' . $type_to_title[$type]);

    $idx = 0;
    $data_cmds = '';
    foreach ($graphs_array as $graph) {
        if (isset($graph['opts'])) {
            $opts = $graph['opts'];
        } else {
            $opts = array();
        }

        # Only generate graph commands for RRD files that exist, in case the ports go down later on (or don't yet exist) 
        if (file_exists("{$path_rrd}{$graph['rrdfolder']}/{$graph['rrdname']}_{$type}.rrd")) {
            $data_cmds .= " " . getCommandForRRD(
                $graph['rrdname'],
                $graph['rrdfolder'],
                $type,
                $graph['subtype'],
                $opts,
                $stack,
                $idx++
            );
        }
    }

    $rrd_cmd = "{$path_rrdtool} graph - --imgformat=PNG --font TITLE:8: --start={$start} --end={$end} --title=\"{$title}\" ";
    $rrd_cmd .= "--rigid --vertical-label='{$type_to_label[$type]}' --slope-mode --height={$height} --width={$width} --lower-limit=0 ";
    $rrd_cmd .= $data_cmds;
    return $rrd_cmd;
}

function getDataColumnAndLabel($subtype) {
    switch($subtype) {
        case "bits_in": return array('data_column' => 'traffic_in', 'data_label' => 'Inbound ');
        case "bits_out": return array('data_column' => 'traffic_out', 'data_label' => 'Outbound');
        case "ucastpkts_in": return array('data_column' => 'unicast_in', 'data_label' => 'Unicast In ');
        case "ucastpkts_out": return array('data_column' => 'unicast_out', 'data_label' => 'Unicast Out');
        case "errors_in": return array('data_column' => 'errors_in', 'data_label' => 'Errors In   ');
        case "errors_out": return array('data_column' => 'errors_out', 'data_label' => 'Errors Out  ');
        case "discards_in": return array('data_column' => 'discards_in', 'data_label' => 'Discards In ');            
        case "discards_out": return array('data_column' => 'discards_out', 'data_label' => 'Discards Out');
        case "mcastpkts_in": return array('data_column' => 'multicast_in', 'data_label' => 'Multicast In ');
        case "mcastpkts_out": return array('data_column' => 'multicast_out', 'data_label' => 'Multicast Out');
        case "bcastpkts_in": return array('data_column' => 'broadcast_in', 'data_label' => 'Broadcast In ');
        case "bcastpkts_out": return array('data_column' => 'broadcast_out', 'data_label' => 'Broadcast Out');
    }
}

function getDefaultColor($type, $idx, $stack) {
    if ($stack) {
        // these should match the colors in fitb.js
        $colors = array(
            'bits' => array('#0A2868', '#FFCA00', '#EC4890', '#517CD7', '#00C169', '#D1F94C'),
            'ucastpkts' => array('#2008E6', '#D401E2', '#00DFD6', '#08004E'),
            'errors' => array('#30B6C9', '#FFFE39', '#AD34CF', '#09616D'),
            'mcastpkts' => array('#53B0B8', '#7E65C7', '#C2F2C6', '#FFB472'),
            'bcastpkts' => array('#FFEF9F', '#C17AC3', '#7FA1C3', '#AA9737')
        );
    } else {
        $colors = array(
            'bits' => array('#00CF00FF', '#002A97FF', '#C4FD3DFF', '#00694AFF'),
            'ucastpkts' => array('#FFF200FF', '#00234BFF', '#C4FD3DFF', '#00694AFF'),
            'errors' => array('#FFAB00FF', '#F51D30FF', '#C4FD3DFF', '#00694AFF'),
            'mcastpkts' => array('#FFF200FF', '#00234BFF', '#C4FD3DFF', '#00694AFF'),
            'bcastpkts' => array('#99B898FF', '#00234BFF', '#C4FD3DFF', '#00694AFF')
        );
    }
    return $colors[$type][$idx % count($colors[$type])];
}

function getCommandForRRD($rrdname, $rrdfolder, $type, $subtype, $opts, $stack, $idx = 0) {
    global $path_rrd;

    $buildname = "{$path_rrd}{$rrdfolder}/{$rrdname}_{$type}.rrd";

    if (isset($opts['graphing_method']) && $opts['graphing_method'] != '') {
        $gm = $opts['graphing_method'];
    } else {
        if (!$stack && ($idx > 0 || $type == 'errors')) {
            $gm = 'LINE1';
        } else {
            $gm = 'AREA';
        }
    }

    if (isset($opts['color']) && $opts['color'] != '') {
        $color = $opts['color'];
    } else {
        $color = getDefaultColor($type, $idx, $stack);
    }

    $data_column_label = getDataColumnAndLabel($subtype);
    $data_column = $data_column_label['data_column'];

    if (isset($opts['custom_label']) && $opts['custom_label'] != '') {
        $data_label = $opts['custom_label'];
    } else {
        // if the graphs are stacked, then they'd all have the same label. lets just use the rrd name by default.
        if ($stack) {
            $data_label = $rrdname;
        } else {
            $data_label = $data_column_label['data_label'];
        }
    }
    $data_label = str_replace('"', '\"', $data_label);

    $def = "a{$idx}"; //avoid collisions by using idx to name data
    $rrdcmd = "DEF:{$def}='{$buildname}':{$data_column}:AVERAGE ";
    $data_key = $def;
    if ($type == "bits") {
        $cdef = "${def},8,*"; // bits/sec
        $rrdcmd .= "CDEF:cdef{$def}={$cdef} ";
        $data_key = "cdef{$def}";
    }
    
    if ($stack) { 
        $stk = ":STACK";
    } else {
        $stk = "";
    }
    $rrdcmd .= "{$gm}:{$data_key}{$color}:\"{$data_label}\"{$stk} ";

    $rrdcmd .= "GPRINT:{$data_key}:LAST:\"Curr\:%8.2lf %s\" ";
    $rrdcmd .= "GPRINT:{$data_key}:AVERAGE:\"Ave\:%8.2lf %s\" ";
    if ($type == 'bits')  { 
        $rrdcmd .= "GPRINT:{$data_key}:MIN:\"Min\:%8.2lf %s\" ";
    }
    $rrdcmd .= "GPRINT:{$data_key}:MAX:\"Max\:%8.2lf %s\\n\" ";
    
    return $rrdcmd;
}

function getAggregateGraphsArrayFromRequest($request_data) {
    $rrdnames = explode('|', $request_data['rrdname']);
    $hosts = explode('|', $request_data['host']);
    $type = $request_data['type'];

    $graph_count = count($rrdnames);

    if (isset($request_data['subtype'])) {
        $subtypes = explode('|', $request_data['subtype']);
        if (count($subtypes) < $graph_count) {
            $subtypes = array_merge($subtypes, array_fill(count($subtypes) - 1, $graph_count - count($subtypes), getDefaultSubtypeForGraphType($type)));
        }
    } else {
        $subtypes = array_fill(0, $graph_count, getDefaultSubtypeForGraphType($type));
    }

    $colors = array();
    if (isset($request_data['color'])) {
        $colors = explode('|', $request_data['color']);
        if (count($colors) < $graph_count) {
            $colors = array_merge($colors, array_fill(count($colors) - 1, $graph_count - count($colors), ''));
        }
    }
    
    $graphing_methods = array();
    if (isset($request_data['graphing_method'])) {
        $graphing_methods = explode('|', $request_data['graphing_method']);
        if (count($graphing_methods) < $graph_count) {
            $graphing_methods = array_merge($graphing_methods, array_fill(count($graphing_methods) - 1, $graph_count - count($graphing_methods), ''));
        }
    }

    $custom_labels = array();
    if (isset($request_data['custom_label'])) {
        $custom_labels = explode('|', $request_data['custom_label']);
        if (count($custom_labels) < $graph_count) {
            $custom_labels = array_merge($custom_labels, array_fill(count($custom_labels) - 1, $graph_count - count($custom_labels), ''));
        }
    }

    if (count($rrdnames) == $graph_count && count($hosts) == $graph_count) {
        $graphs_array = array();
        foreach (range(0, $graph_count - 1) as $i) {
            $graphs_array[] = array(
                'rrdname' => $rrdnames[$i],
                'rrdfolder' => $hosts[$i],
                'subtype' => ($subtypes[$i] == "" ? getDefaultSubtypeForGraphType($type) : $subtypes[$i]),
                'opts' => array()
            );
            if (count($colors) > $i) {
                $graphs_array[$i]['opts']['color'] = $colors[$i];
            }
            if (count($graphing_methods) > $i) {
                $graphs_array[$i]['opts']['graphing_method'] = $graphing_methods[$i];
            }
            if (count($custom_labels) > $i) {
                $graphs_array[$i]['opts']['custom_label'] = $custom_labels[$i];
            }
        }
        return $graphs_array;
    }
    return null;   
}

function saveAggregate($graphs_array, $meta) {
    if(count($graphs_array) < 1) return false;
    $link = connectToDB();
    if(!$link) return false;

    $res = mysqli_query($link, 'START TRANSACTION');

    $friendlytitle = $meta['friendlytitle'] ? $meta['friendlytitle'] : null;
    $type = $meta['type'];
    $stack = $meta['stack'] !== false ? '1' : '0';
    $fields = array(
        $friendlytitle ? "'" . mysqli_real_escape_string($link, $friendlytitle) . "'" : 'NULL',
        "'" . mysqli_real_escape_string($link, $type) . "'",
        "'" . mysqli_real_escape_string($link, $stack) . "'"
    );
    $query = "INSERT INTO aggregates (friendlytitle, type, stack) VALUES (" . implode(',', $fields) . ")";
                
    $inserted = mysqli_query($link, $query);

    if ($inserted) {
        $agg_id = mysqli_insert_id($link);

        $inserts = array();
        foreach ($graphs_array as $g) {
            $options = 'NULL';
            if ($g['opts'] != null) {
                $options = mysqli_real_escape_string($link, json_encode($g['opts']));
            }

            $fields = array(
                $agg_id,
                "'" . mysqli_real_escape_string($link, $g['rrdfolder']) . "'",
                "'" . mysqli_real_escape_string($link, $g['rrdname']) . "'",
                $g['subtype'] ? "'" . mysqli_real_escape_string($link, $g['subtype']) . "'" : 'NULL',
                $g['opts'] ? "'" . mysqli_real_escape_string($link, json_encode($g['opts'])) . "'" : 'NULL'
            );
            $inserts[] = '(' . implode(',', $fields) . ')';
        }
        $query = "INSERT INTO aggregate_parts (aggregate_id, host, rrdname, subtype, options) VALUES " . implode(',', $inserts);
        $inserted = mysqli_query($link, $query);
    }

    if ($inserted) {
        $inserted = mysqli_query($link, 'COMMIT');
        return $agg_id;
    } else {
        $res = mysqli_query($link, 'ROLLBACK');
    }
    return null;
}

function deleteAggregate($aggregate_id) {
    if (!$link=connectToDB()) {
        return false;
    }
    
    $res = mysqli_query($link, 'START TRANSACTION');
    
    $query = 'DELETE FROM aggregates WHERE aggregate_id = "'.mysqli_real_escape_string($link, $aggregate_id).'"';
    $deleted = mysqli_query($link, $query);
    
    if ($deleted) {
        $query = 'DELETE FROM aggregate_parts WHERE aggregate_id = "'.mysqli_real_escape_string($link, $aggregate_id).'"';
        $deleted = mysqli_query($link, $query);
    }
    
    if ($deleted) {
        $deleted = mysqli_query($link, 'COMMIT');
    } else {
        $res = mysqli_query($link, 'ROLLBACK');
    }
    return $deleted;
}

function getAggregateData($agg_id) {
    $link=connectToDB();

    $result = mysqli_query($link, 'SELECT * FROM aggregates WHERE aggregate_id='.mysqli_real_escape_string($link, $agg_id));
    if (mysqli_num_rows($result) == 1) {
        $agg = mysqli_fetch_assoc($result);
        $meta = array(
            'friendlytitle' => !is_null($agg['friendlytitle']) ? $agg['friendlytitle'] : '',
            'type' => $agg['type'],
            'stack' => $agg['stack'] == '1'
        );

        $result = mysqli_query($link, 'SELECT * FROM aggregate_parts WHERE aggregate_id='.mysqli_real_escape_string($link, $agg_id));
        $graphs_array = array();
        if (mysqli_num_rows($result) > 0) {
            while ($part = mysqli_fetch_assoc($result)) {
                $graphs_array[] = array(
                    'rrdfolder' => $part['host'],
                    'rrdname' => $part['rrdname'],
                    'subtype' => $part['subtype'],
                    'opts' => json_decode($part['options'], true)
                );
            }
        }
        return array('graphs_array' => $graphs_array, 'meta' => $meta);
    }

    return null;
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

    $link=connectToDB();
    $findold = "SELECT * FROM ports WHERE lastpoll < {$unixpurgeage}";
    $results = mysqli_query($link, $findold);

    $numresults = mysqli_num_rows($results);
    if($numresults > 0) {
        logline("PURGER: Found {$numresults} candidate old RRDs/ports for deletion (older than " . _ago($unixpurgeage) . ")", 0, $loglevel);
         while ($row = mysqli_fetch_assoc($results)) {
            logline("PURGER: Deleting '{$row['name']}' from host '{$row['host']}' and graphtype '{$row['graphtype']}' from the database", 2, $loglevel);
            $filetodelete = "{$path_rrd}{$row['host']}/{$row['host']}-{$row['safename']}_{$row['graphtype']}.rrd";
            logline("PURGER: Deleting '{$filetodelete}'. ", 2, $loglevel);
            unlink($filetodelete);
            logline("PURGER: Deleting row for '{$row['name']}' from the database", 2, $loglevel);
            $deleterow = 'DELETE FROM ports WHERE host="' . $row['host']. '" AND safename="'. $row['safename'] .'" AND graphtype="' . $row['graphtype']. '"';
            mysqli_query($link, $deleterow);
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
    static $link = false;
    if(!$link) {
        $link = mysqli_connect($mysql_host, $mysql_user, $mysql_pass);
        mysqli_select_db($link, $mysql_db);
    }
    return $link;
}

function getAllEnabledHosts() {
    global $pollhosts;
    $isEnabled = function($host) {
        return $host['enabled'];
    };
    return array_filter($pollhosts, $isEnabled);
}

function getPortsForHostAndType($host, $type) {
    $ports = array();
    if($link=connectToDB()) {
        $result = mysqli_query($link, 'SELECT * FROM ports WHERE host like "%' . mysqli_real_escape_string($link, $host). '%" AND graphtype like "%' . mysqli_real_escape_string($link, $type) . '%" ORDER BY lastpoll DESC, safename ASC');
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $ports[] = $host . '-' . $row["safename"];
            }
        }
        return $ports;
    }
    return array();
}

function htmlHostsInConfig() {
    # Prints HTML output of all the switches in the config
    echo "<ul id=\"navlinks\">";
    echo '<li><a href="aggregates.php">aggregate graphs</a>';
    $currentHost = @$_GET['host'];
    foreach (getAllEnabledHosts() as $thishost) {
        if($thishost['showoninterface'] == true) {
            echo '<li><a href="viewhost.php?host=' . $thishost['prettyname'] . '">' . $thishost['prettyname'] . '</a>';
            if ($currentHost == $thishost['prettyname']) {
                echo '<ul>';
            } else {
                echo '<ul style="display: none;">';
            }
            foreach ($thishost['graphtypes'] as $thistype) {
                echo '<li><a href="viewhost.php?host=' . $thishost['prettyname'] . '&type=' . $thistype . '">' . $thistype . '</a></li>';
            }
            echo '</ul>';
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
    if($link=connectToDB()) {
        $results = mysqli_query($link, "SELECT COUNT(*) from ports;");
        $ports = mysqli_fetch_row($results);
        echo "{$ports[0]} graphs";
    } else {
        echo " Connect to database failed, are your MySQL details correct? ";
    }
}

function htmlNumberOfHosts() {
    if($link=connectToDB()) {
        $results = mysqli_query($link, "SELECT COUNT(DISTINCT(host)) from ports;");
        $hosts = mysqli_fetch_row($results);
        echo "{$hosts[0]} hosts";
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
    snmp_set_valueretrieval(SNMP_VALUE_PLAIN );
    snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

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

