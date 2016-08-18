<?php
/**
 * Plugin Name: Comments Since Last Visit
 * Description: Highlights new comments made on a post since a person's last visit.
 * Plugin URI: http://www.johnparris.com/wordpress-plugins/comments-since-last-visit/
 * Version:     1.0.2
 * Author:      John Parris
 * Author URI:  http://www.johnparris.com/
 * License:     GPL
 * Text Domain: wp-cslv
 * Domain Path: /languages
 */

/*
 Borrows heavily from Natko Hasic http://natko.com/highlighting-the-comments-since-your-last-visit/
 */


add_action(
	'plugins_loaded',
	array ( WP_CSLV::get_instance(), 'plugin_setup' )
);


class WP_CSLV
{
	/**
	 * Plugin instance.
	 *
	 * @see  get_instance()
	 * @type object
	 */
	protected static $instance = NULL;



	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';



	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';



	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @since   1.0
	 * @return  object of this class
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}



	/**
	 * Used for regular plugin work.
	 *
	 * @wp-hook plugins_loaded
	 * @since   1.0
	 * @return  void
	 */
	public function plugin_setup()
	{

		$this->plugin_url  = plugins_url( '/', __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->load_language( 'wp-cslv' );

		// Register actions and filters
		add_action( 'get_header',         array( $this, 'cookie' ) );
		add_filter( 'comment_class',      array( $this, 'comment_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
		add_filter( 'http_request_args',  array( $this, 'prevent_public_updates' ), 5, 2 );
	}



	/**
	 * Constructor. Intentionally left empty and public.
	 *
	 * @see   plugin_setup()
	 * @since 1.0
	 */
	public function __construct() {}



	/**
	 * Loads translation file.
	 *
	 * Accessible to other classes to load different language files (admin and
	 * front-end for example).
	 *
	 * @wp-hook init
	 * @param   string $domain
	 * @since   1.0
	 * @return  void
	 */
	public function load_language( $domain )
	{
		load_plugin_textdomain(
			$domain,
			FALSE,
			$this->plugin_path . 'languages'
		);
	}



	/**
	 * Mix, bake, and eat the cookies
	 *
	 * @since 1.0
	 */
	public function cookie()
	{
		// We only want this on singular views
		if ( is_singular() )
		{
			// Get current post ID
			$id = get_the_ID();

			// Get current time
			$current_time = strtotime( current_time( 'mysql' ) );

			// See if cookie already exists and if so, get the last visit
			if ( isset( $_COOKIE['last_visit'] ) )
			{
				$latest_visit = json_decode( stripslashes( $_COOKIE['last_visit'] ), true );

				// Keep only last 50
				if ( count( $latest_visit ) >= 50 )
				{
					$latest_visit = array_diff( $latest_visit, array( min( $latest_visit ) ) );
				}
			}

			// Save the time of the visit on this post in a cookie for 90 days
			$latest_visit[$id] = $current_time;
			setcookie( 'last_visit', json_encode( $latest_visit ), time()+3600*2160, COOKIEPATH );

		}

	}



	/**
	 * Modify comment_class on comments made since last visit
	 *
	 * @since 1.0
	 * @uses comment_class filter
	 * @return $classes variable. CSS classes for single comment.
	 */
	public function comment_class( $classes )
	{
		// Get time for comment
		$comment_time = strtotime( get_comment_date( 'Y-m-d G:i:s' ) );

		if ( isset( $_COOKIE['last_visit'] ) ) {
			$latest_visit = json_decode( stripslashes( $_COOKIE['last_visit']), true );
		}

		// Add new-comment class if the comment was posted since user's last visit
		if ( $comment_time > $latest_visit[get_the_ID()] )
		{
			$classes[] = 'new-comment';
		}
		return $classes;
	}


	/**
	 * Adds background color to new comments.
	 *
	 * Inlines to eliminate a return trip.
	 *
	 * @since 1.0
	 */
	function styles()
	{
		?>
		<style>.new-comment { background-color: #f0f8ff; }</style>
		<?php
	}



	/**
	 * Prevents the plugin from being updated from the WP public repo.
	 *
	 * In case someone adds a plugin to the wordpress.org repo with the same name.
	 *
	 * @since 1.0.1
	 */
	function prevent_public_updates( $r, $url )
	{
		if ( 0 === strpos( $url, 'https://api.wordpress.org/plugins/update-check/1.1/' ) )
		{
			$plugins = json_decode( $r['body']['plugins'], true );
			unset( $plugins['plugins'][plugin_basename( __FILE__ )] );
			$r['body']['plugins'] = json_encode( $plugins );
		}
		return $r;
	}

} //class
