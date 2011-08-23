# FITB

## What is FITB?

__FITB__ (_fit-bee_) or __"Fill in the blank"__ is a PHP and RRDtool based web interface designed to make polling every
switch or router on your network easier. Think Cacti but simpler and automated. 

## Features

FITB automatically polls every port on a list of switches you give it. It's feature list includes:

* Simple configuration: 1 line to add a new switch, just give it a name, address and SNMP community
* Precise polling: 1 minute poll intervals to make sure you never miss a spike in your network
* Easy searching: Search both interface aliases and names, and filter down by graph type and host
* Automatic discovery: FITB finds every port in use on the switch and graphs it, and stops when it goes down. 

Screenshots and a guide to FITB are available here: [http://www.flickr.com/photos/lozzd/sets/72157627375145065](http://www.flickr.com/photos/lozzd/sets/72157627375145065)

## Prerequisites
* A webserver
* PHP (including CLI)
* RRDtool
* MySQL
* Cron (to run the poller, although you can run by hand/in a loop)
* Some network switches supporting the standard network MIBs and SNMPv2

## Installation
1. Download/clone the repo into an appropriate folder either in your webservers directory or symlinked to it
2. Create a new database for FITB to keep it's state in. For example:

        mysql> create database fitb;
        mysql> grant all on fitb.* to fitbuser@localhost IDENTIFIED BY 'f1tbP4ss';

3. Load the database structure into your database:

        # mysql fitb < fitb.sql

4. Create a directory for your RRD files. For example, /var/lib/fitb/rrds/
    
    Note: Make sure this directory is writeable by the user you wish to run the poller as. Either as you, or create
    a new user just for FITB. Your webserver only needs read only access. 
5. Edit config.php with your favourite editor and set the database connection information, and the path you created 
for your RRDs. 
6. At this point you should be able to load FITB in your browser without errors. Reward yourself with a beverage. 

## Configuring switches

Configuring switches in FITB is designed to be as painless as possible. The procedure is as follows:

1. Open config.php
2. Copy a previous (or the example) line and edit it
3. Save the file. The next time the poller runs (or when you run your poller) the graphs will be created. 

The config line per switch is made up of the following:

    "switchname" => array("prettyname" => "switchname", "enabled" => true, "showoninterface" => true, "ip" => "switchname.yourcompany.com", "snmpcommunity" => "public", "graphtypes" => array('bits','ucastpkts','errors')),

* switchname - The name used for the config file. 
* prettyname - Generally keep this the same as above. Keep it simple.. It is used for the filename and in the interface
* enabled - True/false: Disables or enables polling of this host
* showoninterface - True/false: Allows you to hide a switch from the interface menus
* ip - The hostname or IP address of the host
* snmpcommunity - The SNMP community of the host
* graphtypes - An array of the types of graphs you want for this host. Currently supported:
    * bits - bits/sec, in and out. 
    * ucastpkts - Unicast packets/sec, in and out.
    * errors - Errors/sec in and out, and discards/sec in and out
    * mcastpkts - Multicast packets/sec, in and out. 
    * bcastpkts - Broadcast packets/sec, in and out

## Setting up polling

FITB is designed to poll every minute. To achieve this, I recommend setting up the poller parent to run in your favourite
crond on your host. 

For example:

    */1 * * * * /usr/bin/php /var/www/fitb/poller.php >> /var/www/fitb/poller.log 2>&1

The poller will spawn a child for every host in its configuration, and also run a cleanup job for any graphs due to be purged 
(purging of out of date/stale graphs is controlled from the config).

The poller child grabs the information for every port on that host, and if the port is in an UP state, creates/updates that 
port's graph. It also updates the port's alias if it has changed. 

If a port goes down, it becomes marked as STALE and it will be pushed to the bottom of the interface. There is a configurable
purge which also removes the graphs from the interface completely and removes the file from disk if it passes a certain age,
thus keeping your disk clean of out of date ports. 

## The FITB interface

I highly suggest checking the [screenshot guide](http://www.flickr.com/photos/lozzd/sets/72157627375145065) for information on how the interface is laid out

The interface is designed to be as simple as possible: A small status line in the top right, along with the search function,
and a list of hosts down the left. 

As you drill down through the different graph types and hosts, the search filters in the top right change automatically. 
This means at any point you can expand or filter down your search to find the port you want quickly. 

The search function allows for searching of both interface names and aliases, and the filter drop downs (graph type/host)
update the view instantly. 

There is a time period drop down in the top right that affects all the graphs in the current view. Due to FITB's 1 minute
polling that means you can go down to a 5 minute view of any port. 


## Known issues/limitations
* Your hosts must support SNMPv2, SNMPv1 did not have the information/resolution I required so it was written with v2
in mind. most switches support this though. 
* Keep this inside your network. I can't be held responsible for massive security holes. 
