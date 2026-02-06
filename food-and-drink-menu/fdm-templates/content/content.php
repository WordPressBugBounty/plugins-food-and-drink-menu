<div class="fdm-item-content">
	<?php echo wp_kses_post( $this->content ); ?>
</div>
<?php
global $fdm_controller;
if ( ( $fdm_controller->settings->get_setting( 'fdm-pro-style' ) == 'classic' or $fdm_controller->settings->get_setting( 'fdm-pro-style' ) == 'image' ) and ! $fdm_controller->settings->get_setting( 'fdm-enable-ordering' ) ) {

	echo '</div>';
}