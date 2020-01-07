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
        'Opponents' => array()
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

  if(!key_exists("Score", $game["HomeTeam"])) {
    return;
  }

  $home_team_score = $game["HomeTeam"]["Score"];
  $away_team_score = $game["AwayTeam"]["Score"];
  $winner = $home_team_score > $away_team_score ?
  "HomeTeam" : "AwayTeam";

  return $winner;  
}

function insert_wins_and_losses($games, $teams) {
  foreach($games as $game) {

    $winner = get_winner($game);

    foreach(["HomeTeam", "AwayTeam"] as $home_away_status) {

      $team_id = $game[$home_away_status]["TeamId"];

      foreach($teams as $idx => $team) {

        if($team_id != $team['Id']) { 
          continue; 
        }

        $winner == $home_away_status ?
        $teams[$idx]["Wins"] += 1
        :
        $teams[$idx]["Losses"] += 1;
      }
    }
  }
  return $teams;
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

// Loop through games
//  Loop through teams
//    if home_team == input_team_id
//       input_team_id => opponents => away_team_id
//    if away_team == input_team_id
//       input_team_id => opponents => home_team_id
// 

$games = get_games();
$teams = array();
$teams = insert_team_ids($games, $teams);
$teams = insert_wins_and_losses($games, $teams);
$teams = insert_opponents($games, $teams);

print_r($teams);

?>