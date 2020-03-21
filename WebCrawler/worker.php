<?php
// Include file to get db-connection
include "../dbconnection.php";

// CONFIG:
$cfg_no_worker_loops = 1; // Number of loops to go until the worker stops. Could be endless...
global $cfg_no_links_per_markup;
$cfg_no_links_per_markup = 5; // Number of links to be considered per markup page.
global $cfg_no_terms_per_website;
$cfg_no_terms_per_website = 10; // Words are a little bit expensive to insert, so limit these for development
$cfg_recursion_depth = 3; // Depth of the recursion of the crawler

// Functions
parse_str(implode('&', array_slice($argv, 1)), $_GET);
echo("CRAWLER\n");
echo  $_GET['url'];
echo  $_GET['mode'];


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
        $this->GET_URLS_TO_INDEX = $this->db->prepare("SELECT id, link FROM tbl_link WHERE timestamp_visited <= NOW() - INTERVAL 2 MINUTE;");
        $this->INSERT_URL = $this->db->prepare("INSERT INTO tbl_link (link, timestamp_visited) VALUES (?, NOW());");
        $this->GET_URL = $this->db->prepare("SELECT id FROM tbl_link WHERE link=?;");
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


$db = new DBHandler();
// while (true)
//{
    foreach ($db->get_urls_to_index() as $url)
    {
        crawl($db, $url['link'], $cfg_recursion_depth);
    }
//}

/*
class Crawler {

    protected $markup = '';
    public $base = '';
    public $thesqli = NULL;

    // Constructor...
    public function __construct($uri) {
        $this->base = $uri;
        // $this->base = parse_url($uri)["host"]; // do not use
        echo("Base is $this->base\n");
        $this->markup = $this->getMarkup($uri);
    }

    public function getMarkup($uri) {
        return file_get_contents($uri);
    }

    protected function _get_links() {
        if (!empty($this->markup)){
            preg_match_all('/href=\"(.*?)\"/i', $this->markup, $links);
            if (!empty($links[1])) {
                return $links[1];
            } else {
                return FALSE;
            }
        }
    }

}


// Make the wordlinks.
// The argument $arg_url creates or updates the wordlinks only for this given url. Default is all.
function make_wordlinks($mysqli, $arg_crawler, $arg_url = NULL)
{
    $new_word_link_counter=0;

    // Assume we have all links in the table.
    // Here we simply update all links or the $arg_url in the table...
    $query_where = "";
    if ($arg_url != NULL)
    {
        $query_where = " WHERE link = '$arg_url'";
    }
    if ($result_tbl_links = $mysqli->query("SELECT * FROM tbl_link $query_where;")) {

        $num = $result_tbl_links->num_rows;

        for($i1 = 0; $i1 < $num; $i1++) {
            $row = $result_tbl_links->fetch_array();
            $link = $row["link"];
            $id = $row["id"];
            //Links
            // mylog( "link: $link - id: $id");

            // We read the markup from the link...
            $markup = $arg_crawler->getMarkup($link);

            $pureTxt = strip_tags($markup);

            preg_match_all('/[a-zA-Z0-9][a-zA-Z0-9\-\_]*[a-zA-Z0-9]/i', $pureTxt, $terms);

            // Lets walk word by word through the array...
            $max_words_counter = 0;
            foreach ($terms[0] as $wort){

                // Sometimes we like to limit the number of words per url...
                $max_words_counter++;
                if ($max_words_counter <= $max_words)
                {
                    mylog("[" . strval($max_words_counter)."]" , "span") ;

                    // Now: Adding the word into the database...
                    $retry_counter = 3; // Experimental approach with retries
                    $a_test_result = NULL;

                    // Lets try to insert several times...
                    for (;$retry_counter > 0;) {
                        $sql = "SELECT * from word WHERE word = '$wort';";
                        if ($a_test_result = $mysqli->query($sql)) {
                            // Is the word still not there?
                            if ($a_test_result->num_rows <= 0) {
                                if ($retry_counter == 0)
                                {
                                    echo "Too many retries...<br>";
                                    exit;
                                }
                                else {
                                    // Need to insert the word into the database...
                                    $sql2 = "INSERT INTO word (word) VALUES ('$wort');";
                                    if ($result = $mysqli->query($sql2)) {
                                        echo "New word [$wort] inserted into database... ";
                                    } else {
                                        // Oops. Insertion faild. Lets wait a few seconds and try again...
                                        report_problem($sql2, $mysqli);
                                        $retry_counter = $retry_counter-1;
                                        sleep(2);
                                    }
                                }
                            } else {
                                $retry_counter = 0;
                            }
                        }
                        else {
                            report_problem($sql2, $mysqli);
                            exit;
                        }
                    }

                    // Inserting the connecting data record (word links)

                    // Get the id of the current word
                    $a_row = $a_test_result->fetch_assoc();
                    $id_word = $a_row['id'];
                    // echo  "id_word=" . $id_word . "... ";

                    // Combination is: $id_word <--> $id (the id of the current link from tbl_link)
                    // Check if a data record with such combination is already there.
                    // If not, create a wordlink record...
                    $wordid_sql = "SELECT * FROM wordlinks WHERE id_word = $id_word AND id_link = $id;";
                    if ($wordid_result = $mysqli->query($wordid_sql)) {
                        if ($wordid_result->num_rows == 0) {
                            // We have to add the new combination of id_word and id_link...
                            $add_combination_sql = "INSERT INTO wordlinks (id_word, id_link) VALUES ($id_word, $id);";
                            if ($result = $mysqli->query($add_combination_sql)) {
                                $new_word_link_counter++;
                                echo "[$new_word_link_counter]"; // ""New Word-Link inserted into database... ";
                            } else {
                                report_problem($add_combination_sql, $mysqli);
                                exit;
                            }
                        }
                    } else {
                        report_problem($wordid_sql, $mysqli);
                        exit;
                    }
                }
            }
        }
    }
    else{
        report_problem($sql, $mysqli);
        exit;
    }

} // function

// Add url into database if not already there...
function add_url($mysqli, $arg_url)
{
    $already_there = false;
    $sql = "SELECT * from tbl_link WHERE link = '$arg_url';";
    //mylog ("Querying: " . $sql . "... <br>");
    if ($a_test_result = $mysqli->query($sql)) {
        // mylog ("Number of: " . strval($a_test_result->num_rows) . ".. <br>");
        if ($a_test_result->num_rows > 0) {
            mylog( "INFO: add_url(): Already in table: [" . $arg_url . "]<br>");
            $already_there = true;
        } else {
            // Insert new link. The timestamp is set to a very early time
            $sql = "INSERT INTO tbl_link (link, timestamp_visited) VALUES ('$arg_url', '1977-01-01 12:00:00');";
            // OLD was now(): $sql = "INSERT INTO tbl_link (link, timestamp_visited) VALUES ('$arg_url', now());";
            if ($result = $mysqli->query($sql)) {
                mylog("Link inserted into database...");
            } else {
                report_problem($sql, $mysqli);
                exit;
            }
        }
        $a_test_result->close();
    } else {
        report_problem($sql, $mysqli);
        exit;
    }
}

// Independent recursive crawl function
// Arguments:
// $arg_url (string): URL to be crawled
// $arg_recursion (integer): Number of levels to step into. (0 = nothing to to. 1 = crawl $arg_url, 2 = crawl arg_url and the next level, ...)
//
function crawl($mysqli, $arg_url, $depth)
{
    if ($depth <= 0)
    {
        return;
    }

    add_url($mysqli, $arg_url);

    // We also update the time stamp of the link we are just visiting...
    $sql = "UPDATE tbl_link set timestamp_visited=CURRENT_TIMESTAMP WHERE link = '$arg_url';";
    $mysqli->query($sql);

    // Lets continue...
    // ...and analyze the content...
    mylog( "Updating [" . $arg_url . "]...<br>");
    $crawl = new Crawler($arg_url);
    $images = $crawl->get('images');
    $links = $crawl->get('links');

    // First we create/update the wordlinks for the URL $arg_url...
    make_wordlinks($mysqli, $crawl, $arg_url);

    // Then we walk the links...
    if ($links != FALSE)
    {
        $link_counter = 0;
        foreach($links as $l) {
            $link_counter++;
            $l = $mysqli->real_escape_string($l);
            mylog( "<b>Processing link</b> [" . $l . "]...<br>");
            mylog( "<b>    link_counter:</b> [" . $link_counter . "]<br>");
            mylog( "<b>    strpos:      </b> [" . intval(strpos($l, '#')) . "]<br>");

            global $cfg_no_links_per_markup;
            if (($link_counter < $cfg_no_links_per_markup) and (strpos($l, '#') === false)) // No # in links...
            {
                mylog( "<b>    Link accepted for further processing.</b><br>");

                // Unify URLs... (TODO: Make sure all links are processed properly..., )

                // Using helping external urltool
                // ====================
                $a_value = url_to_absolute($crawl->base, $l );
                mylog( "<b>    Creating Link from base [" . $crawl->base . "] and Link [$l] to [$a_value].</b><br>");

                // Or take the own way:
                // ====================
                // $a_value = "";
                // if (substr($l,0,7)!='http://' and substr($l,0,8)!='https://') {
                //     if (substr($l,0,1)=='/')
                //     {
                //         $l = substr($l, 1); // Remove the first character avoiding //
                //     }
                //     $a_value = $mysqli->real_escape_string ("$crawl->base/$l");
                // }
                // else
                // {// No modification...
                //     $a_value = $l;
                // }

                // The link might be just found. So we try to add it to the database (in this case with a very old date...) :)
                add_url($mysqli, $a_value);

                // Recursion
                // mylog( "<br><b>Recursion</b> ==> $arg_recursion ==============================<br>");
                crawl($mysqli, $a_value, $depth-1);
            }
        }
    }
}

// Endless loop. Working the linklist again and again
function worker($mysqli)
{
    $worker_loop_counter = 0;
    global $cfg_no_worker_loops;
    while ($worker_loop_counter < $cfg_no_worker_loops) // Could be true...
    {
        $worker_loop_counter++;
        mylog("Worker: Loop number $worker_loop_counter");
        $query_where = "";
        if ($result_tbl_links = $mysqli->query("SELECT * FROM tbl_link $query_where;"))
        {
            $num = $result_tbl_links->num_rows;
            mylog("Worker: $num links to be analyzed...");
            for($i1 = 0; $i1 < $num; $i1++) {
                $row = $result_tbl_links->fetch_array();
                $link = $row["link"];
                $id = $row["id"];
                $ts = $row['timestamp_visited'];

                $curnum = intval($i1) + 1;
                mylog ("Worker: Working on " . $curnum . " of $num in mainloop $worker_loop_counter. [". $link . "]...");

                $date = new DateTime();
                $timestamp_now = $date->getTimestamp();

                $visit_required = false;
                if ($ts != NULL)
                {
                    $ts_sec = strtotime($ts);
                    $delta_sec = intval($timestamp_now) - intval($ts_sec);
                    if ($delta_sec > 60*60*24) // One visit a day...
                    {
                        $visit_required = true;
                    }
                    mylog("Time since last visit: " . number_format($delta_sec/60,0, ".", "") . " min");
                }
                else
                {
                    $visit_required = true;
                }
                if ($visit_required)
                {
                    crawl($mysqli, $link, 1); // 1 level recursion
                }
                // Be not too fast. Lets sleep until we visit the next link...
                if (($i1 % 10) == 0)
                {
                    sleep(1);
                }
            } // for
        }
        else
        {
            mylog("Error: Query in worker's loop failed.");
            exit;
        }
    }
    mylog("...done.");
}

// MAIN

$mysqli = new mysqli('127.0.0.1', $mysqluser, $mysqluserpwd, $cfg_database_name);

// Check connection...
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit;
}

if ($crawler_mode == 'add_url') // SUPPORTED
{
    add_url($mysqli, "http://".$url);
}
elseif ($crawler_mode == "worker") // SUPPORTED
{
    add_url($mysqli, "http://".$url);
    worker($mysqli); // ...and start the worker
}
elseif ($crawler_mode == 'add') // NOT FULLY SUPPORTED
{
    crawl($mysqli, "http://".$url, 1); // We add
}
else
{
    echo "Error: Crawler mode [".$crawler_mode."] not supported.<br>";
}
// Close connection...
$mysqli->close();
*/
?>
