<?php
/**
 * Elementor screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$als_elementor = $plugin->elementor();
$als_available = $als_elementor->is_available();
?>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Elementor Status', 'advanced-language-switcher' ); ?></h2>

	<table class="widefat als-table als-table--status">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Elementor', 'advanced-language-switcher' ); ?></th>
				<td>
					<?php if ( $als_available ) : ?>
						<span class="als-badge als-badge--ok"><?php esc_html_e( 'Active', 'advanced-language-switcher' ); ?></span>
						<?php echo esc_html( $als_elementor->version() ); ?>
					<?php else : ?>
						<span class="als-badge als-badge--missing"><?php esc_html_e( 'Not installed', 'advanced-language-switcher' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Elementor Pro', 'advanced-language-switcher' ); ?></th>
				<td>
					<?php if ( $als_elementor->has_pro() ) : ?>
						<span class="als-badge als-badge--ok"><?php esc_html_e( 'Active', 'advanced-language-switcher' ); ?></span>
					<?php else : ?>
						<span class="als-badge als-badge--muted"><?php esc_html_e( 'Not detected', 'advanced-language-switcher' ); ?></span>
						<span class="als-muted"><?php esc_html_e( 'Theme Builder templates and popups need Elementor Pro; everything else works without it.', 'advanced-language-switcher' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Widget category', 'advanced-language-switcher' ); ?></th>
				<td><code>Language Translator</code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Widget', 'advanced-language-switcher' ); ?></th>
				<td><code>Language Switcher</code></td>
			</tr>
		</tbody>
	</table>

	<?php if ( ! $als_available ) : ?>
		<p class="als-inline-notice als-inline-notice--warning">
			<?php esc_html_e( 'Elementor integration requires Elementor to be installed and activated. The core language system, translations, the shortcode and the template function all keep working without it.', 'advanced-language-switcher' ); ?>
		</p>
	<?php endif; ?>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Using the widget', 'advanced-language-switcher' ); ?></h2>
	<ol class="als-list">
		<li><?php esc_html_e( 'Open any Elementor document: a page, a header, a footer, a popup or a Theme Builder template.', 'advanced-language-switcher' ); ?></li>
		<li><?php esc_html_e( 'Search the widget panel for "Language Switcher", found under the Language Translator category.', 'advanced-language-switcher' ); ?></li>
		<li><?php esc_html_e( 'Drag it into any container. The editor preview renders exactly what visitors will see.', 'advanced-language-switcher' ); ?></li>
		<li><?php esc_html_e( 'Pick a preset, or switch Design Mode to Custom and style every state from the Style tab.', 'advanced-language-switcher' ); ?></li>
		<li><?php esc_html_e( 'Publish. Multiple switchers on one page stay synchronised automatically.', 'advanced-language-switcher' ); ?></li>
	</ol>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Preview of every preset', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'These are the ready made designs available in the widget. Each one remains fully editable.', 'advanced-language-switcher' ); ?></p>

	<div class="als-preset-grid">
		<?php foreach ( \ALS\Switcher::presets() as $als_preset => $als_label ) : ?>
			<?php $als_defaults = \ALS\Switcher::preset_defaults()[ $als_preset ] ?? array(); ?>
			<div class="als-preset-card<?php echo 'glassmorphism' === $als_preset ? ' als-preset-card--dark' : ''; ?>">
				<span class="als-preset-card__label"><?php echo esc_html( $als_label ); ?></span>
				<div class="als-preview">
					<?php
					// Switcher::render() escapes everything it outputs.
					echo \ALS\Switcher::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'preset'      => $als_preset,
							'layout'      => (string) ( $als_defaults['layout'] ?? 'pills' ),
							'display'     => (string) ( $als_defaults['display'] ?? 'code' ),
							'is_preview'  => true,
							'instance_id' => 'als-preset-' . $als_preset,
						)
					);
					?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Outside Elementor', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'The same switcher is available anywhere in WordPress:', 'advanced-language-switcher' ); ?></p>
	<pre class="als-code"><code>[als_language_switcher preset="compact-pill" display="code"]

&lt;?php als_language_switcher( array( 'preset' =&gt; 'flag-name' ) ); ?&gt;</code></pre>
</section>
