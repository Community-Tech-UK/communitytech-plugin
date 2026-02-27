<?php
/**
 * WP Options Module
 *
 * Provides REST API endpoints to read, update, and delete WordPress options.
 * Restricted to administrators only.
 *
 * Endpoints:
 *   GET  /wp-json/communitytech/v1/options?names=opt1,opt2  → read options
 *   POST /wp-json/communitytech/v1/options                  → update options
 *   DELETE /wp-json/communitytech/v1/options                 → delete options
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommunityTech_Module_Wp_Options extends CommunityTech_Module_Base {

	private const REST_NAMESPACE = 'communitytech/v1';

	public function get_name(): string {
		return 'WP Options';
	}

	public function get_slug(): string {
		return 'wp-options';
	}

	public function is_available(): bool {
		return true; // Always available — core WordPress feature.
	}

	public function init(): void {
		// Routes registered via register_rest_routes().
	}

	public function get_info(): array {
		return array_merge( parent::get_info(), [
			'description' => 'REST API endpoints to read, update, and delete wp_options entries.',
		] );
	}

	// -------------------------------------------------------------------------
	//  REST Route Registration
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		register_rest_route( self::REST_NAMESPACE, '/options', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_options' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'names' => [
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Comma-separated list of option names to read.',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_options' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'options' => [
						'required'    => true,
						'type'        => 'object',
						'description' => 'Object of option_name => value pairs to set.',
					],
				],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_options' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'names' => [
						'required'    => true,
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Array of option names to delete.',
					],
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	//  Permission Callback
	// -------------------------------------------------------------------------

	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	//  Endpoint Callbacks
	// -------------------------------------------------------------------------

	/**
	 * GET /options?names=opt1,opt2
	 */
	public function get_options( WP_REST_Request $request ): WP_REST_Response {
		$names  = array_map( 'trim', explode( ',', $request->get_param( 'names' ) ) );
		$result = [];

		foreach ( $names as $name ) {
			$value = get_option( $name, null );
			$result[ $name ] = [
				'exists' => $value !== null,
				'value'  => $value,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /options  { "options": { "key": "value", ... } }
	 */
	public function update_options( WP_REST_Request $request ): WP_REST_Response {
		$options = $request->get_param( 'options' );
		$result  = [];

		foreach ( $options as $name => $value ) {
			$name = sanitize_text_field( $name );
			$updated = update_option( $name, $value );
			$result[ $name ] = [
				'updated' => $updated,
				'value'   => get_option( $name ),
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * DELETE /options  { "names": ["opt1", "opt2"] }
	 */
	public function delete_options( WP_REST_Request $request ): WP_REST_Response {
		$names  = $request->get_param( 'names' );
		$result = [];

		foreach ( $names as $name ) {
			$name = sanitize_text_field( $name );
			$existed = get_option( $name, null ) !== null;
			$deleted = delete_option( $name );
			$result[ $name ] = [
				'existed' => $existed,
				'deleted' => $deleted,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}
}
