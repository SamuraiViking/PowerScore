<?php 

////////////////////////////
// 1. Win/Loss Percentage //
////////////////////////////

// W/L% = W / ( W + L )

// Scaled W/L% = { ( 0.5 ) * [ ( W/L% ) - MIN( W/L% ) ] } / { [ MAX(W/L%) - MIN(W/L%) ] } + 0.5

// W/L Weight = 80

// Points from W/L = W/L Weight x (Scaled W/L %)


/////////////////////////////
// 2. Strength of Schedule //
/////////////////////////////

// Opponents W/L% = O-W/L%

// Opponents Opponents W/L% = OO-W/L%

// W/L% = W / ( W + L )

// O-W/L% = Sum of W/L% of all O / Number of O

// OO-W/L% = Sum of W/L% of all OO / Number of OO

// SOS = 2/3 * (O-W/L %) + 1/3 * (OO-W/L%)

// Scaled SOS = { ( 0.5 ) * [ ( SOS ) - MIN( SOS ) ] } / { [ MAX(SOS) - MIN(SOS) ] } + 0.5

// W/L% Weight = 20

// Points from SOS = W/L% Weight x (Scaled SOS %)


// Step 1: 

//

function get_games() {
  $file_contents_str = file_get_contents("game_results.json");
  $file_contents_json = json_decode($file_contents_str, true);
  $games = $file_contents_json["Games"]["Results"];
  return $games;
}

function elem_in_array_has_key_with_value($array, $key, $value) {
  foreach($array as $elem) {
    if($elem[$key] == $value) {
      return true;
    }
  }
  return false;
}

function insert_team_ids($games, $teams) {
  foreach($games as $game) {
    foreach(["HomeTeam", "AwayTeam"] as $home_away_status) {

      $team_id   = $game[$home_away_status]["TeamId"];
      $team_name = $game[$home_away_status]["Name"];

      if(elem_in_array_has_key_with_value(
        $teams, 
        "Id", 
        $team_id)) {
        continue;
      }

      $team = array(
        'Id'     => $team_id,
        'Name'   => $team_name,
        'Wins'   => 0,
        'Losses' => 0,
        'Win_loss_per' => 0,
        'Opponents' => array(),
        'Opponents_win_loss_per' => 0,
      );
      array_push($teams, $team);
    }
  }
  return $teams;
}

function get_winner($game) {

  if(!key_exists("WonBy", $game["HomeTeam"])) {
    return "HomeTeam";
  }

  if(!key_exists("WonBy", $game["AwayTeam"])) {
    return "AwayTeam";
  }

  $home_team_score = $game["HomeTeam"]["Score"];
  $away_team_score = $game["AwayTeam"]["Score"];
  
  $winner = $home_team_score > $away_team_score ?
  "HomeTeam" : "AwayTeam";

  return $winner;  
}

function insert_wins_and_losses($games, $input_teams) {
  foreach($games as $game) {

    $winner = get_winner($game);

    $home_team_id = $game["HomeTeam"]["TeamId"];
    $away_team_id = $game["AwayTeam"]["TeamId"];

    foreach ($input_teams as $idx => $input_team) {
      $input_team_id = $input_team["Id"];

      if($input_team_id == $home_team_id) {
        $winner == "HomeTeam" ?
        $input_teams[$idx]["Wins"] += 1
        :
        $input_teams[$idx]["Losses"] += 1;
      }

      if($input_team_id == $away_team_id) {
        $winner == "AwayTeam" ?
        $input_teams[$idx]["Wins"] += 1
        :
        $input_teams[$idx]["Losses"] += 1;
      }
    }
  }
  return $input_teams;
}

function insert_opponents($games, $input_teams) {
  foreach ($games as $game) {
    foreach($input_teams as $idx => $input_team) {
      $input_team_id = $input_team["Id"];
      $home_team_id  = $game["HomeTeam"]["TeamId"];
      $away_team_id  = $game["AwayTeam"]["TeamId"];

      if($input_team_id == $home_team_id) {
        array_push($input_teams[$idx]["Opponents"], $away_team_id);
      }

      if($input_team_id == $away_team_id) {
        array_push($input_teams[$idx]["Opponents"], $home_team_id);
      }
    }
  }
  return $input_teams;
}

function insert_win_loss_per($teams) {
  foreach ($teams as $idx => $team) {

    $wins = $team["Wins"];
    $losses = $team["Losses"];

    // value overwritten by conditions below if $wins or $losses == 0
    $win_loss_per = ($wins / ( $wins + $losses ) * 100);

    if($wins == 0) { 
      $win_loss_per = 0; 
    }

    if($losses == 0) { 
      $win_loss_per = 100; 
    }

    $teams[$idx]["Win_loss_per"] = $win_loss_per;
  }
  return $teams;
}

function insert_opponents_win_loss_per($teams) {
  foreach ($teams as $idx => $team) {
    $opponents = $team["Opponents"];
    $opponents_win_loss_per = 0;
    $num_of_opponents = 0;
    // opponents is an array of ids
    foreach ($opponents as $opponent) {
      foreach($teams as $team) {
        if($team["Id"] == $opponent) {
          $opponents_win_loss_per += $team["Win_loss_per"];
        }
        $num_of_opponents += 1;
      }
    }
    $opponents_win_loss_per /= $num_of_opponents;
    $teams[$idx]["Opponents_win_loss_per"] = $opponents_win_loss_per;
  }
  return $teams;
}



function insert_opponents_opponents_win_loss_per($teams) {
  foreach($teams as $idx => $team) {
    $opponents = $team["Opponents"];
    $opponents_opponents_win_loss_per = 0;
    $num_of_opponents_opponents = 0;
    // opponents is an array of ids
    foreach($opponents as $opponent) {
      foreach($teams as $team) {
        if($team["Id"] == $opponent) {
          $opponent_opponents = $team["Opponents"];
          // opponent_opponents is an array of ids
          foreach($opponent_opponents as $opponent_opponent) {
            foreach($teams as $team) {
              if($team["Id"] == $opponent_opponent) {
                $opponents_opponents_win_loss_per += $team["Win_loss_per"];
                $num_of_opponents_opponents +=1;
              }
            }
          }
        }
      }
    }
    $opponents_opponents_win_loss_per /= $num_of_opponents_opponents;
    $teams[$idx]["Opponents_opponents_win_loss_per"] = $opponents_opponents_win_loss_per;
  }
  return $teams;
}

$games = get_games();
$teams = array();
$teams = insert_team_ids($games, $teams);
$teams = insert_wins_and_losses($games, $teams);
$teams = insert_opponents($games, $teams);
$teams = insert_win_loss_per($teams);
$teams = insert_opponents_win_loss_per($teams);
$teams = insert_opponents_opponents_win_loss_per($teams);

print_r($teams);

?>