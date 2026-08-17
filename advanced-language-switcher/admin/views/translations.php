<?php
/**
 * Translation editor screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 * @var \ALS\Language[]  $languages
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use ALS\Language;
use ALS\Translation_Manager;

$als_default = $plugin->languages()->get_default();

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$als_requested = isset( $_GET['language'] ) ? sanitize_key( wp_unslash( (string) $_GET['language'] ) ) : '';
$als_status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '';
$als_search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
$als_page      = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
// phpcs:enable

$als_target = null;

if ( '' !== $als_requested ) {
	$als_target = $plugin->languages()->get_by_code( $als_requested );
}

if ( ! $als_target instanceof Language ) {
	foreach ( $languages as $als_candidate ) {
		if ( ! $als_default instanceof Language || $als_candidate->id !== $als_default->id ) {
			$als_target = $als_candidate;
			break;
		}
	}
}

$als_result = array(
	'items' => array(),
	'total' => 0,
	'pages' => 0,
);

if ( $als_target instanceof Language ) {
	$als_result = $plugin->translations()->query(
		array(
			'language_id' => $als_target->id,
			'search'      => $als_search,
			'status'      => $als_status,
			'per_page'    => 25,
			'page'        => $als_page,
		)
	);
}

$als_statuses = Translation_Manager::statuses();
?>

<?php if ( ! $als_target instanceof Language ) : ?>
	<section class="als-card">
		<p class="als-empty">
			<?php esc_html_e( 'Add a second language before translating.', 'advanced-language-switcher' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=als-languages' ) ); ?>"><?php esc_html_e( 'Add a language', 'advanced-language-switcher' ); ?></a>
		</p>
	</section>
	<?php return; ?>
<?php endif; ?>

<section class="als-card">
	<form class="als-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="als-translations" />

		<div class="als-field">
			<label class="als-field__label" for="als-filter-language"><?php esc_html_e( 'Language', 'advanced-language-switcher' ); ?></label>
			<select id="als-filter-language" name="language">
				<?php foreach ( $languages as $als_language ) : ?>
					<?php if ( $als_default instanceof Language && $als_language->id === $als_default->id ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<option value="<?php echo esc_attr( $als_language->code ); ?>" <?php selected( $als_language->code, $als_target->code ); ?>>
						<?php echo esc_html( $als_language->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="als-field">
			<label class="als-field__label" for="als-filter-status"><?php esc_html_e( 'Status', 'advanced-language-switcher' ); ?></label>
			<select id="als-filter-status" name="status">
				<option value=""><?php esc_html_e( 'All', 'advanced-language-switcher' ); ?></option>
				<?php foreach ( $als_statuses as $als_key => $als_label ) : ?>
					<option value="<?php echo esc_attr( $als_key ); ?>" <?php selected( $als_key, $als_status ); ?>><?php echo esc_html( $als_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="als-field als-field--grow">
			<label class="als-field__label" for="als-filter-search"><?php esc_html_e( 'Search', 'advanced-language-switcher' ); ?></label>
			<input type="search" id="als-filter-search" name="s" value="<?php echo esc_attr( $als_search ); ?>" placeholder="<?php esc_attr_e( 'Search original text, translation or context', 'advanced-language-switcher' ); ?>" />
		</div>

		<p class="als-actions">
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'advanced-language-switcher' ); ?></button>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=als-translations' ) ); ?>"><?php esc_html_e( 'Reset', 'advanced-language-switcher' ); ?></a>
		</p>
	</form>
</section>

<section class="als-card">
	<div class="als-bulk" data-language-id="<?php echo esc_attr( (string) $als_target->id ); ?>">
		<h2 class="als-card__title"><?php esc_html_e( 'Automatic Translation', 'advanced-language-switcher' ); ?></h2>
		<p class="als-card__intro">
			<?php
			printf(
				/* translators: 1: language name, 2: number of missing strings. */
				esc_html__( '%1$s currently has %2$s untranslated strings.', 'advanced-language-switcher' ),
				esc_html( $als_target->name ),
				esc_html( number_format_i18n( $plugin->translations()->count_pending( $als_target->id ) ) )
			);
			?>
		</p>

		<p class="als-actions">
			<button type="button" class="button button-primary" data-als-auto-translate="missing">
				<?php esc_html_e( 'Translate Missing Only', 'advanced-language-switcher' ); ?>
			</button>
			<button type="button" class="button" data-als-auto-translate="all">
				<?php esc_html_e( 'Translate All', 'advanced-language-switcher' ); ?>
			</button>
			<button type="button" class="button" data-als-auto-translate="selected" disabled>
				<?php esc_html_e( 'Translate Selected', 'advanced-language-switcher' ); ?>
			</button>
		</p>

		<div class="als-batch-progress" hidden>
			<div class="als-progress__bar"><span style="width:0%"></span></div>
			<p class="als-batch-progress__label"></p>
		</div>
	</div>
</section>

<section class="als-card">
	<h2 class="als-card__title">
		<?php
		printf(
			/* translators: 1: language name, 2: number of strings. */
			esc_html__( '%1$s — %2$s strings', 'advanced-language-switcher' ),
			esc_html( $als_target->name ),
			esc_html( number_format_i18n( (int) $als_result['total'] ) )
		);
		?>
	</h2>

	<?php if ( ! $als_result['items'] ) : ?>
		<p class="als-empty">
			<?php esc_html_e( 'No strings match this filter. Run a website scan from the dashboard to discover content.', 'advanced-language-switcher' ); ?>
		</p>
	<?php else : ?>
		<table class="widefat als-table als-table--editor">
			<thead>
				<tr>
					<th scope="col" class="als-table__check"><input type="checkbox" data-als-select-all aria-label="<?php esc_attr_e( 'Select all strings', 'advanced-language-switcher' ); ?>" /></th>
					<th scope="col"><?php esc_html_e( 'Original Text', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Translation', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'advanced-language-switcher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $als_result['items'] as $als_item ) : ?>
					<tr data-string-id="<?php echo esc_attr( (string) $als_item['id'] ); ?>" data-language-id="<?php echo esc_attr( (string) $als_target->id ); ?>">
						<td class="als-table__check">
							<input type="checkbox" data-als-string-check value="<?php echo esc_attr( (string) $als_item['id'] ); ?>" aria-label="<?php esc_attr_e( 'Select string', 'advanced-language-switcher' ); ?>" />
						</td>
						<td class="als-table__source">
							<div class="als-source-text"><?php echo esc_html( (string) $als_item['source_text'] ); ?></div>
							<?php if ( '' !== (string) $als_item['context'] ) : ?>
								<code class="als-muted"><?php echo esc_html( (string) $als_item['context'] ); ?></code>
							<?php endif; ?>
							<?php if ( '' !== (string) $als_item['source_location'] ) : ?>
								<div class="als-muted als-truncate"><?php echo esc_html( (string) $als_item['source_location'] ); ?></div>
							<?php endif; ?>
						</td>
						<td>
							<textarea
								class="als-translation-input"
								rows="2"
								data-als-translation
								placeholder="<?php esc_attr_e( 'Enter the translation…', 'advanced-language-switcher' ); ?>"
							><?php echo esc_textarea( (string) $als_item['translated_text'] ); ?></textarea>
						</td>
						<td>
							<span class="als-badge als-badge--<?php echo esc_attr( str_replace( '_', '-', (string) $als_item['translation_status'] ) ); ?>" data-als-status-badge>
								<?php echo esc_html( $als_statuses[ $als_item['translation_status'] ] ?? (string) $als_item['translation_status'] ); ?>
							</span>
						</td>
						<td class="als-table__actions">
							<button type="button" class="button button-small" data-als-save-translation><?php esc_html_e( 'Save', 'advanced-language-switcher' ); ?></button>
							<button type="button" class="button-link" data-als-copy-original><?php esc_html_e( 'Copy Original', 'advanced-language-switcher' ); ?></button>
							<button type="button" class="button-link" data-als-mark-reviewed><?php esc_html_e( 'Mark Reviewed', 'advanced-language-switcher' ); ?></button>
							<button type="button" class="button-link als-danger" data-als-reset-translation><?php esc_html_e( 'Reset', 'advanced-language-switcher' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="als-card__intro">
			<?php esc_html_e( 'Keyboard shortcuts: Ctrl + S saves the field you are editing, Ctrl + Enter saves and moves to the next string.', 'advanced-language-switcher' ); ?>
		</p>

		<?php if ( (int) $als_result['pages'] > 1 ) : ?>
			<nav class="als-pagination" aria-label="<?php esc_attr_e( 'Translation pages', 'advanced-language-switcher' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $als_page,
							'total'     => (int) $als_result['pages'],
							'prev_text' => __( 'Previous', 'advanced-language-switcher' ),
							'next_text' => __( 'Next', 'advanced-language-switcher' ),
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Glossary', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'Glossary terms always win over automatic translation, which keeps brand and product terminology consistent.', 'advanced-language-switcher' ); ?></p>

	<form class="als-form als-form--inline" id="als-glossary-form">
		<input type="hidden" name="language_id" value="<?php echo esc_attr( (string) $als_target->id ); ?>" />

		<div class="als-field">
			<label class="als-field__label" for="als-glossary-source"><?php esc_html_e( 'Source term', 'advanced-language-switcher' ); ?></label>
			<input type="text" id="als-glossary-source" name="source_term" required />
		</div>

		<div class="als-field">
			<label class="als-field__label" for="als-glossary-target"><?php esc_html_e( 'Translation', 'advanced-language-switcher' ); ?></label>
			<input type="text" id="als-glossary-target" name="target_term" required />
		</div>

		<div class="als-field als-field--toggle">
			<label class="als-toggle" for="als-glossary-case">
				<input type="checkbox" id="als-glossary-case" name="case_sensitive" value="1" />
				<span class="als-toggle__track" aria-hidden="true"></span>
				<span class="als-toggle__label"><?php esc_html_e( 'Case sensitive', 'advanced-language-switcher' ); ?></span>
			</label>
		</div>

		<p class="als-actions">
			<button type="submit" class="button"><?php esc_html_e( 'Add term', 'advanced-language-switcher' ); ?></button>
		</p>
	</form>

	<?php $als_terms = $plugin->glossary()->get_terms( $als_target->id ); ?>

	<?php if ( $als_terms ) : ?>
		<table class="widefat als-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Source', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Translation', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Case', 'advanced-language-switcher' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'advanced-language-switcher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $als_terms as $als_term ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $als_term['source_term'] ); ?></td>
						<td><?php echo esc_html( (string) $als_term['target_term'] ); ?></td>
						<td><?php echo empty( $als_term['case_sensitive'] ) ? esc_html__( 'Any', 'advanced-language-switcher' ) : esc_html__( 'Exact', 'advanced-language-switcher' ); ?></td>
						<td>
							<button type="button" class="button-link als-danger" data-als-delete-glossary="<?php echo esc_attr( (string) $als_term['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'advanced-language-switcher' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
