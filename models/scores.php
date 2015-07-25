<?php

/**
 * Class Score This class represents a single instance of a score at some point in time
 * for one person or another that is currently being held by our database
 */
class Score {

    /**
     * @var Player $player
     * @var int $score
     * @var int $rank
     * @var array $raw
     * @var float $percentile
     * @var DateTime $first_created
     * @var String $hash
     * @var int $hidden
     */
    public $player, $score, $rank, $raw, $percentile, $first_created, $hash, $hidden;

    /**
     * Okay... let's get started with this can of worms. According to the original author,
     * 'This class doubles as score retrieval and score storage class,' meaning that there
     * is a lot of overlap here in a constructor for a class which has one method otherwise,
     * which makes me wonder why it wasn't just made into a function which returned an array
     *
     * Anyway, at the current time, to look for a score, just pass an array of
     * {"hash" => $score_hash} to retrieve that score and its information. Otherwise, you can,
     * and I quote: 'pass an array with data provided by the table.'
     *
     * @param array $score Some mystical data that does something
     */
    public function __construct($score) {
        /**
         * @var ThroneBase $db
         */
        $db = Application::getDatabase();

//        $score = array();

        //If a hash was provided
        if (isset($score["hash"])) {

            //Our job is to get the score that hash corresponds to
            try {
                $score = $db->get_single_score($score['hash']);
            } catch (Exception $e) {
                die ("Error reading score: " . $e->getMessage());
            }

            //And, now we have that score, we can construct a corresponding player object
            // and shove the data we initially got into a 'raw' hiding place apparently
            $score["player"] = new Player(array("search" => $score["steamId"]));
            $score["raw"] = $score;

        }

        //Now, once again, there's no validation checking here to see that we didn't just
        // get passed an empty array, because, if we did... this will hurt a lot of things
        //
        //Either way, now we have the data, we build it into a score object for use with
        // the website
        $this->player = $score["player"];

        if (isset($score["percentile"]))
            $this->percentile = $score["percentile"];

        $this->score = $score["score"];
        $this->rank = $score["rank"];
        $this->hash = $score["raw"]["hash"];
        $this->hidden = $score["hidden"];

        if (isset($score["raw"])) {
            $this->raw = $score["raw"];
        }

        if ($score['first_created'] == "0000-00-00 00:00:00") {
            $score['first_created'] = "n/a";
        }

        $this->first_created = $score["first_created"];

    }

    /**
     * Return an array representation of this score object for whatever day it
     * represents
     *
     * @return array
     */
    public function to_array() {
        return array(
            "player" => $this->player->to_array(),
            "hash" => $this->hash,
            "score" => $this->score,
            "rank" => $this->rank,
            "first_created" => $this->first_created,
            "percentile" => ceil($this->percentile * 100),
            "hidden" => $this->hidden,
            "raw" => $this->raw
        );
    }
}
