<?php
/**
 * Languages screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 * @var \ALS\Language[]  $languages
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$als_catalog = \ALS\Language_Catalog::all();
?>

<div class="als-grid als-grid--sidebar">
	<section class="als-card">
		<h2 class="als-card__title"><?php esc_html_e( 'Configured Languages', 'advanced-language-switcher' ); ?></h2>
		<p class="als-card__intro"><?php esc_html_e( 'Drag rows to change the order languages appear in the switcher.', 'advanced-language-switcher' ); ?></p>

		<?php if ( ! $languages ) : ?>
			<p class="als-empty"><?php esc_html_e( 'No languages yet. Add your first language using the form.', 'advanced-language-switcher' ); ?></p>
		<?php else : ?>
			<table class="widefat als-table" id="als-languages-table">
				<thead>
					<tr>
						<th scope="col" class="als-table__handle"><span class="screen-reader-text"><?php esc_html_e( 'Reorder', 'advanced-language-switcher' ); ?></span></th>
						<th scope="col"><?php esc_html_e( 'Language', 'advanced-language-switcher' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Code', 'advanced-language-switcher' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Flag', 'advanced-language-switcher' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Direction', 'advanced-language-switcher' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'advanced-language-switcher' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Default', 'advanced-language-switcher' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'advanced-language-switcher' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $languages as $als_language ) : ?>
						<tr draggable="true" data-language-id="<?php echo esc_attr( (string) $als_language->id ); ?>">
							<td class="als-table__handle"><span class="dashicons dashicons-menu" aria-hidden="true"></span></td>
							<td>
								<strong><?php echo esc_html( $als_language->name ); ?></strong>
								<?php if ( '' !== $als_language->native_name && $als_language->native_name !== $als_language->name ) : ?>
									<span class="als-muted"><?php echo esc_html( $als_language->native_name ); ?></span>
								<?php endif; ?>
								<div class="als-muted"><?php echo esc_html( $als_language->locale ); ?></div>
							</td>
							<td><code><?php echo esc_html( $als_language->display_code() ); ?></code></td>
							<td class="als-table__flag"><?php echo esc_html( $als_language->flag ); ?></td>
							<td><?php echo esc_html( strtoupper( $als_language->direction ) ); ?></td>
							<td>
								<span class="als-badge <?php echo $als_language->status ? 'als-badge--ok' : 'als-badge--muted'; ?>">
									<?php echo $als_language->status ? esc_html__( 'Active', 'advanced-language-switcher' ) : esc_html__( 'Disabled', 'advanced-language-switcher' ); ?>
								</span>
							</td>
							<td><?php echo $als_language->is_default ? esc_html__( 'Yes', 'advanced-language-switcher' ) : esc_html__( 'No', 'advanced-language-switcher' ); ?></td>
							<td class="als-table__actions">
								<button type="button" class="button-link" data-als-edit-language='<?php echo esc_attr( (string) wp_json_encode( $als_language->to_array() ) ); ?>'>
									<?php esc_html_e( 'Edit', 'advanced-language-switcher' ); ?>
								</button>
								<button type="button" class="button-link" data-als-language-action="duplicate_language" data-id="<?php echo esc_attr( (string) $als_language->id ); ?>">
									<?php esc_html_e( 'Duplicate', 'advanced-language-switcher' ); ?>
								</button>
								<?php if ( ! $als_language->is_default ) : ?>
									<button type="button" class="button-link" data-als-language-action="toggle_language" data-id="<?php echo esc_attr( (string) $als_language->id ); ?>" data-status="<?php echo esc_attr( $als_language->status ? '0' : '1' ); ?>">
										<?php echo $als_language->status ? esc_html__( 'Disable', 'advanced-language-switcher' ) : esc_html__( 'Enable', 'advanced-language-switcher' ); ?>
									</button>
									<button type="button" class="button-link" data-als-language-action="set_default_language" data-id="<?php echo esc_attr( (string) $als_language->id ); ?>">
										<?php esc_html_e( 'Set Default', 'advanced-language-switcher' ); ?>
									</button>
									<button type="button" class="button-link als-danger" data-als-language-action="delete_language" data-id="<?php echo esc_attr( (string) $als_language->id ); ?>" data-confirm="1">
										<?php esc_html_e( 'Delete', 'advanced-language-switcher' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<section class="als-card" id="als-language-form-card">
		<h2 class="als-card__title" id="als-language-form-title"><?php esc_html_e( 'Add Language', 'advanced-language-switcher' ); ?></h2>

		<form class="als-form" id="als-language-form">
			<input type="hidden" name="id" value="0" />

			<div class="als-field">
				<label class="als-field__label" for="als-language-preset"><?php esc_html_e( 'Start from a known language', 'advanced-language-switcher' ); ?></label>
				<select id="als-language-preset">
					<option value=""><?php esc_html_e( 'Choose a language…', 'advanced-language-switcher' ); ?></option>
					<?php foreach ( $als_catalog as $als_code => $als_entry ) : ?>
						<option
							value="<?php echo esc_attr( (string) $als_code ); ?>"
							data-language='<?php echo esc_attr( (string) wp_json_encode( $als_entry ) ); ?>'
						><?php echo esc_html( $als_entry['name'] . ' — ' . $als_entry['native_name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="als-field__description"><?php esc_html_e( 'Fills the fields below; every value can still be edited.', 'advanced-language-switcher' ); ?></p>
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-language-name"><?php esc_html_e( 'Language Name', 'advanced-language-switcher' ); ?></label>
				<input type="text" id="als-language-name" name="name" required />
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-language-native"><?php esc_html_e( 'Native Name', 'advanced-language-switcher' ); ?></label>
				<input type="text" id="als-language-native" name="native_name" />
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-language-code"><?php esc_html_e( 'Language Code', 'advanced-language-switcher' ); ?></label>
				<input type="text" id="als-language-code" name="code" maxlength="12" required />
				<p class="als-field__description"><?php esc_html_e( 'Used in URLs and shown in the switcher, for example es or esp.', 'advanced-language-switcher' ); ?></p>
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-language-locale"><?php esc_html_e( 'Locale', 'advanced-language-switcher' ); ?></label>
				<input type="text" id="als-language-locale" name="locale" />
				<p class="als-field__description"><?php esc_html_e( 'WordPress locale, for example es_ES. Used to load core and theme language packs.', 'advanced-language-switcher' ); ?></p>
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-language-country"><?php esc_html_e( 'Country', 'advanced-language-switcher' ); ?></label>
				<input type="text" id="als-language-country" name="country" maxlength="6" />
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-language-flag"><?php esc_html_e( 'Flag (emoji)', 'advanced-language-switcher' ); ?></label>
				<input type="text" id="als-language-flag" name="flag" maxlength="16" />
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-language-flag-url"><?php esc_html_e( 'Custom Flag Image URL', 'advanced-language-switcher' ); ?></label>
				<input type="url" id="als-language-flag-url" name="flag_url" />
				<p class="als-field__description"><?php esc_html_e( 'Optional. Used when the widget flag type is set to Image.', 'advanced-language-switcher' ); ?></p>
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-language-direction"><?php esc_html_e( 'Direction', 'advanced-language-switcher' ); ?></label>
				<select id="als-language-direction" name="direction">
					<option value="auto"><?php esc_html_e( 'Auto', 'advanced-language-switcher' ); ?></option>
					<option value="ltr"><?php esc_html_e( 'LTR', 'advanced-language-switcher' ); ?></option>
					<option value="rtl"><?php esc_html_e( 'RTL', 'advanced-language-switcher' ); ?></option>
				</select>
			</div>

			<div class="als-field als-field--toggle">
				<label class="als-toggle" for="als-language-status">
					<input type="checkbox" id="als-language-status" name="status" value="1" checked />
					<span class="als-toggle__track" aria-hidden="true"></span>
					<span class="als-toggle__label"><?php esc_html_e( 'Enabled', 'advanced-language-switcher' ); ?></span>
				</label>
			</div>

			<div class="als-field als-field--toggle">
				<label class="als-toggle" for="als-language-default">
					<input type="checkbox" id="als-language-default" name="is_default" value="1" />
					<span class="als-toggle__track" aria-hidden="true"></span>
					<span class="als-toggle__label"><?php esc_html_e( 'Set as default language', 'advanced-language-switcher' ); ?></span>
				</label>
			</div>

			<p class="als-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Language', 'advanced-language-switcher' ); ?></button>
				<button type="button" class="button" data-als-reset-language-form><?php esc_html_e( 'Cancel', 'advanced-language-switcher' ); ?></button>
			</p>
		</form>
	</section>
</div>
