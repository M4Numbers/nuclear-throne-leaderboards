<?php

/**
 * This file should be in a crontab to run every hour, depending on service load.
 * It represents how the alltime database is updated with all the new scores for
 * the last hour of the day
 */

//We require ourselves a config file... Men!
require "config.php";

// I... um... no.
if (!isset($db_username)) {

    //Look at it this way, if the $db_username is not set, it's unlikely that the
    // password is set either (and this is completely ignoring the bucket of worms
    // that including root as a username is)
    $db_username = "root";

    //As an alternative, I would say that if this check fails, the script should
    // die; there are no-longer any certainties and we should end everything asap

} // My dev box sucks.

function update_alltime() {

    //Some more duplication for your troubles, squire?
    global $db_username, $db_password, $db_location, $db_name;

    $db = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8', $db_location, $db_name),
        $db_username, $db_password,
        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    try {
        //Let's actually adhere to transactional standards for a minute
        $db->beginTransaction();

        //And truncate the alltime table completely...
        $db->query("TRUNCATE TABLE throne_alltime");

        //... before rebuilding it back up from the ground
        $db->query(
            "INSERT INTO throne_alltime(steamid, score, average, runs)
              SELECT throne_scores.steamId, SUM(score) as score,
                AVG(score) as average, COUNT(score) AS runs
              FROM `throne_scores`
                LEFT JOIN throne_players ON throne_scores.steamId = throne_players.steamid
              WHERE suspected_hacker = 0
              GROUP BY throne_scores.steamId
              ORDER BY score DESC"
        );

        //And, once that's done, commit all our changes to the database, meaning that
        // the runtime of everything shouldn't be affected (probably)
        $db->commit();

    } catch (PDOException $ex) {
        //If this fails, we have to immediately rollback all our changes so that nothing
        // untoward happens to the database
        $db->rollBack();
        echo $ex->getMessage();
    }

    //Then report a successful update
    echo "All time database update successful. \n";

}

update_alltime();
