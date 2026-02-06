<?php
global $fdm_controller;

if ( $fdm_controller->settings->get_setting('fdm-pro-style') == 'classic' or $fdm_controller->settings->get_setting('fdm-pro-style') == 'image' ) {

	echo '<div class="fdm-item-non-image-container">';
}
?>

<p class="fdm-item-title"><?php echo esc_html( $this->title ); ?></p>