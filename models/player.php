<?php

/**
 * Class Player This class represents a user profile on our thronebutt system that allows
 * for a player to be tracked as a steam user with actual details on that person (as opposed
 * to just keeping the scores as tracked by steamids)
 */
class Player {

    /**
     * @var String $steamid
     * @var String $name
     * @var String $avatar
     * @var String $twitch,
     * @var String $avatar_medium
     * @var int $suspected_hacker
     * @var int $admin
     * @var array $raw
     * @var int $rank
     */
    public $steamid, $name, $avatar, $twitch, $avatar_medium, $suspected_hacker, $admin, $raw, $rank;

    /**
     * @var PDO $db
     */
    private $db;

    /**
     * The constructor, when given a search user id, will find that player's details and
     * construct them into an object that represents them as a player.
     *
     * This function is... um... well, if I construct an input variable where data is:
     * ["steamid": 1], that isn't going to be caught and is just going to continue without
     * any other data to its name. In addition, if an empty array is passed, that will break
     * PHP (and the page that it's trying to load)
     *
     * @param array $data An array of potential items (even though only one is checked for)
     */
    public function __construct($data) {

        //Get the database instance (yep, it is used in the code, I know... amazing)
        $this->db = Application::$db;

        //And check that the search term has been set
        if (isset($data["search"])) {

            //Now, since it is, search for the provided term
            $stmt = $this->db->prepare(
                "SELECT * FROM `throne_players`
                  LEFT JOIN (
                    (SELECT COUNT(*) AS wins, steamId FROM throne_scores
                    WHERE rank = 1 GROUP BY steamId)
                  AS w) ON w.steamId = throne_players.steamid
                WHERE throne_players.steamid = :steamid"
            );

            $stmt->execute(array(':steamid' => $data["search"]));
            $data = $stmt->fetchAll();

            //And, if no data was returned (the first row doesn't exist), we bin
            // everything
            if (!isset($data[0])) {
                //But not without first setting this to false for some unknown reason.
                //Seriously... Why is this done? It's out of scope for that variable to
                // go back up the chain, so what is it doing?
                $data["steamid"] = false;
                return;
            } else {
                //Otherwise, set the data
                $data = $data[0];
                //Then perform recursion apparently...
                //Wat.
                $data["steamid"] = $data[0];
            }

            $data["raw"] = $data;
        }

        //And, without an else statement, simply assume that there's a [steamid] option in the array
        $this->steamid = $data["steamid"];

        //Along with these things
        if ($data['avatar'] === "") {
            //This is pointless actually, steam auto-gens a no-avatar picture (it's overridable w/
            // comparison checking though I guess)
            $this->avatar_medium = "/img/no-avatar.png";
            $this->avatar = "/img/no-avatar-small.png";
        } else {
            //Otherwise, the user knows what their avatar is, so let's fill that in
            $this->avatar = $data["avatar"];
            $this->avatar_medium = substr($data['avatar'], 0, -4) . "_medium.jpg";
        }

        //If they have no visible name, then they have no profile (not certain how just yet)
        // (Perhaps it's for when it comes out for PS4 and that? I dunno... we're looking at
        // the STEAM leaderboards, so it shouldn't matter)
        if ($data['name'] === "") {
            $data['name'] = "[no profile]";
        }

        //Plug all the data in to the fields
        $this->name = $data['name'];
        $this->suspected_hacker = $data["suspected_hacker"];

        //Why does this need to be suppressed?
        @$this->rank = $data["rank"]; // I don't know how this works, but it works.
        $this->admin = $data["admin"];
        $this->twitch = $data["twitch"];
        if (isset($data["raw"])) {
            //Raw is apparently optional (not too sure why)
            $this->raw = $data["raw"];
        }

    }

    /**
     * Get the alltime ranking of this player in comparison to everyone else running for
     * the Nuclear Throne
     *
     * @return int
     */
    public function get_rank() {
        $stmt = $this->db->prepare(
            "SELECT d.*, c.ranks FROM (
              SELECT score, @rank:=@rank+1 ranks FROM (
                SELECT DISTINCT score FROM throne_alltime a
                ORDER BY score DESC
              ) t, (SELECT @rank:= 0) r
            ) c INNER JOIN throne_alltime d ON c.score = d.score
            WHERE d.steamid = :steamid"
        );
        $stmt->execute(array(':steamid' => $this->steamid));
        if ($stmt->rowCount() != 1) {
            return -1;
        }
        $data = $stmt->fetchAll()[0];
        return $data["ranks"];
    }

    /**
     * Get the ranking of this player for today's daily run for the Nuclear Throne
     *
     * @return int
     */
    public function get_rank_today() {
        $userboard = new Leaderboard();
        $score = $userboard->create_player($this->steamid, "date", "DESC", 0, 1, 0)->to_array();
        if (isset($score[0]["rank"])) {
            return $score[0]["rank"];
        } else {
            return -1;
        }
    }

    /**
     * Allow someone to set their own Twitch profile to their Steam profile
     *
     * @param String $twitch A person's Twitch username
     * @return bool
     */
    public function set_twitch($twitch) {
        if ($this->twitch == $twitch) {
            return true;
        }
        $stmt = $this->db->prepare(
            "UPDATE throne_players SET twitch = :twitch WHERE steamid = :steamid"
        );
        $stmt->execute(array(":twitch" => $twitch, ":steamid" => $this->steamid));

        //Okay, we've done that, now let's see if it worked...

        if ($stmt->rowCount() != 1) {
            return false;
        } else {
            return true;
        }

    }

    /**
     * Convert the contents of this class into an array for general use elsewhere on
     * the site.
     *
     * @return array This is the array representation of a player
     */
    public function to_array() {
        return array(
            "steamid" => $this->steamid,
            "name" => $this->name,
            "avatar" => $this->avatar,
            "avatar_medium" => $this->avatar_medium,
            "suspected_hacker" => $this->suspected_hacker,
            "admin" => $this->admin,
            "rank" => $this->rank,
            "twitch" => $this->twitch,
            "raw" => $this->raw
        );
    }
}
