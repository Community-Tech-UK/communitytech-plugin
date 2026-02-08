<?php
/**
 * Elementor Kit Settings Module
 *
 * Exposes the active Elementor Kit's global settings (colors, fonts, typography,
 * theme style) via REST API endpoints so the MCP server can read and write them.
 *
 * Endpoints:
 *   GET  /wp-json/communitytech/v1/elementor/kit              → full kit settings
 *   POST /wp-json/communitytech/v1/elementor/kit              → update full kit settings
 *   GET  /wp-json/communitytech/v1/elementor/kit/colors       → global colors only
 *   POST /wp-json/communitytech/v1/elementor/kit/colors       → update global colors
 *   GET  /wp-json/communitytech/v1/elementor/kit/typography   → global typography only
 *   POST /wp-json/communitytech/v1/elementor/kit/typography   → update global typography
 *   GET  /wp-json/communitytech/v1/elementor/kit/theme-style  → theme style settings
 *   POST /wp-json/communitytech/v1/elementor/kit/theme-style  → update theme style
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CommunityTech_Module_Elementor_Kit extends CommunityTech_Module_Base {

    private const REST_NAMESPACE = 'communitytech/v1';

    /**
     * Keys in _elementor_page_settings that represent global colors.
     */
    private const COLOR_KEYS = [
        'system_colors',
        'custom_colors',
    ];

    /**
     * Keys in _elementor_page_settings that represent global typography.
     */
    private const TYPOGRAPHY_KEYS = [
        'system_typography',
        'custom_typography',
    ];

    /**
     * Keys in _elementor_page_settings that represent theme style settings.
     * This is not exhaustive — we'll also return anything that's not colors/typography.
     */
    private const THEME_STYLE_KEYS = [
        // Body & typography
        'body_color',
        'body_typography_typography',
        'body_typography_font_family',
        'body_typography_font_size',
        'body_typography_font_weight',
        'body_typography_line_height',
        'body_typography_letter_spacing',
        // Headings
        'h1_color', 'h1_typography_typography', 'h1_typography_font_family', 'h1_typography_font_size', 'h1_typography_font_weight',
        'h2_color', 'h2_typography_typography', 'h2_typography_font_family', 'h2_typography_font_size', 'h2_typography_font_weight',
        'h3_color', 'h3_typography_typography', 'h3_typography_font_family', 'h3_typography_font_size', 'h3_typography_font_weight',
        'h4_color', 'h4_typography_typography', 'h4_typography_font_family', 'h4_typography_font_size', 'h4_typography_font_weight',
        'h5_color', 'h5_typography_typography', 'h5_typography_font_family', 'h5_typography_font_size', 'h5_typography_font_weight',
        'h6_color', 'h6_typography_typography', 'h6_typography_font_family', 'h6_typography_font_size', 'h6_typography_font_weight',
        // Links
        'link_normal_color',
        'link_hover_color',
        // Buttons
        'button_typography_typography',
        'button_typography_font_family',
        'button_typography_font_size',
        'button_typography_font_weight',
        'button_text_color',
        'button_background_color',
        'button_hover_text_color',
        'button_hover_background_color',
        'button_border_border',
        'button_border_width',
        'button_border_color',
        'button_border_radius',
        'button_padding',
        // Form fields
        'form_field_typography_typography',
        'form_field_typography_font_family',
        'form_field_text_color',
        'form_field_background_color',
        'form_field_border_border',
        'form_field_border_width',
        'form_field_border_color',
        'form_field_border_radius',
        'form_label_color',
        // Images
        'image_border_border',
        'image_border_width',
        'image_border_color',
        'image_border_radius',
        'image_opacity',
        'image_opacity_hover',
        'image_hover_border_color',
        'image_hover_css_filters_css_filter',
        // Layout
        'container_width',
        'page_title_selector',
        'stretched_section_container',
        'default_generic_fonts',
        'viewport_md',
        'viewport_lg',
        // Lightbox
        'global_image_lightbox',
        'lightbox_color',
        'lightbox_ui_color',
        'lightbox_ui_color_hover',
    ];

    public function get_name(): string {
        return 'Elementor Kit Settings';
    }

    public function get_slug(): string {
        return 'elementor-kit';
    }

    /**
     * This module requires Elementor to be active.
     */
    public function is_available(): bool {
        return defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' );
    }

    public function init(): void {
        // Nothing extra needed at init time — routes are registered via register_rest_routes().
    }

    public function get_info(): array {
        return array_merge( parent::get_info(), [
            'description' => 'Exposes Elementor global colors, typography, and theme style via REST API for MCP integration.',
        ] );
    }

    // -------------------------------------------------------------------------
    //  REST Route Registration
    // -------------------------------------------------------------------------

    public function register_rest_routes(): void {
        // Full kit settings
        register_rest_route( self::REST_NAMESPACE, '/elementor/kit', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_kit_settings' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_kit_settings' ],
                'permission_callback' => [ $this, 'check_edit_permissions' ],
            ],
        ] );

        // Global colors
        register_rest_route( self::REST_NAMESPACE, '/elementor/kit/colors', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_kit_colors' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_kit_colors' ],
                'permission_callback' => [ $this, 'check_edit_permissions' ],
            ],
        ] );

        // Global typography
        register_rest_route( self::REST_NAMESPACE, '/elementor/kit/typography', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_kit_typography' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_kit_typography' ],
                'permission_callback' => [ $this, 'check_edit_permissions' ],
            ],
        ] );

        // Theme style
        register_rest_route( self::REST_NAMESPACE, '/elementor/kit/theme-style', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_kit_theme_style' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_kit_theme_style' ],
                'permission_callback' => [ $this, 'check_edit_permissions' ],
            ],
        ] );

        // CSS variables map (read-only convenience endpoint)
        register_rest_route( self::REST_NAMESPACE, '/elementor/kit/css-variables', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_css_variables_map' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    //  Permission Callbacks
    // -------------------------------------------------------------------------

    /**
     * Read access: must be able to edit posts (i.e. authenticated contributor+).
     */
    public function check_permissions(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Write access: must be able to manage options (admin).
     */
    public function check_edit_permissions(): bool {
        return current_user_can( 'manage_options' );
    }

    // -------------------------------------------------------------------------
    //  Kit Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Get the active Kit post ID.
     */
    private function get_kit_id(): ?int {
        $kit_id = get_option( 'elementor_active_kit' );

        if ( ! $kit_id ) {
            return null;
        }

        return (int) $kit_id;
    }

    /**
     * Get the full page_settings array from the active Kit.
     *
     * @return array|WP_Error
     */
    private function get_page_settings() {
        $kit_id = $this->get_kit_id();

        if ( ! $kit_id ) {
            return new WP_Error(
                'no_active_kit',
                'No active Elementor Kit found. Is Elementor installed and configured?',
                [ 'status' => 404 ]
            );
        }

        // Elementor stores kit settings in _elementor_page_settings
        $page_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

        if ( empty( $page_settings ) ) {
            // Fallback: try reading from _elementor_data (some versions store settings differently)
            $elementor_data = get_post_meta( $kit_id, '_elementor_data', true );

            if ( $elementor_data ) {
                $parsed = json_decode( $elementor_data, true );

                // The kit data structure may have settings at the top level or nested
                if ( is_array( $parsed ) && isset( $parsed[0]['settings'] ) ) {
                    $page_settings = $parsed[0]['settings'];
                }
            }
        }

        if ( empty( $page_settings ) || ! is_array( $page_settings ) ) {
            return new WP_Error(
                'empty_kit_settings',
                'The active Elementor Kit has no page settings configured yet.',
                [ 'status' => 404 ]
            );
        }

        return $page_settings;
    }

    /**
     * Save page_settings back to the active Kit.
     *
     * @param array $settings Full page_settings array to save.
     * @return true|WP_Error
     */
    private function save_page_settings( array $settings ) {
        $kit_id = $this->get_kit_id();

        if ( ! $kit_id ) {
            return new WP_Error(
                'no_active_kit',
                'No active Elementor Kit found.',
                [ 'status' => 404 ]
            );
        }

        // Update the page settings meta.
        update_post_meta( $kit_id, '_elementor_page_settings', $settings );

        // Clear Elementor's CSS cache so changes take effect.
        $this->clear_elementor_cache( $kit_id );

        return true;
    }

    /**
     * Clear Elementor CSS cache for the Kit (and optionally globally).
     */
    private function clear_elementor_cache( int $kit_id ): void {
        // Delete the Kit's CSS file reference so it regenerates.
        delete_post_meta( $kit_id, '_elementor_css' );

        // If Elementor's Plugin class is available, use the official method.
        if ( class_exists( '\Elementor\Plugin' ) ) {
            try {
                // Clear the files manager cache.
                if ( method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
                    \Elementor\Plugin::$instance->files_manager->clear_cache();
                }
            } catch ( \Throwable $e ) {
                error_log( '[CommunityTech] Failed to clear Elementor cache: ' . $e->getMessage() );
            }
        }

        // Bump the Elementor CSS timestamp to force regeneration site-wide.
        update_option( 'elementor-custom-breakpoints-files', '' );
    }

    /**
     * Extract a subset of keys from the page settings.
     */
    private function extract_keys( array $settings, array $keys ): array {
        $result = [];
        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $settings ) ) {
                $result[ $key ] = $settings[ $key ];
            }
        }
        return $result;
    }

    /**
     * Merge updated keys into existing settings and save.
     *
     * @param array $updates  Key-value pairs to update.
     * @param array $allowed  Which keys are allowed to be updated (empty = allow all).
     * @return WP_REST_Response|WP_Error
     */
    private function merge_and_save( array $updates, array $allowed = [] ) {
        $settings = $this->get_page_settings();

        if ( is_wp_error( $settings ) ) {
            // If settings don't exist yet, start fresh.
            if ( $settings->get_error_code() === 'empty_kit_settings' ) {
                $settings = [];
            } else {
                return $settings;
            }
        }

        // Filter to allowed keys if specified.
        if ( ! empty( $allowed ) ) {
            $updates = array_intersect_key( $updates, array_flip( $allowed ) );
        }

        $merged = array_merge( $settings, $updates );
        $result = $this->save_page_settings( $merged );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( [
            'success'  => true,
            'message'  => 'Kit settings updated successfully.',
            'updated'  => array_keys( $updates ),
            'kit_id'   => $this->get_kit_id(),
        ], 200 );
    }

    // -------------------------------------------------------------------------
    //  REST Endpoint Callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /elementor/kit — full kit settings.
     */
    public function get_kit_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $settings = $this->get_page_settings();

        if ( is_wp_error( $settings ) ) {
            return $settings;
        }

        return new WP_REST_Response( [
            'kit_id'   => $this->get_kit_id(),
            'settings' => $settings,
        ], 200 );
    }

    /**
     * POST /elementor/kit — update full kit settings (merge).
     */
    public function update_kit_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $body = $request->get_json_params();

        if ( empty( $body ) || ! is_array( $body ) ) {
            return new WP_Error(
                'invalid_body',
                'Request body must be a JSON object with settings to update.',
                [ 'status' => 400 ]
            );
        }

        return $this->merge_and_save( $body );
    }

    /**
     * GET /elementor/kit/colors — global colors only.
     */
    public function get_kit_colors( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $settings = $this->get_page_settings();

        if ( is_wp_error( $settings ) ) {
            return $settings;
        }

        $colors = $this->extract_keys( $settings, self::COLOR_KEYS );

        // Also build the CSS variable map for convenience.
        $css_vars = $this->build_color_css_map( $colors );

        return new WP_REST_Response( [
            'kit_id'        => $this->get_kit_id(),
            'colors'        => $colors,
            'css_variables'  => $css_vars,
        ], 200 );
    }

    /**
     * POST /elementor/kit/colors — update global colors.
     */
    public function update_kit_colors( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $body = $request->get_json_params();

        if ( empty( $body ) || ! is_array( $body ) ) {
            return new WP_Error(
                'invalid_body',
                'Request body must be a JSON object with color settings.',
                [ 'status' => 400 ]
            );
        }

        return $this->merge_and_save( $body, self::COLOR_KEYS );
    }

    /**
     * GET /elementor/kit/typography — global typography only.
     */
    public function get_kit_typography( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $settings = $this->get_page_settings();

        if ( is_wp_error( $settings ) ) {
            return $settings;
        }

        return new WP_REST_Response( [
            'kit_id'     => $this->get_kit_id(),
            'typography' => $this->extract_keys( $settings, self::TYPOGRAPHY_KEYS ),
        ], 200 );
    }

    /**
     * POST /elementor/kit/typography — update global typography.
     */
    public function update_kit_typography( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $body = $request->get_json_params();

        if ( empty( $body ) || ! is_array( $body ) ) {
            return new WP_Error(
                'invalid_body',
                'Request body must be a JSON object with typography settings.',
                [ 'status' => 400 ]
            );
        }

        return $this->merge_and_save( $body, self::TYPOGRAPHY_KEYS );
    }

    /**
     * GET /elementor/kit/theme-style — theme style settings.
     */
    public function get_kit_theme_style( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $settings = $this->get_page_settings();

        if ( is_wp_error( $settings ) ) {
            return $settings;
        }

        return new WP_REST_Response( [
            'kit_id'      => $this->get_kit_id(),
            'theme_style' => $this->extract_keys( $settings, self::THEME_STYLE_KEYS ),
        ], 200 );
    }

    /**
     * POST /elementor/kit/theme-style — update theme style settings.
     */
    public function update_kit_theme_style( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $body = $request->get_json_params();

        if ( empty( $body ) || ! is_array( $body ) ) {
            return new WP_Error(
                'invalid_body',
                'Request body must be a JSON object with theme style settings.',
                [ 'status' => 400 ]
            );
        }

        return $this->merge_and_save( $body, self::THEME_STYLE_KEYS );
    }

    /**
     * GET /elementor/kit/css-variables — read-only map of CSS variable names to values.
     */
    public function get_css_variables_map( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $settings = $this->get_page_settings();

        if ( is_wp_error( $settings ) ) {
            return $settings;
        }

        $colors     = $this->extract_keys( $settings, self::COLOR_KEYS );
        $typography = $this->extract_keys( $settings, self::TYPOGRAPHY_KEYS );

        return new WP_REST_Response( [
            'kit_id'     => $this->get_kit_id(),
            'colors'     => $this->build_color_css_map( $colors ),
            'typography' => $this->build_typography_css_map( $typography ),
        ], 200 );
    }

    // -------------------------------------------------------------------------
    //  CSS Variable Map Builders
    // -------------------------------------------------------------------------

    /**
     * Build a map of CSS variable name → hex value from color settings.
     *
     * System colors use readable names:  --e-global-color-primary
     * Custom colors use their _id field: --e-global-color-{_id}
     */
    private function build_color_css_map( array $colors ): array {
        $map = [];

        if ( ! empty( $colors['system_colors'] ) && is_array( $colors['system_colors'] ) ) {
            foreach ( $colors['system_colors'] as $color ) {
                if ( isset( $color['_id'], $color['color'] ) ) {
                    $var_name = '--e-global-color-' . $color['_id'];
                    $map[ $var_name ] = $color['color'];
                }
            }
        }

        if ( ! empty( $colors['custom_colors'] ) && is_array( $colors['custom_colors'] ) ) {
            foreach ( $colors['custom_colors'] as $color ) {
                if ( isset( $color['_id'], $color['color'] ) ) {
                    $var_name = '--e-global-color-' . $color['_id'];
                    $map[ $var_name ] = $color['color'];
                }
            }
        }

        return $map;
    }

    /**
     * Build a map of CSS variable name → font family from typography settings.
     */
    private function build_typography_css_map( array $typography ): array {
        $map = [];

        foreach ( [ 'system_typography', 'custom_typography' ] as $key ) {
            if ( ! empty( $typography[ $key ] ) && is_array( $typography[ $key ] ) ) {
                foreach ( $typography[ $key ] as $typo ) {
                    if ( isset( $typo['_id'] ) ) {
                        $base_var = '--e-global-typography-' . $typo['_id'];

                        if ( isset( $typo['typography_font_family'] ) ) {
                            $map[ $base_var . '-font-family' ] = $typo['typography_font_family'];
                        }
                        if ( isset( $typo['typography_font_size'] ) ) {
                            $map[ $base_var . '-font-size' ] = $typo['typography_font_size'];
                        }
                        if ( isset( $typo['typography_font_weight'] ) ) {
                            $map[ $base_var . '-font-weight' ] = $typo['typography_font_weight'];
                        }
                        if ( isset( $typo['typography_line_height'] ) ) {
                            $map[ $base_var . '-line-height' ] = $typo['typography_line_height'];
                        }
                        if ( isset( $typo['typography_letter_spacing'] ) ) {
                            $map[ $base_var . '-letter-spacing' ] = $typo['typography_letter_spacing'];
                        }
                    }
                }
            }
        }

        return $map;
    }
}
