<?php

require_once 'indent_json.php';

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

// Input: None
// Output: [{ id: 1068426, Name: Miami Heat, ... }, { id: 1068426, Name: Lakers, ... }, ... ]
function get_games()
{
    $file_contents_str = file_get_contents("game_results.json");
    $file_contents_json = json_decode($file_contents_str, true);
    $games = $file_contents_json["Games"]["Results"];
    return $games;
}

// Input:  []
// Output: [{ id: 1068426, Name: Miami Heat, ... }, { id: 1068426, Name: Lakers, ... } ... ]
function insert_team_ids($games, $teams)
{
    $inserted_ids = array();
    foreach ($games as $game) {
        foreach (["HomeTeam", "AwayTeam"] as $home_away_status) {
            $team_id = $game[$home_away_status]["TeamId"];
            $team_name = $game[$home_away_status]["Name"];

            if (in_array($team_id, $inserted_ids)) {
                continue;
            }

            array_push($inserted_ids, $team_id);

            $team = array(
                'Id' => $team_id,
                'Name' => $team_name,
            );
            array_push($teams, $team);
        }
    }
    return $teams;
}

// Input:  { Id: 1, ... , HomeTeam: { ... , Score: 65 }, AwayTeam: { ... , Score: 33 } }
// Output: "HomeTeam"
//
// or
//
// Input:  { Id: 1, ... , HomeTeam: { ... , WonBy: 7 }, AwayTeam: { ... } }
// Output: "HomeTeam"
function get_winner($game)
{
    if (!key_exists("WonBy", $game["HomeTeam"])) {
        return "HomeTeam";
    }

    if (!key_exists("WonBy", $game["AwayTeam"])) {
        return "AwayTeam";
    }

    $home_team_score = $game["HomeTeam"]["Score"];
    $away_team_score = $game["AwayTeam"]["Score"];

    $winner = $home_team_score > $away_team_score ?
    "HomeTeam" : "AwayTeam";

    return $winner;
}

// Input:  [{ Id: 1, Name: Miami Heat, ... }]
// Output: [{ Id: 1, Name: Miami Heat, ... , W: 3, L: 2 }]
function insert_W_and_L($games, $input_teams)
{
    foreach ($games as $game) {

        $winner = get_winner($game);

        $home_team_id = $game["HomeTeam"]["TeamId"];
        $away_team_id = $game["AwayTeam"]["TeamId"];

        foreach ($input_teams as $idx => $input_team) {

            if (!key_exists("W", $input_team)) {
                $input_team["W"] = 0;
            }

            if (!key_exists("L", $input_team)) {
                $input_team["L"] = 0;
            }

            if ($input_team["Id"] == $home_team_id) {

                $winner == "HomeTeam" ?

                $input_team["W"] += 1
                :
                $input_team["L"] += 1;
            }

            if ($input_team["Id"] == $away_team_id) {

                $winner == "AwayTeam" ?

                $input_team["W"] += 1
                :
                $input_team["L"] += 1;
            }
            $input_teams[$idx] = $input_team;
        }
    }
    return $input_teams;
}

// Input:  [{ Id: 1, Name: Miami Heat, ... }]
// Output: [{ Id: 1, Name: Miami Heat, ... , Opponents: array( 1068407, 1068426, ... , 1068388 ) }]
function insert_opponents($games, $input_teams)
{
    foreach ($games as $game) {
        foreach ($input_teams as $idx => $input_team) {

            $input_team_id = $input_team["Id"];
            $home_team_id = $game["HomeTeam"]["TeamId"];
            $away_team_id = $game["AwayTeam"]["TeamId"];

            if (!key_exists("Opponents", $input_teams[$idx])) {
                $input_teams[$idx]["Opponents"] = array();
            }

            if ($input_team_id == $home_team_id) {
                array_push($input_teams[$idx]["Opponents"], $away_team_id);
            }

            if ($input_team_id == $away_team_id) {
                array_push($input_teams[$idx]["Opponents"], $home_team_id);
            }
        }
    }
    return $input_teams;
}

function insert_W_per($teams)
{
    foreach ($teams as $idx => $team) {
        $wins = $team["W"];
        $losses = $team["L"];
        // value overwritten by conditions below if $wins or $losses == 0
        $W_per = ($wins / ($wins + $losses) * 100);

        if ($wins == 0) {
            $W_per = 0;
        }
        if ($losses == 0) {
            $W_per = 100;
        }
        $teams[$idx]["W%"] = $W_per;
    }
    return $teams;
}

// Input:  [{ Id: 1, Name: Miami Heat, ... }]
// Output: [{ Id: 1, Name: Miami Heat, ... , OW_per: 50 }]
function insert_OW_per($teams)
{
    foreach ($teams as $idx => $team) {
        $opponents = $team["Opponents"];
        $OW_per = 0;
        $num_of_opponents = 0;
        // opponents is an array of ids
        foreach ($opponents as $opponent) {

            foreach ($teams as $team) {

                if ($team["Id"] != $opponent) {
                    continue;
                }

                $OW_per += $team["W%"];
                $num_of_opponents += 1;
            }
        }
        $OW_per /= $num_of_opponents;
        $teams[$idx]["OW%"] = $OW_per;
    }
    return $teams;
}

// Input:  [{ Id: 1, Name: Miami Heat, ... }]
// Output: [{ Id: 1, Name: Miami Heat, ... , OOW_per: 50 }]
function insert_OOW_per($teams)
{
    foreach ($teams as $idx => $team) {
        $opponents_OW_per = 0;
        $num_of_opponents_opponents = 0;

        foreach ($team["Opponents"] as $opponent) { // loop through opponents

            foreach ($teams as $team) { // Find opponent in teams

                if ($team["Id"] != $opponent) {
                    continue;
                }

                foreach ($team["Opponents"] as $opponent) { // Loop through Opponents opponents

                    foreach ($teams as $team) { // Find ooponents opponent in teams

                        if ($team["Id"] != $opponent) {
                            continue;
                        }

                        $opponents_OW_per += $team["W%"];
                        $num_of_opponents_opponents += 1;
                    }
                }
            }
        }
        $opponents_OW_per /= $num_of_opponents_opponents;
        $teams[$idx]["OOW%"] = $opponents_OW_per;
    }
    return $teams;
}

function array_column_min($elems, $key)
{
    $values = array_column($elems, $key);
    $min_value = min($values);
    return $min_value;
}

function array_column_max($elems, $key)
{
    $values = array_column($elems, $key);
    $max_value = max($values);
    return $max_value;
}

// Scaled W/L% = [ 0.5 * ( W/L% - MIN( W/L% ) ) ] / { [ MAX(W/L%) - MIN(W/L%) ] } + 0.5
function scaled_W_per($W_per, $min_W_per, $max_W_per)
{
    return (0.5 * ($W_per - $min_W_per)) / ($max_W_per - $min_W_per) + 0.5;
}

// Input:  [{ Id: 1, Name: Miami Heat, ... }]
// Output: [{ Id: 1, Name: Miami Heat, ... , Scaled_W_per: 50 }]
function insert_scaled_W_per($teams)
{
    $max_W_per = array_column_max($teams, "W%");
    $min_W_per = array_column_min($teams, "W%");
    foreach ($teams as $idx => $team) {
        $W_per = $team["W%"];
        $scaled_W_per = scaled_W_per($W_per, $min_W_per, $max_W_per);
        $teams[$idx]["Scaled_W%"] = $scaled_W_per;
    }
    return $teams;
}

// SOS = 2/3 * (O-W/L %) + 1/3 * (OO-W/L%)
// Input:
function SOS($OW_per, $OOW_per)
{
    return 2 / 3 * $OW_per + 1 / 3 * $OOW_per;
}

function insert_SOS($teams)
{
    foreach ($teams as $idx => $team) {
        $OW_per = $team["OW%"];
        $OOW_per = $team["OOW%"];
        $SOS = SOS($OW_per, $OOW_per);
        $teams[$idx]["SOS"] = $SOS;
    }
    return $teams;
}

function multi_array_key_values($elems, $key)
{
    $values = array();
    foreach ($elems as $elem) {
        $value = $elem[$key];
        array_push($values, $value);
    }
    return $values;
}

function scaled_SOS($SOS, $min_SOS, $max_SOS)
{
    return (0.5 * ($SOS - $min_SOS)) / (($max_SOS - $min_SOS) + 0.5);
}

// Scaled SOS = { ( 0.5 ) * [ ( SOS ) - MIN( SOS ) ] } / { [ MAX(SOS) - MIN(SOS) ] } + 0.5
function insert_scaled_SOS($teams)
{
    $min_SOS = array_column_min($teams, "SOS");
    $max_SOS = array_column_max($teams, "SOS");
    foreach ($teams as $idx => $team) {
        $SOS = $team["SOS"];
        $scaled_SOS = scaled_SOS($SOS, $min_SOS, $max_SOS);
        $teams[$idx]["Scaled_SOS"] = $scaled_SOS;
    }
    return $teams;
}

function insert_W_per_power_score($teams)
{
    // Points from W/L = W/L Weight x (Scaled W/L %)
    $W_per_weight = 80;
    foreach ($teams as $idx => $team) {
        $scaled_W_per = $team["Scaled_W%"];
        $W_per_power_score = $W_per_weight * $scaled_W_per;
        $teams[$idx]["W%_power_score"] = $W_per_power_score;
    }
    return $teams;
}
// W/L% Weight = 20

// Points from SOS = W/L% Weight x (Scaled SOS %)
function insert_SOS_power_score($teams)
{
    $SOS_weight = 20;
    foreach ($teams as $idx => $team) {
        $scaled_SOS = $team["Scaled_SOS"];
        $SOS_power_score = $SOS_weight * $scaled_SOS;
        $teams[$idx]["SOS_power_score"] = $SOS_power_score;
    }
    return $teams;
}

function insert_power_score($teams)
{
    foreach ($teams as $idx => $team) {
        $W_per_power_score = $team["W%_power_score"];
        $SOS_power_score = $team["SOS_power_score"];
        $power_score = $W_per_power_score + $SOS_power_score;
        $teams[$idx]["Power_score"] = $power_score;
    }
    return $teams;
}

$games = get_games();
$teams = array();
$teams = insert_team_ids($games, $teams);
$teams = insert_W_and_L($games, $teams);
$teams = insert_opponents($games, $teams);
$teams = insert_W_per($teams);
$teams = insert_OW_per($teams);
$teams = insert_OOW_per($teams);
$teams = insert_scaled_W_per($teams);
$teams = insert_SOS($teams);
$teams = insert_scaled_SOS($teams);
$teams = insert_W_per_power_score($teams);
$teams = insert_SOS_power_score($teams);
$teams = insert_power_score($teams);

// Sort teams by asc order by power score
array_multisort(array_map(function ($elem) {
    return $elem["Power_score"];
}, $teams), SORT_ASC, $teams);

$teams = json_encode($teams);
$teams = indent_json($teams);

print_r($teams);

file_put_contents('power_rankings.json', $teams);
