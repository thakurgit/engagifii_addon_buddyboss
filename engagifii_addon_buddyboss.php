<?php
/**
 * Plugin Name: Engagifii Add-on for Buddyboss
 * Plugin URI:  https://engagifii.com/
 * Description: Engagifii plugin to fetch data into BuddyBoss Platform.
 * Author:      Engagifii
 * Author URI:  https://engagifii.com/
 * Version:     1.2
 * Text Domain: engagifii-addon
 * Domain Path: /languages/
 * License:     GPLv3 or later (license.txt)
 * Icon URI: assets/images/icon-128x128.png
 */

/**
 * This file should always remain compatible with the minimum version of
 * PHP supported by WordPress.
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;
define('BB_ENGAGIFII_VERSION','1.2');
if ( ! class_exists( 'engagifii_BB_Platform_Addon' ) ) {

	/**
	 * Main MYPlugin Custom Emails Class
	 *
	 * @class engagifii_BB_Platform_Addon
	 * @version	1.0.0
	 */
	final class engagifii_BB_Platform_Addon {

		/**
		 * @var engagifii_BB_Platform_Addon The single instance of the class
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Main engagifii_BB_Platform_Addon Instance
		 *
		 * Ensures only one instance of engagifii_BB_Platform_Addon is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @see engagifii_BB_Platform_Addon()
		 * @return engagifii_BB_Platform_Addon - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Cloning is forbidden.
		 * @since 1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'engagifii-addon' ), '1.0.0' );
		}
		/**
		 * Unserializing instances of this class is forbidden.
		 * @since 1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'engagifii-addon' ), '1.0.0' );
		}

		/**
		 * engagifii_BB_Platform_Addon Constructor.
		 */
		public function __construct() {
			$this->define_constants();
			$this->includes();
			$this->init_hooks();
			// Set up localisation.
			$this->load_plugin_textdomain();
		}

		/**
		 * Define WCE Constants
		 */
		private function define_constants() {
			$this->define( 'engagifii_BB_ADDON_PLUGIN_FILE', __FILE__ );
			$this->define( 'engagifii_BB_ADDON_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			$this->define( 'engagifii_BB_ADDON_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
			$this->define( 'engagifii_BB_ADDON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		/**
		 * Define constant if not already set
		 * @param  string $name
		 * @param  string|bool $value
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 */
		public function includes() {
			include_once( 'functions.php' );
			include_once dirname( __FILE__ ) . '/integration/engagifii.php';
			include_once dirname( __FILE__ ) . '/integration/member.php';
			include_once dirname( __FILE__ ) . '/integration/hub.php';
			require_once dirname( __FILE__ ) . '/integration/updater.php';
			require_once dirname( __FILE__ ) . '/integration/activity.php';
		}
		
		private function init_hooks() {		
		 add_action('admin_menu',array($this,'engagifii_bb_add_admin_menu'), 10);	
		}

		/**
		 * Get the plugin url.
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugins_url( '/', __FILE__ ) );
		}

		/**
		 * Get the plugin path.
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

		/**
		 * Load Localisation files.
		 *
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
		 */
		public function load_plugin_textdomain() {
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $locale, 'engagifii-addon' );

			unload_textdomain( 'engagifii-addon' );
			load_textdomain( 'engagifii-addon', WP_LANG_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' . plugin_basename( dirname( __FILE__ ) ) . '-' . $locale . '.mo' );
			load_plugin_textdomain( 'engagifii-addon', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}
		
		function engagifii_bb_add_admin_menu() {
			$parent = site_url().'/wp-admin/admin.php?page=bp-integrations';
			add_submenu_page( 'buddyboss-platform', 'Engagifii Add-on', '<b>Engagifii</b> <span class="bb-upgrade-nav-tag">New</span>', 'manage_options', $parent.'&tab=bp-engagifii_settings',  $callback = '');
		}
		

	}
	/**
	 * Returns the main instance of engagifii_BB_Platform_Addon to prevent the need to use globals.
	 *
	 * @since  1.0.0
	 * @return engagifii_BB_Platform_Addon
	 */
	function engagifii_BB_Platform_Addon() {
		return engagifii_BB_Platform_Addon::instance();
	}

	function engagifii_BB_Platform_install_bb_platform_notice() {
		echo '<div class="error fade"><p>';
		_e('<strong>BuddyBoss Platform Add-on</strong></a> requires the BuddyBoss Platform plugin to work. Please <a href="https://buddyboss.com/platform/" target="_blank">install BuddyBoss Platform</a> first.', 'engagifii-addon');
		echo '</p></div>';
	}

	function engagifii_BB_Platform_update_bb_platform_notice() {
		echo '<div class="error fade"><p>';
		_e('<strong>BuddyBoss Platform Add-on</strong></a> requires BuddyBoss Platform plugin version 1.2.6 or higher to work. Please update BuddyBoss Platform.', 'engagifii-addon');
		echo '</p></div>';
	}

	function engagifii_BB_Platform_is_active() {
		if ( defined( 'BP_PLATFORM_VERSION' ) && version_compare( BP_PLATFORM_VERSION,'1.2.6', '>=' ) ) {
			return true;
		}
		return false;
	}

	function engagifii_BB_Platform_init() {
		if ( ! defined( 'BP_PLATFORM_VERSION' ) ) {
			add_action( 'admin_notices', 'engagifii_BB_Platform_install_bb_platform_notice' );
			add_action( 'network_admin_notices', 'engagifii_BB_Platform_install_bb_platform_notice' );
			return;
		}

		if ( version_compare( BP_PLATFORM_VERSION,'1.2.6', '<' ) ) {
			add_action( 'admin_notices', 'engagifii_BB_Platform_update_bb_platform_notice' );
			add_action( 'network_admin_notices', 'engagifii_BB_Platform_update_bb_platform_notice' );
			return;
		}

		engagifii_BB_Platform_Addon();
	}

	add_action( 'plugins_loaded', 'engagifii_BB_Platform_init', 9 );
	register_activation_hook( __FILE__, 'engagifii_create_activity_log_table' );

	function engagifii_create_activity_log_table() {
		global $wpdb;
	
		$table_name = $wpdb->prefix . 'engagifii_activity_log';
		$charset_collate = $wpdb->get_charset_collate();
	
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
		$sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        activity_type VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        log_meta LONGTEXT DEFAULT NULL,
        post_id BIGINT(20) DEFAULT NULL,
        hub_id BIGINT(20) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY activity_type (activity_type),
        KEY created_at (created_at),
        KEY hub_id (hub_id),
        KEY post_id (post_id)
    ) $charset_collate;";
	
		dbDelta( $sql );
		// Check if this is the first insert
    $already_initialized = $wpdb->get_var( 
        "SELECT COUNT(*) FROM $table_name WHERE activity_type = 'log_initialized'" 
    );

    if ( ! $already_initialized ) {
        $wpdb->insert(
            $table_name,
            [
                'user_id'       => get_current_user_id(),
                'activity_type' => 'log_initialized',
                'log_meta'      => maybe_serialize([
                    'note' => 'Activity log initialized',
                    'ip'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]),
                'created_at'    => current_time('mysql'),
            ],
            [
                '%d', '%s', '%s', '%s'
            ]
        );
    }
	}

}
