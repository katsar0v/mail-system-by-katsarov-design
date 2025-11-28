<?php
/**
 * Subscribe form template
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="mskd-subscribe-form-wrap">
	<?php if ( ! empty( $atts['title'] ) ) : ?>
		<h3 class="mskd-form-title"><?php echo esc_html( $atts['title'] ); ?></h3>
	<?php endif; ?>

	<form class="mskd-subscribe-form" method="post">
		<input type="hidden" name="list_id" value="<?php echo esc_attr( $atts['list_id'] ); ?>">
		
		<div class="mskd-form-row">
			<input type="text" name="first_name" 
					placeholder="<?php esc_attr_e( 'Name', 'mail-system-by-katsarov-design' ); ?>" 
					class="mskd-input">
		</div>

		<div class="mskd-form-row">
			<input type="email" name="email" required
					placeholder="<?php esc_attr_e( 'Email *', 'mail-system-by-katsarov-design' ); ?>" 
					class="mskd-input">
		</div>

		<div class="mskd-form-row">
			<button type="submit" class="mskd-submit-btn">
				<?php _e( 'Subscribe', 'mail-system-by-katsarov-design' ); ?>
			</button>
		</div>

		<div class="mskd-form-message" style="display: none;"></div>
	</form>
</div>
