<?php
/**
 * Elementor Widget Registry Module
 *
 * Provides a REST API endpoint to discover all registered Elementor widgets,
 * their categories, controls, and content field mappings. This helps MCP
 * tools understand what widgets are available and how to populate them.
 *
 * Endpoints:
 *   GET /wp-json/communitytech/v1/elementor/widgets           → list all widgets
 *   GET /wp-json/communitytech/v1/elementor/widgets/<name>    → single widget detail
 *   GET /wp-json/communitytech/v1/elementor/widgets/categories → widget categories
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommunityTech_Module_Elementor_Widgets extends CommunityTech_Module_Base {

	private const REST_NAMESPACE = 'communitytech/v1';

	public function get_name(): string {
		return 'Elementor Widget Registry';
	}

	public function get_slug(): string {
		return 'elementor-widgets';
	}

	/**
	 * This module requires Elementor to be active.
	 */
	public function is_available(): bool {
		return defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' );
	}

	public function init(): void {
		// Nothing extra needed — routes are registered via register_rest_routes().
	}

	public function get_info(): array {
		return array_merge( parent::get_info(), [
			'description' => 'Exposes Elementor widget registry via REST API for MCP tool discovery.',
		] );
	}

	// -------------------------------------------------------------------------
	//  REST Route Registration
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		// GET /elementor/widgets — list all registered widgets.
		register_rest_route( self::REST_NAMESPACE, '/elementor/widgets', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_widgets' ],
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'category' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Filter widgets by category slug.',
				],
				'detail' => [
					'required'          => false,
					'type'              => 'string',
					'default'           => 'summary',
					'enum'              => [ 'summary', 'full' ],
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Level of detail: "summary" (name, title, icon, category) or "full" (includes controls).',
				],
			],
		] );

		// GET /elementor/widgets/categories — list widget categories.
		register_rest_route( self::REST_NAMESPACE, '/elementor/widgets/categories', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_categories' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		// GET /elementor/widgets/<name> — single widget detail.
		register_rest_route( self::REST_NAMESPACE, '/elementor/widgets/(?P<widget_name>[a-zA-Z0-9_-]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_single_widget' ],
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'widget_name' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	//  Permission Callback
	// -------------------------------------------------------------------------

	/**
	 * Read access requires edit_posts capability (contributors+).
	 */
	public function check_permissions(): bool {
		return current_user_can( 'edit_posts' );
	}

	// -------------------------------------------------------------------------
	//  REST Endpoint Callbacks
	// -------------------------------------------------------------------------

	/**
	 * GET /elementor/widgets
	 *
	 * Returns all registered Elementor widgets.
	 */
	public function get_widgets( WP_REST_Request $request ) {
		$widgets_manager = $this->get_widgets_manager();
		if ( is_wp_error( $widgets_manager ) ) {
			return $widgets_manager;
		}

		$widget_types   = $widgets_manager->get_widget_types();
		$category_filter = $request->get_param( 'category' );
		$detail_level    = $request->get_param( 'detail' ) ?: 'summary';
		$result          = [];

		foreach ( $widget_types as $name => $widget ) {
			$categories = $this->get_widget_categories( $widget );

			// Filter by category if specified.
			if ( $category_filter && ! in_array( $category_filter, $categories, true ) ) {
				continue;
			}

			if ( $detail_level === 'full' ) {
				$result[ $name ] = $this->build_full_widget_info( $name, $widget );
			} else {
				$result[ $name ] = $this->build_summary_widget_info( $name, $widget );
			}
		}

		return new WP_REST_Response( [
			'widgets' => $result,
			'count'   => count( $result ),
			'filter'  => $category_filter ?: 'none',
		], 200 );
	}

	/**
	 * GET /elementor/widgets/categories
	 *
	 * Returns all registered widget categories.
	 */
	public function get_categories( WP_REST_Request $request ) {
		$widgets_manager = $this->get_widgets_manager();
		if ( is_wp_error( $widgets_manager ) ) {
			return $widgets_manager;
		}

		$categories_manager = \Elementor\Plugin::$instance->elements_manager;
		$categories         = [];

		if ( method_exists( $categories_manager, 'get_categories' ) ) {
			$raw = $categories_manager->get_categories();
			foreach ( $raw as $slug => $cat ) {
				$categories[ $slug ] = [
					'title' => $cat['title'] ?? $slug,
					'icon'  => $cat['icon'] ?? null,
				];
			}
		}

		// Count widgets per category.
		$widget_types = $widgets_manager->get_widget_types();
		$counts       = [];
		foreach ( $widget_types as $widget ) {
			foreach ( $this->get_widget_categories( $widget ) as $cat_slug ) {
				$counts[ $cat_slug ] = ( $counts[ $cat_slug ] ?? 0 ) + 1;
			}
		}

		// Merge counts into categories.
		foreach ( $categories as $slug => &$cat ) {
			$cat['widget_count'] = $counts[ $slug ] ?? 0;
		}

		return new WP_REST_Response( [
			'categories' => $categories,
			'count'      => count( $categories ),
		], 200 );
	}

	/**
	 * GET /elementor/widgets/<widget_name>
	 *
	 * Returns detailed info about a single widget.
	 */
	public function get_single_widget( WP_REST_Request $request ) {
		$widgets_manager = $this->get_widgets_manager();
		if ( is_wp_error( $widgets_manager ) ) {
			return $widgets_manager;
		}

		$widget_name = $request->get_param( 'widget_name' );
		$widget_types = $widgets_manager->get_widget_types();

		if ( ! isset( $widget_types[ $widget_name ] ) ) {
			return new WP_Error(
				'widget_not_found',
				sprintf( 'Widget "%s" is not registered.', $widget_name ),
				[ 'status' => 404 ]
			);
		}

		$widget = $widget_types[ $widget_name ];

		return new WP_REST_Response(
			$this->build_full_widget_info( $widget_name, $widget ),
			200
		);
	}

	// -------------------------------------------------------------------------
	//  Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the Elementor widgets manager instance.
	 *
	 * @return \Elementor\Widgets_Manager|WP_Error
	 */
	private function get_widgets_manager() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return new WP_Error(
				'elementor_not_ready',
				'Elementor widgets manager is not available. Is Elementor properly installed and active?',
				[ 'status' => 500 ]
			);
		}

		return \Elementor\Plugin::$instance->widgets_manager;
	}

	/**
	 * Get categories for a widget.
	 *
	 * @param \Elementor\Widget_Base $widget
	 * @return array
	 */
	private function get_widget_categories( $widget ): array {
		if ( method_exists( $widget, 'get_categories' ) ) {
			return (array) $widget->get_categories();
		}
		return [ 'general' ];
	}

	/**
	 * Build summary info for a widget.
	 */
	private function build_summary_widget_info( string $name, $widget ): array {
		return [
			'name'       => $name,
			'title'      => method_exists( $widget, 'get_title' ) ? $widget->get_title() : $name,
			'icon'       => method_exists( $widget, 'get_icon' ) ? $widget->get_icon() : null,
			'categories' => $this->get_widget_categories( $widget ),
			'keywords'   => method_exists( $widget, 'get_keywords' ) ? $widget->get_keywords() : [],
		];
	}

	/**
	 * Build full detail info for a widget, including controls.
	 */
	private function build_full_widget_info( string $name, $widget ): array {
		$info = $this->build_summary_widget_info( $name, $widget );

		// Extract controls (content fields the MCP can populate).
		$controls = $this->extract_widget_controls( $widget );
		$info['controls']          = $controls;
		$info['content_fields']    = $this->identify_content_fields( $controls );
		$info['style_fields']      = $this->identify_style_fields( $controls );

		return $info;
	}

	/**
	 * Extract controls from a widget.
	 *
	 * Controls define what fields a widget accepts (text content, URLs, colors, etc.).
	 */
	private function extract_widget_controls( $widget ): array {
		$controls = [];

		// Ensure controls are registered.
		if ( method_exists( $widget, 'get_stack' ) ) {
			$stack = $widget->get_stack();
			if ( ! empty( $stack['controls'] ) ) {
				foreach ( $stack['controls'] as $control_id => $control ) {
					$controls[ $control_id ] = [
						'type'        => $control['type'] ?? 'unknown',
						'label'       => $control['label'] ?? $control_id,
						'default'     => $control['default'] ?? null,
						'section'     => $control['section'] ?? null,
						'tab'         => $control['tab'] ?? null,
						'description' => $control['description'] ?? null,
						'options'     => $control['options'] ?? null,
						'condition'   => $control['condition'] ?? null,
					];

					// Remove null values for cleaner output.
					$controls[ $control_id ] = array_filter(
						$controls[ $control_id ],
						fn( $v ) => $v !== null
					);
				}
			}
		}

		return $controls;
	}

	/**
	 * Identify which controls are content fields (text, editor, URL, media, etc.).
	 *
	 * These are the fields MCP tools should target when creating/editing widgets.
	 */
	private function identify_content_fields( array $controls ): array {
		$content_types = [
			'text', 'textarea', 'wysiwyg', 'url', 'media', 'gallery',
			'select', 'switcher', 'number', 'code', 'icon', 'icons',
			'repeater', 'image_dimensions',
		];

		$content_fields = [];
		foreach ( $controls as $id => $control ) {
			$type = $control['type'] ?? '';
			if ( in_array( $type, $content_types, true ) ) {
				$content_fields[ $id ] = [
					'type'    => $type,
					'label'   => $control['label'] ?? $id,
					'default' => $control['default'] ?? null,
				];
			}
		}

		return $content_fields;
	}

	/**
	 * Identify which controls are style-related.
	 */
	private function identify_style_fields( array $controls ): array {
		$style_types = [
			'color', 'slider', 'dimensions', 'typography', 'text_shadow',
			'box_shadow', 'border', 'background', 'hover_animation',
			'entrance_animation',
		];

		$style_fields = [];
		foreach ( $controls as $id => $control ) {
			$type = $control['type'] ?? '';
			if ( in_array( $type, $style_types, true ) ) {
				$style_fields[ $id ] = [
					'type'  => $type,
					'label' => $control['label'] ?? $id,
				];
			}
		}

		return $style_fields;
	}
}
