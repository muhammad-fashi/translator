<?php
/**
 * Dashboard screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin        $plugin
 * @var \ALS\Admin\Admin   $admin
 * @var \ALS\Language[]    $languages
 * @var array<string,mixed> $settings
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$als_stats    = $plugin->translations()->global_stats();
$als_default  = $plugin->languages()->get_default();
$als_provider = $plugin->engine()->get_active_provider();
$als_scanner  = $plugin->scanner();
$als_last     = $als_scanner->last_scan();
?>

<div class="als-cards als-cards--stats">
	<div class="als-stat">
		<span class="als-stat__label"><?php esc_html_e( 'Active Languages', 'advanced-language-switcher' ); ?></span>
		<span class="als-stat__value"><?php echo esc_html( number_format_i18n( (int) $als_stats['languages'] ) ); ?></span>
		<span class="als-stat__meta">
			<?php
			printf(
				/* translators: %s: default language name. */
				esc_html__( 'Default: %s', 'advanced-language-switcher' ),
				esc_html( $als_default instanceof \ALS\Language ? $als_default->name : __( 'not set', 'advanced-language-switcher' ) )
			);
			?>
		</span>
	</div>

	<div class="als-stat">
		<span class="als-stat__label"><?php esc_html_e( 'Translation Strings', 'advanced-language-switcher' ); ?></span>
		<span class="als-stat__value"><?php echo esc_html( number_format_i18n( (int) $als_stats['strings'] ) ); ?></span>
		<span class="als-stat__meta">
			<?php
			echo $als_last > 0
				? esc_html(
					sprintf(
						/* translators: %s: human readable time difference. */
						__( 'Last scan %s ago', 'advanced-language-switcher' ),
						human_time_diff( $als_last )
					)
				)
				: esc_html__( 'No scan yet', 'advanced-language-switcher' );
			?>
		</span>
	</div>

	<div class="als-stat">
		<span class="als-stat__label"><?php esc_html_e( 'Completed Translations', 'advanced-language-switcher' ); ?></span>
		<span class="als-stat__value"><?php echo esc_html( number_format_i18n( (int) $als_stats['translated'] ) ); ?></span>
		<span class="als-stat__meta"><?php echo esc_html( sprintf( '%s%%', number_format_i18n( (float) $als_stats['completion'], 1 ) ) ); ?> <?php esc_html_e( 'complete', 'advanced-language-switcher' ); ?></span>
	</div>

	<div class="als-stat">
		<span class="als-stat__label"><?php esc_html_e( 'Missing Translations', 'advanced-language-switcher' ); ?></span>
		<span class="als-stat__value"><?php echo esc_html( number_format_i18n( (int) $als_stats['missing'] ) ); ?></span>
		<span class="als-stat__meta"><a href="<?php echo esc_url( admin_url( 'admin.php?page=als-translations&status=missing' ) ); ?>"><?php esc_html_e( 'Review', 'advanced-language-switcher' ); ?></a></span>
	</div>

	<div class="als-stat">
		<span class="als-stat__label"><?php esc_html_e( 'Cached Translations', 'advanced-language-switcher' ); ?></span>
		<span class="als-stat__value"><?php echo esc_html( number_format_i18n( (int) $als_stats['cached'] ) ); ?></span>
		<span class="als-stat__meta"><a href="<?php echo esc_url( admin_url( 'admin.php?page=als-cache' ) ); ?>"><?php esc_html_e( 'Manage cache', 'advanced-language-switcher' ); ?></a></span>
	</div>
</div>

<div class="als-grid als-grid--2">
	<section class="als-card">
		<h2 class="als-card__title"><?php esc_html_e( 'Translation Activity', 'advanced-language-switcher' ); ?></h2>

		<?php if ( ! $als_stats['per_language'] ) : ?>
			<p class="als-empty"><?php esc_html_e( 'Add a language to get started.', 'advanced-language-switcher' ); ?></p>
		<?php else : ?>
			<ul class="als-progress-list">
				<?php foreach ( $als_stats['per_language'] as $als_row ) : ?>
					<?php /** @var \ALS\Language $als_language */ ?>
					<?php $als_language = $als_row['language']; ?>
					<li class="als-progress">
						<div class="als-progress__head">
							<span class="als-progress__name">
								<?php if ( '' !== $als_language->flag ) : ?>
									<span aria-hidden="true"><?php echo esc_html( $als_language->flag ); ?></span>
								<?php endif; ?>
								<?php echo esc_html( $als_language->name ); ?>
								<?php if ( ! empty( $als_row['is_source'] ) ) : ?>
									<em class="als-badge als-badge--source"><?php esc_html_e( 'Source', 'advanced-language-switcher' ); ?></em>
								<?php endif; ?>
							</span>
							<span class="als-progress__value"><?php echo esc_html( sprintf( '%s%%', number_format_i18n( (float) $als_row['percent'], 1 ) ) ); ?></span>
						</div>
						<div class="als-progress__bar" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $als_row['percent'] ); ?>" aria-valuemin="0" aria-valuemax="100">
							<span style="width: <?php echo esc_attr( (string) min( 100, (float) $als_row['percent'] ) ); ?>%"></span>
						</div>
						<p class="als-progress__meta">
							<?php
							printf(
								/* translators: 1: translated count, 2: total count, 3: missing count. */
								esc_html__( '%1$s of %2$s translated, %3$s missing', 'advanced-language-switcher' ),
								esc_html( number_format_i18n( (int) $als_row['translated'] ) ),
								esc_html( number_format_i18n( (int) $als_row['total'] ) ),
								esc_html( number_format_i18n( (int) $als_row['missing'] ) )
							);
							?>
						</p>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<div class="als-stack">
		<section class="als-card">
			<h2 class="als-card__title"><?php esc_html_e( 'Scan Website', 'advanced-language-switcher' ); ?></h2>
			<p class="als-card__intro"><?php esc_html_e( 'Find every translatable string in posts, pages, products, Elementor documents, menus, widgets and theme output.', 'advanced-language-switcher' ); ?></p>

			<button type="button" class="button button-primary" data-als-scan>
				<?php esc_html_e( 'Scan Website', 'advanced-language-switcher' ); ?>
			</button>

			<div class="als-scan-progress" id="als-scan-progress" hidden>
				<div class="als-progress__bar"><span style="width:0%"></span></div>
				<p class="als-scan-progress__label"></p>
				<ul class="als-scan-progress__counters"></ul>
			</div>
		</section>

		<section class="als-card">
			<h2 class="als-card__title"><?php esc_html_e( 'Translation Engine', 'advanced-language-switcher' ); ?></h2>
			<p class="als-card__intro">
				<?php
				printf(
					/* translators: %s: provider name. */
					esc_html__( 'Current provider: %s', 'advanced-language-switcher' ),
					'<strong>' . esc_html( $als_provider->get_label() ) . '</strong>'
				);
				?>
			</p>

			<?php if ( $als_provider->is_automatic() && ! $als_provider->is_configured() ) : ?>
				<p class="als-inline-notice als-inline-notice--warning">
					<?php esc_html_e( 'This provider has no API key yet, so automatic translation is unavailable.', 'advanced-language-switcher' ); ?>
				</p>
			<?php endif; ?>

			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=als-providers' ) ); ?>">
					<?php esc_html_e( 'Configure engine', 'advanced-language-switcher' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=als-translations' ) ); ?>">
					<?php esc_html_e( 'Open translation editor', 'advanced-language-switcher' ); ?>
				</a>
			</p>
		</section>

		<section class="als-card">
			<h2 class="als-card__title"><?php esc_html_e( 'Switcher Preview', 'advanced-language-switcher' ); ?></h2>
			<p class="als-card__intro"><?php esc_html_e( 'This is how the switcher renders with the current global defaults.', 'advanced-language-switcher' ); ?></p>
			<div class="als-preview">
				<?php
				// Switcher::render() escapes everything it outputs.
				echo \ALS\Switcher::render( array( 'is_preview' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<p class="als-card__intro">
				<?php esc_html_e( 'Shortcode:', 'advanced-language-switcher' ); ?>
				<code>[als_language_switcher]</code>
			</p>
		</section>
	</div>
</div>
