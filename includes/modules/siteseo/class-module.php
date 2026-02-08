<?php
/**
 * SiteSEO Module
 *
 * Provides REST API endpoints to manage SiteSEO meta fields for posts and pages,
 * audit SEO completeness across content, and read global SiteSEO settings.
 *
 * Endpoints:
 *   GET  /wp-json/communitytech/v1/siteseo/post/{id}    → get SEO meta for a post
 *   POST /wp-json/communitytech/v1/siteseo/post/{id}    → update SEO meta for a post
 *   GET  /wp-json/communitytech/v1/siteseo/audit        → audit SEO completeness
 *   GET  /wp-json/communitytech/v1/siteseo/settings     → read global SiteSEO config
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommunityTech_Module_Siteseo extends CommunityTech_Module_Base {

	private const REST_NAMESPACE = 'communitytech/v1';

	/**
	 * Meta key mapping: friendly name => actual meta key
	 */
	private const META_KEYS = [
		'title'               => '_siteseo_titles_title',
		'description'         => '_siteseo_titles_desc',
		'target_keywords'     => '_siteseo_analysis_target_kw',
		'noindex'             => '_siteseo_robots_index',
		'nofollow'            => '_siteseo_robots_follow',
		'canonical'           => '_siteseo_robots_canonical',
		'og_title'            => '_siteseo_social_fb_title',
		'og_description'      => '_siteseo_social_fb_desc',
		'og_image'            => '_siteseo_social_fb_img',
		'twitter_title'       => '_siteseo_social_twitter_title',
		'twitter_description' => '_siteseo_social_twitter_desc',
		'twitter_image'       => '_siteseo_social_twitter_img',
		'redirect_enabled'    => '_siteseo_redirections_enabled',
		'redirect_type'       => '_siteseo_redirections_type',
		'redirect_url'        => '_siteseo_redirections_value',
		'noarchive'           => '_siteseo_robots_archive',
		'nosnippet'           => '_siteseo_robots_snippet',
		'noimageindex'        => '_siteseo_robots_imageindex',
	];

	/**
	 * Boolean fields stored as "yes" or empty string
	 */
	private const BOOLEAN_FIELDS = [
		'noindex',
		'nofollow',
		'redirect_enabled',
		'noarchive',
		'nosnippet',
		'noimageindex',
	];

	public function get_name(): string {
		return 'SiteSEO';
	}

	public function get_slug(): string {
		return 'siteseo';
	}

	/**
	 * This module requires SiteSEO to be active.
	 */
	public function is_available(): bool {
		return defined( 'SITESEO_VERSION' );
	}

	public function init(): void {
		// Nothing extra needed at init time — routes are registered via register_rest_routes().
	}

	public function get_info(): array {
		return array_merge( parent::get_info(), [
			'description' => 'Provides REST API endpoints to manage SiteSEO meta fields, audit SEO completeness, and read global settings.',
		] );
	}

	// -------------------------------------------------------------------------
	//  REST Route Registration
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		// GET /siteseo/post/{id}
		register_rest_route( self::REST_NAMESPACE, '/siteseo/post/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_post_seo' ],
			'permission_callback' => [ $this, 'check_read_permissions' ],
			'args'                => [
				'id' => [
					'required'          => true,
					'type'              => 'integer',
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// POST /siteseo/post/{id}
		register_rest_route( self::REST_NAMESPACE, '/siteseo/post/(?P<id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'update_post_seo' ],
			'permission_callback' => [ $this, 'check_write_permissions' ],
			'args'                => [
				'id' => [
					'required'          => true,
					'type'              => 'integer',
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// GET /siteseo/audit
		register_rest_route( self::REST_NAMESPACE, '/siteseo/audit', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'audit_seo' ],
			'permission_callback' => [ $this, 'check_read_permissions' ],
			'args'                => [
				'post_type' => [
					'required'          => false,
					'type'              => 'string',
					'default'           => 'post,page',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'per_page' => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 50,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0 && $param <= 100;
					},
					'sanitize_callback' => 'absint',
				],
				'page' => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 1,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
				'status' => [
					'required'          => false,
					'type'              => 'string',
					'default'           => 'publish',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		// GET /siteseo/settings
		register_rest_route( self::REST_NAMESPACE, '/siteseo/settings', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_settings' ],
			'permission_callback' => [ $this, 'check_read_permissions' ],
		] );
	}

	// -------------------------------------------------------------------------
	//  Permission Callbacks
	// -------------------------------------------------------------------------

	/**
	 * Check read permissions (edit_posts capability).
	 */
	public function check_read_permissions(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check write permissions (manage_options capability).
	 */
	public function check_write_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	//  REST Endpoint Callbacks
	// -------------------------------------------------------------------------

	/**
	 * GET /siteseo/post/{id}
	 *
	 * Read all SEO meta for one post/page.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_post_seo( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'id' );

		// Validate post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				sprintf( 'Post with ID %d does not exist.', $post_id ),
				[ 'status' => 404 ]
			);
		}

		// Read all meta fields.
		$seo_data = $this->read_post_seo_meta( $post_id );

		return new WP_REST_Response( [
			'success' => true,
			'post'    => [
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'type'   => $post->post_type,
				'status' => $post->post_status,
				'url'    => get_permalink( $post->ID ),
			],
			'seo'     => $seo_data,
		], 200 );
	}

	/**
	 * POST /siteseo/post/{id}
	 *
	 * Update SEO meta for one post/page.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_post_seo( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'id' );

		// Validate post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				sprintf( 'Post with ID %d does not exist.', $post_id ),
				[ 'status' => 404 ]
			);
		}

		// Get JSON body.
		$body = $request->get_json_params();
		if ( empty( $body ) || ! is_array( $body ) ) {
			return new WP_Error(
				'invalid_request',
				'Request body must be a JSON object with SEO fields to update.',
				[ 'status' => 400 ]
			);
		}

		// Update meta fields.
		$updated_fields = [];
		foreach ( $body as $friendly_name => $value ) {
			if ( ! isset( self::META_KEYS[ $friendly_name ] ) ) {
				continue; // Skip unknown fields.
			}

			$meta_key = self::META_KEYS[ $friendly_name ];

			// Convert boolean values for boolean fields.
			if ( in_array( $friendly_name, self::BOOLEAN_FIELDS, true ) ) {
				$value = $value ? 'yes' : '';
			}

			update_post_meta( $post_id, $meta_key, $value );
			$updated_fields[] = $friendly_name;
		}

		// Return updated state.
		$seo_data = $this->read_post_seo_meta( $post_id );

		return new WP_REST_Response( [
			'success'        => true,
			'message'        => 'SEO meta updated successfully.',
			'updated_fields' => $updated_fields,
			'post'           => [
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'type'   => $post->post_type,
				'status' => $post->post_status,
				'url'    => get_permalink( $post->ID ),
			],
			'seo'            => $seo_data,
		], 200 );
	}

	/**
	 * GET /siteseo/audit
	 *
	 * Audit SEO completeness across posts/pages.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function audit_seo( WP_REST_Request $request ) {
		$post_type = $request->get_param( 'post_type' );
		$per_page  = $request->get_param( 'per_page' );
		$page      = $request->get_param( 'page' );
		$status    = $request->get_param( 'status' );

		// Parse post types.
		$post_types = array_map( 'trim', explode( ',', $post_type ) );

		// Query posts.
		$query_args = [
			'post_type'      => $post_types,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$query = new WP_Query( $query_args );

		// Analyze each post.
		$posts          = [];
		$summary        = [
			'total_posts'      => 0,
			'with_title'       => 0,
			'with_description' => 0,
			'with_keywords'    => 0,
			'noindexed'        => 0,
		];

		foreach ( $query->posts as $post ) {
			$seo_data = $this->read_post_seo_meta( $post->ID );

			$has_title       = ! empty( $seo_data['title'] );
			$has_description = ! empty( $seo_data['description'] );
			$has_keywords    = ! empty( $seo_data['target_keywords'] );
			$is_noindex      = $seo_data['noindex'] === true;

			if ( $has_title ) {
				$summary['with_title']++;
			}
			if ( $has_description ) {
				$summary['with_description']++;
			}
			if ( $has_keywords ) {
				$summary['with_keywords']++;
			}
			if ( $is_noindex ) {
				$summary['noindexed']++;
			}

			$posts[] = [
				'id'              => $post->ID,
				'title'           => $post->post_title,
				'type'            => $post->post_type,
				'url'             => get_permalink( $post->ID ),
				'has_title'       => $has_title,
				'has_description' => $has_description,
				'has_keywords'    => $has_keywords,
				'is_noindex'      => $is_noindex,
			];

			$summary['total_posts']++;
		}

		$total_pages = (int) $query->max_num_pages;

		return new WP_REST_Response( [
			'success'      => true,
			'posts'        => $posts,
			'total'        => $query->found_posts,
			'pages'        => $total_pages,
			'current_page' => $page,
			'summary'      => $summary,
		], 200 );
	}

	/**
	 * GET /siteseo/settings
	 *
	 * Read global SiteSEO config from wp_options.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_settings( WP_REST_Request $request ) {
		$settings = [
			'titles'      => get_option( 'siteseo_titles_option_name', [] ),
			'social'      => get_option( 'siteseo_social_option_name', [] ),
			'toggle'      => get_option( 'siteseo_toggle', [] ),
			'xml_sitemap' => get_option( 'siteseo_xml_sitemap_option_name', [] ),
		];

		return new WP_REST_Response( [
			'success'  => true,
			'settings' => $settings,
		], 200 );
	}

	// -------------------------------------------------------------------------
	//  Helper Methods
	// -------------------------------------------------------------------------

	/**
	 * Read all SEO meta fields for a post.
	 *
	 * @param int $post_id
	 * @return array
	 */
	private function read_post_seo_meta( int $post_id ): array {
		$data = [];

		foreach ( self::META_KEYS as $friendly_name => $meta_key ) {
			$value = get_post_meta( $post_id, $meta_key, true );

			// Convert boolean fields from "yes"/empty to true/false.
			if ( in_array( $friendly_name, self::BOOLEAN_FIELDS, true ) ) {
				$value = 'yes' === $value;
			}

			$data[ $friendly_name ] = $value;
		}

		return $data;
	}
}
