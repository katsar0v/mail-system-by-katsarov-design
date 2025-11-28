<?php
/**
 * Form Gallery Template
 *
 * Displays available subscription forms with their shortcodes for easy copying.
 * This is a read-only page for users to discover and embed forms.
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="mskd-form-gallery-wrap">
	<?php if ( ! empty( $atts['title'] ) ) : ?>
		<h3 class="mskd-gallery-title"><?php echo esc_html( $atts['title'] ); ?></h3>
	<?php endif; ?>

	<?php if ( empty( $lists ) ) : ?>
		<p class="mskd-gallery-empty">
			<?php esc_html_e( 'No subscription forms available yet.', 'mail-system-by-katsarov-design' ); ?>
		</p>
	<?php else : ?>
		<p class="mskd-gallery-description">
			<?php esc_html_e( 'Copy any shortcode below and paste it into your page or post to display the subscription form.', 'mail-system-by-katsarov-design' ); ?>
		</p>

		<div class="mskd-form-gallery">
			<?php foreach ( $lists as $list ) : ?>
				<div class="mskd-gallery-item">
					<div class="mskd-gallery-item-header">
						<h4 class="mskd-gallery-item-title"><?php echo esc_html( $list->name ); ?></h4>
					</div>
					
					<?php if ( ! empty( $list->description ) ) : ?>
						<p class="mskd-gallery-item-description"><?php echo esc_html( $list->description ); ?></p>
					<?php endif; ?>

					<div class="mskd-shortcode-container">
						<code class="mskd-shortcode-code">[mskd_subscribe_form list_id="<?php echo esc_attr( $list->id ); ?>" title="<?php echo esc_attr( $list->name ); ?>"]</code>
						<button type="button" class="mskd-copy-btn" data-shortcode='[mskd_subscribe_form list_id="<?php echo esc_attr( $list->id ); ?>" title="<?php echo esc_attr( $list->name ); ?>"]'>
							<?php esc_html_e( 'Copy', 'mail-system-by-katsarov-design' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>

			<!-- General subscription form (no specific list) -->
			<div class="mskd-gallery-item mskd-gallery-item-general">
				<div class="mskd-gallery-item-header">
					<h4 class="mskd-gallery-item-title"><?php esc_html_e( 'General Subscription Form', 'mail-system-by-katsarov-design' ); ?></h4>
				</div>
				<p class="mskd-gallery-item-description">
					<?php esc_html_e( 'Subscribe without assigning to a specific list.', 'mail-system-by-katsarov-design' ); ?>
				</p>

				<div class="mskd-shortcode-container">
					<code class="mskd-shortcode-code">[mskd_subscribe_form]</code>
					<button type="button" class="mskd-copy-btn" data-shortcode='[mskd_subscribe_form]'>
						<?php esc_html_e( 'Copy', 'mail-system-by-katsarov-design' ); ?>
					</button>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
