<?php

namespace apicache;

class Cache {

    /**
     * Path to where the cache files will be stored. If false, this will
     * default to the system tmp folder. 
     * 
     * @var String
     */
    public static $cache_path = false;

    /**
     * Time, in seconds, before a cache file is considered stale and
     * will be replaced
     * @var int
     */
    public static $cache_age = 3600;

    /**
     *
     * @var String
     */
    public static $cache_extension = '.api.cache';

    /**
     *
     * @var String
     */
    public static $cache_prefix = '';

    /**
     * If true, then HTTPS certificate issues will be ignored. Generally
     * this should stay false, but sometimes for testing you need to 
     * be able to ignore. 
     * @var boolean
     */
    public static $ignore_https = false;

    /**
     * Time, in seconds, for the HTTP timeout when connecting to the remote URL
     * @var int
     */
    public static $http_timeout = 5;

    /**
     * Main entry point to request a URL either from the cache or live if
     * needed.
     * 
     * @param String $url
     *  URL to request.
     * @param String $name
     *  Used to differentiate between multiple calls to the same URL if needed
     */
    public static function get_request( $url, $name = '' ) {
        $cache = self::get_cache( $url, $name );

        if ( $cache !== false ) {
            return $cache;
        }

        $data = self::remote_request( $url );

        $filename = self::get_cache_dir() . self::$cache_prefix . self::create_filename( $url . $name ) . self::$cache_extension;

        file_put_contents( $filename, $data );

        return $data;
    }

    /**
     * Tries to load the contents from the cache file. 
     * 
     * If the data is there and not stale it is returned. If it's either not
     * available or too old then this returns false. 
     * 
     * @param String $url
     * @param String $name
     * @return boolean|String
     *  String of the cache file data if it's still valid, false if not
     */
    private static function get_cache( $url, $name = '' ) {
        $filename = self::get_cache_dir() . self::$cache_prefix . self::create_filename( $url . $name ) . self::$cache_extension;
        
        if ( file_exists( $filename ) && (filemtime( $filename ) + self::$cache_age >= time()) ) {
            return file_get_contents( $filename );
        }

        return false;
    }

    /**
     * Returns the cache folder. The class variable is wrapped so that the
     * default value of false can stay, but we'll get this to return the TMP
     * folder in that case. 
     */
    private static function get_cache_dir() {
        if ( self::$cache_path === false ) {
            self::$cache_path = sys_get_temp_dir() . '/';
        }
        return self::$cache_path;
    }

    /**
     * Returns a filename that can be used to store the cached data
     * 
     * For now this is just creating an MD5 hash from the url request and
     * the name, but it's here to make it easier to change the process
     * if needed.
     * 
     * @param String $source
     * @return String
     */
    private static function create_filename( $source ) {
        return md5( $source );
    }

    private static function remote_request( $url ) {
        if ( function_exists( "curl_init" ) ) {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, self::$http_timeout );

            if ( self::$ignore_https ) {
                curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            }

            $vers = curl_version();
            curl_setopt( $ch, CURLOPT_USERAGENT, 'curl/' . $vers[ 'version' ] );

            $content = curl_exec( $ch );
            curl_close( $ch );
            return $content;
        }
        else {
            return file_get_contents( $url );
        }
    }

}
