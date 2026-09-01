<?php
/*
 * Validation: the shows behind a watch-URL finding.
 *
 * Both watch tabs used to print a bare "N shows" next to a broken provider URL,
 * which told an editor how much damage there was but not where. Finding out meant
 * querying wp_postmeta for the URL by hand. The scans already know the post IDs
 * -- Watch_Hosts::in_use() builds them to count distinct shows -- so this turns
 * that count into the list.
 *
 * The list is as of the last scan. A show whose watch URL changed since then is
 * still in it, the same staleness the count has always had; deleted and
 * unpublished posts are dropped, because those we can see.
 *
 * No JavaScript. <details> is native and degrades to an open list, which matches
 * the no-JS stance the Watch Providers tab already takes.
 */

namespace LWTV\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Affected_Shows {

	/**
	 * Warm the post cache for every row on the page at once.
	 *
	 * Titles and edit links are resolved per ID at render, so without this a
	 * hundred-odd rows would each be its own query.
	 *
	 * @param array  $items Rows, each optionally carrying a list of post IDs.
	 * @param string $key   Row key holding the IDs.
	 * @return void
	 */
	public static function prime( array $items, string $key = 'show_ids' ): void {
		$ids = array();

		foreach ( $items as $item ) {
			foreach ( (array) ( $item[ $key ] ?? array() ) as $id ) {
				$ids[ (int) $id ] = true;
			}
		}

		unset( $ids[0] );

		if ( empty( $ids ) ) {
			return;
		}

		_prime_post_caches( array_keys( $ids ), false, false );
	}

	/**
	 * The Shows cell: a count, expandable into the shows themselves.
	 *
	 * Falls back to the bare count when there is nothing to expand -- an empty
	 * list, a finding cached before IDs were stored, or every stored ID gone
	 * since. An empty <details> would look like a bug.
	 *
	 * @param array $show_ids Post IDs from the scan.
	 * @param int   $count    Count the scan recorded.
	 * @return void
	 */
	public static function cell( array $show_ids, int $count ): void {
		$links = self::links( $show_ids );

		if ( empty( $links ) ) {
			echo esc_html( (string) $count );
			return;
		}
		?>
		<details class="lwtv-affected-shows">
			<summary>
				<?php
				printf(
					/* translators: %d: number of shows. */
					esc_html( _n( '%d show', '%d shows', $count, 'lwtv' ) ),
					absint( $count )
				);
				?>
			</summary>
			<ul>
				<?php foreach ( $links as $link ) : ?>
					<li><?php echo wp_kses_post( $link ); ?></li>
				<?php endforeach; ?>
			</ul>
		</details>
		<?php
	}

	/**
	 * One linked title per show still worth linking to, by title.
	 *
	 * A post that no longer exists or is no longer published is dropped rather
	 * than rendered as a dead link: it cannot be the show the reader is looking
	 * at any more.
	 *
	 * @param array $show_ids Post IDs.
	 * @return array<int, string> Escaped markup.
	 */
	private static function links( array $show_ids ): array {
		$links = array();

		foreach ( $show_ids as $id ) {
			$id   = (int) $id;
			$post = $id ? get_post( $id ) : null;

			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}

			$title = get_the_title( $id );
			$title = ( '' !== trim( (string) $title ) ) ? (string) $title : sprintf(
				/* translators: %d: post ID. */
				__( 'Show #%d', 'lwtv' ),
				$id
			);
			$edit = get_edit_post_link( $id );

			$links[ $title . ' ' . $id ] = $edit
				? '<a href="' . esc_url( $edit ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a>'
				: esc_html( $title );
		}

		// By title: a Netflix-sized list in post-ID order is a wall of numbers.
		ksort( $links, SORT_NATURAL | SORT_FLAG_CASE );

		return array_values( $links );
	}
}
