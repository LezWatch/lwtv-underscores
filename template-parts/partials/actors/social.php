<?php
/**
 * Template part for displaying the actor's social media.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$actor = $args['actor'] ?? null;

$social_urls   = array();
$external_urls = array();

$maybe_social = array(
	'twitter'   => array(
		'label'    => 'X (Twitter)',
		'meta'     => 'lezactors_twitter',
		'base'     => 'https://twitter.com/',
		'post'     => '',
		'icon'     => 'x-twitter.svg',
		'hide'     => true,
		'use_meta' => true,
	),
	'bluesky'   => array(
		'meta'     => 'lezactors_bluesky',
		'base'     => '',
		'post'     => '',
		'icon'     => 'bluesky.svg',
		'hide'     => true,
		'use_meta' => true,
	),
	'instagram' => array(
		'meta'     => 'lezactors_instagram',
		'base'     => 'https://instagram.com/',
		'post'     => '',
		'icon'     => 'instagram.svg',
		'hide'     => true,
		'use_meta' => true,
	),
	'threads'   => array(
		'label'    => 'Threads',
		'meta'     => 'lezactors_has_threads',
		'base'     => 'https://threads.net/',
		'post'     => get_post_meta( $actor, 'lezactors_instagram', true ),
		'icon'     => 'threads.svg',
		'hide'     => true,
		'use_meta' => false,
	),
	'facebook'  => array(
		'meta'     => 'lezactors_facebook',
		'base'     => '',
		'post'     => '',
		'icon'     => 'facebook.svg',
		'hide'     => true,
		'use_meta' => true,
	),
	'tiktok'    => array(
		'label'    => 'TikTok',
		'meta'     => 'lezactors_tiktok',
		'base'     => 'https://tiktok.com/',
		'post'     => '',
		'icon'     => 'tiktok.svg',
		'hide'     => true,
		'use_meta' => true,
	),
	'twitch'    => array(
		'meta'     => 'lezactors_twitch',
		'base'     => '',
		'post'     => '',
		'icon'     => 'twitch.svg',
		'hide'     => true,
		'use_meta' => true,
	),
	'youtube'   => array(
		'meta'     => 'lezactors_youtube',
		'base'     => '',
		'post'     => '',
		'icon'     => 'youtube.svg',
		'hide'     => true,
		'use_meta' => true,
	),
	'tumblr'    => array(
		'meta'     => 'lezactors_tumblr',
		'base'     => 'https://',
		'post'     => '.tumblr.com/',
		'icon'     => 'tumblr.svg',
		'hide'     => true,
		'use_meta' => true,
	),
	'mastodon'  => array(
		'meta'     => 'lezactors_mastodon',
		'base'     => '',
		'post'     => '',
		'icon'     => 'mastodon.svg',
		'hide'     => true,
		'use_meta' => true,
	),
);

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
			'icon' => $data['icon'],
		);
	}
}

if ( count( $social_urls ) > 0 ) {
	?>
	<div class="card-body">
		<div class="card-meta">
			<div class="card-meta-item">
					<span ID="actor-links"><strong>Social Media: </strong></span>
					<ul class="actor-meta-links" aria-labelledby="actor-links">
						<?php
						foreach ( $social_urls as $source ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo '<li><a href="' . esc_url( $source['url'] ) . '" target="_blank">' . lwtv_plugin()->get_symbolicon( svg: $source['icon'], icon: str_replace( '.svg', '', 'svg-' . $source['icon'] ), max_size: '20' ) . '&nbsp;' . esc_html( $source['name'] ) . '<span class="screen-reader-text">, opens in new tab</span></a></li>';
						}
						?>
					</ul>
			</div>
		</div>
	</div>
	<?php
}
