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
     * @param array $data Some mystical data that does something
     */
    public function __construct($data) {
        /**
         * @var PDO $db
         */
        $db = Application::$db;

        //If a hash was provided
        if (isset($data["hash"])) {

            //Our job is to get the score that hash corresponds to
            try {
                $stmt = $db->prepare(
                    "SELECT * FROM `throne_scores`
                      LEFT JOIN `throne_dates` ON `throne_dates`.dayId = `throne_scores`.dayId
                    WHERE `hash` = :hash"
                );
                $stmt->execute(array(':hash' => $data["hash"]));
                $result = $stmt->fetchAll();
            } catch (Exception $e) {
                die ("Error reading score: " . $e->getMessage());
            }

            //And, now we have that score, we can construct a corresponding player object
            // and shove the data we initially got into a 'raw' hiding place apparently
            $data = $result[0];
            $data["player"] = new Player(array("search" => $data["steamId"]));
            $data["raw"] = $data;

        }

        //Now, once again, there's no validation checking here to see that we didn't just
        // get passed an empty array, because, if we did... this will hurt a lot of things
        //
        //Either way, now we have the data, we build it into a score object for use with
        // the website
        $this->player = $data["player"];

        if (isset($data["percentile"]))
            $this->percentile = $data["percentile"];

        $this->score = $data["score"];
        $this->rank = $data["rank"];
        $this->hash = $data["raw"]["hash"];
        $this->hidden = $data["hidden"];

        if (isset($data["raw"])) {
            $this->raw = $data["raw"];
        }

        if ($data['first_created'] == "0000-00-00 00:00:00") {
            $data['first_created'] = "n/a";
        }

        $this->first_created = $data["first_created"];

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
