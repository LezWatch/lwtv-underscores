<?php
/**
 * Template part for displaying the actor's social media.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$this_id = $args['to_show'] ?? null;

// Generate URLs.
// Usage: $actor_urls.
$actor_urls = array();
if ( get_post_meta( $this_id, 'lezactors_homepage', true ) ) {
	$actor_urls['home'] = array(
		'name' => 'Website',
		'url'  => esc_url( get_post_meta( $this_id, 'lezactors_homepage', true ) ),
		'fa'   => 'fas fa-home',
	);
}
if ( get_post_meta( $this_id, 'lezactors_imdb', true ) ) {
	$actor_urls['imdb'] = array(
		'name' => 'IMDb',
		'url'  => esc_url( 'https://www.imdb.com/name/' . get_post_meta( $this_id, 'lezactors_imdb', true ) ),
		'fa'   => 'fab fa-imdb',
	);
}
if ( get_post_meta( $this_id, 'lezactors_wikipedia', true ) ) {
	$actor_urls['wikipedia'] = array(
		'name' => 'WikiPedia',
		'url'  => esc_url( get_post_meta( $this_id, 'lezactors_wikipedia', true ) ),
		'fa'   => 'fab fa-wikipedia-w',
	);
}

if ( ! lwtv_plugin()->hide_actor_data( $this_id, 'socials' ) ) {
	if ( get_post_meta( $this_id, 'lezactors_twitter', true ) ) {
		$actor_urls['twitter'] = array(
			'name' => 'X (Twitter)',
			'url'  => esc_url( 'https://twitter.com/' . get_post_meta( $this_id, 'lezactors_twitter', true ) ),
			'fa'   => 'fab fa-x-twitter',
		);
	}

	if ( get_post_meta( $this_id, 'lezactors_instagram', true ) ) {
		$actor_urls['instagram'] = array(
			'name' => 'Instagram',
			'url'  => esc_url( 'https://www.instagram.com/' . get_post_meta( $this_id, 'lezactors_instagram', true ) ),
			'fa'   => 'fab fa-instagram',
		);
	}

	if ( get_post_meta( $this_id, 'lezactors_facebook', true ) ) {
		$actor_urls['facebook'] = array(
			'name' => 'Facebook',
			'url'  => esc_url( get_post_meta( $this_id, 'lezactors_facebook', true ) ),
			'fa'   => 'fab fa-facebook',
		);
	}

	if ( get_post_meta( $this_id, 'lezactors_tiktok', true ) ) {
		$actor_urls['tiktok'] = array(
			'name' => 'TikTok',
			'url'  => esc_url( 'https://tiktok.com/' . get_post_meta( $this_id, 'lezactors_tiktok', true ) ),
			'fa'   => 'fab fa-tiktok',
		);
	}

	if ( get_post_meta( $this_id, 'lezactors_bluesky', true ) ) {
		$actor_urls['bluesky'] = array(
			'name' => 'BlueSky',
			'url'  => esc_url( get_post_meta( $this_id, 'lezactors_bluesky', true ) ),
			'fa'   => 'fab fa-square',
		);
	}

	if ( get_post_meta( $this_id, 'lezactors_twitch', true ) ) {
		$actor_urls['twitch'] = array(
			'name' => 'Twitch',
			'url'  => esc_url( get_post_meta( $this_id, 'lezactors_twitch', true ) ),
			'fa'   => 'fab fa-twitch',
		);
	}

	if ( get_post_meta( $this_id, 'lezactors_tumblr', true ) ) {
		$actor_urls['tumblr'] = array(
			'name' => 'Tumblr',
			'url'  => esc_url( 'https://' . get_post_meta( $this_id, 'lezactors_tumblr', true ) . '.tumblr.com' ),
			'fa'   => 'fab fa-tumblr',
		);
	}

	if ( get_post_meta( $this_id, 'lezactors_mastodon', true ) ) {
		$actor_urls['mastodon'] = array(
			'name' => 'Mastodon',
			'url'  => esc_url( get_post_meta( $this_id, 'lezactors_mastodon', true ) ),
			'fa'   => 'fab fa-mastodon',
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
