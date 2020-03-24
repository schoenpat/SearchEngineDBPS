--
-- Database: `dhbw_crawler`
--
-- SQL-Code zur Erstellung der MySQL-Tabellen
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_link`
--

CREATE TABLE IF NOT EXISTS `tbl_link` (
  `id` int(11) NOT NULL,
  `link` varchar(1024) NOT NULL,
  `timestamp_visited` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=321 DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_link`
--
ALTER TABLE `tbl_link`
  ADD PRIMARY KEY (`id`);

  
-- --------------------------------------------------------

--
-- Table structure for table `word`
--

CREATE TABLE IF NOT EXISTS `word` (
  `id` int(11) NOT NULL,
  `word` varchar(1024) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1617 DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `word`
--
ALTER TABLE `word`
  ADD PRIMARY KEY (`id`);


-- --------------------------------------------------------

--
-- Table structure for table `wordlinks`
--

CREATE TABLE IF NOT EXISTS `wordlinks` (
  `id` int(11) NOT NULL,
  `id_word` int(11) NOT NULL,
  `id_link` int(11) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=7657 DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `wordlinks`
--
ALTER TABLE `wordlinks`
  ADD PRIMARY KEY (`id`);

  
-- EOF
