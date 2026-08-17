<?php
/**
 * URL settings screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use ALS\Language;

$als_default = $plugin->languages()->get_default();
$als_sample  = null;

foreach ( $plugin->languages()->active() as $als_candidate ) {
	if ( ! $als_default instanceof Language || $als_candidate->id !== $als_default->id ) {
		$als_sample = $als_candidate;
		break;
	}
}

$als_code = $als_sample instanceof Language ? $als_sample->code : 'es';
$als_home = untrailingslashit( home_url( '/' ) );
?>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'URL Structure', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'Directory URLs are recommended: they are unambiguous, cacheable and give search engines a distinct address per language.', 'advanced-language-switcher' ); ?></p>

	<div class="als-url-modes">
		<?php
		$als_modes = array(
			'directory'     => array(
				'label'   => __( 'Directory, default language without prefix', 'advanced-language-switcher' ),
				'example' => array( $als_home . '/', $als_home . '/' . $als_code . '/' ),
				'note'    => __( 'Recommended.', 'advanced-language-switcher' ),
			),
			'directory_all' => array(
				'label'   => __( 'Directory for every language', 'advanced-language-switcher' ),
				'example' => array( $als_home . '/' . ( $als_default instanceof Language ? $als_default->code : 'en' ) . '/', $als_home . '/' . $als_code . '/' ),
				'note'    => __( 'Useful when no language should be treated as special.', 'advanced-language-switcher' ),
			),
			'query'         => array(
				'label'   => __( 'Query parameter', 'advanced-language-switcher' ),
				'example' => array( $als_home . '/', $als_home . '/?lang=' . $als_code ),
				'note'    => __( 'Works on any host, including installs without pretty permalinks.', 'advanced-language-switcher' ),
			),
			'cookie'        => array(
				'label'   => __( 'Cookie / session only', 'advanced-language-switcher' ),
				'example' => array( $als_home . '/', $als_home . '/' ),
				'note'    => __( 'No distinct URL per language, so search engines only index one version.', 'advanced-language-switcher' ),
			),
		);

		foreach ( $als_modes as $als_mode => $als_details ) :
			?>
			<label class="als-url-mode" for="als-url-mode-<?php echo esc_attr( $als_mode ); ?>">
				<input
					type="radio"
					id="als-url-mode-<?php echo esc_attr( $als_mode ); ?>"
					name="url_mode"
					value="<?php echo esc_attr( $als_mode ); ?>"
					data-als-setting="url_mode"
					<?php checked( (string) \ALS\Settings::get( 'url_mode', 'directory' ), $als_mode ); ?>
				/>
				<span class="als-url-mode__body">
					<span class="als-url-mode__label"><?php echo esc_html( $als_details['label'] ); ?></span>
					<span class="als-url-mode__example">
						<?php foreach ( $als_details['example'] as $als_example ) : ?>
							<code><?php echo esc_html( $als_example ); ?></code>
						<?php endforeach; ?>
					</span>
					<span class="als-url-mode__note"><?php echo esc_html( $als_details['note'] ); ?></span>
				</span>
			</label>
		<?php endforeach; ?>
	</div>
</section>

<?php
$admin->field_group(
	__( 'Detection and Persistence', 'advanced-language-switcher' ),
	array(
		'hide_default_prefix'  => array(
			'type'        => 'toggle',
			'label'       => __( 'Hide the prefix for the default language', 'advanced-language-switcher' ),
			'description' => __( 'Only applies to directory mode.', 'advanced-language-switcher' ),
		),
		'browser_detection'    => array(
			'type'        => 'toggle',
			'label'       => __( 'Detect browser language on first visit', 'advanced-language-switcher' ),
			'description' => __( 'A visitor who has already chosen a language is never redirected, and search engine crawlers are always left alone.', 'advanced-language-switcher' ),
		),
		'cookie_lifetime_days' => array(
			'type'  => 'number',
			'label' => __( 'Remember the choice for (days)', 'advanced-language-switcher' ),
			'min'   => 1,
			'max'   => 365,
		),
		'fallback_behaviour'   => array(
			'type'        => 'select',
			'label'       => __( 'When a page has no translation', 'advanced-language-switcher' ),
			'options'     => array(
				'original' => __( 'Show the original page', 'advanced-language-switcher' ),
				'home'     => __( 'Redirect to the translated home page', 'advanced-language-switcher' ),
			),
			'description' => __( 'Showing the original page is the default so a visitor never loses their place.', 'advanced-language-switcher' ),
		),
	)
);

$admin->field_group(
	__( 'Multilingual SEO', 'advanced-language-switcher' ),
	array(
		'hreflang_enabled'    => array(
			'type'        => 'toggle',
			'label'       => __( 'Output hreflang alternates', 'advanced-language-switcher' ),
			'description' => __( 'Adds a link rel="alternate" tag for every enabled language, plus x-default.', 'advanced-language-switcher' ),
		),
		'canonical_enabled'   => array(
			'type'        => 'toggle',
			'label'       => __( 'Keep canonical URLs in the active language', 'advanced-language-switcher' ),
			'description' => __( 'Also applies to Yoast SEO, Rank Math and All in One SEO when they are active.', 'advanced-language-switcher' ),
		),
		'x_default_language'  => array(
			'type'        => 'text',
			'label'       => __( 'x-default language code', 'advanced-language-switcher' ),
			'description' => __( 'Leave empty to use the default language.', 'advanced-language-switcher' ),
		),
	)
);

$admin->save_button();
?>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Language Persistence', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'The active language is resolved in this order, so an explicit choice always wins:', 'advanced-language-switcher' ); ?></p>
	<ol class="als-list">
		<li><?php esc_html_e( 'The language segment or parameter in the URL', 'advanced-language-switcher' ); ?></li>
		<li><?php esc_html_e( 'The visitor cookie', 'advanced-language-switcher' ); ?></li>
		<li><?php esc_html_e( 'The logged in user preference', 'advanced-language-switcher' ); ?></li>
		<li><?php esc_html_e( 'Browser detection, when enabled and no choice was made', 'advanced-language-switcher' ); ?></li>
		<li><?php esc_html_e( 'The site default language', 'advanced-language-switcher' ); ?></li>
	</ol>
	<p class="als-card__intro">
		<?php esc_html_e( 'Once a language is active, every link WordPress generates keeps it, so a visitor stays in that language while browsing.', 'advanced-language-switcher' ); ?>
	</p>
</section>
