<?php
/**
 * Import/Export page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get all lists for filter dropdown.
use MSKD\Services\List_Service;
$list_service = new List_Service();
$lists = $list_service->get_all();
?>

<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'Import / Export', 'mail-system-by-katsarov-design' ); ?></h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<div class="mskd-import-export-container">
		<!-- Export Section -->
		<div class="mskd-card">
			<h2 class="mskd-card-title">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export', 'mail-system-by-katsarov-design' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Export subscribers or lists to a CSV or JSON file for backup or migration.', 'mail-system-by-katsarov-design' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'mskd_export', 'mskd_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="export_type"><?php esc_html_e( 'Export type', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<select name="export_type" id="export_type" class="regular-text">
								<option value="subscribers"><?php esc_html_e( 'Subscribers', 'mail-system-by-katsarov-design' ); ?></option>
								<option value="lists"><?php esc_html_e( 'Lists', 'mail-system-by-katsarov-design' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="export_format"><?php esc_html_e( 'Format', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<select name="export_format" id="export_format" class="regular-text">
								<option value="csv">CSV</option>
								<option value="json">JSON</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'CSV is recommended for Excel and spreadsheet applications.', 'mail-system-by-katsarov-design' ); ?>
							</p>
						</td>
					</tr>
					<tr class="mskd-export-subscribers-options">
						<th scope="row">
							<label for="export_list_id"><?php esc_html_e( 'Filter by list', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<select name="export_list_id" id="export_list_id" class="regular-text">
								<option value=""><?php esc_html_e( 'All lists', 'mail-system-by-katsarov-design' ); ?></option>
								<?php foreach ( $lists as $list ) : ?>
									<option value="<?php echo esc_attr( $list->id ); ?>">
										<?php echo esc_html( $list->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr class="mskd-export-subscribers-options">
						<th scope="row">
							<label for="export_status"><?php esc_html_e( 'Filter by status', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<select name="export_status" id="export_status" class="regular-text">
								<option value=""><?php esc_html_e( 'All statuses', 'mail-system-by-katsarov-design' ); ?></option>
								<option value="active"><?php esc_html_e( 'Active', 'mail-system-by-katsarov-design' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'mail-system-by-katsarov-design' ); ?></option>
								<option value="unsubscribed"><?php esc_html_e( 'Unsubscribed', 'mail-system-by-katsarov-design' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="mskd_export" class="button button-primary" value="<?php esc_attr_e( 'Export', 'mail-system-by-katsarov-design' ); ?>">
				</p>
			</form>
		</div>

		<!-- Import Section -->
		<div class="mskd-card">
			<h2 class="mskd-card-title">
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Import', 'mail-system-by-katsarov-design' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Import subscribers or lists from a CSV or JSON file.', 'mail-system-by-katsarov-design' ); ?>
			</p>

			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'mskd_import', 'mskd_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="import_type"><?php esc_html_e( 'Import type', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<select name="import_type" id="import_type" class="regular-text">
								<option value="subscribers"><?php esc_html_e( 'Subscribers', 'mail-system-by-katsarov-design' ); ?></option>
								<option value="lists"><?php esc_html_e( 'Lists', 'mail-system-by-katsarov-design' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="import_format"><?php esc_html_e( 'Format', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<select name="import_format" id="import_format" class="regular-text">
								<option value="csv">CSV</option>
								<option value="json">JSON</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="import_file"><?php esc_html_e( 'File', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<input type="file" name="import_file" id="import_file" accept=".csv,.json" required>
							<p class="description">
								<?php esc_html_e( 'Maximum file size: 5MB.', 'mail-system-by-katsarov-design' ); ?>
							</p>
						</td>
					</tr>
					<tr class="mskd-import-subscribers-options">
						<th scope="row">
							<?php esc_html_e( 'Options', 'mail-system-by-katsarov-design' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="update_existing" value="1">
									<?php esc_html_e( 'Update existing subscribers', 'mail-system-by-katsarov-design' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'If a subscriber with the same email already exists, update their information.', 'mail-system-by-katsarov-design' ); ?>
								</p>
								<br>
								<label>
									<input type="checkbox" name="assign_lists" value="1" checked>
									<?php esc_html_e( 'Assign to lists from file', 'mail-system-by-katsarov-design' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'If the file contains a "lists" column, assign subscribers to those lists. Lists will be created if they do not exist.', 'mail-system-by-katsarov-design' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="mskd_import" class="button button-primary" value="<?php esc_attr_e( 'Import', 'mail-system-by-katsarov-design' ); ?>">
				</p>
			</form>
		</div>

		<!-- File Format Info -->
		<div class="mskd-card mskd-card-info">
			<h2 class="mskd-card-title">
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'File format requirements', 'mail-system-by-katsarov-design' ); ?>
			</h2>

			<h3><?php esc_html_e( 'Subscribers CSV format', 'mail-system-by-katsarov-design' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'The CSV file must contain at minimum an "email" column. Optional columns:', 'mail-system-by-katsarov-design' ); ?>
			</p>
			<ul>
				<li><code>email</code> - <?php esc_html_e( 'Email address (required)', 'mail-system-by-katsarov-design' ); ?></li>
				<li><code>first_name</code> - <?php esc_html_e( 'First name', 'mail-system-by-katsarov-design' ); ?></li>
				<li><code>last_name</code> - <?php esc_html_e( 'Last name', 'mail-system-by-katsarov-design' ); ?></li>
				<li><code>status</code> - <?php esc_html_e( 'Status: active, inactive, or unsubscribed (default: active)', 'mail-system-by-katsarov-design' ); ?></li>
				<li><code>lists</code> - <?php esc_html_e( 'List names separated by semicolon (;)', 'mail-system-by-katsarov-design' ); ?></li>
			</ul>

			<h4><?php esc_html_e( 'Example:', 'mail-system-by-katsarov-design' ); ?></h4>
			<pre class="mskd-code-example">email,first_name,last_name,status,lists
john@example.com,John,Doe,active,Newsletter;Updates
jane@example.com,Jane,Smith,active,Newsletter</pre>

			<h3><?php esc_html_e( 'Lists CSV format', 'mail-system-by-katsarov-design' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'The CSV file must contain at minimum a "name" column. Optional columns:', 'mail-system-by-katsarov-design' ); ?>
			</p>
			<ul>
				<li><code>name</code> - <?php esc_html_e( 'List name (required)', 'mail-system-by-katsarov-design' ); ?></li>
				<li><code>description</code> - <?php esc_html_e( 'List description', 'mail-system-by-katsarov-design' ); ?></li>
			</ul>

			<h4><?php esc_html_e( 'Example:', 'mail-system-by-katsarov-design' ); ?></h4>
			<pre class="mskd-code-example">name,description
Newsletter,Weekly newsletter subscribers
Updates,Product update notifications</pre>

			<h3><?php esc_html_e( 'JSON format', 'mail-system-by-katsarov-design' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'JSON files should contain an array of objects with the same field names as the CSV columns.', 'mail-system-by-katsarov-design' ); ?>
			</p>

			<h4><?php esc_html_e( 'Example:', 'mail-system-by-katsarov-design' ); ?></h4>
			<pre class="mskd-code-example">[
  {
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "status": "active",
    "lists": ["Newsletter", "Updates"]
  }
]</pre>
		</div>
	</div>
</div>

<style>
.mskd-import-export-container {
	display: flex;
	flex-wrap: wrap;
	gap: 20px;
	margin-top: 20px;
}

.mskd-import-export-container .mskd-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	flex: 1 1 400px;
	max-width: 600px;
}

.mskd-import-export-container .mskd-card-info {
	flex: 1 1 100%;
	max-width: 100%;
}

.mskd-card-title {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 0;
	margin-bottom: 10px;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}

.mskd-card-title .dashicons {
	color: #2271b1;
}

.mskd-code-example {
	background: #f6f7f7;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 12px;
	overflow-x: auto;
	font-family: monospace;
	font-size: 12px;
	line-height: 1.5;
	white-space: pre;
}

.mskd-card-info h3 {
	margin-top: 20px;
	margin-bottom: 10px;
}

.mskd-card-info h4 {
	margin-top: 15px;
	margin-bottom: 5px;
}

.mskd-card-info ul {
	margin-left: 20px;
}

.mskd-card-info ul li {
	margin-bottom: 5px;
}

.mskd-card-info ul li code {
	background: #f0f0f1;
	padding: 2px 6px;
	border-radius: 3px;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Toggle subscriber-specific export options.
	$('#export_type').on('change', function() {
		if ($(this).val() === 'subscribers') {
			$('.mskd-export-subscribers-options').show();
		} else {
			$('.mskd-export-subscribers-options').hide();
		}
	});

	// Toggle subscriber-specific import options.
	$('#import_type').on('change', function() {
		if ($(this).val() === 'subscribers') {
			$('.mskd-import-subscribers-options').show();
		} else {
			$('.mskd-import-subscribers-options').hide();
		}
	});

	// Update accepted file types based on format.
	$('#import_format').on('change', function() {
		var format = $(this).val();
		$('#import_file').attr('accept', '.' + format);
	});
});
</script>
