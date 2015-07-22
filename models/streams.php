<?php

/**
 * Class Streams This class represents the streams that are currently running on Twitch
 * at the current time. This is primarily used by the Index page to show current streams
 */
class Streams {

    /**
     * @var ThroneBase $db
     */
    private $db;

    /**
     * @var array $streams An array of all the streams currently online on Twitch
     */
    public $streams;

    /**
     * Interesting little thing. One known usage of this constructor was found (3 instances
     * of ->streams was though, in the same scope interestingly enough). This class is
     * really just a function; it performs no real OO work of value considering that these
     * streams are used in one place and are in possession of no special properties that
     * require an object to represent them
     *
     * @param int $limit How many streams are we returning (even though this isn't used at
     *  all in the statement below)
     */
    public function __construct($limit = 3) {
        $this->db = Application::getDatabase();
        $this->streams = $this->db->get_streams();
    }

}
