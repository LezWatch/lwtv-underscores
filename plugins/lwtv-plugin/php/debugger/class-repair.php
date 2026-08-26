<?php
/**
 * Repair one finding from wp-admin.
 *
 * The other half of `wp lwtv debug <check> --fix-it`: same registry, same repair
 * methods, one issue on one post at a time. Nothing about a repair is defined
 * here -- this is the request handler, the permission check, and the cache
 * bookkeeping around a call the registry already describes.
 *
 * Two deliberate choices:
 *
 * 1. It is a form POST, not a link. The plan called these "per-finding fix
 *    links", but a repair writes to the database, so it should not sit behind
 *    something a browser or crawler can prefetch. Watch_Providers already
 *    settled this pattern for the same reason.
 * 2. A successful repair *prunes* the cached findings rather than deleting the
 *    transient. Dropping it would send the next viewer of that tab into a full
 *    rescan of every show, character or actor -- thousands of posts -- to
 *    reflect one fixed field.
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Build\Issue_Registry;

class Repair {

	/**
	 * admin-post action.
	 */
	const ACTION = 'lwtv_debug_fix';

	/**
	 * Transient prefix for one-shot admin notices, per user.
	 */
	const NOTICE_PREFIX = 'lwtv_debug_fix_notice_';

	/**
	 * Where a repaired finding lives, per issue level.
	 *
	 * Ties an issue's level to the cache that has to be pruned, the status key
	 * whose count has to be re-recorded, and the tab to go back to.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const LEVELS = array(
		'show'      => array(
			'transient' => Shows::TRANSIENT_PROBLEMS,
			'status'    => 'show_problems',
			'name'      => 'Shows with Issues',
			'tab'       => 'tab_show_checker',
		),
		'character' => array(
			'transient' => Characters::TRANSIENT_PROBLEMS,
			'status'    => 'character_problems',
			'name'      => 'Characters with Issues',
			'tab'       => 'tab_character_checker',
		),
		'actor'     => array(
			'transient' => Actors::TRANSIENT_PROBLEMS,
			'status'    => 'actor_problems',
			'name'      => 'Actors with Issues',
			'tab'       => 'tab_actor_checker',
		),
	);

	/**
	 * Hook the handler.
	 *
	 * Must be called from somewhere that runs on every admin request --
	 * admin-post.php never fires `admin_menu`, so Validation::init() will not do.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Can this issue be repaired from the admin at all?
	 *
	 * Fixable in the registry is necessary but not sufficient: the level also
	 * has to be one whose findings cache we know how to prune.
	 *
	 * @param  string $issue_type Issue type key.
	 * @return bool
	 */
	public static function is_supported( string $issue_type ): bool {
		return Issue_Registry::is_fixable( $issue_type )
			&& isset( self::LEVELS[ Issue_Registry::level( $issue_type ) ] );
	}

	/**
	 * May the current user edit this post?
	 *
	 * Memoised for the request: a table of a hundred rows asks this once per
	 * issue per row, and map_meta_cap is not free.
	 *
	 * @param  int  $post_id Post ID.
	 * @return bool
	 */
	private static function can_edit( int $post_id ): bool {
		static $cache = array();

		if ( ! isset( $cache[ $post_id ] ) ) {
			$cache[ $post_id ] = current_user_can( 'edit_post', $post_id );
		}

		return $cache[ $post_id ];
	}

	/**
	 * Nonce action for one repair.
	 *
	 * Scoped to the post and the issue so a nonce for one row cannot be replayed
	 * against another.
	 *
	 * @param  int    $post_id    Post to repair.
	 * @param  string $issue_type Issue type key.
	 * @return string
	 */
	public static function nonce_action( int $post_id, string $issue_type ): string {
		return self::ACTION . '_' . $issue_type . '_' . $post_id;
	}

	/**
	 * The submit button for one issue on one row.
	 *
	 * Returns markup rather than printing so the caller controls placement; it
	 * is already escaped. Empty when the issue has no admin repair or the user
	 * may not edit that post, so the table simply shows nothing.
	 *
	 * @param  int    $post_id    Post to repair.
	 * @param  string $issue_type Issue type key.
	 * @return string
	 */
	public static function button( int $post_id, string $issue_type ): string {
		if ( ! self::is_supported( $issue_type ) || ! self::can_edit( $post_id ) ) {
			return '';
		}

		$label = sprintf(
			/* translators: %s: what the repair does, e.g. 'adds the "none" trope'. */
			__( 'Fix: %s', 'lwtv' ),
			Issue_Registry::fix_label( $issue_type )
		);

		// Inline so the button sits beside its message rather than below it; a
		// form is a block element and there is no admin stylesheet to hook.
		return '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" class="lwtv-debug-fix" style="display:inline;">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />'
			. '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '" />'
			. '<input type="hidden" name="issue_type" value="' . esc_attr( $issue_type ) . '" />'
			. wp_nonce_field( self::nonce_action( $post_id, $issue_type ), '_wpnonce', true, false )
			. '<button type="submit" class="button button-small">' . esc_html( $label ) . '</button>'
			. '</form>';
	}

	/**
	 * Run one repair.
	 *
	 * @return void
	 */
	public function handle(): void {
		$post_id    = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$issue_type = isset( $_POST['issue_type'] ) ? sanitize_key( wp_unslash( $_POST['issue_type'] ) ) : '';

		check_admin_referer( self::nonce_action( $post_id, $issue_type ) );

		if ( ! self::is_supported( $issue_type ) ) {
			wp_die( esc_html__( 'That is not a repairable issue.', 'lwtv' ), '', array( 'response' => 400 ) );
		}

		// The page cap (upload_files) gates *reading* the report. Changing a
		// post's data should need rights over that post.
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to repair this.', 'lwtv' ), '', array( 'response' => 403 ) );
		}

		$level = Issue_Registry::level( $issue_type );

		list( $class, $method ) = Issue_Registry::fix_callable( $issue_type );
		$class                  = ltrim( $class, '\\' );

		$repaired = (bool) ( new $class() )->$method( $post_id );

		if ( ! $repaired ) {
			/*
			 * The repair declined. Usually that means the problem is already
			 * gone -- someone edited the post, or the same fix ran on cron --
			 * so the finding is stale and pruning it is right either way.
			 */
			self::prune( $level, $post_id, $issue_type );
			self::set_notice(
				'info',
				sprintf(
					/* translators: %s: post title. */
					__( 'Nothing to repair on “%s” — it looks like this was already fixed. Removed it from the list.', 'lwtv' ),
					get_the_title( $post_id )
				)
			);
			self::redirect_back( $level );
		}

		self::prune( $level, $post_id, $issue_type );

		self::set_notice(
			'success',
			sprintf(
				/* translators: 1: what the repair did, 2: post title. */
				__( 'Done — %1$s on “%2$s”.', 'lwtv' ),
				Issue_Registry::fix_label( $issue_type ),
				get_the_title( $post_id )
			),
			(string) get_edit_post_link( $post_id, 'url' )
		);

		self::redirect_back( $level );
	}

	/**
	 * Drop one repaired issue from the cached findings.
	 *
	 * Rewrites the row without that issue, drops the row entirely when it was
	 * the only problem, and re-records the count so the tab badge agrees with
	 * the table. A cache that has since expired is not an error -- the next scan
	 * will be correct anyway.
	 *
	 * @param  string $level      Issue level.
	 * @param  int    $post_id    Repaired post.
	 * @param  string $issue_type Repaired issue type.
	 * @return void
	 */
	private static function prune( string $level, int $post_id, string $issue_type ): void {
		$config = self::LEVELS[ $level ] ?? array();

		if ( empty( $config ) ) {
			return;
		}

		$items = lwtv_plugin()->get_transient( $config['transient'] );

		if ( ! is_array( $items ) ) {
			return;
		}

		$kept = array();

		foreach ( $items as $item ) {
			if ( ! isset( $item['id'] ) || (int) $item['id'] !== $post_id ) {
				$kept[] = $item;
				continue;
			}

			$updated = Findings::without_issue( $item, $issue_type );

			if ( ! empty( $updated ) ) {
				$kept[] = $updated;
			}
		}

		lwtv_plugin()->set_transient( $config['transient'], $kept, WEEK_IN_SECONDS );

		/*
		 * Three args on purpose, which clears the stored new/open breakdown: the
		 * count just changed without a scan, so any surviving "4 new" would be
		 * arithmetic nobody did. The tab falls back to a plain count until the
		 * next run.
		 *
		 * The baseline itself is deliberately left alone. It records what the
		 * last *scan* found, so the next scan legitimately reports this finding
		 * as resolved -- which is exactly what happened.
		 */
		Status::record( $config['status'], $config['name'], count( $kept ) );
	}

	/**
	 * Stash a one-shot notice for the current user.
	 *
	 * @param  string $type    'success', 'error' or 'info'.
	 * @param  string $message Text.
	 * @param  string $link    Optional URL to offer afterwards.
	 * @return void
	 */
	private static function set_notice( string $type, string $message, string $link = '' ): void {
		set_transient(
			self::NOTICE_PREFIX . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
				'link'    => $link,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Print and clear any pending notice.
	 *
	 * @return void
	 */
	public static function show_notice(): void {
		$key    = self::NOTICE_PREFIX . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		$class = 'notice-success';
		if ( 'error' === $notice['type'] ) {
			$class = 'notice-error';
		} elseif ( 'info' === $notice['type'] ) {
			$class = 'notice-info';
		}

		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
			<p>
				<?php echo esc_html( $notice['message'] ); ?>
				<?php if ( ! empty( $notice['link'] ) ) : ?>
					<a href="<?php echo esc_url( $notice['link'] ); ?>"><?php esc_html_e( 'Edit the post', 'lwtv' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Back to the tab the repair was requested from.
	 *
	 * @param  string $level Issue level.
	 * @return void
	 */
	private static function redirect_back( string $level ): void {
		$tab = self::LEVELS[ $level ]['tab'] ?? 'intro';

		wp_safe_redirect( admin_url( 'admin.php?page=lwtv_data_check&tab=' . $tab ) );
		exit;
	}
}
