<?php
/*
 * Exclusion Checks For LezWatch.TV - Display code.
 */

namespace LWTV\Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Build\Exclusion_Registry;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Shows as CPT_Shows;

class Exclusions {

	/*
	 * Construct
	 *
	 * Actions to happen immediately
	 */
	public function init() {
		add_submenu_page( 'lwtv', 'Exclusion Checker', 'Exclusion Checker', 'activate_plugins', 'lwtv_exclusion_check', array( $this, 'settings_page' ) );
	}

	/*
	 * Settings Page Content
	 */
	public static function settings_page() {
		// Get the active tab for later
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'intro'; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap">

			<h1>Exclusion Tools</h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=lwtv_exclusion_check" class="nav-tab <?php echo ( 'intro' === $active_tab ) ? 'nav-tab-active' : ''; ?>">Introduction</a>
				<?php
				foreach ( Exclusion_Registry::all() as $tab => $value ) {
					$active = ( $tab === $active_tab ) ? 'nav-tab-active' : '';
					echo '<a href="?page=lwtv_exclusion_check&tab=' . esc_attr( $tab ) . '" class="nav-tab ' . esc_attr( $active ) . '">' . esc_html( $value['name'] ) . '</a>';
				}
				?>
			</h2>

			<div id="dashboard" class="lwtvtab">
				<?php
				if ( Exclusion_Registry::exists( $active_tab ) ) {
					self::tab_check( $active_tab );
				} else {
					self::tab_introduction();
				}
				?>
			</div>

		</div>
		<?php
	}

	/**
	 * Static Introduction to what the hell is going on...
	 */
	public static function tab_introduction() {
		?>
		<div class="tab-block"><div class="lwtv-tools-container">
			<h3>LezWatch.TV Exclusion Checks</h3>
			<p>There are times when we override certain settings because automation only goes so far. However to keep ourselves honest, we have to track those things.</p>

			<hr>

			<ul>
				<?php
				foreach ( Exclusion_Registry::all() as $tab => $value ) {
					echo '<li>&bull; <a href="?page=lwtv_exclusion_check&tab=' . esc_attr( $tab ) . '">' . esc_html( $value['name'] ) . '</a> - ' . esc_html( $value['desc'] ) . '</li>';
				}
				?>
			</ul>

		</div></div>

		<?php
	}

	/**
	 * Output the results of one check.
	 *
	 * @param  string $key A registry tab slug.
	 * @return void
	 */
	public static function tab_check( string $key ) {
		$check = Exclusion_Registry::get( $key );

		if ( empty( $check ) ) {
			return;
		}

		$rows = self::rows_for( $key, $check );

		if ( empty( $rows ) ) {
			?>
			<div class="lwtv-tools-container lwtv-tools-container__alert">
				<h3><span class="dashicons dashicons-info"></span> None!</h3>
				<div id="lwtv-tools-alerts">
					<p><?php echo esc_html( $check['empty'] ); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		$count = count( $rows );
		$stale = count(
			array_filter(
				$rows,
				static function ( $row ) {
					return '' !== $row['stale'];
				}
			)
		);
		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<h3><span class="dashicons dashicons-flag"></span> Overridden (<?php echo (int) $count; ?>)</h3>
			<div id="lwtv-tools-alerts">
				<p><?php echo esc_html( self::summary_line( $key, $count ) ); ?></p>
				<?php if ( $stale > 0 ) : ?>
					<p><strong><?php echo esc_html( self::stale_line( $stale ) ); ?></strong></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="lwtv-tools-table">
			<table class="widefat fixed" cellspacing="0">
				<thead><tr>
					<th id="item" class="manage-column column-item" scope="col"><?php echo esc_html( $check['column'] ); ?></th>
					<th id="setting" class="manage-column column-setting" scope="col">Setting</th>
					<th id="problem" class="manage-column column-problem" scope="col">Still true?</th>
				</tr></thead>

				<tbody>
					<?php self::table_content( $rows ); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * The rows for one check: only posts that genuinely carry the override.
	 *
	 * All filtering happens here, once, so count() of the result always matches
	 * the number of table rows rendered from it.
	 *
	 * Queries directly rather than through Queeries\Post_Meta, which caches its
	 * result in a 30-minute transient.
	 *
	 * @param  string $key   Tab slug.
	 * @param  array  $check Its registry definition.
	 * @return array<int, array<string, mixed>>
	 */
	private static function rows_for( string $key, array $check ) {
		$post_type = ( Exclusion_Registry::CPT_ACTORS === $check['cpt'] )
			? CPT_Actors::SLUG
			: CPT_Shows::SLUG;

		// Always match on values, never EXISTS. ACF writes a row for every post
		// it has ever saved -- "0" for an unticked boolean, 'undefined' for an
		// unchosen select -- so EXISTS returns the entire catalogue and leaves
		// the filtering to PHP, which means hydrating thousands of WP_Post
		// objects and their meta to display a few hundred rows.
		$match = (string) $check['match'];

		$meta_query = ( Exclusion_Registry::MATCH_ANY !== $match )
			? array(
				'key'     => $check['meta'],
				'value'   => $match,
				'compare' => '=',
			)
			: array(
				'key'     => $check['meta'],
				'value'   => Exclusion_Registry::UNSET_VALUES,
				'compare' => 'NOT IN',
			);

		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'posts_per_page'         => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- An override is exceptional by definition, so these sets are small; a cap here would silently hide overrides, which is the one thing this page must not do.
				'suppress_filters'       => true,
				'update_post_term_cache' => false,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'meta_query'             => array( $meta_query ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only audit screen; the whole question it answers is "which posts carry this meta".
			)
		);

		if ( empty( $posts ) ) {
			return array();
		}

		$rows = array();

		// Iterate the returned posts rather than the_post(), which would set up
		// and then leave behind global post state on an admin screen.
		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;
			$value   = (string) get_post_meta( $post_id, $check['meta'], true );

			if ( ! Exclusion_Registry::qualifies( $value, $match ) ) {
				continue;
			}

			$context = array( 'value' => $value );

			foreach ( $check['context'] as $alias => $meta_key ) {
				$context[ $alias ] = (string) get_post_meta( $post_id, $meta_key, true );
			}

			$rows[] = array(
				'id'      => $post_id,
				'setting' => Exclusion_Registry::describe( $key, $context ),
				'stale'   => Exclusion_Registry::staleness( $key, $context ),
			);
		}

		return $rows;
	}

	/**
	 * Table content
	 *
	 * Renders every row it is given. Deciding whether a post belongs in the
	 * table is rows_for()'s job and only its job.
	 *
	 * @param  array $rows Rows from rows_for().
	 * @return void
	 */
	public static function table_content( $rows ) {
		$number = 1;

		foreach ( $rows as $row ) {
			$class = ( 0 === $number % 2 ) ? '' : 'alternate';
			$title = (string) get_the_title( $row['id'] );
			$edit  = (string) get_edit_post_link( $row['id'] );
			$view  = (string) get_permalink( $row['id'] );

			echo '
			<tr class="' . esc_attr( $class ) . '">
				<td><strong><a href="' . esc_url( $edit ) . '" target="_blank">' . wp_kses_post( $title ) . '</a></strong>

				<div class="row-actions"><span class="edit"><a href="' . esc_url( $edit ) . '" aria-label="Edit ' . esc_attr( $title ) . '" target="_blank">Edit</a>
				| </span><span class="view"><a href="' . esc_url( $view ) . '" rel="bookmark" aria-label="View ' . esc_attr( $title ) . '" target="_blank">View</a></span></div>
				</td>
				<td>' . esc_html( $row['setting'] ) . '</td>
				<td>' . ( ( '' === $row['stale'] ) ? '&mdash;' : '<span class="dashicons dashicons-warning"></span> ' . esc_html( $row['stale'] ) ) . '</td>
			</tr>
			';
			++$number;
		}
	}

	/**
	 * The sentence under the heading.
	 *
	 * @param  string $key   Tab slug.
	 * @param  int    $count How many rows.
	 * @return string
	 */
	private static function summary_line( string $key, int $count ) {
		switch ( $key ) {
			case 'queer_checker':
				/* translators: %d: number of actors. */
				return sprintf( _n( '%d actor has had their queerness overridden.', '%d actors have had their queerness overridden.', $count, 'lwtv' ), $count );
			case 'dead_checker':
				/* translators: %d: number of shows. */
				return sprintf( _n( '%d show has had death-score deductions overridden.', '%d shows have had death-score deductions overridden.', $count, 'lwtv' ), $count );
			case 'wikidata_ignore':
				/* translators: %d: number of actors. */
				return sprintf( _n( '%d actor has had their WikiData match overridden.', '%d actors have had their WikiData match overridden.', $count, 'lwtv' ), $count );
			case 'tvmaze_ignore':
				/* translators: %d: number of shows. */
				return sprintf( _n( '%d show has had its TVMaze match overridden.', '%d shows have had their TVMaze match overridden.', $count, 'lwtv' ), $count );
			case 'no_known_chars':
				/* translators: %d: number of shows. */
				return sprintf( _n( '%d show is flagged as having no known characters.', '%d shows are flagged as having no known characters.', $count, 'lwtv' ), $count );
		}

		/* translators: %d: number of overridden posts. */
		return sprintf( _n( '%d override is set.', '%d overrides are set.', $count, 'lwtv' ), $count );
	}

	/**
	 * The warning line for overrides that have stopped being true.
	 *
	 * @param  int $stale How many rows have a staleness note.
	 * @return string
	 */
	private static function stale_line( int $stale ) {
		// translators: %d is the number of items.
		return sprintf( __( '%d of these may no longer apply. See the last column.', 'lwtv' ), $stale );
	}
}
