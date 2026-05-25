<?php
/*
 * Name: Actor Privacy
 * Desc: Set actor Privacy details.
 * Since: 6.1.0
 */

namespace LWTV\CPTs\Actors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Privacy {

	/**
	 * Make Private
	 *
	 * Updates the post status based on privacy settings.
	 * Only calls wp_update_post() when the status actually needs to change
	 * to avoid expensive unnecessary database writes.
	 *
	 * @param  int         $post_id The post ID.
	 * @param  string|bool $set     'check' to auto-determine, true for private, false for publish.
	 * @return void
	 */
	public function make( $post_id, $set ) {
		$privacy        = get_post_meta( $post_id, 'lezactors_make_option_private', true );
		$privacy_date   = get_post_meta( $post_id, 'lezactors_make_option_private_date', true );
		$current_status = get_post_status( $post_id );
		$new_status     = $current_status; // Default: no change

		// Determine if the post should be private based on privacy meta.
		$should_be_private = is_array( $privacy ) && in_array( 'hide_all', $privacy, true );

		if ( 'check' === $set ) {
			// Auto-determine based on privacy settings.
			if ( $should_be_private && 'private' !== $current_status ) {
				// Needs to be private but isn't.
				$new_status = 'private';
			} elseif ( ! $should_be_private && 'private' === $current_status ) {
				// No longer needs to be private, restore to publish.
				$new_status = 'publish';
			}
			// If status matches what it should be, do nothing.
		} elseif ( true === $set ) {
			$new_status = 'private';
		} else {
			$new_status = 'publish';
		}

		// ONLY update if status actually changed to avoid expensive wp_update_post().
		if ( $new_status !== $current_status ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $new_status,
				)
			);
		}

		// If the post is private, and $privacy_date is EMPTY, set it to the current date.
		if ( 'private' === get_post_status( $post_id ) && empty( $privacy_date ) ) {
			update_post_meta( $post_id, 'lezactors_make_option_private_date', current_time( 'Y-m-d' ) );
		}
	}

	/**
	 * Hide sections
	 *
	 * @param  int    $post_id
	 * @param  string $type
	 * @return bool
	 */
	public function hide( $post_id, $type ): bool {
		$privacy = get_post_meta( $post_id, 'lezactors_make_option_private', true );

		if ( ! is_array( $privacy ) ) {
			return false;
		}

		switch ( $type ) {
			case 'dob':
				if ( in_array( 'hide_dob', $privacy, true ) ) {
					return true;
				}
				break;
			case 'socials':
				if ( in_array( 'hide_socials', $privacy, true ) ) {
					return true;
				}
				break;
			case 'all':
				if ( in_array( 'hide_all', $privacy, true ) ) {
					return true;
				}
				break;
		}

		return false;
	}

	/**
	 * Get the privacy warning
	 *
	 * @param  int $post_id
	 * @param  bool $return_echo
	 * @return void|array
	 */
	public function get_warning( $post_id, $return_echo = true ) {
		// if we're not logged in, return.
		if ( ! is_user_logged_in() ) {
			return;
		}

		$private_note = get_post_meta( $post_id, 'lezactors_make_option_private_notes', true );
		$privacy      = get_post_meta( $post_id, 'lezactors_make_option_private', true );
		$privacy_date = get_post_meta( $post_id, 'lezactors_make_option_private_date', true );

		// If Privacy is not an array, early return.
		if ( ! is_array( $privacy ) ) {
			return;
		}

		$amount_of_privacy = ( in_array( 'hide_all', $privacy, true ) ) ? 'all' : 'some';

		if ( 'all' === $amount_of_privacy && 'private' !== get_post_status( $post_id ) ) {
			$this->make( $post_id, true );
		} elseif ( 'some' === $amount_of_privacy && 'publish' !== get_post_status( $post_id ) ) {
			$this->make( $post_id, false );
		}

		if ( $return_echo ) {
			// Return the HTML.
			$this->get_warning_html( $amount_of_privacy, $privacy, $private_note, $privacy_date );
			return;
		}

		return array(
			'amount_of_privacy' => $amount_of_privacy,
			'privacy'           => $privacy,
			'private_note'      => $private_note,
			'privacy_date'      => $privacy_date,
		);
	}

	/**
	 * Get the privacy warning HTML
	 *
	 * @param  string $amount_of_privacy
	 * @param  array  $privacy
	 * @param  string $private_note
	 * @return void
	 */
	private function get_warning_html( $amount_of_privacy, $privacy, $private_note, $privacy_date ) {
		echo '<div class="wp-block-lez-library-private-note alert alert-warning" role="alert">';
		echo '<p><strong>Privacy Notice:</strong> This actor has requested that <em>' . esc_html( $amount_of_privacy ) . '</em> of their personal information be hidden from public view as of ' . esc_html( $privacy_date ) . '.';

		if ( 'some' === $amount_of_privacy ) {
			echo '<br/><br/>Hidden: ';

			if ( in_array( 'hide_dob', $privacy, true ) ) {
				echo '<br/>&bull; Birthday';
			}

			if ( in_array( 'hide_socials', $privacy, true ) ) {
				echo '<br/>&bull; Social Media';
			}
		}

		if ( $private_note ) {
			echo '<br/><br/>' . esc_html( $private_note );
		}

		echo '</p>';

		echo '</div>';
	}
}
