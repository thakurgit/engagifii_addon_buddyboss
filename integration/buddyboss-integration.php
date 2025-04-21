<?php
/**
 * BuddyBoss Compatibility Integration Class.
 *
 * @since BuddyBoss 1.1.5
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Setup the bp compatibility class.
 *
 * @since BuddyBoss 1.1.5
 */
class engagifii_BuddyBoss_Integration extends BP_Integration {

	public function __construct() {
		$this->start(
			'engagifii_settings',
			__( 'Engagifii', 'engagifii-addon' ),
			'engagifii_settings',
			array(
				'required_plugin' => array(),
			)
		);

		// Add link to settings page.
		add_filter( 'plugin_action_links',               array( $this, 'action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'action_links' ), 10, 2 );
	}

	/**
	 * Register admin integration tab
	 */
	public function setup_admin_integration_tab() {

		require_once 'buddyboss-addon-integration-tab.php';

		new engagifii_BuddyBoss_Admin_Integration_Tab(
			"bp-{$this->id}",
			$this->name,
			array(
				'root_path'       => engagifii_BB_ADDON_PLUGIN_PATH . '/integration',
				'root_url'        => engagifii_BB_ADDON_PLUGIN_URL . '/integration',
				'required_plugin' => $this->required_plugin,
			)
		);
	}

	public function action_links( $links, $file ) {

		// Return normal links if not BuddyPress.
		if ( engagifii_BB_ADDON_PLUGIN_BASENAME != $file ) {
			return $links;
		}

		// Add a few links to the existing links array.
		return array_merge(
			$links,
			array(
				'settings' => '<a href="' . esc_url( bp_get_admin_url( 'admin.php?page=bp-integrations&tab=bp-engagifii_settings' ) ) . '">' . __( 'Settings', 'engagifii-addon' ) . '</a>',
			)
		);
	}
}
