<?php
/**
 * User Profiles
 */

namespace LWTV\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class User_Profiles {

	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'extra_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'extra_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_extra_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_extra_profile_fields' ) );
		add_filter( 'user_contactmethods', array( $this, 'user_contactmethods' ) );

		// Rest API
		register_meta(
			'user',
			'jobrole',
			array(
				'type'         => 'string',
				'show_in_rest' => true, // this is the key part
			)
		);
		register_meta(
			'user',
			'twitter',
			array(
				'type'         => 'string',
				'show_in_rest' => true, // this is the key part
			)
		);
		add_action( 'rest_api_init', array( $this, 'rest_api_add_user_field' ), 10, 2 );
	}

	public function rest_api_add_user_field() {
		register_rest_field(
			'user',
			'lez_user_favourite_shows',
			array(
				'get_callback' => function ( $user, $field_name, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
					return get_user_meta( $user['id'], $field_name, true );
				},
				'schema'       => null,
			)
		);
	}

	public function user_contactmethods() {
		// Add moar contact methods and reorder:
		$methods['twitter']   = 'Twitter username (without @ so "mytwitter")';
		$methods['facebook']  = 'Facebook profile UR';
		$methods['instagram'] = 'Instagram profile ID (ex. "my_cool_insta")';
		$methods['threads']   = 'Threads profile ID  (ex "my_cool_threader")';
		$methods['tumblr']    = 'Tumblr profile URL (ex. https://my-site.tumblr.com )';
		$methods['tiktok']    = 'TikTok URL (ex. https://tiktok.com/@myname)';
		$methods['mastodon']  = 'Mastodon URL (ex. https://mastodon.site/@myname )';
		$methods['bluesky']   = 'BlueSky (ex. https://bsky.app/profile/myname.bsky.social )';

		return $methods;
	}

	/**
	 * Add extra fields to user profiles in the admin
	 *
	 * @param mixed $user The WP_User object of the user being edited
	 * @return void
	 * @access public
	 */
	public function extra_profile_fields( $user ) {
		?>
		<table class="form-table">

			<?php
			if ( current_user_can( 'update_core' ) ) {
				?>
				<tr>
					<th><label for="jobrole">Job Role</label></th>
					<td>
						<input type="text" name="jobrole" id="jobrole" value="<?php echo esc_attr( get_the_author_meta( 'jobrole', $user->ID ) ); ?>" class="regular-text" /><br />
						<span class="description">Job Role (i.e. Editor etc)</span>
					</td>
				</tr>
				<?php
			}
			?>
			<tr>
				<th><label for="pronouns">Pronouns</label></th>
				<td>
					<input type="text" name="gender" id="gender" value="<?php echo esc_attr( get_the_author_meta( 'pronouns', $user->ID ) ); ?>" class="regular-text" /><br />
					<span class="description">Preferred Pronouns</span>
				</td>
			</tr>
			<tr>
				<th><label for="gender">Gender</label></th>
				<td>
					<input type="text" name="gender" id="gender" value="<?php echo esc_attr( get_the_author_meta( 'gender', $user->ID ) ); ?>" class="regular-text" /><br />
					<span class="description">Gender Identity</span>
				</td>
			</tr>
			<tr>
				<th><label for="sexuality">Sexuality</label></th>
				<td>
					<input type="text" name="sexuality" id="sexuality" value="<?php echo esc_attr( get_the_author_meta( 'sexuality', $user->ID ) ); ?>" class="regular-text" /><br />
					<span class="description">Sexuality</span>
				</td>
			</tr>

		</table>
		<?php
	}

	public function save_extra_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		// jobrole is an admin-only field (see extra_profile_fields) — gate the
		// write on the same capability, not the generic edit_user.
		if ( current_user_can( 'update_core' ) && isset( $_POST['jobrole'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_user_meta( $user_id, 'jobrole', sanitize_text_field( wp_unslash( $_POST['jobrole'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['gender'] ) ) {
			update_user_meta( $user_id, 'gender', sanitize_text_field( wp_unslash( $_POST['gender'] ) ) );
		}
		if ( isset( $_POST['sexuality'] ) ) {
			update_user_meta( $user_id, 'sexuality', sanitize_text_field( wp_unslash( $_POST['sexuality'] ) ) );
		}
		if ( isset( $_POST['pronouns'] ) ) {
			update_user_meta( $user_id, 'pronouns', sanitize_text_field( wp_unslash( $_POST['pronouns'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
