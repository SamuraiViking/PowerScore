<?php

require_once 'pretty_json.php'; // has pretty_json Fn which takes json input and returns a formatted/indented JSON
include 'keys.php';

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

    $game_results = trim(curl_exec($ch));
    $game_results = pretty_json($game_results);

    file_put_contents('games.json', $game_results);
}

get_most_recent_game_results($API_KEY, $SECRET_KEY, $URL);
