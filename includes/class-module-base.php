<?php
/**
 * Abstract base class for all CommunityTech modules.
 *
 * Each module must extend this class and implement the required methods.
 * Modules live in includes/modules/{slug}/class-module.php.
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class CommunityTech_Module_Base {

    /**
     * Human-readable module name.
     */
    abstract public function get_name(): string;

    /**
     * Unique slug for this module (used as array key, REST namespace segment, etc.).
     */
    abstract public function get_slug(): string;

    /**
     * Check whether this module's dependencies are met.
     *
     * For example, the Elementor Kit module should return false
     * if the Elementor plugin is not active.
     */
    abstract public function is_available(): bool;

    /**
     * Called once during plugins_loaded if is_available() returns true.
     * Use this to register WordPress hooks, filters, etc.
     */
    abstract public function init(): void;

    /**
     * Register REST API routes for this module.
     * Called during rest_api_init.
     */
    public function register_rest_routes(): void {
        // Override in subclass if the module exposes REST endpoints.
    }

    /**
     * Info array surfaced on the admin dashboard.
     *
     * @return array{ description: string, status: string }
     */
    public function get_info(): array {
        return [
            'name'        => $this->get_name(),
            'slug'        => $this->get_slug(),
            'available'   => $this->is_available(),
            'description' => '',
        ];
    }
}
