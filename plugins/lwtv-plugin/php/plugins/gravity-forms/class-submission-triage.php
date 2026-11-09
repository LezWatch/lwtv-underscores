<?php
/**
 * Name: Submission Triage
 * Description: Queues "new show/actor/character" contact-form entries for the Content to Add sheet.
 *
 * Holds spam and moderation-flagged entries until an admin un-spams or
 * releases them, then queues the entry ID for Sheet_Triage_Task. Nothing slow
 * happens during the form request itself.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Features\Spammers;
use LWTV\Schedulers\Sheet_Triage_Task;

class Submission_Triage {

	/**
	 * Contact Us form and its fields.
	 */
	const FORM_ID     = 2;
	const FIELD_TOPIC = 11;
	const TOPIC_VALUE = 'A new show/actor/character';
	const FIELD_EMAIL = 4;
	const FIELD_SHOW  = 12;
	const FIELD_INFO  = 14;

	/**
	 * Default spreadsheet ID ("LWTV: Content to Add"); LWTV_CONTENT_SHEET_ID overrides it.
	 */
	const DEFAULT_SHEET_ID = '1W596yJz11ALCjfq2HMkmZO-PN765Q2rlFxfTSqu2G28';

	/**
	 * Entry meta keys.
	 */
	const META_DONE = 'lwtv_sheet_triage_done';
	const META_HELD = 'lwtv_sheet_triage_held';

	/**
	 * Note sub-type for everything this feature writes.
	 */
	const NOTE_SUB_TYPE = 'sheet-triage';

	/**
	 * POST field and nonce field for the release button.
	 */
	const RELEASE_FIELD = 'lwtv_triage_release';
	const RELEASE_NONCE = 'lwtv_triage_release_nonce';

	/**
	 * Filtered config, built once per request.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $config = null;

	/**
	 * Whether the "not configured" line was logged this request.
	 *
	 * @var bool
	 */
	private static $logged_unconfigured = false;

	/**
	 * Whether the "no Action Scheduler" line was logged this request.
	 *
	 * @var bool
	 */
	private static $logged_no_scheduler = false;

	/**
	 * Why the last queue() call returned false, for the entry note.
	 *
	 * @var string
	 */
	private static $queue_error = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'gform_after_submission', array( $this, 'after_submission' ), 20, 2 );
		add_action( 'gform_update_status', array( $this, 'update_status' ), 10, 3 );
		add_action( 'gform_approvals_entry_approved', array( $this, 'entry_approved' ), 10, 2 );
		add_action( 'gform_entry_detail_sidebar_middle', array( $this, 'sidebar_box' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'handle_release' ) );
	}

	/**
	 * Form config, passed through the lwtv_submission_triage_config filter once.
	 *
	 * @return array{form_id:int, field_topic:int, topic_value:string, field_email:int, field_show:int, field_info:int}
	 */
	public static function config(): array {
		if ( null === self::$config ) {
			$defaults = array(
				'form_id'     => self::FORM_ID,
				'field_topic' => self::FIELD_TOPIC,
				'topic_value' => self::TOPIC_VALUE,
				'field_email' => self::FIELD_EMAIL,
				'field_show'  => self::FIELD_SHOW,
				'field_info'  => self::FIELD_INFO,
			);
			$filtered = apply_filters( 'lwtv_submission_triage_config', $defaults );
			$filtered = is_array( $filtered ) ? array_merge( $defaults, $filtered ) : $defaults;

			self::$config = array(
				'form_id'     => (int) $filtered['form_id'],
				'field_topic' => (int) $filtered['field_topic'],
				'topic_value' => (string) $filtered['topic_value'],
				'field_email' => (int) $filtered['field_email'],
				'field_show'  => (int) $filtered['field_show'],
				'field_info'  => (int) $filtered['field_info'],
			);
		}

		return self::$config;
	}

	/**
	 * Spreadsheet ID in use.
	 *
	 * @return string
	 */
	public static function sheet_id(): string {
		if ( defined( 'LWTV_CONTENT_SHEET_ID' ) && is_string( LWTV_CONTENT_SHEET_ID ) && '' !== LWTV_CONTENT_SHEET_ID ) {
			return LWTV_CONTENT_SHEET_ID;
		}
		return self::DEFAULT_SHEET_ID;
	}

	/**
	 * Are the Gemini key and the Google service-account key file in place?
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		$missing = array();

		if ( ! defined( 'LWTV_GEMINI_API_KEY' ) || ! is_string( LWTV_GEMINI_API_KEY ) || '' === LWTV_GEMINI_API_KEY ) {
			$missing[] = 'LWTV_GEMINI_API_KEY';
		}
		if ( ! defined( 'LWTV_GOOGLE_SERVICE_ACCOUNT' ) || ! is_string( LWTV_GOOGLE_SERVICE_ACCOUNT ) || '' === LWTV_GOOGLE_SERVICE_ACCOUNT ) {
			$missing[] = 'LWTV_GOOGLE_SERVICE_ACCOUNT';
		} elseif ( ! is_readable( LWTV_GOOGLE_SERVICE_ACCOUNT ) ) {
			$missing[] = 'a readable LWTV_GOOGLE_SERVICE_ACCOUNT key file';
		}

		if ( empty( $missing ) ) {
			return true;
		}

		if ( ! self::$logged_unconfigured ) {
			self::$logged_unconfigured = true;
			self::debug( 'Sheet triage is not configured (missing ' . implode( ', ', $missing ) . '); nothing queued.' );
		}

		return false;
	}

	/**
	 * Is this entry from the contact form, about a new show/actor/character?
	 *
	 * @param array $entry Entry.
	 * @return bool
	 */
	public static function is_triage_entry( array $entry ): bool {
		$config = self::config();

		if ( (int) ( $entry['form_id'] ?? 0 ) !== $config['form_id'] ) {
			return false;
		}

		return trim( (string) ( $entry[ (string) $config['field_topic'] ] ?? '' ) ) === $config['topic_value'];
	}

	/**
	 * Hold or queue a new submission. Never throws.
	 *
	 * @param array $entry Entry.
	 * @param array $form  Form.
	 * @return void
	 */
	public function after_submission( $entry, $form ): void {
		unset( $form );

		try {
			if ( ! is_array( $entry ) || ! self::is_triage_entry( $entry ) ) {
				return;
			}

			$entry_id = (int) ( $entry['id'] ?? 0 );
			if ( $entry_id <= 0 ) {
				return;
			}

			if ( 'spam' === ( $entry['status'] ?? '' ) ) {
				gform_update_meta( $entry_id, self::META_HELD, 'spam' );
				self::note( $entry_id, 'Held: marked spam. Will be added to the sheet if un-spammed.' );
				return;
			}

			if ( self::is_moderated( $entry ) ) {
				gform_update_meta( $entry_id, self::META_HELD, 'moderated' );
				self::note( $entry_id, 'Held: flagged for moderation. Will be added to the sheet once released from the "Content to Add sheet" box.' );
				return;
			}

			self::queue( $entry_id );
		} catch ( \Throwable $e ) {
			self::error( 'Could not hold or queue entry ' . (int) ( $entry['id'] ?? 0 ) . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Track spam status changes on triage entries.
	 *
	 * Un-spamming a held entry releases it. Marking an unfinished entry spam
	 * holds it, so a job that is already queued does nothing.
	 *
	 * @param int    $entry_id   Entry ID.
	 * @param string $new_status New status.
	 * @param string $old_status Previous status.
	 * @return void
	 */
	public function update_status( $entry_id, $new_status, $old_status ): void {
		try {
			$entry_id = (int) $entry_id;

			if ( 'spam' === $old_status && 'active' === $new_status ) {
				if ( '' !== (string) gform_get_meta( $entry_id, self::META_HELD ) ) {
					self::release( $entry_id, 'Released: no longer spam. Queued for the sheet.' );
				}
				return;
			}

			if ( 'spam' === $new_status && 'spam' !== $old_status && ! gform_get_meta( $entry_id, self::META_DONE ) && '' === (string) gform_get_meta( $entry_id, self::META_HELD ) ) {
				$entry = \GFAPI::get_entry( $entry_id );
				if ( is_array( $entry ) && self::is_triage_entry( $entry ) ) {
					gform_update_meta( $entry_id, self::META_HELD, 'spam' );
					self::note( $entry_id, 'Held: marked spam. Will be added to the sheet if un-spammed.' );
				}
			}
		} catch ( \Throwable $e ) {
			self::error( 'Could not handle status change for entry ' . (int) $entry_id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Release a moderation-held entry when an approval feed approves it.
	 *
	 * @param array $entry Entry.
	 * @param array $form  Form.
	 * @return void
	 */
	public function entry_approved( $entry, $form ): void {
		unset( $form );

		try {
			$entry_id = is_array( $entry ) ? (int) ( $entry['id'] ?? 0 ) : 0;
			if ( $entry_id > 0 && 'moderated' === gform_get_meta( $entry_id, self::META_HELD ) ) {
				self::release( $entry_id, 'Released: approved. Queued for the sheet.' );
			}
		} catch ( \Throwable $e ) {
			self::error( 'Could not release approved entry: ' . $e->getMessage() );
		}
	}

	/**
	 * Queue an entry for the sheet.
	 *
	 * @param int $entry_id Entry ID.
	 * @return bool Whether a job is now scheduled (or already was).
	 */
	public static function queue( int $entry_id ): bool {
		self::$queue_error = '';

		if ( $entry_id <= 0 ) {
			self::$queue_error = 'invalid entry ID';
			return false;
		}
		if ( gform_get_meta( $entry_id, self::META_DONE ) ) {
			self::$queue_error = 'already on the sheet';
			return false;
		}
		if ( ! self::is_configured() ) {
			self::$queue_error = 'sheet triage is not configured';
			return false;
		}

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! self::$logged_no_scheduler ) {
				self::$logged_no_scheduler = true;
				self::error( 'Action Scheduler unavailable; sheet triage entries are not queued.' );
			}
			self::note( $entry_id, 'Action Scheduler unavailable; not queued.', 'error' );
			self::$queue_error = 'Action Scheduler unavailable';
			return false;
		}

		if ( self::is_queued( $entry_id ) ) {
			return true;
		}

		$action_id = as_schedule_single_action( time() + 10, Sheet_Triage_Task::AS_HOOK, array( $entry_id, 0 ), Sheet_Triage_Task::AS_GROUP );
		if ( ! $action_id ) {
			self::error( 'Could not schedule sheet triage for entry ' . $entry_id . '.' );
			self::$queue_error = 'Action Scheduler could not schedule it';
			return false;
		}

		self::debug( 'Queued entry ' . $entry_id . ' for sheet triage.' );
		return true;
	}

	/**
	 * Release a held entry: queue it first, and clear the hold only if that worked.
	 *
	 * @param int    $entry_id      Entry ID.
	 * @param string $released_note Note for a successful release.
	 * @return bool Whether it was released.
	 */
	private static function release( int $entry_id, string $released_note ): bool {
		if ( self::queue( $entry_id ) ) {
			gform_delete_meta( $entry_id, self::META_HELD );
			self::note( $entry_id, $released_note );
			return true;
		}

		self::note( $entry_id, 'Not released: could not queue for the sheet (' . self::$queue_error . '). The entry is still held.', 'error' );
		return false;
	}

	/**
	 * Is a first run or a retry already pending for this entry?
	 *
	 * Checks every attempt number, so a pending retry is never doubled up.
	 *
	 * @param int $entry_id Entry ID.
	 * @return bool
	 */
	public static function is_queued( int $entry_id ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		for ( $attempt = 0; $attempt < Sheet_Triage_Task::MAX_ATTEMPTS; $attempt++ ) {
			if ( as_has_scheduled_action( Sheet_Triage_Task::AS_HOOK, array( $entry_id, $attempt ), Sheet_Triage_Task::AS_GROUP ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle the "Release to Content sheet" button (runs before the entry page renders).
	 *
	 * @return void
	 */
	public function handle_release(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only; verified below.
		if ( ! isset( $_POST[ self::RELEASE_FIELD ] ) || ! class_exists( '\GFAPI' ) ) {
			return;
		}

		$entry_id = absint( wp_unslash( $_POST[ self::RELEASE_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line.
		check_admin_referer( 'lwtv_triage_release_' . $entry_id, self::RELEASE_NONCE );

		if ( ! \GFAPI::current_user_can_any( 'gravityforms_edit_entries' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to release this entry.', 'lwtv-underscores' ), 403 );
		}

		try {
			$entry = \GFAPI::get_entry( $entry_id );
			if ( ! is_array( $entry ) || ! self::is_triage_entry( $entry ) || '' === (string) gform_get_meta( $entry_id, self::META_HELD ) ) {
				return;
			}

			$user = wp_get_current_user();
			self::release( $entry_id, 'Released to the Content sheet by ' . $user->display_name . '. Queued for the sheet.' );
		} catch ( \Throwable $e ) {
			self::error( 'Could not release entry ' . $entry_id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Sheet status box on the entry detail sidebar.
	 *
	 * @param array $form  Form.
	 * @param array $entry Entry.
	 * @return void
	 */
	public function sidebar_box( $form, $entry ): void {
		$config = self::config();
		if ( ! is_array( $form ) || ! is_array( $entry ) || (int) ( $form['id'] ?? 0 ) !== $config['form_id'] ) {
			return;
		}

		$entry_id = (int) ( $entry['id'] ?? 0 );
		$held     = (string) gform_get_meta( $entry_id, self::META_HELD );
		$done     = (int) gform_get_meta( $entry_id, self::META_DONE );
		$button   = false;

		if ( ! self::is_triage_entry( $entry ) ) {
			$status = __( 'Not applicable: this entry is not about a new show, actor or character.', 'lwtv-underscores' );
		} elseif ( $done > 0 ) {
			/* translators: %s: date and time the entry was added to the sheet. */
			$status = sprintf( __( 'Done: added to the sheet on %s.', 'lwtv-underscores' ), (string) wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $done ) );
		} elseif ( 'spam' === $held ) {
			$status = __( 'Held: marked spam.', 'lwtv-underscores' );
			$button = true;
		} elseif ( 'moderated' === $held ) {
			$status = __( 'Held: flagged for moderation.', 'lwtv-underscores' );
			$button = true;
		} elseif ( self::is_queued( $entry_id ) ) {
			$status = __( 'Queued: will be added to the sheet shortly.', 'lwtv-underscores' );
		} else {
			$status = __( 'Not queued. Check the entry notes for errors.', 'lwtv-underscores' );
		}

		$can_release = $button && \GFAPI::current_user_can_any( 'gravityforms_edit_entries' );
		?>
		<div class="postbox">
			<h3 class="hndle"><span><?php esc_html_e( 'Content to Add sheet', 'lwtv-underscores' ); ?></span></h3>
			<div class="inside">
				<p><?php echo esc_html( $status ); ?></p>
				<?php if ( $can_release ) : ?>
					<?php wp_nonce_field( 'lwtv_triage_release_' . $entry_id, self::RELEASE_NONCE, false ); ?>
					<button type="submit" class="button" name="<?php echo esc_attr( self::RELEASE_FIELD ); ?>" value="<?php echo esc_attr( (string) $entry_id ); ?>"><?php esc_html_e( 'Release to Content sheet', 'lwtv-underscores' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Is the submitter's email or IP on the moderated keys list?
	 *
	 * @param array $entry Entry.
	 * @return bool
	 */
	private static function is_moderated( array $entry ): bool {
		$email = trim( (string) ( $entry[ (string) self::config()['field_email'] ] ?? '' ) );
		if ( '' !== $email && is_email( $email ) && Spammers::is_spammer( $email, 'email', 'moderated_keys' ) ) {
			return true;
		}

		$ip = trim( (string) ( $entry['ip'] ?? '' ) );
		return '' !== $ip && Spammers::is_spammer( $ip, 'ip', 'moderated_keys' );
	}

	/**
	 * Add an LWTV Robot note to an entry.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $message  Note text.
	 * @param string $type     Note type.
	 * @return void
	 */
	public static function note( int $entry_id, string $message, string $type = 'notification' ): void {
		if ( class_exists( '\GFAPI' ) ) {
			\GFAPI::add_note( $entry_id, 0, 'LWTV Robot', $message, $type, self::NOTE_SUB_TYPE );
		}
	}

	/**
	 * Debug-log to the submissions topic.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function debug( string $message ): void {
		if ( function_exists( 'lwtv_plugin' ) ) {
			lwtv_plugin()->debug_log( 'submissions', $message );
		}
	}

	/**
	 * Error-log to the submissions topic.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function error( string $message ): void {
		if ( function_exists( 'lwtv_plugin' ) ) {
			lwtv_plugin()->error_log( 'submissions', $message );
		}
	}
}
