<?php

/**
 * I believe that this file should be on a crontab to run every day at midnight.
 * Every time it runs, it should report that days winner in terms of who did the
 * best at the Daily Challenge to Twitter.
 */

require("config.php");
require("codebird.php");

// Connect to the database (another duplication!).
$db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
    $db_username, $db_password,
    array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);

// Get latest day ID in a very roundabout method
$daily = $db->query(
    "SELECT * FROM throne_dates ORDER BY dayId DESC LIMIT 0,2"
);
$result = $daily->fetchAll();

//Get the details for the day that just passed
$today = $result[0]['dayId'];
$today_date = $result[0]['date'];

//And get the winner from the database with some good-ol' PHP insertion...
$winner = $db->query(
    "SELECT throne_scores.score, throne_players.name FROM throne_scores
      LEFT JOIN throne_players ON throne_players.steamid = throne_scores.steamId
    WHERE throne_scores.dayId = '".$today."' ORDER BY rank ASC LIMIT 0,1"
);
$result = $winner->fetchAll();

//Now just tweet the result to our good friend, Twitter
\Codebird\Codebird::setConsumerKey($twitter_settings["consumer_key"], $twitter_settings["consumer_secret"]);

$cb = \Codebird\Codebird::getInstance();
$cb->setToken($twitter_settings["oauth_access_token"], $twitter_settings["oauth_access_token_secret"]);

$params = array(
    'status' => $result[0]['name'] . " is today's winner with " . $result[0]['score'] . " kills!"
);

//It seems that Codebird uses magic methods, so that's fine
$reply = $cb->statuses_update($params);
