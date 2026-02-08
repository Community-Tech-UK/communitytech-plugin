<?php
/**
 * Module auto-discovery and loading.
 *
 * Scans includes/modules/ for subdirectories containing a class-module.php file,
 * instantiates each module, checks availability, and initialises it.
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CommunityTech_Module_Loader {

    /** @var string Base path to the modules directory. */
    private string $modules_dir;

    public function __construct() {
        $this->modules_dir = COMMUNITYTECH_PLUGIN_DIR . 'includes/modules/';
    }

    /**
     * Discover, validate, and initialise all modules.
     *
     * @return array<string, CommunityTech_Module_Base> Keyed by module slug.
     */
    public function load_all(): array {
        $modules = [];

        if ( ! is_dir( $this->modules_dir ) ) {
            return $modules;
        }

        $dirs = glob( $this->modules_dir . '*', GLOB_ONLYDIR );

        if ( ! $dirs ) {
            return $modules;
        }

        foreach ( $dirs as $dir ) {
            $module_file = $dir . '/class-module.php';

            if ( ! file_exists( $module_file ) ) {
                continue;
            }

            require_once $module_file;

            // Convention: directory name maps to class CommunityTech_Module_{PascalSlug}
            // e.g. elementor-kit  →  CommunityTech_Module_Elementor_Kit
            $slug       = basename( $dir );
            $class_name = $this->slug_to_class_name( $slug );

            if ( ! class_exists( $class_name ) ) {
                error_log( "[CommunityTech] Module class {$class_name} not found in {$module_file}" );
                continue;
            }

            /** @var CommunityTech_Module_Base $instance */
            $instance = new $class_name();

            if ( ! $instance instanceof CommunityTech_Module_Base ) {
                error_log( "[CommunityTech] Module {$class_name} does not extend CommunityTech_Module_Base" );
                continue;
            }

            if ( ! $instance->is_available() ) {
                error_log( "[CommunityTech] Module '{$slug}' skipped — dependencies not met." );
                continue;
            }

            $instance->init();
            $modules[ $instance->get_slug() ] = $instance;
        }

        return $modules;
    }

    /**
     * Convert a kebab-case slug to the expected class name.
     *
     * elementor-kit → CommunityTech_Module_Elementor_Kit
     */
    private function slug_to_class_name( string $slug ): string {
        $parts = array_map( 'ucfirst', explode( '-', $slug ) );
        return 'CommunityTech_Module_' . implode( '_', $parts );
    }
}
