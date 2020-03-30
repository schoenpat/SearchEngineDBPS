<?php
  // No error messages...
  error_reporting(0);
  // ALL error messages...
  // error_reporting(E_ALL);

  if (isset($_GET['search'])) {
    $search = $_GET['search'];
  } else {
    $search = "";
    echo "Search phrase missing...";
  }

  include "../WebCrawler/worker.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <!-- 8-bit Universal Character Set Transformation Format => Unicode -->
  <title>DHBW Crawler</title>

  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0" />

  <!-- CSS  -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="css/materialize.css" type="text/css" rel="stylesheet" media="screen,projection" />
  <link href="css/style.css" type="text/css" rel="stylesheet" media="screen,projection" />
</head>

<body>

  <!-- NAVI -->
  <nav class="light-blue lighten-1" role="navigation">
    <div class="nav-wrapper container">
      <a href="index.html" id="logo-container" class="brand-logo"><i class="material-icons">search</i> DHBW Search-Engine</a>


      <ul class="right hide-on-med-and-down">
        <li><a href="./add-site.html">Add Pages to Crawler</a></li>
      </ul>

      <ul id="nav-mobile" class="sidenav">
        <li><a href="./add-site.html">Add Pages to Crawler</a></li>
      </ul>
      <a href="" data-target="nav-mobile" class="sidenav-trigger"><i class="material-icons">menu</i></a>
    </div>
  </nav>


  <!-- CONTENT -->
  <div class="section no-pad-bot" id="index-banner">
    <div class="container">
      <br><br>
      <h1 class="header center orange-text">Results</h1>
      <br><br>

      <div class="row center">


        <?php

          $db = new DBHandler();
      
          
          // Only if there is a search phrase...
          if ($search != "") {

            // Open the ul list and loop the word entries...
            echo "<ul>\n";
            $record_counter = 0;

            foreach ($db->get_matching_words($search) as $word_item) {

                      //echo "<li><a href='" . $_SERVER['SCRIPT_NAME'] . "?aid=" . $user['id'] . "'>\n";
                      echo "<li> Result(s) for search phrase [" . $word_item['word'] . "]:\n"; //  (id: " . $word_item['id'] . ")\n";
                      

                      // Finally we query the links being connected to the word entry...                      

                      $word_links = $db->get_word_links($word_item['id']);


                      // Open the next level ul list and loop the wordlink entries ...
                      echo "<ul>\n";

                      foreach($word_links as $wordlink_item) {
                            echo "<li>\n";
                          // Trace: echo "Wordlink found. The id_link is " . $wordlink_item["id_link"] . "</br>\n";
                          
                          // Finally we need the real link...


                          //$sql = "SELECT * FROM tbl_link WHERE id = '".$wordlink_item["id_link"]."';";

                          $link = $db->get_link(wordlink_item["id_link"]);


                          // We have found the link. So lets display it...
                          $a_link = $link['link'];
                          $record_counter++;
                          echo "$record_counter - <a href='" . $a_link . "'>" . $a_link . "</a>\n";
                          
                          echo "</li>\n";
                      }


                      echo "</ul>\n";
                      
                      // echo $user['firstname'] . ' ' . $user['lastname'];
                      echo "</li>\n";

            }
            echo "</ul>\n";
              

          } else {
              echo "Oops, nothing found. => Enter a search phrase!</br>\n";
          }
        ?>
      
      </div>


    </div>
  </div>



  <!-- FOOTER 
  <footer class="page-footer orange">
    <div class="container">
      <div class="row">
        <div class="col l6 s12">
          <h5 class="white-text">Company Bio</h5>
          <p class="grey-text text-lighten-4">We are a team of college students working on this project like it's our
            full time job. Any amount would help support and continue development on this project and is greatly
            appreciated.</p>


        </div>
        <div class="col l3 s12">
          <h5 class="white-text">Settings</h5>
          <ul>
            <li><a class="white-text" href="#!">Link 1</a></li>
            <li><a class="white-text" href="#!">Link 2</a></li>
            <li><a class="white-text" href="#!">Link 3</a></li>
            <li><a class="white-text" href="#!">Link 4</a></li>
          </ul>
        </div>
        <div class="col l3 s12">
          <h5 class="white-text">Connect</h5>
          <ul>
            <li><a class="white-text" href="#!">Link 1</a></li>
            <li><a class="white-text" href="#!">Link 2</a></li>
            <li><a class="white-text" href="#!">Link 3</a></li>
            <li><a class="white-text" href="#!">Link 4</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="footer-copyright">
      <div class="container">
        Made by <a class="orange-text text-lighten-3" href="http://materializecss.com">Materialize</a>
      </div>
    </div>
  </footer>
  -->


  <!--  Scripts-->
  <script src="https://code.jquery.com/jquery-2.1.1.min.js"></script>
  <script src="js/materialize.js"></script>
  <script src="js/init.js"></script>

</body>

</html>