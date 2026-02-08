<?php
/**
 * Elementor CSS Manager Module
 *
 * Provides a REST API endpoint to regenerate Elementor CSS files for pages.
 * This is necessary because updating _elementor_data via REST API doesn't
 * automatically regenerate the cached CSS file (post-{id}.css).
 *
 * Endpoints:
 *   POST /wp-json/communitytech/v1/elementor/css/regenerate → regenerate CSS for a page
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommunityTech_Module_Elementor_Css extends CommunityTech_Module_Base {

	private const REST_NAMESPACE = 'communitytech/v1';

	public function get_name(): string {
		return 'Elementor CSS Manager';
	}

	public function get_slug(): string {
		return 'elementor-css';
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
			'description' => 'Provides REST API endpoint to regenerate Elementor CSS files after updating page data.',
		] );
	}

	// -------------------------------------------------------------------------
	//  REST Route Registration
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		register_rest_route( self::REST_NAMESPACE, '/elementor/css/regenerate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'regenerate_css' ],
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
	 * Only admins can regenerate CSS files.
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	//  REST Endpoint Callback
	// -------------------------------------------------------------------------

	/**
	 * POST /elementor/css/regenerate
	 *
	 * Regenerates the Elementor CSS file for a given post ID.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function regenerate_css( WP_REST_Request $request ) {
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
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			return new WP_Error(
				'not_elementor_page',
				sprintf( 'Post %d does not have Elementor data (_elementor_data meta).', $post_id ),
				[ 'status' => 400 ]
			);
		}

		// Clear the cached CSS meta to force regeneration.
		delete_post_meta( $post_id, '_elementor_css' );

		// Use Elementor's CSS manager to regenerate the file.
		if ( ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			return new WP_Error(
				'elementor_css_class_missing',
				'Elementor CSS Post class not found. Is Elementor properly installed?',
				[ 'status' => 500 ]
			);
		}

		try {
			$css_file = new \Elementor\Core\Files\CSS\Post( $post_id );
			$css_file->update();

			// Get info about the generated file.
			$css_file_path = $css_file->get_file_path();
			$css_file_url  = $css_file->get_url();
			$css_file_time = file_exists( $css_file_path ) ? filemtime( $css_file_path ) : null;

			return new WP_REST_Response( [
				'success'     => true,
				'message'     => 'CSS file regenerated successfully.',
				'post_id'     => $post_id,
				'post_title'  => $post->post_title,
				'css_file'    => [
					'path'      => $css_file_path,
					'url'       => $css_file_url,
					'timestamp' => $css_file_time,
				],
			], 200 );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'css_regeneration_failed',
				sprintf( 'Failed to regenerate CSS: %s', $e->getMessage() ),
				[ 'status' => 500 ]
			);
		}
	}
}
