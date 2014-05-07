CREATE TABLE `ports` (
      `host` varchar(255) NOT NULL,
      `name` varchar(255) NOT NULL,
      `safename` varchar(255) NOT NULL,
      `filename` varchar(255) NOT NULL,
      `alias` text NOT NULL,
      `graphtype` varchar(255) NOT NULL,
      `lastpoll` int(10) unsigned NOT NULL,
      PRIMARY KEY  (`filename`),
      KEY `host_name` (`host`),
      KEY `port_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aggregates` (
    `aggregate_id` int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `friendlytitle` varchar(255) DEFAULT NULL,
    `type` varchar(255) NOT NULL,
    `stack` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aggregate_parts` (
    `aggregate_part_id` int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `aggregate_id` int(11) unsigned NOT NULL,
    `host` varchar(255) NOT NULL,
    `rrdname` varchar(255) NOT NULL,
    `subtype` varchar(255) DEFAULT NULL,
    `options` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `altgraphs` (
      `host` varchar(255) NOT NULL,
      `name` varchar(255) NOT NULL,
      `safename` varchar(255) NOT NULL,
      `filename` varchar(255) NOT NULL,
      `alias` text NOT NULL,
      `graphtype` varchar(255) NOT NULL,
      `lastpoll` int(10) unsigned NOT NULL,
      PRIMARY KEY  (`filename`),
      KEY `host_name` (`host`),
      KEY `port_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;          
