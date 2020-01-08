<?php

/**
 * This File imports the matches from games.json
 * and then outputs the teams into teams.json
 * in order of their power score.
 *
 * The Calculation for the power score of each team can be
 * Seen Down Below
 *
 */

//////////////////////////////////
// 1. W% Percentage Power Score //
//////////////////////////////////

// W  = Wins
// L  = Losses
// W% = Win Percentage

// W% = W / ( W + L )

// Scaled W% = { ( 0.5 ) * [ ( W% ) - MIN( W% ) ] } / { [ MAX(W%) - MIN(W%) ] } + 0.5

// W% Weight = 80

// W% Power Score = W% Weight x (Scaled W%)

/////////////////////////////////////////
// 2. Strength of Schedule Power Score //
/////////////////////////////////////////

// SOS = Strength of Schedule

// W% = W / ( W + L )

// OW% = Sum of W% of all Opponents / Number of Opponents

// OOW% = Sum of W% of all Opponents Opponents / Number of Opponents Opponents

// SOS = 2/3 * (O-W/L %) + 1/3 * (OO-W%)

// Scaled SOS = { ( 0.5 ) * [ ( SOS ) - MIN( SOS ) ] } / { [ MAX(SOS) - MIN(SOS) ] } + 0.5

// W% Weight = 20

// SOS Power Score = W% Weight x (Scaled SOS %)

////////////////////
// 3. Power Score //
////////////////////

// Power Score = SOS Power Score + W% Power Score

// Input: None
// Output: [{ id: 1068426, Name: Miami Heat, ... }, { id: 1068426, Name: Lakers, ... }, ... ]
function get_games()
{
    $file_contents_str = file_get_contents("games.json");
    $file_contents_json = json_decode($file_contents_str, true);
    $games = $file_contents_json["Games"]["Results"];
    return $games;
}

// Input:  []
// Output: [{ id: 1068426, Name: Miami Heat, ... }, { id: 1068426, Name: Lakers, ... } ... ]
function insert_team_name_and_ids($games, $teams)
{
    $inserted_ids = array();
    foreach ($games as $game) {

        $home_team = $game["HomeTeam"];
        $away_team = $game["AwayTeam"];

        foreach ([$home_team, $away_team] as $team) {

            $team_id = $team["TeamId"];
            $team_name = $team["Name"];

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
function winner($game)
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
function insert_wins_and_losses($games, $teams)
{
    foreach ($games as $game) {

        $winner = winner($game);
        $home_team_id = $game["HomeTeam"]["TeamId"];
        $away_team_id = $game["AwayTeam"]["TeamId"];

        foreach ($teams as $idx => $team) {

            if (!key_exists("W", $team)) {
                $team["W"] = 0;
            }

            if (!key_exists("L", $team)) {
                $team["L"] = 0;
            }

            if ($team["Id"] == $home_team_id) {
                $winner == "HomeTeam" ?
                $team["W"] += 1
                :
                $team["L"] += 1;
            }

            if ($team["Id"] == $away_team_id) {
                $winner == "AwayTeam" ?
                $team["W"] += 1
                :
                $team["L"] += 1;
            }
            $teams[$idx] = $team;
        }
    }
    return $teams;
}

function find_elem($elems, $key, $value)
{
    foreach ($elems as $elem) {
        if ($elem[$key] === $value) {
            return $elem;
        }
    }
    return false;
}

function find_elem_idx($elems, $key, $value)
{
    foreach ($elems as $idx => $elem) {
        if ($elem[$key] === $value) {
            return $idx;
        }
    }
    return false;
}

// Input :  [ { ... }, { ... },  ... ]
//
// Output:  [ { ... Opponent_ids: array( 125423, 1256345 ) },
//            { ... Opponent_ids: array( 125423, 1256345 ) },  ... ]
function insert_opponents($games, $teams)
{
    foreach ($games as $game) {

        $home_team_id = $game["HomeTeam"]["TeamId"];
        $away_team_id = $game["AwayTeam"]["TeamId"];

        foreach ([$home_team_id, $away_team_id] as $team_id) {

            // find idx of elem where elem["Id"] == $team_id in $teams
            $idx = find_elem_idx($teams, "Id", $team_id);

            if (!key_exists("Opponent_ids", $teams[$idx])) {
                $teams[$idx]["Opponent_ids"] = array();
            }

            $opponent_id = $team_id == $home_team_id ? $away_team_id : $home_team_id;

            array_push($teams[$idx]["Opponent_ids"], $opponent_id);
        }
    }
    return $teams;
}

function insert_win_per($teams)
{
    foreach ($teams as $idx => $team) {
        $wins = $team["W"];
        $losses = $team["L"];
        // value overwritten by conditions below if $wins or $losses == 0
        $win_per = ($wins / ($wins + $losses) * 100);
        if ($wins == 0) {$win_per = 0;}
        if ($losses == 0) {$win_per = 100;}

        $teams[$idx]["W%"] = $win_per;
    }
    return $teams;
}

// Input :  [ { ...                   }, { ...                   },  ... ]
// Output:  [ { ... OW%: 43, OOW%: 50 }, { ... OW%: 30, OOW%: 20 },  ... ]
function insert_opponents_and_opponents_opponents_win_per($teams)
{
    foreach ($teams as $idx => $team) {

        $OW_per = 0;
        $OOW_per = 0;
        $num_of_opponents = 0;
        $num_of_opponents_opponents = 0;

        $opponents_ids = $team["Opponent_ids"];
        foreach ($opponents_ids as $opponent_id) {

            $opponent = find_elem($teams, "Id", $opponent_id);
            $OW_per += $opponent["W%"];
            $num_of_opponents += 1;

            $opponents_ids = $opponent["Opponent_ids"];
            foreach ($opponents_ids as $opponent_id) {

                $opponent = find_elem($teams, "Id", $opponent_id);
                $OOW_per += $opponent["W%"];
                $num_of_opponents_opponents += 1;

            }
        }
        $OW_per /= $num_of_opponents;
        $OOW_per /= $num_of_opponents_opponents;
        $teams[$idx]["OW%"] = $OW_per;
        $teams[$idx]["OOW%"] = $OOW_per;
    }
    return $teams;
}

function scaled_W_per($W_per, $min_W_per, $max_W_per)
{
    return (0.5 * ($W_per - $min_W_per)) / ($max_W_per - $min_W_per) + 0.5;
}

// Input:  [{ ... },
//          { ... }, ... ]
//
// Output: [{ ... Scaled_W_per: 50 },
//          { ... Scaled_W_per: 80 }, ... ]
function insert_scaled_win_per($teams)
{
    $min_W_per = min(array_column($teams, "W%"));
    $max_W_per = max(array_column($teams, "W%"));
    foreach ($teams as $idx => $team) {

        $W_per = $team["W%"];
        $scaled_W_per = scaled_W_per($W_per, $min_W_per, $max_W_per);
        $teams[$idx]["Scaled_W%"] = $scaled_W_per;
    }
    return $teams;
}

function strength_of_schedule($OW_per, $OOW_per)
{
    return (2 / 3 * $OW_per) + (1 / 3 * $OOW_per);
}

function insert_strength_of_schedule($teams)
{
    foreach ($teams as $idx => $team) {

        $OW_per = $team["OW%"];
        $OOW_per = $team["OOW%"];
        $SOS = strength_of_schedule($OW_per, $OOW_per);
        $teams[$idx]["SOS"] = $SOS;
    }
    return $teams;
}

function scaled_strength_of_schedule($SOS, $min_SOS, $max_SOS)
{
    return (0.5 * ($SOS - $min_SOS)) / (($max_SOS - $min_SOS) + 0.5);
}

// Input:  [{ ...                   }, { ...                   }, ... ]
// Output: [{ ... scaled_SOS: 0.435 }, { ... scaled_SOS: 0.817 }, ... ]
function insert_scaled_strength_of_schedule($teams)
{
    $min_SOS = min(array_column($teams, "SOS"));
    $max_SOS = max(array_column($teams, "SOS"));
    foreach ($teams as $idx => $team) {

        $SOS = $team["SOS"];
        $scaled_SOS = scaled_strength_of_schedule($SOS, $min_SOS, $max_SOS);
        $teams[$idx]["Scaled_SOS"] = $scaled_SOS;
    }
    return $teams;
}

// Input:  [{ ...                    }, { ...                    }, ... ]
// Output: [{ ... W%_power_score: 54 }, { ... W%_power_score: 78 }, ... ]
function insert_win_per_power_score($teams)
{
    $W_per_weight = 80;
    foreach ($teams as $idx => $team) {

        $scaled_W_per = $team["Scaled_W%"];
        $W_per_power_score = $W_per_weight * $scaled_W_per;
        $teams[$idx]["W%_power_score"] = $W_per_power_score;
    }
    return $teams;
}

// Input:  [{ ...                     }, { ...                     }, ... ]
// Output: [{ ... SOS_power_score: 63 }, { ... SOS_power_score: 72 }, ... ]
function insert_strength_of_schedule_power_score($teams)
{
    $SOS_weight = 20;
    foreach ($teams as $idx => $team) {

        $scaled_SOS = $team["Scaled_SOS"];
        $SOS_power_score = $SOS_weight * $scaled_SOS;
        $teams[$idx]["SOS_power_score"] = $SOS_power_score;
    }
    return $teams;
}

// Input:  [{ ...                 }, { ...                 }, ... ]
// Output: [{ ... power_score: 63 }, { ... power_score: 72 }, ... ]
function insert_power_score($teams)
{
    foreach ($teams as $idx => $team) {

        $power_score = $team["W%_power_score"] + $team["SOS_power_score"];
        $teams[$idx]["Power_score"] = $power_score;
    }
    return $teams;
}

function multi_array_sort($teams, $key)
{
    array_multisort(array_map(function ($elem) {
        return $elem["Power_score"];
    }, $teams), SORT_ASC, $teams);
    return $teams;
}

function update_teams($file)
{
    $games = get_games();
    $teams = array();
    $teams = insert_team_name_and_ids($games, $teams);
    $teams = insert_wins_and_losses($games, $teams);
    $teams = insert_opponents($games, $teams);
    $teams = insert_win_per($teams);
    $teams = insert_opponents_and_opponents_opponents_win_per($teams);
    $teams = insert_scaled_win_per($teams);
    $teams = insert_strength_of_schedule($teams);
    $teams = insert_scaled_strength_of_schedule($teams);
    $teams = insert_win_per_power_score($teams);
    $teams = insert_strength_of_schedule_power_score($teams);
    $teams = insert_power_score($teams);
    $teams = multi_array_sort($teams, "Power_score");
    $teams = json_encode($teams, JSON_PRETTY_PRINT);
    file_put_contents($file, $teams);
}

update_teams("teams.json");
