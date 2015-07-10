<?php

/**
 * This file deals with some very common functions that will be called upon by
 * all areas of the site, centralising them here and - well, you get the picture
 */

date_default_timezone_set("UTC");
global $db;

/**
 * When provided with the steamid of a player, return all the profile data that we
 * have on that player
 *
 * A search for usages draws a blank through - which is somewhat odd. This whole file
 * may actually be redundant if that's the case...
 *
 * @param String $steamid
 * @return array
 */
function get_player($steamid) {

    //Get the globals from our config definitions to create a new database instance
    //Note: This should be completely unnecessary. There is already a database interface
    // that takes the form of the Application static class. Why not just centralise
    // all the database connections into one place? It means we can avoid global vars
    // like the sketchy things that they are.
    //Note: Currently untested as to whether $db_location and $db_name are in scope
    global $db_username, $db_password, $db_location, $db_name;
    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%scharset=utf8', $db_location, $db_name),
        $db_username, $db_password, array(
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        )
    );

    //If we've been given a player handle as the steamid, we have to go through the
    // process of converting it to an actual profile number
    if ((int) $steamid == 0) {
        $steamid = convertSteamId($steamid);

        //Which, if it fails, means that the user doesn't actually exist on Steam
        // return an empty array to signify failure.
        if ($steamid == false) {
            return array();
        } //$steamid == false

    } //(int) $steamid == 0

    //Get all the players with that steam id
    $stmt = $db->prepare(
        "SELECT * FROM throne_players WHERE steamid = :steamid"
    );
    $stmt->execute(array(
        ':steamid' => $steamid
    ));

    //And if no players were returned, throw the method back in someone's face
    if ($stmt->rowCount() === 0) {
        return array();
    } //$stmt->rowCount() === 0

    //Get all the rows that were turned up in the search
    $rows                    = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //And throw them into a player variable (however we need to manually add in
    // the medium avatar)
    $player                  = $rows[0];
    $player['avatar_medium'] = substr($player['avatar'], 0, -4) . "_medium.jpg";

    //If the player's name is not available, that means they're basically holding
    // a private profile and we're not invited
    if ($player['name'] === "") {
        $player['name'] = "[no profile]";
    } //$player['name'] === ""

    //If their avatar is blank, we just need to provide them with a nice, simple
    // avatar for the time being
    //Note: This may be unnecessary, I'm fairly certain that Steam enforces an
    // avatar for those who don't actually have one just yet
    if ($player['avatar'] === "") {
        $player['avatar_medium'] = "/img/no-avatar.png";
        $player['avatar']        = "/img/no-avatar-small.png";
    } //$player['avatar'] === ""

    //Now let's fill in some scores for the player...
    $scores = array();
    $stmt   = $db->prepare(
        "SELECT * FROM throne_scores
        LEFT JOIN throne_dates ON throne_scores.dayId = throne_dates.dayId
        LEFT JOIN (
          SELECT dayid AS d, COUNT(*) AS runs FROM throne_scores
           GROUP BY dayid) x ON x.d = throne_scores.dayId
        WHERE throne_scores.steamId = :steamid
        ORDER BY throne_scores.dayId ASC LIMIT 100"
    );
    $stmt->execute(array(
        ":steamid" => $steamid
    ));

    //Generate a few scope variables for us to mess with...
    $allscores  = array();
    $totalscore = 0;
    $allrank    = array();
    $totalrank  = 0;
    $count      = 0;

    //And start sorting through each of the rows that we've gathered
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $score) {

        //Calculate their percentage as their rank in comparison to the number of
        // people who did the daily that day
        $score['percentage'] = ceil(((int) $score['rank'] / (int) $score['runs']) * 100);
        // Make sure that the score shown is at least 0.
        $score['score'] = max(0, $score['score']);

        //Add those details into the end score array
        $scores[]            = $score;

        //And add some of the details into the surrounding variables around it
        $totalscore += $score['score'];
        $totalrank += $score['rank'];
        $count       = $count + 1;
        $allscores[] = $score['score'];
        $allrank[]   = $score['rank'];

    } //$stmt->fetchAll(PDO::FETCH_ASSOC) as $score

    //I believe (note the choice of language; I'm not sure on this one), that this
    // statement gets the players global alltime ranking.
    $stmt = $db->prepare(
        "SELECT  d.*, c.ranks FROM (
          SELECT score, @rank:=@rank+1 Ranks FROM (
            SELECT DISTINCT Score FROM throne_alltime a
            ORDER BY score DESC
          ) t, (SELECT @rank:= 0) r
        ) c INNER JOIN throne_alltime d ON c.score = d.score
        WHERE d.steamid = :steamid"
    );
    $stmt->execute(array(
        ":steamid" => $steamid
    ));

    //If the rowcount is empty, they presumably haven't actually ranked anywhere
    // yet on the leaderboards (which makes you wonder how the hell they're in the
    // system; unless they hacked the first score they scored - which is just a bit
    // sad really)
    if ($stmt->rowCount() === 0) {
        $player['totalrank'] = -1;
    } else {
        //Otherwise, we can add their total rank and total kills to the leaderboard
        $row                  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $player['totalrank']  = $row[0]['ranks'];
        $player['totalkills'] = $row[0]['score'];
    }

    //Add in a lot more general background statistics while we're here (because why not)
    $player['avgscore'] = round($totalscore / $count);
    $player['avgrank']  = round($totalrank / $count);
    $player['hiscore']  = max($allscores);
    $player['loscore']  = min($allscores);
    $player['hirank']   = max($allrank);
    $player['lorank']   = min($allrank);
    $player['runs']     = $count;

    //And return the final array to the caller, complete with many things!
    return array(
        'player' => $player,
        'steamid' => $steamid,
        'scores' => $scores,
        'scores_reverse' => array_reverse($scores)
    );

}

/**
 * Internal call only (which effectively means that it's unused) and, in the words of
 * the original developer, was 'Ported from my LotusClan web admin interface thing.'
 *
 * This snippet will, when presented with a steam profile instead of the id, get the
 * id of that profile if it is available to be got
 *
 * @param String $steamid
 * @return string
 */
function convertSteamId($steamid) {

    //Get rid of any surrounding tags (that should probably have been removed before
    // being passed in here at any rate
    $steamid = trim(strip_tags($steamid));

    //Get the user's details directly from the steam community site
    //You're being suppressed? I see no suppression here. You're a suppression!
    $xml = @file_get_contents("http://steamcommunity.com/id/" . $steamid . "?xml=1");
    
    // Verify if the community ID exists 
    if (preg_match("/<steamID64>(\d*)<\/steamID64>/", $xml, $match)) { // based regex xml reading
        //And if it does, return that match
        return $match[1];
    } //preg_match("/<steamID64>(\d*)<\/steamID64>/", $xml, $match)

    //Otherwise, we didn't see anything, we didn't do anything, we didn't touch anything... got it?
    return "";

}
