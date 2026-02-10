<?php
/**
 * Elementor Render Rebuild Module
 *
 * Provides a REST API endpoint to force Elementor to rebuild the rendered HTML
 * for a page. This is necessary because updating _elementor_data via REST API
 * saves the data correctly but the PHP frontend renderer doesn't pick up the
 * changes until the document is re-saved through Elementor's save pipeline.
 *
 * This endpoint loads the Elementor document and calls its save() method with
 * the existing element data, which triggers all rendering hooks, regenerates
 * post_content (the rendered HTML fallback), and rebuilds CSS.
 *
 * Endpoints:
 *   POST /wp-json/communitytech/v1/elementor/render/rebuild → rebuild HTML for a page
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommunityTech_Module_Elementor_Render extends CommunityTech_Module_Base {

	private const REST_NAMESPACE = 'communitytech/v1';

	public function get_name(): string {
		return 'Elementor Render Rebuild';
	}

	public function get_slug(): string {
		return 'elementor-render';
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
			'description' => 'Provides REST API endpoint to rebuild Elementor rendered HTML after updating page data via REST API.',
		] );
	}

	// -------------------------------------------------------------------------
	//  REST Route Registration
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		register_rest_route( self::REST_NAMESPACE, '/elementor/render/rebuild', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rebuild_render' ],
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'post_id' => [
					'required'          => true,
					'type'              => 'integer',
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	//  Permission Callback
	// -------------------------------------------------------------------------

	/**
	 * Only admins can trigger render rebuilds.
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	//  REST Endpoint Callback
	// -------------------------------------------------------------------------

	/**
	 * POST /elementor/render/rebuild
	 *
	 * Forces Elementor to re-save a document, which rebuilds the rendered HTML
	 * in post_content and regenerates CSS.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function rebuild_render( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );

		// Validate post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				sprintf( 'Post with ID %d does not exist.', $post_id ),
				[ 'status' => 404 ]
			);
		}

		// Check if post has Elementor data.
		$elementor_data_raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data_raw ) ) {
			return new WP_Error(
				'not_elementor_page',
				sprintf( 'Post %d does not have Elementor data (_elementor_data meta).', $post_id ),
				[ 'status' => 400 ]
			);
		}

		// Ensure Elementor's Plugin class is available.
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error(
				'elementor_not_loaded',
				'Elementor Plugin class not found. Is Elementor properly installed?',
				[ 'status' => 500 ]
			);
		}

		try {
			// Get the Elementor document for this post.
			$document = \Elementor\Plugin::$instance->documents->get( $post_id );

			if ( ! $document ) {
				return new WP_Error(
					'document_not_found',
					sprintf( 'Could not load Elementor document for post %d.', $post_id ),
					[ 'status' => 500 ]
				);
			}

			// Decode the current element data from the database.
			$elements = json_decode( $elementor_data_raw, true );

			if ( ! is_array( $elements ) ) {
				return new WP_Error(
					'invalid_elementor_data',
					sprintf( 'The _elementor_data for post %d is not valid JSON.', $post_id ),
					[ 'status' => 500 ]
				);
			}

			// Call Elementor's document save with the existing data.
			// This triggers the full save pipeline:
			//   1. Processes and sanitizes elements
			//   2. Updates _elementor_data with processed data
			//   3. Generates plain-text HTML and stores in post_content
			//   4. Fires elementor/document/after_save hook
			//   5. Triggers CSS regeneration via hooks
			$document->save( [
				'elements' => $elements,
			] );

			return new WP_REST_Response( [
				'success'    => true,
				'message'    => 'Elementor render rebuilt successfully.',
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
			], 200 );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'render_rebuild_failed',
				sprintf( 'Failed to rebuild render: %s', $e->getMessage() ),
				[ 'status' => 500 ]
			);
		}
	}
}
