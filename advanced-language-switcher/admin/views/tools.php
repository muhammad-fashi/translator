<?php
/**
 * Import / export screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 * @var \ALS\Language[]  $languages
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use ALS\Porter;
?>

<div class="als-grid als-grid--2">
	<section class="als-card">
		<h2 class="als-card__title"><?php esc_html_e( 'Export Language', 'advanced-language-switcher' ); ?></h2>
		<p class="als-card__intro"><?php esc_html_e( 'Download every source string with its translation for one language.', 'advanced-language-switcher' ); ?></p>

		<form class="als-form" id="als-export-form">
			<div class="als-field">
				<label class="als-field__label" for="als-export-language"><?php esc_html_e( 'Language', 'advanced-language-switcher' ); ?></label>
				<select id="als-export-language" name="language_id" required>
					<?php foreach ( $languages as $als_language ) : ?>
						<option value="<?php echo esc_attr( (string) $als_language->id ); ?>"><?php echo esc_html( $als_language->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-export-format"><?php esc_html_e( 'Format', 'advanced-language-switcher' ); ?></label>
				<select id="als-export-format" name="format">
					<?php foreach ( Porter::formats() as $als_key => $als_label ) : ?>
						<option value="<?php echo esc_attr( (string) $als_key ); ?>"><?php echo esc_html( (string) $als_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<p class="als-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Export', 'advanced-language-switcher' ); ?></button>
			</p>
		</form>
	</section>

	<section class="als-card">
		<h2 class="als-card__title"><?php esc_html_e( 'Import Language', 'advanced-language-switcher' ); ?></h2>
		<p class="als-card__intro"><?php esc_html_e( 'JSON, CSV, PO and MO files are accepted. The file is validated before anything is written, and a backup is created automatically.', 'advanced-language-switcher' ); ?></p>

		<form class="als-form" id="als-import-form" enctype="multipart/form-data">
			<div class="als-field">
				<label class="als-field__label" for="als-import-language"><?php esc_html_e( 'Language', 'advanced-language-switcher' ); ?></label>
				<select id="als-import-language" name="language_id" required>
					<?php foreach ( $languages as $als_language ) : ?>
						<option value="<?php echo esc_attr( (string) $als_language->id ); ?>"><?php echo esc_html( $als_language->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="als-field">
				<label class="als-field__label" for="als-import-file"><?php esc_html_e( 'File', 'advanced-language-switcher' ); ?></label>
				<input type="file" id="als-import-file" name="file" accept=".json,.csv,.po,.mo" required />
			</div>

			<div class="als-field als-field--toggle">
				<label class="als-toggle" for="als-import-overwrite">
					<input type="checkbox" id="als-import-overwrite" name="overwrite" value="1" />
					<span class="als-toggle__track" aria-hidden="true"></span>
					<span class="als-toggle__label"><?php esc_html_e( 'Overwrite manual translations', 'advanced-language-switcher' ); ?></span>
				</label>
				<p class="als-field__description"><?php esc_html_e( 'Off by default: human edits are preserved and only missing or machine translated strings are replaced.', 'advanced-language-switcher' ); ?></p>
			</div>

			<p class="als-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'advanced-language-switcher' ); ?></button>
			</p>
		</form>
	</section>
</div>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'File formats', 'advanced-language-switcher' ); ?></h2>

	<table class="widefat als-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Format', 'advanced-language-switcher' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Best for', 'advanced-language-switcher' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Notes', 'advanced-language-switcher' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><code>JSON</code></td>
				<td><?php esc_html_e( 'Moving translations between sites', 'advanced-language-switcher' ); ?></td>
				<td><?php esc_html_e( 'Keeps context, status and provider. A flat {"source": "translation"} object is also accepted.', 'advanced-language-switcher' ); ?></td>
			</tr>
			<tr>
				<td><code>CSV</code></td>
				<td><?php esc_html_e( 'Sending work to a translator', 'advanced-language-switcher' ); ?></td>
				<td><?php esc_html_e( 'Needs at least a source and a translation column. Opens cleanly in any spreadsheet application.', 'advanced-language-switcher' ); ?></td>
			</tr>
			<tr>
				<td><code>PO</code></td>
				<td><?php esc_html_e( 'Poedit and standard translation tools', 'advanced-language-switcher' ); ?></td>
				<td><?php esc_html_e( 'Context is written as msgctxt so nothing is lost on a round trip.', 'advanced-language-switcher' ); ?></td>
			</tr>
			<tr>
				<td><code>MO</code></td>
				<td><?php esc_html_e( 'Reusing an existing compiled catalogue', 'advanced-language-switcher' ); ?></td>
				<td><?php esc_html_e( 'Both byte orders are read. Exports contain only completed translations.', 'advanced-language-switcher' ); ?></td>
			</tr>
		</tbody>
	</table>
</section>
