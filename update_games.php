<?php

include 'keys.php';

function valid_game_results($game_results)
{
    if ($game_results == null) {
        echo "\ngot NULL response from API. Make sure that the URL is correct\n\n";
        return false;
    }

    if (property_exists($game_results, "Errors")) {
        $error_msg = $game_results->Errors[0]->Message;
        echo "\n$error_msg\n\n";
        return false;
    }

    if (!property_exists($game_results, "Games")) {
        echo "\nExpected game_results to have property \"Games\"\n\n";
        return false;
    }

    if (!property_exists($game_results->Games, "Results")) {
        echo "\nExpected game_results[\"Games\"] to have property \"Results\"\n\n";
        return false;
    }

    return true;
}

function get_most_recent_game_results($input_api_key, $input_secret_key, $input_url)
{
    $api_key = $input_api_key;

    $datetime = new \DateTime();
    $datetime->setTimezone(new \DateTimeZone('UTC'));
    $timestamp = $datetime->format('Y-m-d\TH:i:s.u\Z');

    $path = $api_key . '&get&' . $timestamp . '&/api/v1/games';
    $message = strtoupper($path);
    $secret_key = $input_secret_key;
    $hash = hash_hmac('sha256', $message, $secret_key, true);
    $hashString = base64_encode($hash);

    $headers = [
        'Timestamp:' . $timestamp,
        'Authentication:' . $api_key . '.' . $hashString,
        "Content-Type: application/json",
        'Accept: application/json',
        'Content-length: 0',
    ];

    $url = $input_url;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $games = trim(curl_exec($ch));
    $games_json = json_decode($games);

    if (!valid_game_results($games_json)) {
        return;
    }

    $games = json_encode($games_json, JSON_PRETTY_PRINT);

    file_put_contents('games.json', $games);
}

get_most_recent_game_results($API_KEY, $SECRET_KEY, $URL);
