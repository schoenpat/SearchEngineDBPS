<?php
// Include file to get db-connection
include "../dbconnection.php";
// Include some tools
include "../urltools.php";

// CONFIG:
$cfg_no_worker_loops = 5; // Number of loops to go until the worker stops. Could be endless...
$cfg_no_links_per_markup = 100; // Number of links to be considered per markup page.

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

    public function __construct()
    {
        $this->db = get_connection();
        $this->UPDATE_URL_DATETIME = $this->db->prepare("UPDATE tbl_link set timestamp_visited=NOW() WHERE link = ?;");
        $this->INSERT_WORD = $this->db->prepare("INSERT INTO word (word) VALUES (?);");
        $this->ADD_WORD_TO_URL = $this->db->prepare("INSERT INTO wordlinks (id_word, id_link) VALUES ((SELECT id FROM word where word=?), ?);");
        $this->GET_WORD = $this->db->prepare("SELECT id FROM word WHERE word = ?");
        // This statement gets all urls which are old enough to be revisited
        $this->GET_URLS_TO_INDEX = $this->db->prepare("SELECT id, link FROM tbl_link WHERE timestamp_visited <= NOW() - INTERVAL 1 SECOND ");
        $this->INSERT_URL = $this->db->prepare("INSERT INTO tbl_link (link, timestamp_visited) VALUES (?, NOW());");
    }

    public function update_url_timestamp($link)
    {

    }

    public function insert_word($word)
    {
        $this->INSERT_WORD->bindValue(1, $word);
        $this->INSERT_WORD->execute();
    }

    public function add_word_to_url($word, $url)
    {
        $this->ADD_WORD_TO_URL->bindValue(1, $word);
        $this->ADD_WORD_TO_URL->bindValue(2, $url['id']);
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

    public function insert_url($db, $url)
    {

        $this->INSERT_URL->bindValue(1, 'www.google.de');
        return $this->INSERT_URL->execute();
    }
}


/**
 * @param $db DBHandler
 * @param $url
 */
function crawl($db, $url)
{
    //url_to_absolute($url, $l );
    // Use Line 350 - 360!
    // First we get the Markup-Text of the website
    $page = file_get_contents($url['link']);
    // Get all links per REGEX on the <href> - tag
    preg_match_all('/href=\"(.*?)\"/i', $page, $links);
    // Next process the words on the website
    $pureTxt = strip_tags($page);
    preg_match_all('/[a-zA-Z0-9][a-zA-Z0-9\-\_]*[a-zA-Z0-9]/i', $pureTxt, $terms);

    foreach ($terms[0] as $word)
    {
        $word_res = $db->get_word();
        if (sizeof($word_res) == 0) {
            $db->insert_word($word);
        }

        $db->add_word_to_url($word, $url);
    }
    // TODO: Update timestamp of url
    // Now all words are in the database and connected to the url.
    // Now we have to do the same for all found urls on the website.
    // crawl($db, foreach($links as $url));
}


$db = new DBHandler();
while (true)
{
    foreach ($db->get_urls_to_index() as $url)
    {
        crawl($db, $url);
    }
}

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
