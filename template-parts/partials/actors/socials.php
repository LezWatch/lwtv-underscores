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
$social_urls   = array();
$external_urls = array();

// Social Media and other external links.
$maybe_external = array(
	'website'   => array(
		'meta'     => 'lezactors_homepage',
		'base'     => '',
		'post'     => '',
		'fa'       => 'fas fa-home',
		'hide'     => false,
		'use_meta' => true,
	),
	'imdb'      => array(
		'label'    => 'IMDb',
		'meta'     => 'lezactors_imdb',
		'base'     => 'https://imdb.com/name/',
		'post'     => '',
		'fa'       => 'fab fa-imdb',
		'hide'     => false,
		'use_meta' => true,
	),
	'wikipedia' => array(
		'meta'     => 'lezactors_wikipedia',
		'base'     => '',
		'post'     => '',
		'fa'       => 'fab fa-wikipedia-w',
		'hide'     => false,
		'use_meta' => true,
	),
);
// Social Media and other external links.
$maybe_social = array(
	'twitter'   => array(
		'label'    => 'X (Twitter)',
		'meta'     => 'lezactors_twitter',
		'base'     => 'https://twitter.com/',
		'post'     => '',
		'fa'       => 'fab fa-x-twitter',
		'hide'     => true,
		'use_meta' => true,
	),
	'bluesky'   => array(
		'meta'     => 'lezactors_bluesky',
		'base'     => '',
		'post'     => '',
		'fa'       => 'fab fa-bluesky',
		'hide'     => true,
		'use_meta' => true,
	),
	'instagram' => array(
		'meta'     => 'lezactors_instagram',
		'base'     => 'https://instagram.com/',
		'post'     => '',
		'fa'       => 'fab fa-instagram',
		'hide'     => true,
		'use_meta' => true,
	),
	'threads'   => array(
		'label'    => 'Threads',
		'meta'     => 'lezactors_has_threads',
		'base'     => 'https://threads.net/',
		'post'     => get_post_meta( $actor, 'lezactors_instagram', true ),
		'fa'       => 'fab fa-threads',
		'hide'     => true,
		'use_meta' => false,
	),
	'facebook'  => array(
		'meta'     => 'lezactors_facebook',
		'base'     => '',
		'post'     => '',
		'fa'       => 'fab fa-facebook',
		'hide'     => true,
		'use_meta' => true,
	),
	'tiktok'    => array(
		'label'    => 'TikTok',
		'meta'     => 'lezactors_tiktok',
		'base'     => 'https://tiktok.com/',
		'post'     => '',
		'fa'       => 'fab fa-tiktok',
		'hide'     => true,
		'use_meta' => true,
	),
	'twitch'    => array(
		'meta'     => 'lezactors_twitch',
		'base'     => '',
		'post'     => '',
		'fa'       => 'fab fa-twitch',
		'hide'     => true,
		'use_meta' => true,
	),
	'youtube'   => array(
		'meta'     => 'lezactors_youtube',
		'base'     => '',
		'post'     => '',
		'fa'       => 'fab fa-youtube',
		'hide'     => true,
		'use_meta' => true,
	),
	'tumblr'    => array(
		'meta'     => 'lezactors_tumblr',
		'base'     => 'https://',
		'post'     => '.tumblr.com/',
		'fa'       => 'fab fa-tumblr',
		'hide'     => true,
		'use_meta' => true,
	),
	'mastodon'  => array(
		'meta'     => 'lezactors_mastodon',
		'base'     => '',
		'post'     => '',
		'fa'       => 'fab fa-mastodon',
		'hide'     => true,
		'use_meta' => true,
	),
);

foreach ( $maybe_external as $site => $data ) {
	// If we're hiding social media content, and this has hide set to true, skip it.
	if ( lwtv_plugin()->hide_actor_data( $actor, 'socials' ) && $data['hide'] ) {
		continue;
	}

	if ( get_post_meta( $actor, $data['meta'], true ) ) {
		$name                   = ( isset( $data['label'] ) ) ? $data['label'] : ucwords( $site );
		$external_url           = ( $data['use_meta'] ) ? get_post_meta( $actor, $data['meta'], true ) : '';
		$external_urls[ $site ] = array(
			'name' => $name,
			'url'  => $data['base'] . $external_url . $data['post'],
			'fa'   => $data['fa'],
		);
	}
}

foreach ( $maybe_social as $social => $data ) {
	// If we're hiding social media content, and this has hide set to true, skip it.
	if ( lwtv_plugin()->hide_actor_data( $actor, 'socials' ) && $data['hide'] ) {
		continue;
	}

	if ( get_post_meta( $actor, $data['meta'], true ) ) {
		$name                   = ( isset( $data['label'] ) ) ? $data['label'] : ucwords( $social );
		$social_url             = ( $data['use_meta'] ) ? get_post_meta( $actor, $data['meta'], true ) : '';
		$social_urls[ $social ] = array(
			'name' => $name,
			'url'  => $data['base'] . $social_url . $data['post'],
			'fa'   => $data['fa'],
		);
	}
}

if ( count( $social_urls ) > 0 || count( $external_urls ) > 0 ) {
	?>
	<div class="card-body">
		<div class="card-meta">
			<div class="card-meta-item">
				<span ID="actor-links"><strong>Links: </strong></span>
					<?php
					// External Links.
					if ( count( $external_urls ) > 0 ) {
						?>
						<ul class="actor-meta-links" aria-labelledby="actor-links">
							<?php
							foreach ( $external_urls as $source ) {
								echo '<li><i class="' . esc_attr( strtolower( $source['fa'] ) ) . '" aria-hidden="true"></i> <a href="' . esc_url( $source['url'] ) . '" target="_blank">' . esc_html( $source['name'] ) . '</a><span class="screen-reader-text">, opens in new tab</span></li>';
							}
							?>
						</ul>
						<?php
					}

					// Social URLS
					if ( count( $social_urls ) > 0 ) {
						?>
						<ul class="actor-meta-links" aria-labelledby="actor-links">
							<?php
							foreach ( $social_urls as $source ) {
								echo '<li><i class="' . esc_attr( strtolower( $source['fa'] ) ) . '" aria-hidden="true"></i> <a href="' . esc_url( $source['url'] ) . '" target="_blank">' . esc_html( $source['name'] ) . '</a><span class="screen-reader-text">, opens in new tab</span></li>';
							}
							?>
						</ul>
						<?php
					}
					?>
				</ul>
			</div>
		</div>
	</div>
	<?php
}
