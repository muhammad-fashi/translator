<?php
/**
 * Language switcher template.
 *
 * Copy this file into your theme at `advanced-language-switcher/language-switcher.php`
 * and it will be used instead of the bundled markup.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var array<int,array{language:\ALS\Language,url:string,is_active:bool}> $items   Language entries.
 * @var \ALS\Language|null                                                 $current Active language.
 * @var array<string,mixed>                                                $args    Render arguments.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>

<div class="als-language-switcher als-layout--<?php echo esc_attr( (string) $args['layout'] ); ?>" data-als-switcher="1" role="group"
	aria-label="<?php echo esc_attr( (string) $args['label'] ); ?>">
	<?php foreach ( $items as $item ) : ?>
		<?php /** @var \ALS\Language $language */ ?>
		<?php $language = $item['language']; ?>
		<a
			class="als-language-item<?php echo $item['is_active'] ? ' als-language-item--active active' : ''; ?>"
			href="<?php echo esc_url( (string) $item['url'] ); ?>"
			data-language="<?php echo esc_attr( $language->code ); ?>"
			lang="<?php echo esc_attr( $language->html_lang() ); ?>"
			hreflang="<?php echo esc_attr( $language->html_lang() ); ?>"
			<?php echo $item['is_active'] ? ' aria-current="true"' : ''; ?>
		>
			<?php if ( '' !== $language->flag ) : ?>
				<span class="als-language-flag" aria-hidden="true"><?php echo esc_html( $language->flag ); ?></span>
			<?php endif; ?>
			<span class="als-language-code"><?php echo esc_html( $language->display_code() ); ?></span>
		</a>
	<?php endforeach; ?>
</div>
