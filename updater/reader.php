<?php

/**
 * The reader file deals in getting all the runs throughout the day. It should
 * be on a crontab to run every 15 minutes, server load depending. apparently
 * there is a 1 second pause between downloads, which should provide a long
 * enough pause, presuming that there are no more than 900 scores every 15
 * minutes
 */

require("config.php");
//TODO: Uncomment. This is here because I'm not messing with Twitter for the
// minute
//require("codebird.php");

/**
 * A quick method to shoot off a http request to somewhere from in-house.
 * Note: This is a duplicated method from somewhere that we could combine.
 *
 * @param $url
 * @return mixed
 */
function get_data($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

//The throne daily resets at 00:00 UTC, so that's our timezone
date_default_timezone_set("UTC");

/**
 * This function will pull the latest leaderboard data from Steam and update the
 * data for that day's daily in the database for caching purposes.
 *
 * @param int $leaderboardId
 */
function update_leaderboard($leaderboardId = -1) {
    global $db_username, $db_password, $db_location, $db_name, $twitter_settings, $steam_apikey;

    $leaderboardDate = 0;

    //If no leaderboard was provided, we're just going to assume that they want the latest
    // leaderboard we can get
    if ($leaderboardId === -1) {

        // Fetch the XML file for Nuclear Throne.
        $xmlLeaderboardList = get_data('http://steamcommunity.com/stats/242680/leaderboards/?xml=1');

        // Make a SimpleXMLElement instance to read the file.
        $leaderboardReader = new SimpleXMLElement($xmlLeaderboardList);

        // Find last leaderboard in the file (i.e., today's daily)
        // Steam sometime fucks us over, so we have to account for that and grab the previous
        // runs instead if todays aren't available.
        $found_good_leaderboard = false;
        $last = 0;

        //While we have no valid leaderboard...
        while ($found_good_leaderboard == false) {

            //We start by checking the last available leaderboard, we can make use of a magic Steam
            // last() function to do this, so that helps
            if ($last == 0)
                $lastLeaderboardElemenent = $leaderboardReader->xpath("/response/leaderboard[last()]/lbid");
            //Otherwise, it's however many leaderboards we are before the last leadboard
            else
                $lastLeaderboardElemenent = $leaderboardReader->xpath(
                    sprintf("/response/leaderboard[last()-%d]/lbid", $last)
                );

            //Get the currently inspected leaderboard
            $leaderboardId = (int)$lastLeaderboardElemenent[0];

            //And, if it's the first iteration again, we can use the magic last() function
            if ($last == 0)
                $lastLeaderboardDate = $leaderboardReader->xpath("/response/leaderboard[last()]/name");
            else
                $lastLeaderboardDate = $leaderboardReader->xpath(
                    sprintf("/response/leaderboard[last()-%d]/name", $last)
                );

            //Let's set up an array that we're going to immediately fill with the leaderboard
            // date
            $cleanDate = array();
            preg_match("/^daily_lb_([0-9]+)$/", $lastLeaderboardDate[0], $cleanDate);

            //I assume that this is a magic number that represents the point where the actual
            // leaderaboards start
            @$leaderboardDate = (int)$cleanDate[1] - 16421;

            //If it's less than 0, then it isn't an actual leaderboard
            if ($leaderboardDate < 0) {
                //So we have to look one further back
                $last += 1;
                print("Leaderboard not found, going to last - " . $last . "\n");
                continue;
            } else {
                //Otherwise, we found it! Continue.
                print ($leaderboardDate[0]);
                $found_good_leaderboard = true;
            }
        }

        //Mark today's date
        $todayDate = new DateTime('2014-12-17');
        $todayDate->add(new DateInterval('P' . $leaderboardDate . 'D'));

    } else {

        //Otherwise, we've been provided with a leaderboard id, so get the main leaderboard
        // crawler again
        $xmlLeaderboardList = get_data('http://steamcommunity.com/stats/242680/leaderboards/?xml=1');

        // Make a SimpleXMLElement instance to read the file.
        $leaderboardReader = new SimpleXMLElement($xmlLeaderboardList);

        //And find the requested leaderboard
        $lastLeaderboardDate = $leaderboardReader->xpath(
            sprintf("/response/leaderboard[lbid=%d]/name", $leaderboardId)
        );

        //Now that we have that, let's put that thing into todays date
        $cleanDate = array();
        preg_match("/^daily_lb_([0-9]+)$/", $lastLeaderboardDate[0], $cleanDate);
        $leaderboardDate = (int)$cleanDate[1] - 16421;

        //Like this =D
        $todayDate = new DateTime('2014-12-17');
        $todayDate->add(new DateInterval('P' . $leaderboardDate . 'D'));

    }

    // Download the today's daily challenge leaderboard
    $leaderboardUrl =
        sprintf("http://steamcommunity.com/stats/242680/leaderboards/%d/?xml=1",
            $leaderboardId);
    $xmlLeaderboardData = get_data($leaderboardUrl);

    // Instance another SimpleXMLElement to read from it.
    $xmlLeaderboard = new SimpleXMLElement($xmlLeaderboardData);

    // Connect to the database (in a separate instance again).
    $db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
        $db_username, $db_password,
        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    /* I assume this comment set and the statements therein are legacy instances, so I'll
     * leave them be for the time being
    // Purge scores from today so that there are no rank collisions.
    // $stmt = $db->prepare("DELETE FROM throne_scores WHERE dayId = ?;");
    // $stmt->execute(array($leaderboardId));
     */
    //If it's the first update of the day, we need to add in another day to the throne
    // tracker punch card thing
    $stmt = $db->prepare("INSERT IGNORE INTO throne_dates(`dayId`, `date`) VALUES(:dayId, :day)");
    $stmt->execute(array(
        ':dayId' => $leaderboardId,
        ':day' => $todayDate->format('Y-m-d')
    ));

    $scores = array();

    //For each entry into todays leaderboard...
    foreach ($xmlLeaderboard->entries->entry as $entry) {

        // Don't count runs with no score (score = -1).
        if ($entry->score >= 0) {
            // For each score, we shall make a unique hash, by combining their steamid
            // and today's daily leaderboard ID.
            $hash = md5($leaderboardId . $entry->steamid);
            // We'll put all results into an array so that we can weed out the hackers.
            $scores[] = array('hash' => $hash, 'dayId' => $leaderboardId, 'steamID' => $entry->steamid, 'score' => $entry->score, 'rank' => $entry->rank);
        }

    }

    // Sort by rank.
    usort($scores, function ($a, $b) {
        return $a['rank'] - $b['rank'];
    });

    // get list of banned people
    $banned = array();

    //For each person we've got as a suspected hacker...
    foreach ($db->query(
        "SELECT steamid FROM throne_players WHERE suspected_hacker = 1"
    ) as $row) {
        //Add them to the array of banned people
        $banned[] = $row['steamid'];
    }

    // get a list of hidden players for today
    foreach ($db->query(
        sprintf(
            "SELECT steamId FROM throne_scores WHERE hidden = 1 AND dayId = %d", $leaderboardId
        )
    ) as $row) {
        //Add then to the list of banned people for today
        $banned[] = $row['steamid'];
        echo("[DEBUG] Hiding scores by " . $row['steamid'] . " today.\n");
    }

    //Mark the number one rank
    $rank = 1;
    //And the lowest rank currently possible
    $rank_hax = count($scores) + 1;

    try {

        //Start some transaction management for a minute so we can roll out everything
        // as a single big change as opposed to many little ones
        $db->beginTransaction();

        foreach ($scores as $score) {

            /*  I assume that this is more legacy code. Leaving it along for the moment
             *   if ($rank == 1) {
                if ($score["steamID"] != file_get_contents("first.txt") && $score["score"] > 300) {
                  $file = fopen("first.txt", "w");
                  fwrite($file, $score["steamID"]);
                  fclose($file);

                  \Codebird\Codebird::setConsumerKey($twitter_settings["consumer_key"], $twitter_settings["consumer_secret"]);
                  $cb = \Codebird\Codebird::getInstance();
                  $cb->setToken($twitter_settings["oauth_access_token"], $twitter_settings["oauth_access_token_secret"]);

                  $jsonUserData = get_data("http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".$steam_apikey."&steamids=" . $score['steamID']);
                  $user = json_decode($jsonUserData, true);

                  $username = $user["response"]["players"][0]["personaname"];

                  $params = array(
                    'status' => $username . " has taken the lead with " . $score["score"] . " kills!"
                  );
                  $reply = $cb->statuses_update($params);
                }

              }*/

            //If this person is not on the list of those currently banned from the leaderboard
            if (array_search($score['steamID'], $banned) === false) {

                // Prepare the SQL statement
                $stmt = $db->prepare(
                    "INSERT INTO throne_scores(hash, dayId, steamId, score, rank, first_created)
                    VALUES(:hash, :dayId,:steamID,:score,:rank,NOW())
                    ON DUPLICATE KEY UPDATE rank=VALUES(rank), score=VALUES(score);"
                );

                // Insert data into the database
                $stmt->execute(array(
                    ":hash" => $score['hash'],
                    ":dayId" => $score['dayId'],
                    ":steamID" => $score['steamID'],
                    ":score" => $score['score'],
                    ":rank" => $rank
                ));

                //And prop the rank up one more for the next person
                $rank += 1;

            } else {

                //Hackers get their own special rank, so, rather suitably, they get their own
                // SQL statement too
                $stmt = $db->prepare(
                    "INSERT INTO throne_scores(hash, dayId, steamId, score, rank, hidden, first_created)
                    VALUES(:hash, :dayId,:steamID,:score,:rank,:hidden,NOW())
                    ON DUPLICATE KEY UPDATE rank=VALUES(rank), score=VALUES(score), hidden=VALUES(hidden);"
                );

                // Insert data into the database
                $stmt->execute(array(
                    ":hash" => $score['hash'],
                    ":dayId" => $score['dayId'],
                    ":steamID" => $score['steamID'],
                    ":score" => $score['score'],
                    ":rank" => $rank_hax,
                    ":hidden" => 1
                ));

                //And prop the rank up one more for the next hacker
                $rank_hax += 1;

            }

        }

        // Commit our efforts.
        $db->commit();

    } catch (PDOException $ex) {
        // Failsafe

        $db->rollBack();
        echo $ex->getMessage();
    }

    //And say that we've done updating
    echo "Finished updating today's leaderboards.\n";

}

/**
 * Once all the scores have been stored, some new people will have been found (it's
 * definitely a possibility), so we need to add anyone who is missing in our profile
 * tables
 */
function update_steam_profiles() {

    //More databases!
    global $db_username, $db_password, $db_location, $db_name, $steam_apikey;
    $db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
        $db_username, $db_password,
        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    //Lift the time limit on php scripts: this is going to take a while...
    set_time_limit(0);

    // Check which steam ids are eligible for an update
    // SteamIDs will update only if they've been active on the leaderboards in the
    // past five days and if they have not been updated in the past day (or
    // at all)

    $result = $db->query(
        "SELECT DISTINCT throne_scores.steamId FROM throne_scores
          LEFT JOIN throne_players ON throne_scores.steamId = throne_players.steamid
        WHERE DATEDIFF(NOW(), throne_scores.last_updated) < 5
          AND (DATEDIFF(NOW(), throne_players.last_updated) > 1
            OR throne_players.last_updated IS NULL
          );"
    );

    $t = $result->rowCount();
    // Logging.
    echo($t . " profiles to update. \n");

    //A counting variable which is necessary to make sure that we don't overstay our welcome on Steam
    $c = 0;
    try {

        // Prepare for a major alteration
        $db->beginTransaction();

        foreach ($result as $row) {

            $jsonUserData = "";

            //For each player, we get their profile page and save their name and a link
            // to their avatar.
            try {

                global $steam_apikey;
                $jsonUserData = get_data(
                    sprintf(
                        "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s",
                        $steam_apikey, $row['steamId']
                    )
                );

                //Decode the data
                $user = json_decode($jsonUserData, true);

                //And insert the person straight in
                $stmt = $db->prepare(
                    "INSERT INTO throne_players(steamid, name, avatar)
                    VALUES(:steamid, :name, :avatar)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), avatar=VALUES(avatar), last_updated=NOW();"
                );

                $stmt->execute(array(
                    ":steamid" => $row['steamId'],
                    ":name" => $user["response"]["players"][0]["personaname"],
                    ":avatar" => $user["response"]["players"][0]["avatar"]
                ));

                // Log the update.
                printf(
                    "[%d/%d] Updated %s as %s\n",
                    $c, $t, $row['steamId'], $user["response"]["players"][0]["personaname"]
                );

            } catch (Exception $e) {

                //Print an amount of debug

                printf("----- Profile Update Failed -----\n");

                printf(
                    "[%d/%d]   Failed to update %s due to %s\n",
                    $c, $t, $row['steamId'], $e->getMessage()
                );
                printf(
                    "[%d/%d]   Pulled from: http://api.steampowered.com/ISteamUser/GetPlayerSummaries".
                    "/v0002/?key=%s&steamids=%s\n", $c, $t, $steam_apikey, $row['steamId']
                );
                printf(
                    "[%d/%d]   Result: ", $c, $t
                );
                var_dump($jsonUserData);

                printf("\n----- Profile Debug Completed -----\n");

            }

            //Wait for 0.2 seconds so that we don't piss off Lord GabeN and mistakenly
            // DDoS Steam.
            usleep(200000);
            $c = $c + 1;

            // I have to do this.
            //I assume that this is done because of the time that it must be taking by this
            // point and the number of requests that have been fired off to Steam. We need
            // to stop before we go too far
            if ($c === 500) {
                break;
            }

        }

        //Commit all those changes we've made
        $db->commit();

    } catch (PDOException $ex) {
        // Failsafe

        $db->rollBack();
        echo $ex->getMessage();

    }

}

/**
 * As part of the site also promotes current streamers of Nuclear Throne, we should also
 * have a function here which updates our tables of just who is streaming Nuclear Throne
 * right now
 */
function update_twitch() {
    global $db_username, $db_password, $db_location, $db_name;

    //Get some data from the twitch api in regards to who is currently streaming Nuclear
    // Throne (with a limit of 25 results)
    $streamJson = get_data("https://api.twitch.tv/kraken/search/streams?limit=25&q=nuclear+throne");
    $streams = json_decode($streamJson, true);

    //One more database instantiation couldn't hurt, right? Wrong.
    $db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
        $db_username, $db_password,
        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    try {

        //Begin a transaction
        $db->beginTransaction();

        //And clear all previous streamers
        $db->query("TRUNCATE TABLE throne_streams");

        //For each stream we have found, add it into the streaming table
        foreach ($streams['streams'] as $stream) {

            $stmt = $db->prepare(
                "INSERT INTO throne_streams(name, status, viewers, preview)
                VALUES(:name, :status, :viewers, :preview)"
            );

            $stmt->execute(array(
                ":name" => $stream['channel']['name'],
                ":status" => $stream['channel']['status'],
                ":viewers" => $stream['viewers'],
                ":preview" => str_replace("http://","https://", $stream['preview']['small'])
            ));

        }

        //Commit all those changes
        $db->commit();

    } catch (PDOException $ex) {
        //Failsafe...
        $db->rollBack();
        echo $ex->getMessage();
    }

    //And say that things worked
    echo "Twitch update successful. \n";

}

//We're starting our updates...
echo "Begin update: " . date("Y-m-d H:i:s") . "\n";

// I don't know why I made them into functions.
//Presumably because this is a nice structure down here and it allows them to
// be used as actual functions too
if (isset($argv[1])) {
    update_leaderboard($argv[1]);
} else {
    update_leaderboard();
}
update_twitch();
update_steam_profiles();

//End of updates.
echo "End update: " . date("Y-m-d H:i:s") . "\n";
