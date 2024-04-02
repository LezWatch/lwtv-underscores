<?php
/**
 * Template part for displaying the actor's social media.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$actor = $args['actor'] ?? null;

// Generate URLs.
// Usage: $actor_urls.
$actor_urls = array();

// Social Media and other external links.
$maybe_social = array(
	'website'   => array(
		'meta' => 'lezactors_homepage',
		'fa'   => 'fas fa-home',
		'hide' => false,
	),
	'imdb'      => array(
		'label' => 'IMDb',
		'meta'  => 'lezactors_imdb',
		'fa'    => 'fab fa-imdb',
		'hide'  => false,
	),
	'wikipedia' => array(
		'meta' => 'lezactors_wikipedia',
		'fa'   => 'fab fa-wikipedia-w',
		'hide' => false,
	),
	'twitter'   => array(
		'meta' => 'lezactors_twitter',
		'fa'   => 'fab fa-x-twitter',
		'hide' => true,
	),
	'instagram' => array(
		'meta' => 'lezactors_instagram',
		'fa'   => 'fab fa-instagram',
		'hide' => true,
	),
	'facebook'  => array(
		'meta' => 'lezactors_facebook',
		'fa'   => 'fab fa-facebook',
		'hide' => true,
	),
	'tiktok'    => array(
		'meta' => 'lezactors_tiktok',
		'fa'   => 'fab fa-tiktok',
		'hide' => true,
	),
	'bluesky'   => array(
		'meta' => 'lezactors_bluesky',
		'fa'   => 'fab fa-bluesky',
		'hide' => true,
	),
	'twitch'    => array(
		'meta' => 'lezactors_twitch',
		'fa'   => 'fab fa-twitch',
		'hide' => true,
	),
	'tumblr'    => array(
		'meta' => 'lezactors_tumblr',
		'fa'   => 'fab fa-tumblr',
		'hide' => true,
	),
	'mastodon'  => array(
		'meta' => 'lezactors_mastodon',
		'fa'   => 'fab fa-mastodon',
		'hide' => true,
	),
);

foreach ( $maybe_social as $social => $data ) {
	// If we're hiding social media content, and this has hide set to true, skip it.
	if ( lwtv_plugin()->hide_actor_data( $actor, 'socials' ) && $data['hide'] ) {
		continue;
	}

	if ( get_post_meta( $actor, $data['meta'], true ) ) {
		$name                  = ( isset( $data['label'] ) ) ? $data['label'] : ucwords( $social );
		$actor_urls[ $social ] = array(
			'name' => $name,
			'url'  => esc_url( get_post_meta( $actor, $data['meta'], true ) ),
			'fa'   => $data['fa'],
		);
	}
}

if ( count( $actor_urls ) > 0 ) {
	echo '<span ID="actor-links"><strong>Links: </strong></span> ';
	echo '<ul class="actor-meta-links" aria-labelledby="actor-links">';
	foreach ( $actor_urls as $source ) {
		echo '<li><i class="' . esc_attr( strtolower( $source['fa'] ) ) . '" aria-hidden="true"></i> <a href="' . esc_url( $source['url'] ) . '" target="_blank">' . esc_html( $source['name'] ) . '</a><span class="screen-reader-text">, opens in new tab</span></li>';
	}
	echo '</ul>';
}
