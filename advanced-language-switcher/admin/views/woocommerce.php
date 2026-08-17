<?php
/**
 * WooCommerce screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$als_woo       = $plugin->woocommerce();
$als_available = $als_woo->is_available();
?>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'WooCommerce Status', 'advanced-language-switcher' ); ?></h2>

	<p>
		<?php if ( $als_available ) : ?>
			<span class="als-badge als-badge--ok"><?php esc_html_e( 'Active', 'advanced-language-switcher' ); ?></span>
			<?php echo esc_html( $als_woo->version() ); ?>
		<?php else : ?>
			<span class="als-badge als-badge--muted"><?php esc_html_e( 'Not installed', 'advanced-language-switcher' ); ?></span>
			<span class="als-muted"><?php esc_html_e( 'The shop integration stays dormant until WooCommerce is active.', 'advanced-language-switcher' ); ?></span>
		<?php endif; ?>
	</p>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'What gets translated', 'advanced-language-switcher' ); ?></h2>

	<div class="als-grid als-grid--2">
		<div>
			<h3 class="als-subheading"><?php esc_html_e( 'Translated', 'advanced-language-switcher' ); ?></h3>
			<ul class="als-list als-list--check">
				<li><?php esc_html_e( 'Product titles, descriptions and short descriptions', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Product categories, tags and attribute labels', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Variation option names', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Shop, archive and single product templates', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Cart, checkout and My Account pages', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Billing and shipping field labels and placeholders', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Buttons: Add to cart, Out of stock, Sale, Quantity', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'AJAX cart fragments', 'advanced-language-switcher' ); ?></li>
			</ul>
		</div>
		<div>
			<h3 class="als-subheading"><?php esc_html_e( 'Never touched', 'advanced-language-switcher' ); ?></h3>
			<ul class="als-list als-list--cross">
				<li><?php esc_html_e( 'SKUs', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Product, order and internal database IDs', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Prices, currency symbols and amounts', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Order numbers', 'advanced-language-switcher' ); ?></li>
				<li><?php esc_html_e( 'Coupon codes', 'advanced-language-switcher' ); ?></li>
			</ul>
			<p class="als-card__intro">
				<?php esc_html_e( 'These are protected by marking their containers untranslatable, so no configuration is needed. Add more classes under Translation Settings → Exclusions if your theme uses different markup.', 'advanced-language-switcher' ); ?>
			</p>
		</div>
	</div>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Cart and session safety', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro">
		<?php esc_html_e( 'Switching language changes only the rendering context. The WooCommerce session cookie, cart contents, quantities and customer data are untouched, and cart, checkout and account pages are excluded from the page cache so they always reflect the live session.', 'advanced-language-switcher' ); ?>
	</p>
</section>

<?php
$admin->field_group(
	__( 'Shop Translation Options', 'advanced-language-switcher' ),
	array(
		'translate_products' => array(
			'type'  => 'toggle',
			'label' => __( 'Translate products and shop pages', 'advanced-language-switcher' ),
		),
		'translate_forms'    => array(
			'type'        => 'toggle',
			'label'       => __( 'Translate checkout and billing fields', 'advanced-language-switcher' ),
			'description' => __( 'Field keys, types and validation rules are never modified.', 'advanced-language-switcher' ),
		),
	)
);

$admin->save_button();
