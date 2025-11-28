<?php
/**
 * Import/Export page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get all lists for filter dropdown.
use MSKD\Services\List_Service;
$list_service = new List_Service();
$lists = $list_service->get_all();
?>

<div class="wrap mskd-wrap">
	<div class="mskd-page-header">
		<h1>
			<span class="dashicons dashicons-database-import"></span>
			<?php esc_html_e( 'Import / Export', 'mail-system-by-katsarov-design' ); ?>
		</h1>
		<p class="mskd-page-description">
			<?php esc_html_e( 'Manage your subscriber data with easy import and export functionality.', 'mail-system-by-katsarov-design' ); ?>
		</p>
	</div>

	<?php settings_errors( 'mskd_messages' ); ?>

	<div class="mskd-import-export-container">
		<!-- Export Section -->
		<div class="mskd-card mskd-card-export">
			<div class="mskd-card-header">
				<div class="mskd-card-icon mskd-card-icon-export">
					<span class="dashicons dashicons-download"></span>
				</div>
				<div class="mskd-card-header-content">
					<h2 class="mskd-card-title">
						<?php esc_html_e( 'Export Data', 'mail-system-by-katsarov-design' ); ?>
					</h2>
					<p class="mskd-card-subtitle">
						<?php esc_html_e( 'Download subscribers or lists as CSV', 'mail-system-by-katsarov-design' ); ?>
					</p>
				</div>
			</div>

			<div class="mskd-card-body">
				<form method="post" action="">
					<?php wp_nonce_field( 'mskd_export', 'mskd_nonce' ); ?>
					<input type="hidden" name="export_format" value="csv">

					<div class="mskd-form-group">
						<label for="export_type" class="mskd-form-label">
							<span class="dashicons dashicons-category"></span>
							<?php esc_html_e( 'Export type', 'mail-system-by-katsarov-design' ); ?>
						</label>
						<select name="export_type" id="export_type" class="mskd-form-select">
							<option value="subscribers"><?php esc_html_e( 'Subscribers', 'mail-system-by-katsarov-design' ); ?></option>
							<option value="lists"><?php esc_html_e( 'Lists', 'mail-system-by-katsarov-design' ); ?></option>
						</select>
					</div>

					<div class="mskd-export-subscribers-options">
						<div class="mskd-form-row">
							<div class="mskd-form-group mskd-form-group-half">
								<label for="export_list_id" class="mskd-form-label">
									<span class="dashicons dashicons-list-view"></span>
									<?php esc_html_e( 'Filter by list', 'mail-system-by-katsarov-design' ); ?>
								</label>
								<select name="export_list_id" id="export_list_id" class="mskd-form-select">
									<option value=""><?php esc_html_e( 'All lists', 'mail-system-by-katsarov-design' ); ?></option>
									<?php foreach ( $lists as $list ) : ?>
										<option value="<?php echo esc_attr( $list->id ); ?>">
											<?php echo esc_html( $list->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="mskd-form-group mskd-form-group-half">
								<label for="export_status" class="mskd-form-label">
									<span class="dashicons dashicons-flag"></span>
									<?php esc_html_e( 'Filter by status', 'mail-system-by-katsarov-design' ); ?>
								</label>
								<select name="export_status" id="export_status" class="mskd-form-select">
									<option value=""><?php esc_html_e( 'All statuses', 'mail-system-by-katsarov-design' ); ?></option>
									<option value="active"><?php esc_html_e( 'Active', 'mail-system-by-katsarov-design' ); ?></option>
									<option value="inactive"><?php esc_html_e( 'Inactive', 'mail-system-by-katsarov-design' ); ?></option>
									<option value="unsubscribed"><?php esc_html_e( 'Unsubscribed', 'mail-system-by-katsarov-design' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<div class="mskd-card-actions">
						<button type="submit" name="mskd_export" class="button button-primary button-hero">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export to CSV', 'mail-system-by-katsarov-design' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Import Section -->
		<div class="mskd-card mskd-card-import">
			<div class="mskd-card-header">
				<div class="mskd-card-icon mskd-card-icon-import">
					<span class="dashicons dashicons-upload"></span>
				</div>
				<div class="mskd-card-header-content">
					<h2 class="mskd-card-title">
						<?php esc_html_e( 'Import Data', 'mail-system-by-katsarov-design' ); ?>
					</h2>
					<p class="mskd-card-subtitle">
						<?php esc_html_e( 'Upload subscribers or lists from CSV', 'mail-system-by-katsarov-design' ); ?>
					</p>
				</div>
			</div>

			<div class="mskd-card-body">
				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'mskd_import', 'mskd_nonce' ); ?>
					<input type="hidden" name="import_format" value="csv">

					<div class="mskd-form-group">
						<label for="import_type" class="mskd-form-label">
							<span class="dashicons dashicons-category"></span>
							<?php esc_html_e( 'Import type', 'mail-system-by-katsarov-design' ); ?>
						</label>
						<select name="import_type" id="import_type" class="mskd-form-select">
							<option value="subscribers"><?php esc_html_e( 'Subscribers', 'mail-system-by-katsarov-design' ); ?></option>
							<option value="lists"><?php esc_html_e( 'Lists', 'mail-system-by-katsarov-design' ); ?></option>
						</select>
					</div>

					<div class="mskd-form-group">
						<label for="import_file" class="mskd-form-label">
							<span class="dashicons dashicons-media-spreadsheet"></span>
							<?php esc_html_e( 'CSV File', 'mail-system-by-katsarov-design' ); ?>
						</label>
						<div class="mskd-file-upload-area" id="mskd-file-upload-area">
							<div class="mskd-file-upload-content">
								<span class="dashicons dashicons-cloud-upload"></span>
								<p class="mskd-file-upload-text">
									<?php esc_html_e( 'Drag & drop your CSV file here or', 'mail-system-by-katsarov-design' ); ?>
									<span class="mskd-file-upload-link" role="button" tabindex="0"><?php esc_html_e( 'browse', 'mail-system-by-katsarov-design' ); ?></span>
								</p>
								<p class="mskd-file-upload-hint">
									<?php esc_html_e( 'Maximum file size: 5MB', 'mail-system-by-katsarov-design' ); ?>
								</p>
							</div>
							<input type="file" name="import_file" id="import_file" accept=".csv" required class="mskd-file-input">
							<div class="mskd-file-selected mskd-hidden">
								<span class="dashicons dashicons-media-spreadsheet"></span>
								<span class="mskd-file-name"></span>
								<button type="button" class="mskd-file-remove" aria-label="<?php esc_attr_e( 'Remove selected file', 'mail-system-by-katsarov-design' ); ?>">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</div>
					</div>

					<div class="mskd-import-subscribers-options">
						<div class="mskd-form-group">
							<label class="mskd-form-label">
								<span class="dashicons dashicons-admin-settings"></span>
								<?php esc_html_e( 'Import options', 'mail-system-by-katsarov-design' ); ?>
							</label>
							<div class="mskd-checkbox-group">
								<label class="mskd-checkbox-item">
									<input type="checkbox" name="update_existing" value="1">
									<span class="mskd-checkbox-label">
										<strong><?php esc_html_e( 'Update existing subscribers', 'mail-system-by-katsarov-design' ); ?></strong>
										<span class="mskd-checkbox-description">
											<?php esc_html_e( 'If a subscriber with the same email already exists, update their information.', 'mail-system-by-katsarov-design' ); ?>
										</span>
									</span>
								</label>
								<label class="mskd-checkbox-item">
									<input type="checkbox" name="assign_lists" value="1" checked>
									<span class="mskd-checkbox-label">
										<strong><?php esc_html_e( 'Assign to lists from file', 'mail-system-by-katsarov-design' ); ?></strong>
										<span class="mskd-checkbox-description">
											<?php esc_html_e( 'Assign subscribers to lists specified in the "lists" column. New lists will be created automatically.', 'mail-system-by-katsarov-design' ); ?>
										</span>
									</span>
								</label>
							</div>
						</div>
					</div>

					<div class="mskd-card-actions">
						<button type="submit" name="mskd_import" class="button button-primary button-hero">
							<span class="dashicons dashicons-upload"></span>
							<?php esc_html_e( 'Import from CSV', 'mail-system-by-katsarov-design' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- File Format Info -->
		<div class="mskd-card mskd-card-info mskd-card-docs">
			<div class="mskd-card-header">
				<div class="mskd-card-icon mskd-card-icon-info">
					<span class="dashicons dashicons-book-alt"></span>
				</div>
				<div class="mskd-card-header-content">
					<h2 class="mskd-card-title">
						<?php esc_html_e( 'File Format Guide', 'mail-system-by-katsarov-design' ); ?>
					</h2>
					<p class="mskd-card-subtitle">
						<?php esc_html_e( 'Learn how to format your CSV files for successful imports', 'mail-system-by-katsarov-design' ); ?>
					</p>
				</div>
			</div>

			<div class="mskd-card-body">
				<div class="mskd-docs-grid">
					<!-- Subscribers Format -->
					<div class="mskd-docs-section">
						<div class="mskd-docs-section-header">
							<span class="dashicons dashicons-groups"></span>
							<h3><?php esc_html_e( 'Subscribers CSV Format', 'mail-system-by-katsarov-design' ); ?></h3>
						</div>
						<p class="mskd-docs-intro">
							<?php esc_html_e( 'The CSV file must contain at minimum an "email" column.', 'mail-system-by-katsarov-design' ); ?>
						</p>
						<div class="mskd-docs-columns">
							<div class="mskd-docs-column mskd-docs-column-required">
								<span class="mskd-docs-column-badge"><?php esc_html_e( 'Required', 'mail-system-by-katsarov-design' ); ?></span>
								<code>email</code>
								<span class="mskd-docs-column-desc"><?php esc_html_e( 'Email address', 'mail-system-by-katsarov-design' ); ?></span>
							</div>
							<div class="mskd-docs-column">
								<span class="mskd-docs-column-badge mskd-docs-column-badge-optional"><?php esc_html_e( 'Optional', 'mail-system-by-katsarov-design' ); ?></span>
								<code>first_name</code>
								<span class="mskd-docs-column-desc"><?php esc_html_e( 'First name', 'mail-system-by-katsarov-design' ); ?></span>
							</div>
							<div class="mskd-docs-column">
								<span class="mskd-docs-column-badge mskd-docs-column-badge-optional"><?php esc_html_e( 'Optional', 'mail-system-by-katsarov-design' ); ?></span>
								<code>last_name</code>
								<span class="mskd-docs-column-desc"><?php esc_html_e( 'Last name', 'mail-system-by-katsarov-design' ); ?></span>
							</div>
							<div class="mskd-docs-column">
								<span class="mskd-docs-column-badge mskd-docs-column-badge-optional"><?php esc_html_e( 'Optional', 'mail-system-by-katsarov-design' ); ?></span>
								<code>status</code>
								<span class="mskd-docs-column-desc"><?php esc_html_e( 'active, inactive, or unsubscribed', 'mail-system-by-katsarov-design' ); ?></span>
							</div>
							<div class="mskd-docs-column">
								<span class="mskd-docs-column-badge mskd-docs-column-badge-optional"><?php esc_html_e( 'Optional', 'mail-system-by-katsarov-design' ); ?></span>
								<code>lists</code>
								<span class="mskd-docs-column-desc"><?php esc_html_e( 'List names separated by semicolon (;)', 'mail-system-by-katsarov-design' ); ?></span>
							</div>
						</div>
						<div class="mskd-docs-example">
							<div class="mskd-docs-example-header">
								<span class="dashicons dashicons-editor-code"></span>
								<?php esc_html_e( 'Example', 'mail-system-by-katsarov-design' ); ?>
							</div>
							<pre class="mskd-code-example">email,first_name,last_name,status,lists
john@example.com,John,Doe,active,Newsletter;Updates
jane@example.com,Jane,Smith,active,Newsletter</pre>
						</div>
					</div>

					<!-- Lists Format -->
					<div class="mskd-docs-section">
						<div class="mskd-docs-section-header">
							<span class="dashicons dashicons-list-view"></span>
							<h3><?php esc_html_e( 'Lists CSV Format', 'mail-system-by-katsarov-design' ); ?></h3>
						</div>
						<p class="mskd-docs-intro">
							<?php esc_html_e( 'The CSV file must contain at minimum a "name" column.', 'mail-system-by-katsarov-design' ); ?>
						</p>
						<div class="mskd-docs-columns">
							<div class="mskd-docs-column mskd-docs-column-required">
								<span class="mskd-docs-column-badge"><?php esc_html_e( 'Required', 'mail-system-by-katsarov-design' ); ?></span>
								<code>name</code>
								<span class="mskd-docs-column-desc"><?php esc_html_e( 'List name', 'mail-system-by-katsarov-design' ); ?></span>
							</div>
							<div class="mskd-docs-column">
								<span class="mskd-docs-column-badge mskd-docs-column-badge-optional"><?php esc_html_e( 'Optional', 'mail-system-by-katsarov-design' ); ?></span>
								<code>description</code>
								<span class="mskd-docs-column-desc"><?php esc_html_e( 'List description', 'mail-system-by-katsarov-design' ); ?></span>
							</div>
						</div>
						<div class="mskd-docs-example">
							<div class="mskd-docs-example-header">
								<span class="dashicons dashicons-editor-code"></span>
								<?php esc_html_e( 'Example', 'mail-system-by-katsarov-design' ); ?>
							</div>
							<pre class="mskd-code-example">name,description
Newsletter,Weekly newsletter subscribers
Updates,Product update notifications</pre>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		const fileInput = document.getElementById('import_file');
		const uploadArea = document.getElementById('mskd-file-upload-area');

		// Exit early if required elements are not found.
		if (!fileInput || !uploadArea) {
			return;
		}

		const fileContent = uploadArea.querySelector('.mskd-file-upload-content');
		const fileSelected = uploadArea.querySelector('.mskd-file-selected');
		const fileName = uploadArea.querySelector('.mskd-file-name');
		const fileRemove = uploadArea.querySelector('.mskd-file-remove');
		const browseLink = uploadArea.querySelector('.mskd-file-upload-link');

		// Exit early if required child elements are not found.
		if (!fileContent || !fileSelected || !fileName || !fileRemove) {
			return;
		}

		// Handle file selection.
		fileInput.addEventListener('change', function() {
			if (this.files.length > 0) {
				showSelectedFile(this.files[0].name);
			}
		});

		// Handle browse link click and keyboard activation.
		if (browseLink) {
			browseLink.addEventListener('click', function(e) {
				e.preventDefault();
				fileInput.click();
			});

			browseLink.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					fileInput.click();
				}
			});
		}

		// Handle drag and drop.
		uploadArea.addEventListener('dragover', function(e) {
			e.preventDefault();
			uploadArea.classList.add('mskd-file-upload-area-dragover');
		});

		uploadArea.addEventListener('dragleave', function(e) {
			e.preventDefault();
			uploadArea.classList.remove('mskd-file-upload-area-dragover');
		});

		uploadArea.addEventListener('drop', function(e) {
			e.preventDefault();
			uploadArea.classList.remove('mskd-file-upload-area-dragover');
			if (e.dataTransfer.files.length > 0) {
				fileInput.files = e.dataTransfer.files;
				showSelectedFile(e.dataTransfer.files[0].name);
			}
		});

		// Handle file remove.
		fileRemove.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			fileInput.value = '';
			hideSelectedFile();
		});

		function showSelectedFile(name) {
			fileName.textContent = name;
			fileContent.classList.add('mskd-hidden');
			fileSelected.classList.remove('mskd-hidden');
			uploadArea.classList.add('mskd-file-upload-area-has-file');
		}

		function hideSelectedFile() {
			fileContent.classList.remove('mskd-hidden');
			fileSelected.classList.add('mskd-hidden');
			uploadArea.classList.remove('mskd-file-upload-area-has-file');
		}
	});
})();
</script>
