CREATE TABLE IF NOT EXISTS `data` (
  `arrival` date NOT NULL,
  `departure` date NOT NULL,
  `country` varchar(2) NOT NULL,
  `adults` int(11) NOT NULL,
  `children` int(11) NOT NULL,
  `destination` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `booking` tinyint(1) NOT NULL,
  `cancellation` tinyint(1) NOT NULL,
  `submitted_on` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `data`
  ADD KEY `category` (`category`),
  ADD KEY `arrival` (`arrival`,`departure`),
  ADD KEY `country` (`country`),
  ADD KEY `adults` (`adults`),
  ADD KEY `children` (`children`),
  ADD KEY `destination` (`destination`),
  ADD KEY `submitted_on` (`submitted_on`);
