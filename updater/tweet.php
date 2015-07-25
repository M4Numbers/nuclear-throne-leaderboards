<?php

/**
 * I believe that this file should be on a crontab to run every day at midnight.
 * Every time it runs, it should report that days winner in terms of who did the
 * best at the Daily Challenge to Twitter.
 */

require_once "../config/config.php";
require_once "../models/CentralDatabase.php";
require_once "../models/ThroneBase.php";
require_once "../models/application.php";
require_once "codebird.php";

// Connect to the database (another duplication!).
$db = Application::getDatabase();

// Get latest day ID in a very roundabout method
$day = $db->get_latest_day_id();

//Get the details for the day that just passed
$today = $day['dayId'];
$today_date = $day['date'];

//And get the winner from the database with some good-ol' PHP insertion...
$winner = $db->find_top_player_for_day($today);

//Now just tweet the result to our good friend, Twitter
\Codebird\Codebird::setConsumerKey($twitter_settings["consumer_key"], $twitter_settings["consumer_secret"]);

$cb = \Codebird\Codebird::getInstance();
$cb->setToken($twitter_settings["oauth_access_token"], $twitter_settings["oauth_access_token_secret"]);

$params = array(
    'status' => $winner['name'] . " is today's winner with " . $winner['score'] . " kills!"
);

//It seems that Codebird uses magic methods, so that's fine
$reply = $cb->statuses_update($params);
