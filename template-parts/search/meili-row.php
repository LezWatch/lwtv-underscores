<?php
/**
 * Search results page (Meilisearch): one result row.
 *
 * The modal's row, bigger: image, type icon, title link, the same detail
 * line (list, never pair), "Also known as" on shows, and a two-line
 * excerpt. Everything comes from WordPress; Meilisearch only chose the
 * order. Icons and alt names match searchwp-live-ajax-search/live-search-results.php.
 *
 * @package LWTV Underscores
 *
 * @var array $args row (post, type, document), first (bool), q (string).
 */

$lwtv_meili_post = $args['row']['post'];
$lwtv_meili_type = $args['row']['type'];
$lwtv_meili_doc  = $args['row']['document'];
$lwtv_meili_q    = (string) $args['q'];

if ( ! $lwtv_meili_post instanceof WP_Post ) {
	return;
}

$lwtv_meili_is_page = 'page' === $lwtv_meili_post->post_type;
$lwtv_meili_icons   = array(
	'shows'      => array( 'tv.svg', 'svg-tv', __( 'TV Show, mini-series, or movie', 'lwtv-underscores' ) ),
	'characters' => array( 'rubber-stamp.svg', 'svg-user', __( 'Character', 'lwtv-underscores' ) ),
	'actors'     => array( 'award-academy.svg', 'svg-user-tie', __( 'Actor', 'lwtv-underscores' ) ),
	'content'    => array( 'newspaper.svg', 'svg-newspaper', __( 'News Article', 'lwtv-underscores' ) ),
	'page'       => array( 'chalkboard.svg', 'svg-chalkboard', __( 'Page', 'lwtv-underscores' ) ),
);
$lwtv_meili_icon    = $lwtv_meili_icons[ $lwtv_meili_is_page ? 'page' : $lwtv_meili_type ] ?? $lwtv_meili_icons['content'];

// Portrait images for people, landscape for shows and posts. alt="" because
// the title beside it names the same thing; the site's auto_alt_fix (at 10)
// would otherwise fill it with that title, so screen readers hear it twice.
$lwtv_meili_portrait = in_array( $lwtv_meili_type, array( 'characters', 'actors' ), true );
$lwtv_meili_no_alt   = static function ( $attributes ) {
	$attributes['alt'] = '';
	return $attributes;
};
add_filter( 'wp_get_attachment_image_attributes', $lwtv_meili_no_alt, 20 );
$lwtv_meili_thumb = get_the_post_thumbnail(
	$lwtv_meili_post,
	$lwtv_meili_portrait ? 'character-img' : 'postloop-img',
	array(
		'class'   => 'lwtv-meili-page-thumb',
		'alt'     => '',
		'loading' => empty( $args['first'] ) ? 'lazy' : 'eager',
		'sizes'   => $lwtv_meili_portrait ? '(min-width: 576px) 96px, 72px' : '(min-width: 576px) 160px, 112px',
	)
);
remove_filter( 'wp_get_attachment_image_attributes', $lwtv_meili_no_alt, 20 );

// The detail line, per type, as in the modal.
$lwtv_meili_list   = static fn( $value ) => is_array( $value ) ? array_values( array_filter( array_map( 'strval', $value ) ) ) : array();
$lwtv_meili_detail = array();
switch ( $lwtv_meili_type ) {
	case 'shows':
		if ( ! empty( $lwtv_meili_doc['years'] ) ) {
			$lwtv_meili_detail[] = (string) $lwtv_meili_doc['years'];
		}
		$lwtv_meili_n = count( $lwtv_meili_list( $lwtv_meili_doc['characters'] ?? array() ) );
		/* translators: %s: number of characters. */
		$lwtv_meili_detail[] = sprintf( _n( '%s character', '%s characters', $lwtv_meili_n, 'lwtv-underscores' ), number_format_i18n( $lwtv_meili_n ) );
		break;
	case 'characters':
		$lwtv_meili_shows = array_slice( $lwtv_meili_list( $lwtv_meili_doc['shows'] ?? array() ), 0, 2 );
		if ( $lwtv_meili_shows ) {
			$lwtv_meili_detail[] = implode( ', ', $lwtv_meili_shows );
		}
		$lwtv_meili_actor = $lwtv_meili_list( $lwtv_meili_doc['actors'] ?? array() )[0] ?? '';
		if ( '' !== $lwtv_meili_actor ) {
			$lwtv_meili_detail[] = $lwtv_meili_actor;
		}
		break;
	case 'actors':
		$lwtv_meili_chars = array_slice( $lwtv_meili_list( $lwtv_meili_doc['characters'] ?? array() ), 0, 2 );
		if ( $lwtv_meili_chars ) {
			$lwtv_meili_detail[] = implode( ', ', $lwtv_meili_chars );
		}
		$lwtv_meili_n = max( 0, (int) ( $lwtv_meili_doc['character_count'] ?? 0 ) );
		/* translators: %s: number of characters. */
		$lwtv_meili_detail[] = sprintf( _n( '%s character', '%s characters', $lwtv_meili_n, 'lwtv-underscores' ), number_format_i18n( $lwtv_meili_n ) );
		break;
	default:
		$lwtv_meili_detail[] = $lwtv_meili_is_page ? __( 'Page', 'lwtv-underscores' ) : __( 'News', 'lwtv-underscores' );
		$lwtv_meili_detail[] = get_the_date( 'M Y', $lwtv_meili_post );
		break;
}

// "Also known as", exactly as live search does it.
$lwtv_meili_aka = array();
if ( 'shows' === $lwtv_meili_type && '' !== $lwtv_meili_q && function_exists( 'get_field' ) ) {
	$lwtv_meili_alt_rows = get_field( 'lezshows_show_names', $lwtv_meili_post->ID );
	if ( ! empty( $lwtv_meili_alt_rows ) ) {
		if ( empty( $GLOBALS['lwtv_meili_languages'] ) ) {
			$GLOBALS['lwtv_meili_languages'] = ( new \LWTV\Features\Languages() )->all_languages();
		}
		foreach ( \LWTV\Plugins\SearchWP\Build\Alt_Names::matching( $lwtv_meili_alt_rows, $lwtv_meili_q, $GLOBALS['lwtv_meili_languages'], 'remove_accents' ) as $lwtv_meili_item ) {
			$lwtv_meili_aka[] = ( '' === $lwtv_meili_item['language'] )
				? $lwtv_meili_item['name']
				/* translators: 1: alternate show name, 2: language, e.g. "Cable Girls (English)". */
				: sprintf( __( '%1$s (%2$s)', 'lwtv-underscores' ), $lwtv_meili_item['name'], $lwtv_meili_item['language'] );
		}
	}
}

$lwtv_meili_excerpt = trim( wp_strip_all_tags( get_the_excerpt( $lwtv_meili_post ) ) );
?>
<li class="lwtv-meili-page-row">
	<span class="lwtv-meili-page-media<?php echo $lwtv_meili_portrait ? ' lwtv-meili-page-media--portrait' : ''; ?>">
		<?php if ( $lwtv_meili_thumb ) : ?>
			<?php echo $lwtv_meili_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		<?php else : ?>
			<svg class="lwtv-meili-page-thumb" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<rect width="120" height="120" fill="#EFF1F3"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M33.2503 38.4816C33.2603 37.0472 34.4199 35.8864 35.8543 35.875H83.1463C84.5848 35.875 85.7503 37.0431 85.7503 38.4816V80.5184C85.7403 81.9528 84.5807 83.1136 83.1463 83.125H35.8543C34.4158 83.1236 33.2503 81.957 33.2503 80.5184V38.4816ZM80.5006 41.1251H38.5006V77.8751L62.8921 53.4783C63.9172 52.4536 65.5788 52.4536 66.6039 53.4783L80.5006 67.4013V41.1251ZM43.75 51.6249C43.75 54.5244 46.1005 56.8749 49 56.8749C51.8995 56.8749 54.25 54.5244 54.25 51.6249C54.25 48.7254 51.8995 46.3749 49 46.3749C46.1005 46.3749 43.75 48.7254 43.75 51.6249Z" fill="#687787"/>
			</svg>
		<?php endif; ?>
	</span>
	<div class="lwtv-meili-page-text">
		<h2 class="lwtv-meili-page-title">
			<span class="lwtv-meili-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_meili_icon[0], icon: $lwtv_meili_icon[1], max_size: '20px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="screen-reader-text"><?php echo esc_html( $lwtv_meili_icon[2] ); ?></span>
			<a href="<?php echo esc_url( get_permalink( $lwtv_meili_post ) ); ?>"><?php echo esc_html( get_the_title( $lwtv_meili_post ) ); ?></a>
		</h2>
		<?php if ( $lwtv_meili_detail ) : ?>
			<p class="lwtv-meili-detail"><?php echo esc_html( implode( ' · ', $lwtv_meili_detail ) ); ?></p>
		<?php endif; ?>
		<?php if ( $lwtv_meili_aka ) : ?>
			<p class="lwtv-meili-aka">
				<?php
				/* translators: %s: comma-separated list of alternate show names. */
				echo esc_html( sprintf( __( 'Also known as: %s', 'lwtv-underscores' ), implode( ', ', $lwtv_meili_aka ) ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $lwtv_meili_excerpt ) : ?>
			<p class="lwtv-meili-page-excerpt line-clamp-2"><?php echo esc_html( $lwtv_meili_excerpt ); ?></p>
		<?php endif; ?>
	</div>
</li>
