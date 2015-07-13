<?php

/**
 * Class Application This class represents the whole application in regards to
 * what users are going to be doing a lot of. In this case, they are going to
 * be logging in, generating tokens, and having them removed quite actively.
 *
 * In addition, this class also acts as a host for the $db variable which can
 * be used as a central access point to the database
 */
class Application extends CentralDatabase {

    /**
     * @var ThroneBase $db
     */
    private static $db;

    /**
     * And give everyone else something to play with if they wish to do so
     *
     * @staticvar Integer $connection_count
     */
    public static $connection_count;

    public static function getDatabase() {
        if (self::$db == null) {
            self::connect();
        }

        return self::$db;
    }

    /**
     * This is the first thing that we need to do with the database; connect to it
     * (and in this case, return the instance)
     *
     * @return ThroneBase
     */
    public static function connect() {

        //Call a new database up from the ashes of Valkana
        self::$db = new ThroneBase();

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
        self::$db->insert_token($steamid, $token);

        //Now, let's also set a cookie on their end to expire in 14 days
        setcookie("authtoken", $token, time() + 60 * 60 * 24 * 14);

    }

}
