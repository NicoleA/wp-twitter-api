<?php

/**
 * Helper functions
 *
 * @package Twitter API for WordPress
 */


/**
 * Helper function for returning the n latest items from a given user's timeline
 *
 * @param string $screen_name The Twitter handle
 * @param int $count Optional. The number of tweets to retrieve
 * @return array
 */
function tapi_get_user_timeline( $screen_name, $count = 20 ) {
	return WP_Twitter_API()->get_user_timeline( "screen_name={$screen_name}&count={$count}" );
}


/**
 * Return the latest $count tweets from any number of screen names, in reverse chronological order.
 *
 * @param array $screen_names An array of twitter handles.
 * @param int $count Optional. The number of tweets to retrieve.
 * @param int $cache_length Optional. Default is 10 minutes. Notes that this has no effect on WP_Twitter_API, which will cache each user's tweets individually.
 * @return array
 */
function tapi_get_merged_user_timelines( $screen_names = array(), $count = 20, $cache_length = -1 ) {
	if ( 0 > $cache_length )
		$cache_length = 10 * MINUTE_IN_SECONDS;
	$cache_key = 'tafwp_' . md5( $count . ',' . implode( ',', $screen_names ) );
	if ( false === ( $tweets = get_transient( $cache_key ) ) ) {
		$tweets = array();
		foreach ( $screen_names as $screen_name ) {
			$tweets = array_merge( $tweets, tapi_get_user_timeline( $screen_name, $count ) );
		}
		usort( $tweets, function( $a, $b ) {
			if ( $a->created_at == $b->created_at )
				return 0;
			return strtotime( $a->created_at ) < strtotime( $b->created_at ) ? 1 : -1;
		} );
		$tweets = array_slice( $tweets, 0, $count );
		return $tweets;
		set_transient( $cache_key, $tweets, $cache_length );
	}
	return $tweets;
}


/**
 * Helper function for returning the n latest items from a given user's timeline
 *
 * @param int|string $list Either the list id or the list slug
 * @param string $owner Optional. The Twitter handle of the list owner, rquired if $list is the slug
 * @param int $count Optional. The number of tweets to retrieve
 * @return array
 */
function tapi_get_list_timeline( $list, $owner = false, $count = 20 ) {
	if ( is_numeric( $list ) )
		$list = 'list_id=' . $list;
	else
		$list = "slug=$list&owner_screen_name=$owner";
	return WP_Twitter_API()->get_list_timeline( "count={$count}&{$list}" );
}