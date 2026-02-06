<?php global $fdm_controller; ?>
<div 
  class="fdm-menu-cart fdm-cart-menu-item" 
  data-postid="<?php echo esc_attr( $this->id ); ?>"
  data-item_identifier="<?php echo esc_attr( $this->item_identifier ); ?>" >

	<div class="fdm-cart-item-panel">

		<?php
		if ( $fdm_controller->settings->get_setting( 'fdm-order-cart-location' ) == 'side' and $fdm_controller->settings->get_setting( 'fdm-pro-style' ) == 'luxe' ) {

			echo '<div class="fdm-item-non-image-container">';
		}

		echo wp_kses( $this->print_cart_elements( 'body' ), $this->allowed_tags );

		if ( $fdm_controller->settings->get_setting( 'fdm-order-cart-location' ) == 'side' and ( $fdm_controller->settings->get_setting( 'fdm-pro-style' ) == 'refined' or $fdm_controller->settings->get_setting( 'fdm-pro-style' ) == 'ordering' ) ) {

			echo '<div class="fdm-item-non-image-container">';
		}

		if ( $fdm_controller->settings->get_setting( 'fdm-order-cart-location' ) == 'side' or $fdm_controller->settings->get_setting( 'fdm-pro-style' ) == 'image' or $fdm_controller->settings->get_setting( 'fdm-pro-style' ) == 'classic' ) {

			echo '</div>';
		}
		?>

		<div class="clearfix"></div>
	</div>

</div>
