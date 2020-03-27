<?php
    // No error messages...
    // error_reporting(0);
    
    // ALL error messages...
    // error_reporting(E_ALL);

    // Tool...
    function report_problem($msg, $mysqli) {
        echo "Sorry, the website is experiencing problems.</br>";
        echo "Error:</br>";
        echo "Msg:   " . $msg . "</br>";
        echo "Errno: " . $mysqli->errno . "</br>";
        echo "Error: " . $mysqli->error . "</br>";
    }

    // Call me by form or this way: crawler.php?url=http://www.dhbw-heidenheim.de 
    if (isset($_GET['url'])) {
        $url = $_GET['url'];
    } else {
        $url = "";
        echo "'url' argument phrase missing...";
    }
 
    // Add url to database... (format is like www.xyz.de)
    function add_url($arg_url, $mysqli)
    {
        // Lets see if the link is already in the database...
        $already_there = false;
        $sql = "SELECT * from tbl_link WHERE link = '$arg_url';";
        if ($a_test_result = $mysqli->query($sql)) {
            
            if ($a_test_result->num_rows > 0) {
                echo("Link already in database...");
                    
                $already_there = true;
            }
            else
            {
                // Insert new link...
                $sql = "INSERT INTO tbl_link (link, timestamp_visited) VALUES ('$arg_url','1977-01-01 12:00:00');";
                echo $sql . "<br>";
                if ($result = $mysqli->query($sql)) {
                    echo("Link inserted into database...");
                    
                } else {
                    report_problem($sql, $mysqli);
                }            
            }
            $a_test_result->close();
        } else {
            report_problem($sql, $mysqli);
        }     
    }   
 
    // Include some connection info...
    $credentials_file = "../db_settings.php";    
    include $credentials_file;
    $mysqli = new mysqli('127.0.0.1', $db_user, $db_pass, 'dhbw_crawler');
    
    // Check connection...
    if (mysqli_connect_errno()) {
        echo("Connect failed: " . mysqli_connect_error() . "\n");
        exit;
    }
    add_url($url, $mysqli);
    
    $mysqli->close();                                
?>
	