<?php

/**
 * Class Application This class represents the whole application in regards to
 * what users are going to be doing a lot of. In this case, they are going to
 * be logging in, generating tokens, and having them removed quite actively.
 *
 * In addition, this class also acts as a host for the $db variable which can
 * be used as a central access point to the database
 */
class Application {

    /**
     * Let's define our private variables that we don't want anyone else touching
     *
     * @var String $database_host
     * @var String $database_name
     * @var String $database_username
     * @var String $database_password
     */
    private $database_password, $database_host, $database_username, $database_name;

    /**
     * And give everyone else something to play with if they wish to do so
     *
     * @staticvar PDO $db
     * @staticvar Integer $connection_count
     */
    public static $db, $connection_count;

    /**
     * This is the first thing that we need to do with the database; connect to it
     * (and in this case, return the instance)
     *
     * @return PDO
     */
    public static function connect() {

        //Get the config file and decode its contents
        $config_file = file_get_contents("config/config.json");
        $config = json_decode($config_file, true);

        //Now connect to the database///
        self::$db = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8',
                $config['Database']['host'], $config['Database']['name']
            ), $config["Database"]["username"], $config["Database"]["password"],
            array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );

        //And increment the connection counter
        self::$connection_count += 1;

        //Note: We haven't checked whether we've actually connected to the database
        // or not yet
        return self::$db;

    }

    /**
     * This method exists so that we can generate a local token to track the login
     * of a user to the site, thereby enabling us to have a logout timer where we
     * can expire cookies on our end too.
     *
     * This is specifically for people who have asked us to remember them, meaning
     * that they have a v. long login expiry of 14 days
     *
     * @param String $steamid The id of the person we're generating a 'remember me'
     * cookie for
     */
    public static function generate_token($steamid) {

        //Make ourselves a random token
        $token = base64_encode(openssl_random_pseudo_bytes(16));

        /**
         * And add it to the table
         * @var PDOStatement $stmt
         */
        $stmt = self::$db->prepare(
            "INSERT INTO `throne_tokens` (`token`, `user_id`, `last_accessed`)
             VALUES(:token, :steamid, CURRENT_TIMESTAMP())"
        );
        $stmt->execute(array(":token" => $token, "steamid" => $steamid));

        //Now, let's also set a cookie on their end to expire in 14 days
        setcookie("authtoken", $token, time() + 60 * 60 * 24 * 14);

    }

    /**
     * This method, when presented with a token, returns the user that is currently
     * identified by that token in the database. In other words, it's the person that
     * generated that token.
     *
     * This is generally used for asking whether a user has asked us to remember him
     * or not
     *
     * @param String $token
     * @return String
     */
    public static function check_login($token) {

        //Generate ourselves a statement to find this token
        $stmt = self::$db->prepare(
            "SELECT * FROM `throne_tokens` WHERE `token` = :token"
        );
        $stmt->execute(array(":token" => $token));

        //And fetch all of the data that we've gathered for this token
        $data = $stmt->fetchAll();

        if (count($data) > 0) {

            //Now, since we have confirmation that this person has attempted
            // to access their token, we have to update the l_accessed date so
            // that we can update their expiry accordingly.
            $stmt = self::$db->prepare("UPDATE `throne_tokens` SET `last_accessed` = CURRENT_TIMESTAMP() WHERE `token` = :token");
            $stmt->execute(array(":token" => $token));

            //Oh, and return the steam id of the person we found
            return $data[0]["user_id"];

        } else {
            //Otherwise, return nothing
            return "";
        }

    }

    /**
     * When a user's token eventually decays (or if they simply choose to log out)
     * we have to get rid of their token ourselves. Which is what this method does
     *
     * @param String $token
     */
    public static function remove_token($token) {
        $stmt = self::$db->prepare(
            "DELETE FROM `throne_tokens` WHERE `token` = :token"
        );
        $stmt->execute(array(":token" => $token));
    }

    /**
     * A currently unused method that allows the administrators to completely
     * wipe all tokens currently affiliated with a user (effectively forcing
     * them to log out)
     *
     * @param String $userid
     */
    public static function remove_all_tokens($userid) {
        $stmt = self::$db->prepare(
            "DELETE FROM `throne_tokens` WHERE `user_id` = :userid"
        );
        $stmt->execute(array(":userid" => $userid));
    }

    /**
     * Users may often choose to search for themselves or for people they are
     * interested in on the site. Therefore, it is very useful for us to have
     * a method which deals with such things, searching through the database
     * for matching terms and returning all users associated with the term
     *
     * @param String $q
     * @return mixed
     */
    public static function search_user($q) {
        $stmt = self::$db->prepare(
            "SELECT * FROM `throne_players` WHERE MATCH (name) AGAINST (:q)"
        );
        $stmt->execute(array(":q" => $q));
        return $stmt->fetchAll();
    }

}
