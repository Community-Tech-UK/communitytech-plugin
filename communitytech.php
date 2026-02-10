<?php
/**
 * Plugin Name: CommunityTech Workflow
 * Plugin URI:  https://communitytech.co.uk
 * Description: Companion plugin for CommunityTech automation — exposes REST API endpoints for MCP integrations, Elementor global settings, and future workflow tooling.
 * Version:     1.3.0
 * Author:      CommunityTech
 * Author URI:  https://communitytech.co.uk
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: communitytech
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Plugin constants
define( 'COMMUNITYTECH_VERSION', '1.3.0' );
define( 'COMMUNITYTECH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COMMUNITYTECH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'COMMUNITYTECH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// GitHub-based auto-updates.
require_once COMMUNITYTECH_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$communitytech_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Community-Tech-UK/communitytech-plugin/',
    __FILE__,
    'communitytech-plugin'
);
$communitytech_update_checker->setBranch( 'main' );

/**
 * Main plugin class — singleton pattern.
 *
 * Loads modules, registers hooks, and provides a central access point.
 * Each feature lives in its own module under includes/modules/.
 */
final class CommunityTech {

    /** @var self|null */
    private static ?self $instance = null;

    /** @var array<string, object> Loaded module instances, keyed by slug. */
    private array $modules = [];

    /**
     * Get the singleton instance.
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    /**
     * Require core files.
     */
    private function load_dependencies(): void {
        require_once COMMUNITYTECH_PLUGIN_DIR . 'includes/class-module-base.php';
        require_once COMMUNITYTECH_PLUGIN_DIR . 'includes/class-module-loader.php';
    }

    /**
     * Wire up WordPress hooks.
     */
    private function register_hooks(): void {
        // Load modules after all plugins are loaded (so we can check for Elementor, WooCommerce, etc.)
        add_action( 'plugins_loaded', [ $this, 'init_modules' ], 20 );

        // Register REST API routes.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Admin menu (future settings page).
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

        // Activation / deactivation.
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    /**
     * Discover and initialise all modules.
     */
    public function init_modules(): void {
        $loader = new CommunityTech_Module_Loader();
        $this->modules = $loader->load_all();

        /**
         * Fires after all CommunityTech modules have been loaded.
         *
         * @param array<string, object> $modules Loaded module instances.
         */
        do_action( 'communitytech/modules_loaded', $this->modules );
    }

    /**
     * Let each module register its REST routes.
     */
    public function register_rest_routes(): void {
        foreach ( $this->modules as $module ) {
            if ( method_exists( $module, 'register_rest_routes' ) ) {
                $module->register_rest_routes();
            }
        }
    }

    /**
     * Register admin menu pages.
     */
    public function register_admin_menu(): void {
        add_menu_page(
            __( 'CommunityTech', 'communitytech' ),
            __( 'CommunityTech', 'communitytech' ),
            'manage_options',
            'communitytech',
            [ $this, 'render_admin_page' ],
            'dashicons-networking',
            80
        );
    }

    /**
     * Render the top-level admin page.
     */
    public function render_admin_page(): void {
        require_once COMMUNITYTECH_PLUGIN_DIR . 'admin/page-dashboard.php';
    }

    /**
     * Plugin activation.
     */
    public function activate(): void {
        // Store the installed version for future migrations.
        update_option( 'communitytech_version', COMMUNITYTECH_VERSION );

        // Flush rewrite rules so our REST endpoints are immediately available.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Get a loaded module by slug.
     *
     * @param string $slug Module slug (e.g. 'elementor-kit').
     * @return object|null
     */
    public function get_module( string $slug ): ?object {
        return $this->modules[ $slug ] ?? null;
    }

    /**
     * Get all loaded modules.
     *
     * @return array<string, object>
     */
    public function get_modules(): array {
        return $this->modules;
    }
}

/**
 * Returns the main plugin instance.
 *
 * Usage: communitytech()->get_module('elementor-kit');
 */
function communitytech(): CommunityTech {
    return CommunityTech::instance();
}

// Boot the plugin.
communitytech();
