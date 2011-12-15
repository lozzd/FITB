<?php 

# This is the daddy poller. Launch it every 60 seconds, preferably from cron, and it will fire off a 
# worker for every host that is enabled for polling. 
#

include_once('functions.php');

# Check the master kill switch
if ($pollingenabled != true) {
    logline("Polling has been disabled. Not going to run this time. Check config.php to enable. ", 0, $verbose);
    exit();
}

logline("MASTER: Starting to spawn polling children", 0, $verbose);

# Spawn a new poller child process for every host in the config
foreach ($pollhosts as $thishost) {
    # But only the ones that are enabled!
    if ($thishost['enabled'] == true) {
        logline("MASTER: Spawing polling child for {$thishost['prettyname']}", 1, $verbose);
        $cwd = dirname(__FILE__);
        $prettyname = escapeshellarg($thishost['prettyname']);
        $command = "nohup /usr/bin/env php -f {$cwd}/poller_child.php {$prettyname} >> {$cwd}/poller.log 2>&1 & echo $!";
        $pid = shell_exec($command);
        logline("MASTER: Child spawned, PID is {$pid}", 1, $verbose);
    } else {
        logline("MASTER: Not spawning polling child for {$thishost['prettyname']}, is disabled in config.php", 1, $verbose);
    }
}

logline("MASTER: Done spawning polling children", 0, $verbose);
logline("MASTER: Writing last poll time", 2, $verbose);
if (!file_put_contents($path_rrd . "lastpolltime.txt", time())) {
    logline("MASTER: Warning! Could not write last poll time, check directory perms", 0, $verbose);
}

# Execute cleanup job to purge any ports and RRD files older than $purgeage in the config
purgeOld($verbose);

logline("MASTER: Going away now. Be back later. ", 2, $verbose);
