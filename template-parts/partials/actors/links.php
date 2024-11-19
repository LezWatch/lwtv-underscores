<?php
/**
 * Template part for displaying the actor's social media.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$actor     = $args['actor'] ?? null;
$link_type = $args['type'] ?? null;

$external_urls  = array();
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
	'tmdb'      => array(
		'label'    => 'TMDB',
		'meta'     => 'lezactors_tmdb_id',
		'base'     => 'https://themoviedb.org/person/',
		'post'     => '',
		'fa'       => 'fas fa-grip-lines',
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

if ( count( $external_urls ) > 0 ) {
	?>
	<div class="card-body">
		<div class="card-meta">
			<div class="card-meta-item">
				<span ID="actor-links"><strong>Links: </strong></span>
				<ul class="actor-meta-links" aria-labelledby="actor-links">
					<?php
					foreach ( $external_urls as $source ) {
						echo '<li><i class="' . esc_attr( strtolower( $source['fa'] ) ) . '" aria-hidden="true"></i> <a href="' . esc_url( $source['url'] ) . '" target="_blank">' . esc_html( $source['name'] ) . '</a><span class="screen-reader-text">, opens in new tab</span></li>';
					}
					?>
				</ul>
			</div>
		</div>
	</div>
	<?php
}
