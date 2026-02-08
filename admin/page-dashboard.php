<?php
/**
 * CommunityTech admin dashboard page.
 *
 * Shows loaded modules, their status, and available REST endpoints.
 *
 * @package CommunityTech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$modules = communitytech()->get_modules();
?>

<div class="wrap">
    <h1><?php esc_html_e( 'CommunityTech Workflow', 'communitytech' ); ?></h1>

    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2><?php esc_html_e( 'Plugin Status', 'communitytech' ); ?></h2>
        <p>
            <strong><?php esc_html_e( 'Version:', 'communitytech' ); ?></strong>
            <?php echo esc_html( COMMUNITYTECH_VERSION ); ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'REST API Namespace:', 'communitytech' ); ?></strong>
            <code>communitytech/v1</code>
        </p>
    </div>

    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2><?php esc_html_e( 'Loaded Modules', 'communitytech' ); ?></h2>

        <?php if ( empty( $modules ) ) : ?>
            <p><?php esc_html_e( 'No modules are currently loaded. Check that required plugins (e.g. Elementor) are active.', 'communitytech' ); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width: 100%;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Module', 'communitytech' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'communitytech' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'communitytech' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'communitytech' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $modules as $slug => $module ) :
                        $info = $module->get_info();
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $info['name'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $slug ); ?></code></td>
                            <td>
                                <span style="color: green;">&#10003; <?php esc_html_e( 'Active', 'communitytech' ); ?></span>
                            </td>
                            <td><?php echo esc_html( $info['description'] ?? '' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php
    // Show Elementor Kit info if that module is active.
    $kit_module = communitytech()->get_module( 'elementor-kit' );
    if ( $kit_module ) :
        $kit_id = get_option( 'elementor_active_kit' );
    ?>
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2><?php esc_html_e( 'Elementor Kit REST Endpoints', 'communitytech' ); ?></h2>
            <p>
                <strong><?php esc_html_e( 'Active Kit ID:', 'communitytech' ); ?></strong>
                <?php echo $kit_id ? esc_html( $kit_id ) : '<em>' . esc_html__( 'Not found', 'communitytech' ) . '</em>'; ?>
            </p>
            <table class="widefat striped" style="max-width: 100%;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Method', 'communitytech' ); ?></th>
                        <th><?php esc_html_e( 'Endpoint', 'communitytech' ); ?></th>
                        <th><?php esc_html_e( 'Purpose', 'communitytech' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit</code></td>
                        <td><?php esc_html_e( 'Full kit settings (all global settings)', 'communitytech' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit</code></td>
                        <td><?php esc_html_e( 'Update kit settings (merge)', 'communitytech' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit/colors</code></td>
                        <td><?php esc_html_e( 'Global colors (system + custom)', 'communitytech' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit/colors</code></td>
                        <td><?php esc_html_e( 'Update global colors', 'communitytech' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit/typography</code></td>
                        <td><?php esc_html_e( 'Global typography (system + custom)', 'communitytech' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit/typography</code></td>
                        <td><?php esc_html_e( 'Update global typography', 'communitytech' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit/theme-style</code></td>
                        <td><?php esc_html_e( 'Theme style (body, headings, buttons, forms, images)', 'communitytech' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit/theme-style</code></td>
                        <td><?php esc_html_e( 'Update theme style settings', 'communitytech' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/wp-json/communitytech/v1/elementor/kit/css-variables</code></td>
                        <td><?php esc_html_e( 'CSS variable name → value map (read-only)', 'communitytech' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
