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
    return Application::getDatabase()->check_user_is_admin($steamid);
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
    Application::getDatabase()->set_score_hidden($hash, $state);
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
    Application::getDatabase()->mark_user_hacker($user,$state);
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

    //We need to ping the Steam WebAPI for these details, so let's do so
    $jsonUserData = get_data(
        sprintf(
            "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s",
            STEAMAPI, $userId)
    );

    //The data we get back has been jsonified (note: should probably specify this in the url to
    // get the data)
    $user = json_decode($jsonUserData, true);

    //Now we have to update the profile of that user with these details (even if they're identical)
    //TODO: Research is an update faster than a check of two values?
    try {
        Application::getDatabase()->update_user( $userId,
            $user["response"]["players"][0]["personaname"],
            $user["response"]["players"][0]["avatar"]
        );
    } catch (Exception $e) {
        die($e->getMessage());
    }

}
