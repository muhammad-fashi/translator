<?php
/**
 * System status screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use ALS\Database;
use ALS\Settings;

global $wp_version;

$als_missing_tables = Database::missing_tables();
$als_permalinks     = get_option( 'permalink_structure' );
$als_provider       = $plugin->engine()->get_active_provider();
$als_logs           = $plugin->logger()->recent( 100 );

/**
 * Renders one status row.
 *
 * @param string $label   Row label.
 * @param string $value   Row value.
 * @param string $state   ok|warning|error|muted.
 * @param string $note    Optional note.
 * @return void
 */
$als_row = static function ( string $label, string $value, string $state = 'muted', string $note = '' ): void {
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $label ); ?></th>
		<td>
			<span class="als-badge als-badge--<?php echo esc_attr( 'ok' === $state ? 'ok' : ( 'error' === $state ? 'missing' : $state ) ); ?>">
				<?php echo esc_html( $value ); ?>
			</span>
			<?php if ( '' !== $note ) : ?>
				<span class="als-muted"><?php echo esc_html( $note ); ?></span>
			<?php endif; ?>
		</td>
	</tr>
	<?php
};
?>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Environment', 'advanced-language-switcher' ); ?></h2>

	<table class="widefat als-table als-table--status">
		<tbody>
			<?php
			$als_row(
				__( 'Plugin Version', 'advanced-language-switcher' ),
				ALS_VERSION,
				'ok'
			);

			$als_row(
				__( 'WordPress Version', 'advanced-language-switcher' ),
				(string) $wp_version,
				version_compare( (string) $wp_version, '6.0', '>=' ) ? 'ok' : 'warning',
				version_compare( (string) $wp_version, '6.0', '>=' ) ? '' : __( 'WordPress 6.0 or newer is recommended.', 'advanced-language-switcher' )
			);

			$als_row(
				__( 'PHP Version', 'advanced-language-switcher' ),
				PHP_VERSION,
				version_compare( PHP_VERSION, '8.1', '>=' ) ? 'ok' : 'error',
				version_compare( PHP_VERSION, '8.1', '>=' ) ? '' : __( 'This plugin requires PHP 8.1 or newer.', 'advanced-language-switcher' )
			);

			$als_row(
				__( 'Memory Limit', 'advanced-language-switcher' ),
				(string) ini_get( 'memory_limit' ),
				'muted'
			);

			$als_row(
				__( 'Max Execution Time', 'advanced-language-switcher' ),
				(string) ini_get( 'max_execution_time' ) . 's',
				'muted',
				__( 'Scans and bulk translation run in small batches, so a low limit is not a problem.', 'advanced-language-switcher' )
			);

			$als_row(
				__( 'mbstring extension', 'advanced-language-switcher' ),
				extension_loaded( 'mbstring' ) ? __( 'Available', 'advanced-language-switcher' ) : __( 'Missing', 'advanced-language-switcher' ),
				extension_loaded( 'mbstring' ) ? 'ok' : 'error',
				extension_loaded( 'mbstring' ) ? '' : __( 'Required for correct handling of non-Latin scripts.', 'advanced-language-switcher' )
			);

			$als_row(
				__( 'Elementor', 'advanced-language-switcher' ),
				$plugin->elementor()->is_available()
					? sprintf( '%s %s', __( 'Installed', 'advanced-language-switcher' ), $plugin->elementor()->version() )
					: __( 'Not installed', 'advanced-language-switcher' ),
				$plugin->elementor()->is_available() ? 'ok' : 'muted'
			);

			$als_row(
				__( 'Elementor Pro', 'advanced-language-switcher' ),
				$plugin->elementor()->has_pro() ? __( 'Installed', 'advanced-language-switcher' ) : __( 'Not installed', 'advanced-language-switcher' ),
				$plugin->elementor()->has_pro() ? 'ok' : 'muted'
			);

			$als_row(
				__( 'WooCommerce', 'advanced-language-switcher' ),
				$plugin->woocommerce()->is_available()
					? sprintf( '%s %s', __( 'Installed', 'advanced-language-switcher' ), $plugin->woocommerce()->version() )
					: __( 'Not installed', 'advanced-language-switcher' ),
				$plugin->woocommerce()->is_available() ? 'ok' : 'muted'
			);

			$als_row(
				__( 'REST API', 'advanced-language-switcher' ),
				esc_url_raw( rest_url( 'als/v1/languages' ) ),
				'ok'
			);

			$als_row(
				__( 'Rewrite Rules', 'advanced-language-switcher' ),
				$als_permalinks ? __( 'Pretty permalinks active', 'advanced-language-switcher' ) : __( 'Plain permalinks', 'advanced-language-switcher' ),
				$als_permalinks ? 'ok' : 'warning',
				$als_permalinks ? '' : __( 'Directory URL mode needs pretty permalinks. Switch to query parameter mode, or enable permalinks under Settings → Permalinks.', 'advanced-language-switcher' )
			);

			$als_row(
				__( 'Translation Database', 'advanced-language-switcher' ),
				$als_missing_tables ? __( 'Tables missing', 'advanced-language-switcher' ) : __( 'All tables present', 'advanced-language-switcher' ),
				$als_missing_tables ? 'error' : 'ok',
				$als_missing_tables ? implode( ', ', $als_missing_tables ) : ''
			);

			$als_row(
				__( 'Cache Status', 'advanced-language-switcher' ),
				Settings::is_enabled( 'cache_enabled' ) ? __( 'Enabled', 'advanced-language-switcher' ) : __( 'Disabled', 'advanced-language-switcher' ),
				Settings::is_enabled( 'cache_enabled' ) ? 'ok' : 'warning',
				wp_using_ext_object_cache() ? __( 'External object cache detected.', 'advanced-language-switcher' ) : __( 'No external object cache detected; the database cache is used.', 'advanced-language-switcher' )
			);

			$als_row(
				__( 'Translation Provider', 'advanced-language-switcher' ),
				$als_provider->get_label(),
				$als_provider->is_automatic() && ! $als_provider->is_configured() ? 'warning' : 'ok',
				$als_provider->is_automatic() && ! $als_provider->is_configured()
					? __( 'No API key configured; automatic translation is unavailable.', 'advanced-language-switcher' )
					: ''
			);

			$als_row(
				__( 'Multisite', 'advanced-language-switcher' ),
				is_multisite() ? __( 'Yes — per-site configuration', 'advanced-language-switcher' ) : __( 'No', 'advanced-language-switcher' ),
				'muted'
			);
			?>
		</tbody>
	</table>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Logs', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro">
		<?php
		echo Settings::is_enabled( 'logging_enabled' )
			? esc_html__( 'Logging is enabled. Translation failures are recorded here and never shown to visitors.', 'advanced-language-switcher' )
			: esc_html__( 'Logging is disabled, which is the recommended setting for a production site. Enable it under Translation Settings while troubleshooting.', 'advanced-language-switcher' );
		?>
	</p>

	<p class="als-actions">
		<button type="button" class="button" data-als-clear-logs><?php esc_html_e( 'Clear Logs', 'advanced-language-switcher' ); ?></button>
	</p>

	<?php if ( ! $als_logs ) : ?>
		<p class="als-empty"><?php esc_html_e( 'No log entries.', 'advanced-language-switcher' ); ?></p>
	<?php else : ?>
		<table class="widefat als-table als-table--logs">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Time', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Level', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Provider', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Language', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Message', 'advanced-language-switcher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $als_logs as $als_log ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $als_log['created_at'] ); ?></td>
						<td><span class="als-badge als-badge--<?php echo 'error' === $als_log['level'] ? 'missing' : 'muted'; ?>"><?php echo esc_html( (string) $als_log['level'] ); ?></span></td>
						<td><?php echo esc_html( (string) $als_log['provider'] ); ?></td>
						<td><?php echo esc_html( (string) $als_log['language'] ); ?></td>
						<td class="als-truncate"><?php echo esc_html( (string) $als_log['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
