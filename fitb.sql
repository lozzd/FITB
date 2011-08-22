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
