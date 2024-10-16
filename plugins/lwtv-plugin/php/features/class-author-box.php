<?php
/**
 * Author Boxes
 */

namespace LWTV\Features;

class Author_Box {

	protected static $version = '3.0.0';

	protected static $default = array(
		'avatar'    => '<img src="http://0.gravatar.com/avatar/9c7ddb864b01d8e47ce3414c9bbf3008?s=64&d=mm&f=y&r=g">',
		'name'      => 'Mystery Girl',
		'bio'       => 'Yet another queer who slept with Shane. Or Sara Lance.',
		'title'     => '',
		'postcount' => '',
		'fav_shows' => '',
	);

	protected static $social_array = array(
		'bluesky'   => array(
			'name' => 'Bluesky',
			'icon' => 'bluesky.svg',
			'meta' => 'bluesky',
			'url'  => true,
		),
		'instagram' => array(
			'name' => 'Instagram',
			'icon' => 'instagram.svg',
			'meta' => 'instagram',
		),
		'tictok'    => array(
			'name' => 'TikTok',
			'icon' => 'tiktok.svg',
			'meta' => 'tiktok',
			'url'  => true,
		),
		'twitter'   => array(
			'name' => 'X (Twitter)',
			'icon' => 'x-twitter.svg',
			'meta' => 'twitter',
		),
		'tumblr'    => array(
			'name' => 'Tumblr',
			'icon' => 'tumblr.svg',
			'meta' => 'tumblr',
			'url'  => true,
		),
		'mastodon'  => array(
			'name' => 'Mastodon',
			'icon' => 'mastodon.svg',
			'meta' => 'mastodon',
			'url'  => true,
		),
		'website'   => array(
			'name' => 'Website',
			'icon' => 'home.svg',
			'meta' => 'url',
			'url'  => true,
		),
	);

	/**
	 * Make the box
	 *
	 * @param array $attributes Attributes.
	 *
	 * @return string
	 */
	public function make( array $attributes ) {

		wp_enqueue_style( 'author-box-shortcode', plugins_url( 'assets/css/author-box.css', __DIR__ ), array(), self::$version );

		// Default to large
		$format = ( isset( $attributes['format'] ) ) ? sanitize_text_field( $attributes['format'] ) : 'large';

		if ( isset( $attributes['users'] ) && ! is_numeric( $attributes['users'] ) ) {
			$get_user  = get_user_by( 'login', $attributes['users'] );
			$author_id = isset( $get_user->ID ) ? $get_user->ID : 0;
		} else {
			$author_id = isset( $attributes['users'] ) ? absint( $attributes['users'] ) : 0;
		}

		$user = get_userdata( $author_id );

		if ( ! $user ) {
			$content = self::$default;
		} else {
			$content = $this->get_author_content( $author_id, $user );
		}

		$author_details = $this->get_author_details( $format, $content );
		$author_box     = '<div class="author-box-shortcode"><section class="author-box"><div class="row">' . $author_details . '</row></section><br /></div>';

		return $author_box;
	}

	/**
	 * Get Author Details
	 *
	 * @param string $format  Format.
	 * @param array  $content Content.
	 *
	 * @return string
	 */
	public function get_author_details( $format, $content ) {
		$author_details = '';
		$social_array   = array();

		foreach ( $content['social'] as $social => $url ) {
			$data           = self::$social_array[ $social ];
			$social_array[] = '<a href="' . $url . '" target="_blank" rel="nofollow" aria-label="Follow ' . $content['name'] . ' on ' . $data['name'] . '">' . lwtv_plugin()->get_symbolicon( $data['icon'], 'fa-' . $social ) . '</a>';
		}

		$social_array  = array_filter( $social_array );
		$view_articles = ( $content['postcount'] > 0 ) ? '<div class="author-archives">' . lwtv_plugin()->get_symbolicon( 'newspaper.svg', 'fa-newspaper-o' ) . '&nbsp;<a href="' . get_author_posts_url( get_the_author_meta( 'ID', $content['id'] ) ) . '">View all articles by ' . $content['name'] . '</a></div>' : '';
		$author_title  = ( '' !== $content['title'] ) ? ' (' . $content['title'] . ')' : '';

		switch ( $format ) {
			case 'thumbnail':
				$author_details = '<div>' . $content['avatar'] . '<br>' . $content['name'] . ' ' . $author_title . '</div>';
				break;
			case 'compact':
				$author_details = '<div class="col-sm-2">' . $content['avatar'] . '</div><div class="col-sm"><span class="author_name author_box_compact"><a href="' . $content['url'] . '">' . $content['name'] . '</a>' . $author_title . ' <span class="author_box_social">' . implode( ' ', $social_array ) . '</span></span><hr><div class="author-details">' . $view_articles . '</div></div>';
				break;
			case 'large':
				$author_details = '<div class="col-sm-2">' . $content['avatar'] . '</div><div class="col-sm"><span class="author_name author_box_large">' . $content['name'] . $author_title . ' <span class="author_box_social">' . implode( ' ', $social_array ) . '</span></span><hr><div class="author-bio">' . nl2br( $content['bio'] ) . '</div><div class="author-details">' . $view_articles . $content['fav_shows'] . '</div>';
				break;
		}

		return $author_details;
	}

	/**
	 * Get Author Content
	 *
	 * @param int $author_id Author ID.
	 *
	 * @return array
	 */
	public function get_author_content( $author_id, $user ) {
		$content = array(
			'id'        => $author_id,
			'avatar'    => get_avatar( $author_id, 125 ),
			'url'       => get_author_posts_url( $author_id ) ?? null,
			'name'      => $user->display_name ?? null,
			'title'     => get_the_author_meta( 'jobrole', $author_id ) ?? null,
			'bio'       => $user->description ?? null,
			'postcount' => count_user_posts( $author_id, 'post', true ) ?? 0,
			'social'    => array(),
			'fav_shows' => $this->get_fav_shows( $author_id ),
		);

		// Generate social media content
		foreach ( self::$social_array as $social => $data ) {
			$this_social = get_the_author_meta( $data['meta'], $author_id ) ?? null;

			if ( ! $this_social ) {
				continue;
			}

			$content['social'][ $social ] = $this_social;

			// Ensure HTTPS:
			if ( isset( $data['url'] ) && ! str_contains( $content['social'][ $social ], 'http' ) ) {
				$content['social'][ $social ] = 'https://' . $content['social'][ $social ];
			}
		}

		return $content;
	}

	/**
	 * Get Favourite Shows
	 *
	 * @param int $author_id Author ID.
	 *
	 * @return string
	 */
	public function get_fav_shows( $author_id ) {
		// Get author Fav Shows
		$all_fav_shows = (array) get_the_author_meta( 'lez_user_favourite_shows', $author_id );
		if ( '' !== $all_fav_shows ) {
			$show_title = array();
			foreach ( $all_fav_shows as $each_show ) {
				if ( 'publish' !== get_post_status( $each_show ) ) {
					array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show ) . '</span></em>' );
				} else {
					array_push( $show_title, '<em><a href="' . get_permalink( $each_show ) . '">' . get_the_title( $each_show ) . '</a></em>' );
				}
			}
			$favourites = ( empty( $show_title ) ) ? '' : implode( ', ', $show_title );
			$fav_title  = _n( 'Show', 'Shows', count( $show_title ) );
		}
		$fav_shows = ( isset( $favourites ) && ! empty( $favourites ) ) ? '<div class="author-favourites">' . lwtv_plugin()->get_symbolicon( 'tv-hd.svg', 'fa-tv' ) . '&nbsp;Favorite ' . $fav_title . ': ' . $favourites . '</div>' : '';

		return $fav_shows;
	}
}
