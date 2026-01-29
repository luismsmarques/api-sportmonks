<?php
/**
 * Plugin Name: API Sportmonks
 * Plugin URI: https://www.sportmonks.com/
 * Description: Integração com a API Sportmonks para sincronizar dados de futebol e criar posts de jogos.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: api-sportmonks
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'APS_SMONKS_VERSION', '1.0.0' );
define( 'APS_SMONKS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APS_SMONKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'APS_SMONKS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class APS_Sportmonks {
	
	/**
	 * Instance of this class
	 *
	 * @var APS_Sportmonks
	 */
	private static $instance = null;
	
	/**
	 * Get instance of this class
	 *
	 * @return APS_Sportmonks
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}
	
	/**
	 * Load required files
	 */
	private function load_dependencies() {
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-error-logger.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-api-client.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-taxonomy-manager.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-cpt-jogo.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-settings.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-sync-manager.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-cron-handler.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-data-explorer.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-theme-helpers.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-shortcodes.php';
		require_once APS_SMONKS_PLUGIN_DIR . 'includes/class-components.php';
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}
	
	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create error log table
		APS_Error_Logger::create_table();

		// Ensure CPTs and taxonomies are registered before flushing
		APS_Taxonomy_Manager::get_instance()->register_taxonomy();
		APS_CPT_Jogo::get_instance()->register_post_type();
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clear scheduled events
		$timestamp = wp_next_scheduled( 'aps_sportmonks_sync_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'aps_sportmonks_sync_event' );
		}
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'api-sportmonks', false, dirname( APS_SMONKS_PLUGIN_BASENAME ) . '/languages' );
	}
	
	/**
	 * Initialize plugin components
	 */
	public function init() {
		// Initialize components
		APS_Error_Logger::get_instance();
		APS_Taxonomy_Manager::get_instance();
		APS_CPT_Jogo::get_instance();
		APS_Settings::get_instance();
		APS_Sync_Manager::get_instance();
		APS_Cron_Handler::get_instance();
		APS_Data_Explorer::get_instance();
		APS_Theme_Helpers::get_instance();
		APS_Shortcodes::get_instance();
		APS_Components::get_instance();
	}
}

/**
 * Initialize the plugin
 */
function aps_sportmonks_init() {
	return APS_Sportmonks::get_instance();
}

// Start the plugin
aps_sportmonks_init();

