<?php

require_once 'indent_json.php'; // has indent_json Fn which takes json input and returns a formatted/indented JSON

function get_most_recent_game_results()
{
    $api_key = 'R9bUvcj9pJYuGy';

    $datetime = new \DateTime();
    $datetime->setTimezone(new \DateTimeZone('UTC'));
    $timestamp = $datetime->format('Y-m-d\TH:i:s.u\Z');

    $path = $api_key . '&get&' . $timestamp . '&/api/v1/games';
    $message = strtoupper($path);
    $secret_key = 'QLn448NjP97hBd2pW7Q99963K9T9s9wB';
    $hash = hash_hmac('sha256', $message, $secret_key, true);
    $hashString = base64_encode($hash);

    $headers = [
        'Timestamp:' . $timestamp,
        'Authentication:' . $api_key . '.' . $hashString,
        "Content-Type: application/json",
        'Accept: application/json',
        'Content-length: 0',
    ];

    $url = 'https://basketball.exposureevents.com/api/v1/games?eventid=122706&pagesize=300';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $game_results = trim(curl_exec($ch));
    $game_results = indent_json($game_results);

    file_put_contents('games.json', $game_results);
}

get_most_recent_game_results();
