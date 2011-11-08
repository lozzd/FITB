<div id="header">
    <div id="headside">
        <h1><a href="index.php">FITB</a></h1>
    </div>
    <div id="headright">
        <div class="headerinfo"><?php htmlLastPollerTime() ?>, <?php htmlNumberOfGraphs() ?>, <?php htmlNumberOfHosts() ?></div>
        <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="get">
            <?php 
                foreach($_GET as $getvar => $getcontent) {
                    if ($getvar != "duration") {
                        echo '<input type="hidden" name="' . $getvar . '" value="' . $getcontent . '">';
                    }
                }
            ?>
            <select name="duration" onchange="this.form.submit();">
                <option value="">Time period</option>
                <?php 
                    foreach($configtimeperiods as $periodnum => $periodname) {
                        $selected = "";
                        if ($periodnum == $_GET['duration']) {
                            $selected = "selected";
                        }
                        echo '<option ' . $selected . ' value="' . $periodnum . '">' . $periodname . '</option>';
                    }
                ?>
           </select>
           <select name="autorefresh" onchange="this.form.submit();">
                <option value="0">Auto Refresh</option>
                <option value="60">1 minute</option>
                <option value="120">2 minutes</option>
                <option value="300">5 minutes</option>
                <option value="600">10 minutes</option>
                <option value="1800">30 minutes</option>
           </select>
        </form>
        <?php 
        # If there's an autorefresh get variable, we want the page to automatically refresh. 
        # 
                if ($_GET['autorefresh'] != "" && $_GET['autorefresh'] != "0") {
                    echo '<meta http-equiv="refresh" content="' . $_GET['autorefresh'] . '">';
                }
                
        ?>
        <form action="search.php" style="margin-left:10px">
            <label>Search</label>
            <input type="text" name="query" value="<?php echo $_GET['query'] ?>">
            <?php
                # A graph type box, only if we have different graph types in use right now
                $graphtypes = getAllGraphTypesInUse();
                if (count($graphtypes) > 0) {
                    echo '<select name="type" onchange="this.form.submit();">';
                    echo '<option value="">Type (all)</option>';
                    foreach ($graphtypes as $thistype) {
                        $selected = "";
                        if ($_GET['type'] == $thistype) {
                            $selected = "selected";
                        }
                        echo '<option ' . $selected . ' value="'. $thistype .'">' . $thistype . '</option>';
                    }
                    echo '</select>';
                }
                # A host box, incase you only want to search on one host
                echo '<select name="host" onchange="this.form.submit();">';
                echo '<option value="">Host (all)</option>';
                foreach ($pollhosts as $hostname => $a) {
                    $selected = "";
                    if ($_GET['host'] == $hostname) {
                        $selected = "selected";
                    }
                    echo '<option ' . $selected . ' value="' . $hostname . '">' . $hostname . '</option>';
                }
                echo '</select>';
                
                # Include the duration from this page load so the zoomed time frame doesn't suddenly
                # change when we change any filters in this form. 
                foreach($_GET as $getvar => $getcontent) {
                    if ($getvar == "duration") {
                        echo '<input type="hidden" name="' . $getvar . '" value="' . $getcontent . '">';
                    }
                }
 
            ?>
        </form>
    </div>
</div>
