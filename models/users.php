<?php

/**
 * Not so much a class as a collection of functions that are used primarily
 * with the users in mind (such as the admin checks)
 */

/**
 * An abstracted function that allows us to get data from a given url
 *
 * @param String $url This is the URL we're sending a request to
 * @return String
 */
function get_data($url) {

    //Start the Curl object
    $ch = curl_init();
    $timeout = 5;

    //Add in our terms we need to search by
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

    //Perform the search
    $data = curl_exec($ch);

    //Close the handler and return the data
    curl_close($ch);
    return $data;

}

/**
 * Allows us to check whether a user is an administrator of the site or not.
 * If they are, their privilege is: 'entitled'
 *
 * @param String $steamid The steam ID that we're checking the administrative
 *  status of
 * @return int
 */
function check_your_privilege($steamid) {

    //Another instance of database instantiation... Why... seriously. All of these
    // pages are redirects of the index, and the ones that aren't don't even use the
    // central database position that is there? wtf?
    global $db_username, $db_password, $db_location, $db_name;
    $db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
        $db_username, $db_password,
        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    //Get the (whole?) row associated with the person we're looking for
    $stmt = $db->prepare(
        "SELECT * FROM throne_players WHERE steamid = :steamid"
    );
    $stmt->execute(array(
        ':steamid' => $steamid
    ));

    //If no-one was returned, then rather obviously, no-one here is an admin
    if ($stmt->rowCount() === 0) {
        return 0;
    } //$stmt->rowCount() === 0

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $player = $rows[0];

    //Otherwise, return whether the user is an admin or not
    return $player['admin'];

}

/**
 * Hide/show someone's score in the leaderboards (for whatever reason)
 *
 * @param String $hash This is the generated hash of the score that we're
 *  hiding in order to keep it secret, keep it safe
 * @param int $state This corresponds to the action we'd like to perform
 *  (whether it's hiding a score or showing it). 1 is hide, 0 is show
 * Note: Should probably be a boolean value
 */
function hide_score($hash, $state = 1) {

    //? We don't need no central database ?
    //(Sung to the tune of Pink Floyd's Another Brick in the Wall)
    global $db_username, $db_password, $db_location, $db_name;
    $db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
        $db_username, $db_password,
        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    //Update the state
    $stmt = $db->prepare(
        "UPDATE throne_scores SET hidden = :state WHERE hash = :hash"
    );
    $stmt->execute(array(
        ':hash' => $hash,
        ':state' => $state
    ));

    //And assume it worked (I guess...)

}

/**
 * Mark a profile as a hacker of either this or Steam (could theoretically be one or the
 * other I guess). This just marks someone as suspected of hacking for the time being,
 * however, this function can also remove that suspicion too, if it's asked
 *
 * @param String $user The steamid of the person we're marking as a suspect
 * @param int $state 1 if we suspect them, 0 if we absolve them of all guilt
 */
function mark_hacker($user, $state = 1) {

    //See previous remarks about multiple database instantiations
    global $db_username, $db_password, $db_location, $db_name;
    $db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
        $db_username, $db_password,
        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    //All this does is update a row with the state
    $stmt = $db->prepare(
        "UPDATE throne_players SET suspected_hacker = :state WHERE steamid = :user"
    );
    $stmt->execute(array(
        ':user' => $user,
        ':state' => $state
    ));

    //And, again, we assume it worked

}

/**
 * Sometimes, people change - profiles maybe more so. This function allows us to update
 * user profiles with any new information from Steam. However, what this doesn't do, is
 * perform any comparisons whatsoever with the data we get from Steam. We're constantly
 * updating rows with the same information if that's the case.
 *
 * @param String $userId The steam ID of the person we're updating
 */
function update_profile($userId) {

    //More duplication, Princess Fluffybutt?
    global $db_username, $db_password, $db_location, $db_name, $steam_apikey;
    $db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
        $db_username, $db_password,
        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    //We need to ping the Steam WebAPI for these details, so let's do so
    $jsonUserData = get_data(
        sprintf(
            "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s",
            $steam_apikey, $userId)
    );

    //The data we get back has been jsonified (note: should probably specify this in the url to
    // get the data)
    $user = json_decode($jsonUserData, true);

    //Now we have to update the profile of that user with these details (even if they're identical)
    try {
        /**
         * @var PDOStatement $stmt
         */
        $stmt = $db->prepare(
            "UPDATE throne_players SET
              name = :name,
              avatar = :avatar,
              last_updated = NOW()
            WHERE steamid = :steamid"
        );
        $stmt->execute(
            array(
                ':steamid' => $userId,
                ':name' => $user["response"]["players"][0]["personaname"],
                ':avatar' => $user["response"]["players"][0]["avatar"]
            )
        );
    } catch (Exception $e) {
        die($e->getMessage());
    }

}
