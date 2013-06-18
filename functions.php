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

/**
 * Parse the tweet hashtags
 *
 * @param array $hashtags Required. Array of hashtag entity objects in a tweet
 * @return array $parsed_entities. Array of parsed hashtag entity objects in tweet
 */
function tapi_parse_hashtag( $hashtags ) {
	$parsed_entities = array();
	$hashtag_link_pattern = '<a href="http://twitter.com/search?q=%%23%s&src=hash" rel="nofollow" target="_blank">#%s</a>';
	foreach( $hashtags as $hashtag ) {
		$entity = new stdclass();
		$entity->start = $hashtag->indices[0];
		$entity->length = $hashtag->indices[1] - $hashtag->indices[0];
		$entity->replace = sprintf( $hashtag_link_pattern, strtolower( $hashtag->text ), $hashtag->text );
		// use the start index as the array key for sorting purposes
		$parsed_entities[$entity->start] = $entity;
	}
	return $parsed_entities;
}

/**
 * Parse the tweet url links
 *
 * @param array $urls Required. Array of url entity objects in a tweet
 * @return array $parsed_entities. Array of parsed url entity objects in tweet
 */
function tapi_parse_url_link( $urls ) {
	$parsed_entities = array();
	$url_link_pattern = '<a href="%s" rel="nofollow" target="_blank" title="%s">%s</a>';
	foreach( $urls as $url ) {
		$entity = new stdclass();
		$entity->start = $url->indices[0];
		$entity->length = $url->indices[1] - $url->indices[0];
		$entity->replace = sprintf( $url_link_pattern, $url->url, $url->expanded_url, $url->display_url );
		// use the start index as the array key for sorting purposes
		$parsed_entities[$entity->start] = $entity;
	}
	return $parsed_entities;
}

/**
 * Parse the tweet user mentions
 *
 * @param array $user_mentions Required. Array of user mention entity objects in a tweet
 * @return array $parsed_entities. Array of parsed user mention entity objects in tweet
 */
function tapi_parse_user_mention( $user_mentions ) {
	$parsed_entities = array();
	$user_mention_link_pattern = '<a href="http://twitter.com/%s" rel="nofollow" target="_blank" title="%s">@%s</a>';
	foreach( $user_mentions as $user_mention ) {
		$entity = new stdclass();
		$entity->start = $user_mention->indices[0];
		$entity->length = $user_mention->indices[1] - $user_mention->indices[0];
		$entity->replace = sprintf($user_mention_link_pattern, strtolower($user_mention->screen_name), $user_mention->name, $user_mention->screen_name);
		// use the start index as the array key for sorting purposes
		$parsed_entities[$entity->start] = $entity;
	}
	return $parsed_entities;
}

/**
 * Parse the tweet media links
 *
 * @param array $media Required. Array of media entity objects in a tweet
 * @return array $parsed_entities. Array of parsed media entity objects in tweet
 */
function tapi_parse_media_link( $media ) {
	$parsed_entities = array();
	$media_link_pattern = '<a href="%s" rel="nofollow" target="_blank" title="%s">%s</a>';
	foreach( $media as $mediaitem ) {
		$entity = new stdclass();
		$entity->start = $mediaitem->indices[0];
		$entity->length = $mediaitem->indices[1] - $mediaitem->indices[0];
		$entity->replace = sprintf( $media_link_pattern, $mediaitem->url, $mediaitem->expanded_url, $mediaitem->display_url );
		// use the start index as the array key for sorting purposes
		$parsed_entities[$entity->start] = $entity;
	}
	return $parsed_entities;
}

/**
 * Filter the Twitter api response to replace hashtags, urls, user mentions, and media links with
 *
 * @param array $media Required. Array of media entity objects in a tweet
 * @return array $parsed_entities. Array of parsed media entity objects in tweet
 */
function tapi_filter_tweet_text( $response ) {

	// process each tweet in the response
	foreach( $response as $k => $tweet ) {
		// initialize an empty array to hold the parsed entities
		$entities = array();

		if ( !empty( $tweet->entities->hashtags ) ) // parse the hashtags
			$entities = $entities + tapi_parse_hashtag( $tweet->entities->hashtags );

		if ( !empty( $tweet->entities->urls ) ) // parse the urls
			$entities = $entities + tapi_parse_url_link( $tweet->entities->urls );

		if ( !empty( $tweet->entities->user_mentions ) ) // parse the user mentions
			$entities = $entities + tapi_parse_user_mention( $tweet->entities->user_mentions );

		if ( !empty( $tweet->entities->media ) ) // parse the media links
			$entities = $entities + tapi_parse_media_link( $tweet->entities->media );

		// because we're using the location index of the substring to begin the replacement, we must reverse the order and work backwards.
		krsort( $entities );

		// replace the entities in the tweet text with the parsed versions
		foreach ( $entities as $entity ) {
			$tweet->text = substr_replace( $tweet->text, $entity->replace, $entity->start, $entity->length );
		}

		// put the tweet back in the response array
		$response[ $k ] = $tweet;
	}
	// send the processed response back
	return $response;
}
add_filter( 'twitter_get_callback', 'tapi_filter_tweet_text' );