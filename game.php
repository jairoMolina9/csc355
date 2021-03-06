<?php
include("config.php");
   session_start();

if($_SERVER["REQUEST_METHOD"] == "POST") {

    if(isset($_POST['winner'])) {
      $winner = $_POST['winner'];
      $gameID = $_POST['w_game_id'];

      $sql = "UPDATE Games SET winner = '$winner' WHERE gameID = '$gameID'";

      mysqli_query($link,$sql);

      if(isset($_SESSION['username'])) {
        console_log($_SESSION['username']);//debugging purposes
        if($winner != 'blue_player' && $winner != 'red_player') {
          $sql = "UPDATE Users SET wins = (SELECT COUNT(winner) FROM Games WHERE winner = '$winner') WHERE username = '$winner'"; //each game has a winner, count of games that have winner as username
          mysqli_query($link,$sql);
        } else {
          $username = $_SESSION['username'];
          $sql = "UPDATE Users SET losses = (SELECT COUNT(winner) FROM Games WHERE userID = '$username' AND winner !='$username') WHERE username = '$username'";
          mysqli_query($link,$sql);
        }
      }

    }

  if(isset($_POST['submit'])) {
    if($_POST['submit'] == 'Login'){
    if( isset($_POST['username'])) {
      $username = $_POST['username'] ?? '';
      ?>
      <script> var player_name = '<?php echo $username; ?>'</script>
      <?
      $password = md5($_POST['password'] ?? '');

      $sql = "SELECT username FROM Users WHERE username = '$username' AND password = '$password'";

      $result = mysqli_query($link, $sql);

      if(mysqli_num_rows($result) == 1) {
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $username;
        $_SESSION["show_list"] = true;
      } else {
        print '<script>alert("User does not exist...");</script>';
      }
    } else {
      print '<script>alert("Enter username and password...");</script>';

    }
    } else if ($_POST['submit'] == 'Register') {

      if( isset($_POST['username'])) {

        $username = $_POST['username'];
        //hashing function to encrypt passwords
        $password = md5($_POST['password'] ?? '');
        $password2 = md5($_POST['password'] ?? '');

        if($password == $password2) {

                  $sql = "SELECT username FROM  Users WHERE username = '$username'";

                  if($result = mysqli_query($link,$sql)) {
                    if(!mysqli_num_rows($result)) {
                      $_SESSION["logged_in"] = true;
                      $_SESSION["username"] = $username;

                      $sql = ("INSERT INTO Users(username, password) VALUES ('$username', '$password')");

                      $result = mysqli_query($link, $sql);
                      ?>
                      <script> var player_name = '<?php echo $username; ?>'</script>
                      <?
                      print '<script>alert("Successfully registered!");</script>';

                    } else {
                      print '<script>alert("Username is taken...");</script>';

                    }
                  }
        } else {
          print '<script>alert("Passwords do not match...");</script>';
        }


      } else {
        print '<script>alert("Enter username and password...");</script>';
      }


    } else if ($_POST['submit'] == 'Code') {
      if(isset($_POST['pregameid']) && $_POST['pregameid'] != null) {

        if(isset($_SESSION['show_list'])) {
          $_SESSION['show_list'] = false;//after choosing historical game)
        }

        $gameID = $_POST['pregameid'];
        $sql = "SELECT coordinate, playerTurn FROM Moves WHERE gameID = '$gameID'";

          if($result = mysqli_query($link,$sql)) {
        if(!mysqli_num_rows($result)) {
          print '<script>alert("Wrong game ID...");</script>';
        } else {
          $_SESSION['logged_in'] = true;

          //JSON Restore
          // $gamedata = array();
          // $myFile = "json/".$gameID.".json";
          // $jsonData = file_get_contents($myFile);
          // $gamedata_json = json_decode($jsonData, true);
          //
          // for($i = 0; $i < count($gamedata_json)-1; $i++) {
          //   $gamedata[$i][0] = $gamedata_json[$i][0]['coord'];
          //   $gamedata[$i][1] = $gamedata_json[$i][0]['player'];
          // }


          //DB Restore
          $gamedata = array();
          $gamedata = mysqli_fetch_all($result, MYSQLI_NUM);

          //get player_name
          $sql = "SELECT userID FROM Games WHERE gameID = '$gameID'";

          $result = mysqli_query($link,$sql);
          $row = mysqli_fetch_all($result, MYSQLI_NUM);
          $username = $row[0];

          if($username[0] != 'guest') {
            ?>
            <script>
            var is_guest = "random";
            var player_name = <?php echo json_encode($username); ?>;
            </script>
            <?

            if(isset($_SESSION['show_list'])) {
              ?>
              <script>
               is_guest = "loggedinpregame"; //makes sure that when reconstructing from historical list the colors change properly
              </script>
              <?
            }
          }

          $sql = "SELECT winner FROM Games WHERE gameID = '$gameID'";
          $result = mysqli_query($link,$sql);
          $row = mysqli_fetch_all($result, MYSQLI_NUM);
          $pregamewinner = $row[0];

          $sql = "SELECT playerTurn FROM Moves WHERE gameID = '$gameID' AND moveID = (SELECT MAX(moveID) FROM Moves WHERE gameID = '$gameID');"; //gets current turn
          $result = mysqli_query($link, $sql);
          $row = mysqli_fetch_all($result, MYSQLI_NUM);
          $lasturn = $row[0][0];

          if($lasturn == "blue_player") {
            $_SESSION['guest-turn'] = "RED PLAYER";
          } else {
            $_SESSION['guest-turn'] = $username[0];
          }

          ?>
          <script type="text/javascript">
          var gamedata = <?php echo json_encode($gamedata); ?>;
          var pregameID = '<?php echo ($gameID);?>';
          var pregamewinner = <?php echo json_encode($pregamewinner);?>;
          </script>
          <?

        }
      }
    } else {
        $_SESSION['logged_in'] = true;
    }
  }
}

  if ( isset($_POST['coord'])) {

    $gameID = $_POST['gameID'];
    $coord = $_POST['coord'];
    $player = $_POST['player'];

    //DATABASE STORE
    $sql = ("INSERT INTO Moves(gameID, coordinate, playerTurn) VALUES ('$gameID', '$coord', '$player')");
    mysqli_query($link,$sql);

    //JSON STORE
    $myFile = "json/".$gameID.".json"; //new file per game
    $arr_data = array(); //used to append into json file

    //if new file put gameID
    if(0 == filesize($myFile)) {
      $tmp->gameID = $gameID;
      $jsonData = json_encode($tmp);
      file_put_contents($myFile, $jsonData);
    }


    $new_data = array();
    $new_data[] = array("coord"=>$coord, "player"=>$player);

    //get json data
    $jsondata = file_get_contents($myFile);

    //covert json data to array
    $arr_data = json_decode($jsondata,true);

    //push new data to array
    array_push($arr_data, $new_data);

    //encode updated array to json
    $jsondata = json_encode($arr_data, JSON_PRETTY_PRINT);

    //put json to file
    file_put_contents($myFile, $jsondata);



  } else if ( isset($_POST['restart'])) {

    $gameID = $_POST['restart'];
    $sql = ("DELETE FROM Moves WHERE gameID = '$gameID'");
    mysqli_query($link,$sql);

    $sql = ("DELETE FROM Games WHERE gameID = '$gameID'");
    mysqli_query($link,$sql);
      }


  if(isset($_POST['newgame'])) {
    $gameID = $_POST['newgame'];
    $name = $_POST['playername'];
    $sql = ("INSERT INTO Games(gameID, userID) VALUES ('$gameID', '$name')");

    mysqli_query($link,$sql);
  }
}

?>


<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title>Connect 4</title>

    <meta property="og:image" content="img/favicon.png">
    <meta name="description" content="tdis site provides an interactive connect 4 game, fully online witd personal accounts and game-history tracking">
<meta name="keywords" content="connect4,355,queenscollege,nyc">
<meta name="autdor" content="Jairo Molina & Wei Ting">

<link rel="icon" href="img/favicon.png"/>

<!-- bootstrap css -->
<link href="css/bootstrap.min.css" rel="stylesheet">
<!-- custom css -->
  <link  href="css/custom.css" rel="stylesheet">
<!-- custom js -->
  <script src="js/custom.js"></script>
  </head>
  <body>
    <nav class="navbar fixed-top navbar-expand-lg customnavbar">
  <a class="navbar-brand" href="index.html">Connect 4</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon">&#8650;</span>
  </button>

  <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav ml-auto">
      <li class="nav-item active">
        <a class="nav-link" href="/index.html#rules">Rules<span class="sr-only">(current)</span></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/index.html#history">History</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/index.html#about">About</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/index.html#contact">Contact</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" target="_blank"  href="https://www.google.com/search?q=connect4">Search</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          More Games
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
          <a class="dropdown-item" target="_blank" href="https://www.mathsisfun.com/games/connect4.html">Connect 4 v1</a>
          <a class="dropdown-item" target="_blank" href="https://papergames.io/en/connect4">Connect 4 v2</a>
        </div>
      </li>
    </ul>
  </div>
</nav>

    <div class = "login-section" id = "login-section1">
      <div id = "game-container-forms" class = "container">
      <form action ="game.php" method = "post" id = "login-form">
        <div class = "row justify-content-center">
          <div class = "col-4">
        <input type = "text" name = "username" placeholder="Username" required="required" class = "username-form"/><br /><br />
      </div>
    </div>
      <div class = "row justify-content-center">
        <div class = "col-4">
          <input type = "password" required="required" name = "password" placeholder="Password" class = "password-form" /><br/><br />
      </div>
      </div>
        <div class = "row justify-content-center">
          <div class = "col-8">
        <button class="neon-button" type="submit" name = "submit" form="login-form" value="Login">Login</button>
        <button class = "neon-button" onclick="getRegisterForm()" type = "button">Register</button>
        <button class = "neon-button" onclick="getGuestForm()" type = "button">Play Now</button>
      </div>
      </div>
      </form>

      <form action ="game.php" method = "post" id = "register-form" >

        <input type = "text" required="required" placeholder="Username" name = "username" class = "username-form"/><br/><br/>
        <input type = "password" required="required" placeholder="Password" name = "password" class = "password-form" /><br/><br/>
        <input type = "password" required="required" placeholder="Confirm Password "name = "password2" class = "repassword-form" /><br/><br />

        <button class="neon-button" onclick="getLoginForm()" type = "button">Login</button>
        <button class="neon-button" type="submit" name = "submit" form="register-form" value="Register">Register</button>
        <button class="neon-button" onclick="getGuestForm()" type = "button">Play Now</button>
      </form>

      <form action ="game.php" method = "post" id = "guest-form" >

        <input type = "text" name = "pregameid" placeholder="Enter Code (OPTIONAL)" class = "username1-form"/><br /><br />


        <button class="neon-button" onclick="getLoginForm()" type = "button">Login</button>
        <button class="neon-button" onclick="getRegisterForm()" type = "button">Register</button>
        <button class="neon-button" type="submit" name = "submit" form="guest-form" value="Code">Play Now</button>
      </form>
      </div>

    </div>
  </div>

     <div class = "connect4-section" id = "connect4">
       <div id = "game-heading" class = "blue-heading">
         <h2> CONNECT 4 </h2>
       </div>

       <div id = "game-container" class = "container game-blue-ct">
         <div class = "row">

           <div class = "col-md-2 left-pane">
             <p class = "blue-title" id = "user_turn">
               <?php
                if (isset($_SESSION['guest-turn'])) {
                 if($_SESSION['guest-turn'] == "guest") {
                   echo "BLUE PLAYER";
                 } else {
                   echo $_SESSION['guest-turn'];
                 }

               } else if(isset($_SESSION['username'])){
                  echo $_SESSION['username'];
                }else {
                 echo "BLUE PLAYER";
               }
               ?>
             </p>
             <br>
             <br>
             <p class = "blue-title" id = "game-id-header"> Game ID: <br></p>
             <p class = "blue-title" id = "game-id"></p>
           </div>

           <?php
           if(isset($_SESSION['guest-turn'])){
           if($_SESSION['guest-turn'] == "RED PLAYER") {
             echo "<script> document.getElementById('user_turn').className = 'red-title';
             document.getElementById('game-id-header').className = 'red-title';
             document.getElementById('game-id').className = 'red-title';</script>";
               echo "<script> document.getElementById('game-container').className = 'container game-red-ct';</script>";
           }
         }

            ?>

         <div class = "col-md-8 mid-section">
           <div class="table-responsive-lg">
        <table class="table" id = "tblMain">
          <tbody class = "table-body">

            <?php
              for($row = 0; $row < 6; $row++) {
                echo "<tr>";
                for($col = 0; $col < 7; $col++) {
                  echo "<td id = '" . $row .",".$col."' class = 'tab-col'>";
                  echo "</td>";
                }
                echo "</tr>";
              }

             ?>

            </tbody>
        </table>
</div>
         </div>

         <div class = "col-md-2 right-pane">
           <p id = "user_winner" class = "blue-title"></p>
           <button id = "restart" onclick="resetGame('restart')" type="button" class="neon-button-blue">RESTART</button>

           <p style = "color: white;" id="response"></p>
           <?php
              if(isset($_SESSION['username'])) {
                echo "<script> document.getElementById('restart').className = 'neon-button-blue';</script>";
            ?>
           <a id = "logout-btn" href="logout.php" class="neon-button-blue">Logout</a>
           <?
         } else {
           ?>
           <a id = "exit-btn" href="logout.php" class="neon-button-blue">Exit</a>
           <?
         }

         if(isset($_SESSION['guest-turn'])){
         if($_SESSION['guest-turn'] == "RED PLAYER") {

           if(isset($_SESSION['show_list'])) {
             echo "<script> document.getElementById('logout-btn').className = 'neon-button';</script>";
           } else {
              echo "<script> document.getElementById('exit-btn').className = 'neon-button';</script>";
           }


            echo "<script> document.getElementById('restart').className = 'neon-button';</script>";
            echo "<script> document.getElementById('game-container').className = 'container game-red-ct';</script>";
            echo "<script> document.getElementById('game-heading').className = 'red-heading';</script>";
         }
       }
         ?>
         </div>


     </div>
   </div>
     </div>

     <?php

     if($_SESSION['logged_in'] && $_SESSION['logged_in'] != '') {
       if($_SESSION['show_list']) {
         ?>
         <script>
         document.getElementById("login-section1").style.display="none";
         </script>
         <div id = "historical-section">
           <div id="game-heading" class="blue-heading">
         <h2> Welcome, <?php echo $_SESSION['username']; ?></h2>
        </div>

        <?php
        $username = $_SESSION['username'];
        $sql = "SELECT gameID FROM Games WHERE userID = '$username'";

        $result = mysqli_query($link, $sql);
        $sql1 = "SELECT wins,losses FROM Users where username = '$username'";
        $result2 = mysqli_query($link, $sql1);
        $row = mysqli_fetch_array($result2);
        console_log($row);
        echo "<div class ='container' style = 'text-align:center;'>";
        echo "<p style = 'color:white;' class='rule-item'>WINS:". $row[0] . " | LOSSES:". $row[1] . "</p></div>"
        ?>

        <div class ="container" id = "container-list">

          <div class = "row">
            <div class = "col-md-10" id = "game-list">
              <form action ="game.php" method = "post" id = "historical-form" >
                <?php
                while($row = mysqli_fetch_array($result)) {
                  echo "<label class = 'rule-item' style = 'color:white;' for='" . $row['gameID'] . "'>". $row['gameID'] . "</label>";
                  echo "<input type='radio' id='". $row['gameID']. "' name='pregameid' value='" . $row['gameID'] . "'><br>";

                }
                ?>

                <button class="neon-button-blue" type="submit" name = "submit" form="historical-form" value="Code">Play Game</button>


              </form>
            </div>

            <div class = "col-md-2" id = "historical-right-pane">
              <button id = "list-newgame" class="neon-button-blue" onclick = "newgame2()">New Game</button>

              <a href="logout.php" class="neon-button-blue">Logout</a>

              <script type = "text/javascript">
              function newgame2() {
                startGame();
                setPregame();
                document.getElementById("historical-section").style.display="none";
                document.getElementById("connect4").style.display="block";
              }
              </script>

            </div>
          </div>
        </div>

        </div>
         <?

       } else {
       ?>
       <script type = "text/javascript">
       startGame();
       setPregame();
       document.getElementById("login-section1").style.display="none";
       document.getElementById("connect4").style.display="block";
       </script>
       <?
     }
    }
       ?>

<!-- jquery cdn -->
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

    <!-- jquery poppers cdn -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<!-- bootstrap min js -->
<script src="js/bootstrap.min.js"></script>
  </body>
</html>
