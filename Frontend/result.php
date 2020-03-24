<?php

# TODO ...

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

          # TODO ...
          
          // Config
          $cfg_limit_found_words = 100;
          $cfg_limit_found_links_per_words = 100;
      
          // Include some connection info...
          $credentials_file = "../db_settings.php";    
          include $credentials_file;
          $mysqli = new mysqli('127.0.0.1', $db_user, $db_pass, 'dhbw_crawler');
          
          // Connect to the database...
          if ($mysqli->connect_errno) {
              printf("MySQL connect failed: %s\n", $mysqli->connect_error);
              exit;
          }
          
          // Only if there is a search phrase...
          if ($search != "") {
              // ...perform an SQL query for searching the word in the database. It may be found only once...
              $sql = "SELECT * FROM word WHERE word LIKE '%$search%' LIMIT $cfg_limit_found_words;"; // LIMITED NUMBER OF RESULTS
              if (!$result = $mysqli->query($sql)) {
                  report_problem("Query failed to execute: ". $sql, $mysqli);
                  exit;
              } else {
                  // Open the ul list and loop the word entries...
                  echo "<ul>\n";
                  $record_counter = 0;
                  while ($word_item = $result->fetch_assoc()) {
                      //echo "<li><a href='" . $_SERVER['SCRIPT_NAME'] . "?aid=" . $user['id'] . "'>\n";
                      echo "<li> Result(s) for search phrase [" . $word_item['word'] . "]:\n"; //  (id: " . $word_item['id'] . ")\n";
                      
                      // Finally we query the links being connected to the word entry...
                      $sql="SELECT * FROM wordlinks WHERE id_word = '" . $word_item['id'] . "'  LIMIT $cfg_limit_found_links_per_words;"; // LIMITED NUMBER OF RESULTS
                      if (!$result2 = $mysqli->query($sql)) {
                          report_problem("Query failed to execute: ". $sql, $mysqli);
                          exit;
                      } else {
                          // Open the next level ul list and loop the wordlink entries ...
                          echo "<ul>\n";
                          while ($wordlink_item = $result2->fetch_assoc()) {
                              echo "<li>\n";
                              // Trace: echo "Wordlink found. The id_link is " . $wordlink_item["id_link"] . "</br>\n";
                              
                              // Finally we need the real link...
                              $sql = "SELECT * FROM tbl_link WHERE id = '".$wordlink_item["id_link"]."';";
                              if (!$result3 = $mysqli->query($sql)) {
                                  report_problem("Query failed to execute: ". $sql, $mysqli);
                                  exit;
                              } else {
                                  // We have found the link. So lets display it...
                                  $a_link = $result3->fetch_assoc()['link'];
                                  $record_counter++;
                                  echo "$record_counter - <a href='" . $a_link . "'>" . $a_link . "</a>\n";
                              }
                              echo "</li>\n";
                          }
                          echo "</ul>\n";
                      }
                      // echo $user['firstname'] . ' ' . $user['lastname'];
                      echo "</li>\n";
                  }
                  echo "</ul>\n";

                  // Free the resources...
                  $result->free();
                  $mysqli->close();
              }
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