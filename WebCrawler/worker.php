<?php
// Include file to get db-connection
include "../dbconnection.php";

// CONFIG:
$cfg_no_worker_loops = 1; // Number of loops to go until the worker stops. Could be endless...
global $cfg_no_links_per_markup;
$cfg_no_links_per_markup = 20; // Number of links to be considered per markup page.
global $cfg_no_terms_per_website;
$cfg_no_terms_per_website = 100; // Words are a little bit expensive to insert, so limit these for development
$cfg_recursion_depth = 2; // Depth of the recursion of the crawler
$cfg_limit_found_words = 100;
$cfg_limit_found_links_per_words = 100;

// Functions
if (isset($argv))
{
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}


// This is a mapper class to hold the SQL-Statements and does the DB and PDO stuff.
class DBHandler
{
    private $db;
    private $UPDATE_URL_DATETIME;
    private $INSERT_WORD;
    private $ADD_WORD_TO_URL;
    private $GET_WORD;
    private $GET_URLS_TO_INDEX;
    private $INSERT_URL;
    private $GET_URL;

    public function __construct()
    {
        $this->db = get_connection();
        $this->UPDATE_URL_DATETIME = $this->db->prepare("UPDATE tbl_link set timestamp_visited=NOW() WHERE link = ?;");
        $this->INSERT_WORD = $this->db->prepare("INSERT INTO word (word) VALUES (?);");
        $this->ADD_WORD_TO_URL = $this->db->prepare("INSERT INTO wordlinks (id_word, id_link) VALUES ((SELECT id FROM word WHERE word=?), (SELECT id FROM tbl_link WHERE link=?));");
        $this->GET_WORD = $this->db->prepare("SELECT id FROM word WHERE word = ?");
        // This statement gets all urls which are old enough to be revisited
        $this->GET_URLS_TO_INDEX = $this->db->prepare("SELECT id, link FROM tbl_link WHERE timestamp_visited <= NOW() - INTERVAL 1 DAY;");
        $this->INSERT_URL = $this->db->prepare("INSERT INTO tbl_link (link, timestamp_visited) VALUES (?, NOW());");
        $this->GET_URL = $this->db->prepare("SELECT id FROM tbl_link WHERE link=?;");


        // ...perform an SQL query for searching the word in the database. It may be found only once...
        $this->GET_MATCHING_WORDS  = $this->db->prepare("SELECT * FROM word WHERE word LIKE '%?%' LIMIT ?;"); // LIMITED NUMBER OF RESULTS
        // query the links being connected to the word entry...
        $this->GET_WORD_LINKS = $this->db->prepare("SELECT * FROM wordlinks WHERE id_word = ?  LIMIT ?;"); // LIMITED NUMBER OF RESULTS
        $this->GET_LINK = $this->db->prepare("SELECT * FROM tbl_link WHERE id = ?;");
    }

    public function update_url_timestamp($link)
    {
        $this->UPDATE_URL_DATETIME->bindParam(1, $link);
        $this->UPDATE_URL_DATETIME->execute();
    }

    public function insert_word($word)
    {
        $this->INSERT_WORD->bindParam(1, $word);
        $this->INSERT_WORD->execute();
        return $this->db->lastInsertId();
    }

    public function add_word_to_url($word_id, $url)
    {
        $this->ADD_WORD_TO_URL->bindParam(1, $word_id);
        $this->ADD_WORD_TO_URL->bindParam(2, $url);
        $this->ADD_WORD_TO_URL->execute();
    }

    public function get_word($word)
    {
        $this->GET_WORD->execute([$word]);
        return $this->GET_WORD->fetchAll();
    }

    public function get_matching_words($searchphrase)
    {
        $this->ADD_WORD_TO_URL->bindParam(1, $searchphrase);
        $this->ADD_WORD_TO_URL->bindParam(2, $cfg_limit_found_words);
        $this->GET_MATCHING_WORDS->execute();
        return $this->GET_MATCHING_WORDS->fetchAll();
    }

    public function get_word_links($word_id)
    {
        $this->ADD_WORD_TO_URL->bindParam(1, $word_id);
        $this->ADD_WORD_TO_URL->bindParam(2, $cfg_limit_found_links_per_words);
        $this->GET_MATCHING_WORDS->execute();
        return $this->GET_MATCHING_WORDS->fetchAll();
    }

    public function get_link($link_id)
    {
        $this->GET_LINK->bindParam(1, $link_id);
        $this->GET_LINK->execute();
        return $this->GET_LINK->fetchAll();
    }

    public function get_urls_to_index()
    {
        $this->GET_URLS_TO_INDEX->execute();
        return $this->GET_URLS_TO_INDEX->fetchAll();
    }

    public function get_url($url)
    {
        $this->GET_URL->bindParam(1, $url);
        $this->GET_URL->execute();
        return $this->GET_URL->fetchAll();
    }

    public function insert_url($url)
    {
        $this->INSERT_URL->bindParam(1, $url);
        return $this->INSERT_URL->execute();
    }
}


function to_absolute_url($base, $link)
{
    if (substr($link,0,7)!='http://' and substr($link,0,8)!='https://')
    {
        if (substr($link,0,1)=='/')
        {
             $link = substr($link, 1); // Remove the first character avoiding //
        }
        return $base . "/" . $link;
    }
    return $link;
}

/**
 * @param $db DBHandler
 * @param $url -- Never a link, always a full URL!
 * @param $depth
 * @return int
 */
function crawl($db, $url, $depth)
{
    if ($depth == 0)
    {
        return 0;
    }

    $url_parts = parse_url($url);
    $url = $url_parts['scheme'] . "://" . $url_parts['host'] . $url_parts['path'];
    $base = $url_parts['scheme'] . "://" . $url_parts['host'];

    // First we get the Markup-Text of the website
    $page = file_get_contents($url);
    // Check if getting the website was successful
    if (!$page)
    {
        return 0;
    }
    // Get all links per REGEX on the <href> - tag
    preg_match_all('/href=\"(.*?)\"/i', $page, $links);
    // Next process the words on the website
    $pureTxt = strip_tags($page);
    preg_match_all('/[a-zA-Z0-9][a-zA-Z0-9\-\_]*[a-zA-Z0-9]/i', $pureTxt, $terms);

    $count_terms = -1;
    if ($GLOBALS['cfg_no_terms_per_website'] >= 0)
    {
        $count_terms = $GLOBALS['cfg_no_terms_per_website'];
    }
    foreach ($terms[0] as $word)
    {
        if ($count_terms == 0)
        {
            break;
        }
        // Try to insert the word. If it is already in the database nothing happens.
        $db->insert_word($word);
        $db->add_word_to_url($word, $url);
        $count_terms--;
    }
    $db->update_url_timestamp($url);
    // Now all words are in the database and connected to the url.

    // Now we have to do the same for all found urls on the website.
    $no_of_links = $GLOBALS['cfg_no_links_per_markup'];
    foreach ($links[1] as $link)
    {
        if ($no_of_links == 0) {
            break;
        }
        $link = to_absolute_url($base, $link);
        $link_parts = parse_url($link);
        $link = $link_parts['scheme'] . "://" . $link_parts['host'] . $link_parts['path'];
        // Check here if we need to crawl the url and insert into the db
        // If the url is already in the database, it will be handled in the future by the main for-loop of the worker
        $url_in_db = $db->get_url($link);
        if (sizeof($url_in_db))
        {
            // Url is already in the database, so it will be handled the next time the outer for-loop is executed
            continue;
        }
        $db->insert_url($link);
        crawl($db, $link, $depth-1);
        $no_of_links--;
    }
    return 0;
}

/**
 * @param $url string
 * @param $db DBHandler
 */
function insert_single_url($url, $db)
{
    $url_parts = parse_url($url);
    $url = $url_parts['scheme'] . "://" . $url_parts['host'] . $url_parts['path'];
    $db->insert_url($url);
}


$db = new DBHandler();

$url = $_GET['url'];
if ($_GET['mode'] == 'add')
{
    insert_single_url($url, $db);
    crawl($db, $url, $cfg_recursion_depth);
    echo "Indexed " . $url . " and related pages (if they were not indexed already).";
}
elseif ($_GET['mode'] == 'worker')
{
    insert_single_url($url, $db);
    crawl($db, $_GET['url'], $cfg_recursion_depth);
    for ($i=0; $i<=$cfg_no_worker_loops; $i++)
    {
        foreach ($db->get_urls_to_index() as $url)
        {
            crawl($db, $url['link'], $cfg_recursion_depth);
        }
    }

}

// With PDO the db connection will be automatically closed by the end of the script
