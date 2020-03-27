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
    id                int auto_increment
        primary key,
    link              varchar(1024)                         not null,
    timestamp_visited timestamp default current_timestamp() not null on update current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

  
-- --------------------------------------------------------

--
-- Table structure for table `word`
--

CREATE TABLE IF NOT EXISTS `word` (
  id   int auto_increment
      primary key,
  word varchar(64) not null,
  constraint word
      unique (word),
  constraint word_unique_idx
      unique (word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `wordlinks`
--

CREATE TABLE IF NOT EXISTS `wordlinks` (
   id      int auto_increment
       primary key,
   id_word int not null,
   id_link int not null,
   constraint word_only_once_per_website
       unique (id_word, id_link),
   constraint wordlinks_ibfk_1
       foreign key (id_link) references tbl_link (id),
   constraint wordlinks_ibfk_2
       foreign key (id_word) references word (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for table `wordlinks`
--
create index id_link
    on wordlinks (id_link);

