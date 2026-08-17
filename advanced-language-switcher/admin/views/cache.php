<?php
/**
 * Cache screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$als_db      = \ALS\Database::stats();
$als_runtime = $plugin->cache()->stats();
?>

<div class="als-cards als-cards--stats">
	<div class="als-stat">
		<span class="als-stat__label"><?php esc_html_e( 'Cached Entries', 'advanced-language-switcher' ); ?></span>
		<span class="als-stat__value"><?php echo esc_html( number_format_i18n( (int) $als_db['cache'] ) ); ?></span>
	</div>
	<div class="als-stat">
		<span class="als-stat__label"><?php esc_html_e( 'External Object Cache', 'advanced-language-switcher' ); ?></span>
		<span class="als-stat__value"><?php echo wp_using_ext_object_cache() ? esc_html__( 'Active', 'advanced-language-switcher' ) : esc_html__( 'Not detected', 'advanced-language-switcher' ); ?></span>
	</div>
	<div class="als-stat">
		<span class="als-stat__label"><?php esc_html_e( 'Hits This Request', 'advanced-language-switcher' ); ?></span>
		<span class="als-stat__value"><?php echo esc_html( number_format_i18n( (int) $als_runtime['hits'] ) ); ?></span>
	</div>
</div>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Clear Cache', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'Translations are read from memory, then the object cache, then the database. Clearing a cache never deletes a translation.', 'advanced-language-switcher' ); ?></p>

	<p class="als-actions">
		<button type="button" class="button" data-als-clear-cache="dictionary"><?php esc_html_e( 'Clear Translation Cache', 'advanced-language-switcher' ); ?></button>
		<button type="button" class="button" data-als-clear-cache="page"><?php esc_html_e( 'Clear Page Cache', 'advanced-language-switcher' ); ?></button>
		<button type="button" class="button button-primary" data-als-clear-cache="all"><?php esc_html_e( 'Clear All Cache', 'advanced-language-switcher' ); ?></button>
	</p>
</section>

<?php
$admin->field_group(
	__( 'Cache Settings', 'advanced-language-switcher' ),
	array(
		'cache_enabled'        => array(
			'type'        => 'toggle',
			'label'       => __( 'Enable translation cache', 'advanced-language-switcher' ),
			'description' => __( 'Strongly recommended. Without it every page rebuilds the whole dictionary from the database.', 'advanced-language-switcher' ),
		),
		'object_cache_enabled' => array(
			'type'        => 'toggle',
			'label'       => __( 'Use the external object cache when available', 'advanced-language-switcher' ),
			'description' => __( 'Redis, Memcached or any drop-in that WordPress detects.', 'advanced-language-switcher' ),
		),
		'cache_ttl'            => array(
			'type'  => 'number',
			'label' => __( 'Translation cache lifetime (seconds)', 'advanced-language-switcher' ),
			'min'   => 60,
			'step'  => 60,
		),
		'page_cache_enabled'   => array(
			'type'        => 'toggle',
			'label'       => __( 'Cache fully translated pages', 'advanced-language-switcher' ),
			'description' => __( 'Skipped automatically for logged in users, search results, POST requests and WooCommerce cart, checkout and account pages.', 'advanced-language-switcher' ),
		),
		'page_cache_ttl'       => array(
			'type'  => 'number',
			'label' => __( 'Page cache lifetime (seconds)', 'advanced-language-switcher' ),
			'min'   => 60,
			'step'  => 60,
		),
	)
);

$admin->save_button();
?>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Translation Backups', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'A backup is taken automatically before every import. You can also create one on demand.', 'advanced-language-switcher' ); ?></p>

	<form class="als-form als-form--inline" id="als-backup-form">
		<div class="als-field">
			<label class="als-field__label" for="als-backup-language"><?php esc_html_e( 'Language', 'advanced-language-switcher' ); ?></label>
			<select id="als-backup-language" name="language_id">
				<option value="0"><?php esc_html_e( 'All languages', 'advanced-language-switcher' ); ?></option>
				<?php foreach ( $plugin->languages()->all() as $als_language ) : ?>
					<option value="<?php echo esc_attr( (string) $als_language->id ); ?>"><?php echo esc_html( $als_language->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="als-field als-field--grow">
			<label class="als-field__label" for="als-backup-name"><?php esc_html_e( 'Label', 'advanced-language-switcher' ); ?></label>
			<input type="text" id="als-backup-name" name="name" placeholder="<?php esc_attr_e( 'Optional', 'advanced-language-switcher' ); ?>" />
		</div>

		<p class="als-actions">
			<button type="submit" class="button"><?php esc_html_e( 'Create Translation Backup', 'advanced-language-switcher' ); ?></button>
		</p>
	</form>

	<?php $als_backups = $plugin->porter()->list_backups(); ?>

	<?php if ( ! $als_backups ) : ?>
		<p class="als-empty"><?php esc_html_e( 'No backups yet.', 'advanced-language-switcher' ); ?></p>
	<?php else : ?>
		<table class="widefat als-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Backup', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Translations', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Created', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'advanced-language-switcher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $als_backups as $als_backup ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $als_backup['name'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $als_backup['item_count'] ) ); ?></td>
						<td><?php echo esc_html( (string) $als_backup['created_at'] ); ?></td>
						<td class="als-table__actions">
							<button type="button" class="button-link" data-als-restore-backup="<?php echo esc_attr( (string) $als_backup['id'] ); ?>" data-confirm="1">
								<?php esc_html_e( 'Restore', 'advanced-language-switcher' ); ?>
							</button>
							<button type="button" class="button-link als-danger" data-als-delete-backup="<?php echo esc_attr( (string) $als_backup['id'] ); ?>" data-confirm="1">
								<?php esc_html_e( 'Delete', 'advanced-language-switcher' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
