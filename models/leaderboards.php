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
     * @var ThroneBase $db
     */
    private $db;

    /**
     * Ping the database application to get a copy of the connection
     */
    public function __construct() {
        $this->db = Application::getDatabase();
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

        $this->db->clear_magic_variables();
        return $this->db->generate_alltime_leaderboard($order_by, $start, $size, $direction);

    }

    public function generate_day_info($date) {
        //If the caller provided a pure number, that means that they want a leaderboard
        // given definition by the offset of the days from today
        if (is_int($date)) {
            return $this->db->get_day_id_from_offset($date);
        }
        return $this->db->get_day_id_from_date($date);
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

        $day = $this->generate_day_info($date);

        //TODO: Cascade the change of dayId instead of date being used (because why the heck
        // would you use date instead of an id?)

        //Otherwise (and this is so very sketchy) we assume that the date we've been given
        // is completely valid and is the defined date. We then proceed to not do any
        // escaping whatever and insert it into the next statement. Sure... it doesn't
        // currently expose any flaws, but damn...
        //
        //Note: Perform validation on the given date before we just shove it straight into
        // the query
        $this->date = $day['date'];
        $this->global_stats = $this->db->get_global_statistics($day['dayId']);

        //Return an instance of this leaderboard to the caller
        return $this->make_leaderboard("throne_scores.dayId", $day['dayId'], $order_by, $direction, $start, $length);

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
        $dayId = $this->generate_day_info($date)['dayId'];

        //... Okay, this is incredibly shifty and bodgy; the very much hardcoded table
        // means that this is going to suffer in terms of portability (should the need
        // arise)
        return $this->make_leaderboard(
            "throne_scores.steamid", $steamid, $order_by,
            $direction, $start, $length, $dayId
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

        //TODO: Actually check that we have any scores to shove into an array first

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
     * Note: This should be altered to only use a pure integer instead of... well, not.
     *
     * @return Leaderboard
     * Note: This is stupid. This is OO, so we're passing back a reference to this object
     *  instead of a copy. This means that the user now has two references to this exact,
     *  class and a change with one reference does it to the other. There's no point in
     *  even having it here if that's intentional.
     *
     * After analysing the code a bit further, I'm a bit more perplexed. Nothing truly
     * substantial is ever done with the returned reference, as, in all instances found,
     * we immediately send it through the to_array() method above (or below, can't remember),
     * which kinda means that the whole thing is somewhat jacked in the interest in saving
     * one line of code per use.
     */
    private function make_leaderboard($where, $condition, $order_by, $direction, $start = 0, $len = 0, $dateId = -1) {

        $entries = $this->db->generate_leaderboard(
            $where, $condition, $order_by, $direction, $start, $len, $dateId
        );

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
                "donated" => $entry["donated"],
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