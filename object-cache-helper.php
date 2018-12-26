<?php
/**
 * Plugin Name:       WP Object Cache Helper
 * Plugin URI:        https://github.com/dmhendricks/object-cache-helper
 * Description:       A wrapper class for WP Object Cache
 * Version:           1.0.0
 * Author:            Daniel M. Hendricks
 * Author URI:        https://www.danhendricks.com
 * License:           GPL-2.0
 * License URI:       https://opensource.org/licenses/GPL-2.0
 */
namespace MU_Plugins;

class WP_Cache_Object {

    protected $config;

    public function __construct( $args = [] ) {

        // Set configuration defaults
        $this->config = self::set_default_atts( [
            'expire' => HOUR_IN_SECONDS,
            'group' => get_option('stylesheet') . '_cache_group',
            'single' => false, // Store as single key rather than group array
            'network_global' => false, // Set to true to store cache value for entire network, rather than per sub-site
            'force' => false
        ], $args );

        // Validate arguments
        $this->config['expire'] = intval( $this->config['expire'] );
        $this->config['single'] = filter_var( $this->config['single'], FILTER_VALIDATE_BOOLEAN );
        $this->config['network_global'] = filter_var( $this->config['network_global'], FILTER_VALIDATE_BOOLEAN );
        $this->config['force'] = filter_var( $this->config['force'], FILTER_VALIDATE_BOOLEAN );

    }

    /*
     * Retrieves value from cache, if enabled/present, else returns value
     *    generated by callback().
     *
     * @param   string      $key        Key value of cache to retrieve
     * @param   function    $callback   Result to return/set if does not exist in cache
     * @param   array       $args       An array of arguments
     * @return  string      Cached value of key
     * @since 1.0.0
     */
    public function get_object( $key, $callback, $args = [] ) {

        // Set configuration defaults
        $args = self::set_default_atts( $this->config, $args );

        $result = null;
        $result_group = null;

        // Add site ID suffic to cache group if multisite
        if( is_multisite() ) $args['group'] .= '_' . get_current_site()->id;

        // Set key variable, appending blog ID if network_global is false
        $object_cache_key = $key . ( is_multisite() && !$args['network_global'] && get_current_blog_id() ? '_' . get_current_blog_id() : '' );

        // Try to get key value from cache
        if( $args['single'] ) {

            // Store value in individual key
            $result = unserialize( wp_cache_get( $object_cache_key, $args['group'], false, $cache_hit ) );

        } else {

            // Store value in array of values with group as key
            $result_group = wp_cache_get( $args['group'], $args['group'], false, $cache_hit );
            $result_group = $cache_hit ? (array) unserialize( $result_group ) : [];

            if( $cache_hit && isset( $result_group[$object_cache_key] ) ) {
                $result = $result_group[$object_cache_key];
            } else {
                $cache_hit = false;
            }

        }

        // If cache miss, set & return the value from $callback()
        if( !$cache_hit ) {

            $result = $callback();

            // Store cache key value pair
            if( $args['single'] ) {

                // If single, store cache value.
                wp_cache_set( $object_cache_key, serialize( $result ), $args['group'], $args['expire'] );

            } else {

                // Store cache value in group array to allow "flushing" of individual group
                $result_group[$object_cache_key] = $result;
                wp_cache_set( $args['group'], serialize( $result_group ), $args['group'], $args['expire'] );

            }

        }

        if( is_numeric( $result ) ) $result = intval( $result ) ? (int) $result : (float) $result;

        return $result;

    }

    /**
     * Flushes the key group from cache. This is an alternative method of flushing
     * a single group rather than the entire object cache via wp_cache_flush().
     * This function only works if values were stored via get_cache_object().
     *
     * @param   string  $group  The name of the key group to flush (delete)
     * @return  bool    True on success, false on error
     * @since 1.0.0
     */
    public function flush_group( $cache_group = null ) {

        $cache_group = $cache_group ?: $this->config['group'];

        try {
          wp_cache_delete( $cache_group, $cache_group );
        } catch ( Exception $e ) {
          return false;
        }

        return true;

    }

    /**
     * Combines arrays and fill in defaults as needed. 
     * Same usage as shortcode_atts() except for non-shortcodes. Example usage:
     * 
     *    $person = [ 'name' => 'John', 'age' => 29 ];
     *    $human = set_default_atts( [
     *       'name' => 'World',
     *       'human' => true,
     *       'location' => 'USA',
     *       'age' => null
     *    ], $daniel );
     *    print_r( $human ); // Result: [ 'name' => 'John', 'human' => true, 'location' => 'USA', 'age' => 29 ];
     *
     * @param array  $pairs     Entire list of supported attributes and their defaults.
     * @param array  $atts      User defined attributes in shortcode tag.
     * @return array Combined and filtered attribute list.
     * @since 1.0.0
     */
    public static function set_default_atts( $pairs, $atts ) {

        $atts = (array) $atts;
        $result = array();

        foreach ($pairs as $name => $default) {
            if ( array_key_exists($name, $atts) ) {
                $result[$name] = $atts[$name];
            } else {
                $result[$name] = $default;
            }
        }

        return $result;

    }

}