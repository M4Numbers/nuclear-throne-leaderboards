<?php

/**
 * Class Leaderboard This class represents the ability of the website to generate a
 * representative leaderboard of a number of statistics. It has a number of options
 * that allow for it to used as a general daily leaderboard generator, and an alltime
 * leaderboard generator
 */
class Leaderboard {

    /**
     * @var array $scores
     * @var String $date
     * @var array $gloabal_stats
     */
    public $scores, $date, $global_stats;

    /**
     * @var PDO $db
     */
    private $db;

    /**
     * Ping the database application to get a copy of the connection
     */
    public function __construct() {
        $this->db = Application::$db;
    }

    /**
     * The users of this site will sometimes want the all-time rankings of everyone on
     * Steam... isn't it just unbelievably handy that we have a method which deals in
     * just that?
     *
     * @param int $start The offset location from the 0th row
     * @param int $size The number of rows to get from the offset
     * @param string $order_by Choose which column we're ordering by
     * @param string $direction Theoretically should allow the user to specify whether
     *  they want results returned in ascending or descending order... However, at the
     *  moment, it doesn't do that.
     * @return array
     */
    public function create_alltime($start = 0, $size = 30, $order_by = "score", $direction = "DESC") {

        //Two magic methods to reset a few magic variables we have stored somewhere in
        // the database (though I'm not precisely sure where yet)
        $this->db->query("SET @prev_value = NULL");
        $this->db->query("SET @rank_count = 0");

        //Note: May be worthwhile to make a Switch statement with the $order_by var in
        // order to validate that the caller has, in fact, chosen a valid order option

        //Yep... this is an SQL statement...
        //
        //... Yep.
        $stmt = $this->db->prepare(
            "SELECT  d.*, p.*, c.ranks, w.* FROM (
              SELECT {$order_by}, @rank:=@rank+1 ranks FROM (
                SELECT  DISTINCT {$order_by} FROM throne_alltime a
                ORDER BY {$order_by} DESC
              ) t, (SELECT @rank:= 0) r
            ) c INNER JOIN throne_alltime d ON c.{$order_by} = d.{$order_by}
              LEFT JOIN throne_players p ON p.steamid = d.steamid
              LEFT JOIN (
                (SELECT COUNT(*) as wins, steamid FROM throne_scores
                 WHERE rank = 1 GROUP BY steamid) AS w
              ) ON w.steamid = d.steamid
            ORDER BY c.ranks ASC LIMIT :start, :size"
        );

        //Get all those entries that we asked for
        $stmt->execute(array(":start" => $start, ":size" => $size));
        $entries = $stmt->fetchAll();

        return $entries;

    }

    /**
     * Creates a leaderboard based on either a date in YYYY-MM-DD format or an
     * offset from today's date, returning the daily scores for that day either
     * which way
     *
     * @param String $date Either a date in a YYYY-MM-DD format, or an integer which
     *  represents the offset in terms of days (where 0 is today and 1 is yesterday)
     * @param String $order_by Choose the column to be ordered on
     * @param String $direction Optional variable for defining how we want the results
     *  ordered upon being returned
     * @param Integer $start Offsets the leaderboard from the 0th row
     * @param Integer $length This is the number of rows that will be returned (counting
     *  from the offset)
     * @return Leaderboard the class instance for easy manipulation. Results are stored
     *  in ->scores.
     */
    public function create_global($date, $order_by = "rank", $direction = "ASC", $start = 0, $length = 30) {

        //If the caller provided a pure number, that means that they want a leaderboard
        // given definition by the offset of the days from today
        if (is_int($date)) {

            //We do this by performing some slight trickery that could perhaps backfire
            // if one days leaderboards went down spectacularly on Steam (I dunno, I just
            // know that there are ways that this can break)
            $leaderboard = $this->db->query(
                "SELECT * FROM throne_dates ORDER BY dayId DESC"
            );
            $result = $leaderboard->fetchAll();

            //The ID is currently unused (so I have no idea why it is here (unless it was
            // originally planned to be an if...else statement which would return $dateId
            // regardless, which would have been an alternate parameter value to the
            // ->make_leaderboard() $date parameter)
            $dateId = $result[$date]['dayId'];
            $date = $result[$date]['date'];

        }

        //Otherwise (and this is so very sketchy) we assume that the date we've been given
        // is completely valid and is the defined date. We then proceed to not do any
        // escaping whatever and insert it into the next statement. Sure... it doesn't
        // currently expose any flaws, but damn...
        //
        //Note: Perform validation on the given date before we just shove it straight into
        // the query
        $this->date = $date;
        $stats = $this->db->query(
            "SELECT COUNT(*) AS runcount, AVG(score) AS avgscore
              FROM throne_scores
              LEFT JOIN throne_dates ON throne_scores.dayId = throne_dates.dayId
              WHERE `date` = '" . $date . "'");
        $this->global_stats = $stats->fetchAll()[0];

        //Return an instance of this leaderboard to the caller
        return $this->make_leaderboard("date", $date, $order_by, $direction, $start, $length);

    }

    /**
     * Create a personalised leaderboard when presented with a specific player ID
     *
     * @param String $steamid
     * @param string $order_by
     * @param string $direction
     * @param int $start
     * @param int $length
     * @param int $date
     * @return Leaderboard
     */
    public function create_player($steamid, $order_by = "date", $direction = "DESC",
                                  $start = 0, $length = 0, $date = -1) {
        //... Okay, this is incredibly shifty and bodgy; the very much hardcoded table
        // means that this is going to suffer in terms of portability (should the need
        // arise)
        return $this->make_leaderboard(
            "throne_scores`.`steamid", $steamid, $order_by,
            $direction, $start, $length, $date
        );
    }

    /**
     * A method which allows an end user to convert everything the leaderboard to a
     * shippable array for use with other areas of the site
     *
     * @param int $start
     * @param int $length
     * @return array
     */
    public function to_array($start = 0, $length = -1) {

        //Start with an empty array
        $array_scores = array();

        //If a length was not defined, return all scores into the array
        if ($length == -1) {
            $length = count($this->scores) + 1;
        }

        //And for each score we have in shot of us, add that score to our array
        // that we're planning on sending back to the user
        foreach (array_slice($this->scores, $start, $length, TRUE) as $score) {
            /**
             * @var Score $score
             */
            $array_scores[] = $score->to_array();
        }

        //Now throw it back
        return $array_scores;

    }

    /**
     * Look through all the scores we currently have cached and return some general
     * statistics to do with all that data we have lying around (such as the count
     * and the average score for all of this)
     *
     * @return array
     */
    public function get_global_stats() {
        $array_scores = array();

        foreach ($this->scores as $score) {
            $array_scores[] = $score->score;
        }

        asort($array_scores);

        $stats = array();
        $stats["count"] = count($array_scores);
        $stats["sum"] = array_sum($array_scores);
        $stats["average"] = round($stats["sum"] / max($stats["count"], 1));
        $top10 = array_slice($array_scores, -10);
        $stats["average_top10"] = round(array_sum($top10) / max(count($top10), 1));
        if ($stats["sum"] > 999) {
            $stats["ksum"] = floor($stats["sum"] / 1000) . "K";
        } else {
            $stats["ksum"] = $stats["sum"];
        }

        return $stats;

        /**
         * I get the idea that this was once how the global statistics were done - within
         * an SQL statement that is now being kept around as legacy code in comments
         *
         * return $this->db->query('SELECT COUNT(*) AS amount, ROUND(AVG(score)) AS average
         * FROM throne_scores
         * LEFT JOIN throne_dates ON throne_scores.dayId = throne_dates.dayId
         * WHERE `date` = "' . $this->date . "\"")->fetchAll(PDO::FETCH_ASSOC)[0];
         */

    }

    /**
     * This is a helper function for building the query and subsequent leaderboard from
     * the database
     *
     * Note: $order_by and $direction are both unverified and could potentially be
     * hazardous should an error arise in one of those two variables
     *
     * @param String $where This is the WHERE column that we're going to use as a filter
     * @param String|Integer $condition This is the corresponding required value for the
     *  WHERE clause
     * @param String $order_by What column are we ordering on?
     * @param String $direction This tells the SQL whether we want things in ascending or
     *  descending order from the tables
     * @param int $start This is the initial offset from the 0th row
     * @param int $len This is the number of rows we're getting (starting from the $start
     *  location provided above)
     * @param int $date Another unsafe variable. It is used for narrowing down the
     *  leaderboard to specific days if necessary
     *
     * @return Leaderboard
     * Note: This is stupid. This is OO, so we're passing back a reference to this object
     *  instead of a copy. This means that the user now has two references to this exact,
     *  class and a change with one reference does it to the other. There's no point in
     *  even having it here if that's intentional.
     */
    private function make_leaderboard($where, $condition, $order_by, $direction, $start = 0, $len = 0, $date = -1) {

        //Work out if we need a limit on this thing
        if ($len > 0) {
            $limit = "LIMIT $start, $len";
        } else {
            $limit = "";
        }

        //Then ask if we're being asked to make a narrow or wide search (with regards to
        // time anyway)
        if ($date > -1) {
            $leaderboard = $this->db->query('SELECT * FROM throne_dates ORDER BY dayId DESC');
            $result = $leaderboard->fetchAll();
            $date_today = $result[$date]['date'];
            $date_query = "AND throne_dates.date = '" . $date_today . "'";
        } else {
            $date_query = "";
        }

        try {
            //This query is in possession of several orders of sketch
            $query = $this->db->prepare(
                "SELECT * FROM `throne_scores`
                LEFT JOIN throne_dates ON throne_scores.dayId = throne_dates.dayId
                LEFT JOIN throne_players ON throne_scores.steamId = throne_players.steamid
                LEFT JOIN (
                  (SELECT COUNT(*) AS wins, steamId FROM throne_scores
                    WHERE rank = 1 GROUP BY steamId) AS w) ON w.steamId = throne_scores.steamId
                LEFT JOIN (
                    SELECT dayId AS d, COUNT(*) AS runs FROM throne_scores
                    GROUP BY dayId) x ON x.d = throne_scores.dayId
                WHERE `{$where}` = :cnd
                $date_query
                ORDER BY `{$order_by}` {$direction} {$limit}"
            );

            //Still... apparently we're going to try it and hope that nothing breaks
            $query->execute(array(":cnd" => $condition));
            $entries = $query->fetchAll();

        } catch (Exception $e) {
            //Or die trying... that's an acceptable path too
            die ("Error fetching leaderboard: " . $e->getMessage());
        }

        $scores = array();

        $parsedown = new Parsedown();
        foreach ($entries as $entry) {

            /**
             * We pack everything in the following structure
             *
             * [
             *  ...
             *  Score [
             *   player [
             *    steamid
             *    name
             *    avatar
             *    suspected_hacker
             *    admin
             *    twitch
             *    raw [
             *     wins
             *    ]
             *   ]
             *   hidden
             *   rank
             *   first_created
             *   percentile
             *   raw [
             *    hash
             *    video
             *    comment
             *   ]
             *  ]
             *  ...
             * ]
             */
            $meta = array("wins" => $entry["wins"]);

            $meta_scores = array("date" => $entry["date"],
                "hash" => $entry["hash"],
                "video" => $entry["video"],
                "comment" => $parsedown->text($entry["comment"]));

            $player = new Player(array("steamid" => $entry["steamId"],
                "name" => $entry["name"],
                "avatar" => $entry["avatar"],
                "suspected_hacker" => $entry["suspected_hacker"],
                "admin" => $entry["admin"],
                "twitch" => $entry["twitch"],
                "raw" => $meta));

            $scores[] = new Score(array("player" => $player,
                "score" => $entry["score"],
                "hidden" => $entry["hidden"],
                "rank" => $entry["rank"],
                "first_created" => $entry["first_created"],
                "percentile" => $entry["rank"] / $entry["runs"],
                "raw" => $meta_scores));

        }

        //Let's cache our scores too, meaning that the only thing that would
        // have made actual sense to return now also has no point on returning
        $this->scores = $scores;
        return $this;

    }

}

