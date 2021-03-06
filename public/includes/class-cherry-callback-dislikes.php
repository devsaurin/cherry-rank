<?php
/**
 * Init macros callbacks for additional meta
 *
 * @package    Cherry_Ratings
 * @subpackage Class
 * @author     Cherry Team <support@cherryframework.com>
 * @copyright  Copyright (c) 2012 - 2015, Cherry Team
 * @link       http://www.cherryframework.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


// If class 'Cherry_Callback_Dislikes' not exists.
if ( ! class_exists( 'Cherry_Callback_Dislikes' ) ) {

	/**
	 * Add rating system and callback
	 *
	 * @since 1.0.0
	 */
	class Cherry_Callback_Dislikes {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Meta field name for rating storing
		 *
		 * @since 1.0.0
		 * @var   string
		 */
		public $meta_key = 'cherry_dislikes';

		/**
		 * Sinle page meta visibility
		 *
		 * @since 1.0.0
		 * @var   array
		 */
		public $show_single = array();

		/**
		 * Loop page meta visibility
		 *
		 * @since 1.0.0
		 * @var   array
		 */
		public $show_loop = array();

		/**
		 * Contructor for the class
		 */
		function __construct() {

			add_filter( 'cherry_pre_get_the_post_dislikes', array( $this, 'macros_callback' ), 10, 2 );
			add_filter( 'cherry_shortcodes_data_callbacks', array( $this, 'register_dislikes_macros' ), 10, 2 );
			add_action( 'wp_ajax_cherry_handle_dislike', array( $this, 'ajax_handle' ) );
			add_action( 'wp_ajax_nopriv_cherry_handle_dislike', array( $this, 'ajax_handle' ) );
			add_action( 'init', array( $this, 'set_options' ) );

			if ( ! session_id() ) {
				session_start();
			}

		}

		/**
		 * Store required theme options into class property
		 *
		 * @since  1.0.3
		 * @return void
		 */
		public function set_options() {

			$this->show_single = Cherry_Rank_Options::get_option(
				'rank_add_single_meta', array( 'rating', 'likes', 'dislikes', 'views' )
			);

			$this->show_loop = Cherry_Rank_Options::get_option(
				'rank_add_blog_meta', array( 'rating', 'likes', 'dislikes', 'views' )
			);

		}

		/**
		 * Register callback for dislikes macros to process it in shortcodes
		 *
		 * @since  1.0.2
		 * @param  array $data existing callbacks.
		 * @param  array $atts shortcode attributes.
		 * @return array
		 */
		public function register_dislikes_macros( $data, $atts ) {
			$data['dislikes'] = array( $this, 'shortcode_macros_callback' );
			return $data;
		}

		/**
		 * Init macros callbacks
		 *
		 * @since  1.0.0
		 * @return string  dislike button HTML
		 */
		public function macros_callback( $pre, $attr ) {

			global $post;

			if ( ! empty( $attr['where'] ) ) {
				// if need to show on loop, but now is single page
				if ( ( ( 'loop' === $attr['where'] ) && is_singular() ) ) {
					return '';
				}

				// if need to show on single, but now is loop page
				if ( ( 'single' === $attr['where'] ) && ! is_singular() ) {
					return '';
				}
			}

			return $this->get_dislikes();

		}

		/**
		 * Callback for shortcode macros
		 *
		 * @since  1.0.2
		 * @return string
		 */
		public function shortcode_macros_callback() {

			global $post;

			$result = $this->get_dislikes_html( $post->ID );
			return '<div class="meta-rank-dislikes" id="dislike-' . $post->ID . '">' . $result . '</div>';
		}

		/**
		 * Get clean dislikes output
		 *
		 * @since  1.0.0
		 * @return string
		 */
		public function get_dislikes() {

			global $post;

			if ( ! in_array( 'dislikes', $this->show_single ) && is_singular() ) {
				return '';
			}

			if ( ! in_array( 'dislikes', $this->show_loop ) && ! is_singular() ) {
				return '';
			}

			$result = $this->get_dislikes_html( $post->ID );

			return '<div class="meta-rank-dislikes" id="dislike-' . $post->ID . '">' . $result . '</div>';

		}

		/**
		 * Get post Rating HTML
		 *
		 * @since  1.0.0
		 * @since  1.0.2 pass additional parameters to cherry_meta_views_format
		 * @return string
		 */
		public function get_dislikes_html( $post_id ) {

			/**
			 * Fires this action to enqueue rank assets
			 */
			do_action( 'cherry_rank_enqueue_assets' );

			$likes_count = get_post_meta( $post_id, $this->meta_key, true );
			$likes_count = absint( $likes_count );

			$disliked = '';
			if ( isset( $_SESSION['cherry-dislikes'] )
				&& is_array( $_SESSION['cherry-dislikes'] )
				&& in_array( $post_id, $_SESSION['cherry-dislikes'] )
			) {
				$disliked = 'action-done';
			}

			$format = apply_filters(
				'cherry_meta_dislikes_format',
				'<a href="#" class="meta-rank-dislike-this %3$s" data-post="%2$s">%1$s</a>',
				$likes_count, $post_id, $disliked
			);

			return sprintf(
				$format, $likes_count, $post_id, $disliked
			);

		}

		/**
		 * Ajax handler for rating processing
		 *
		 * @since  1.0.0
		 * @return void
		 */
		public function ajax_handle() {

			if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'cherry_rank' ) ) {
				die();
			}

			$post_id  = ( ! empty( $_REQUEST['post'] ) ) ? absint( $_REQUEST['post'] ) : false;
			$disliked = ( ! empty( $_REQUEST['done'] ) && 'true' == $_REQUEST['done'] ) ? true : false;

			if ( ! $post_id ) {
				die();
			}

			if ( ! isset( $_SESSION['cherry-dislikes'] ) ) {
				$_SESSION['cherry-dislikes'] = array();
			}

			$dislikes_count = get_post_meta( $post_id, $this->meta_key, true );
			$dislikes_count = absint( $dislikes_count );

			if ( false == $disliked ) {
				$_SESSION['cherry-dislikes'][ $post_id ] = $post_id;
				$this->maybe_remove_like( $post_id );
				$dislikes_count++;
			} else {
				unset( $_SESSION['cherry-dislikes'][ $post_id ] );
				$dislikes_count = $dislikes_count - 1;
			}

			if ( $dislikes_count < 0 ) {
				$dislikes_count = 0;
			}

			update_post_meta( $post_id, $this->meta_key, $dislikes_count );

			echo $dislikes_count;

			do_action( 'cherry_dislikes_ajax_handle', $post_id );

			die();

		}

		/**
		 * Check if user already liked this post and remove like
		 *
		 * @since  1.0.0
		 * @param  int $post_id post ID to check.
		 */
		public function maybe_remove_like( $post_id ) {

			if ( ! isset( $_SESSION['cherry-likes'] ) ) {
				return;
			}

			if ( ! isset( $_SESSION['cherry-likes'][ $post_id ] ) ) {
				return;
			}

			unset( $_SESSION['cherry-likes'][ $post_id ] );

			$likes = get_post_meta( $post_id, 'cherry_likes', true );
			$likes = $likes - 1;
			if ( $likes < 0 ) {
				$likes = 0;
			}

			update_post_meta( $post_id, 'cherry_likes', $likes );
		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @return object
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}
	}

	Cherry_Callback_Dislikes::get_instance();

}
