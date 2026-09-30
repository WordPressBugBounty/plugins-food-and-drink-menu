<div<?php echo fdm_format_classes( $this->source_classes ); ?>>
	<div class="fdm-item-src-name">
		<p class="src-title"><?php echo esc_html( $this->source_name ); ?></p>

		<?php if ( isset( $this->source_desc ) ) : ?>
			<p class="fdm-item-src-desc"><?php echo esc_html( $this->source_desc ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $this->source_address ) ) : ?>
			<p class="fdm-item-src-address"><?php echo esc_html( $this->source_address ); ?></p>
		<?php endif; ?>

	</div>

	<?php if ( $this->source_map_enabled ) : global $fdm_controller; ?>
		<img class="fdm-item-src-map" src="https://maps.googleapis.com/maps/api/staticmap?markers=size:normal%7Ccolor:blue%7C<?php echo esc_attr( rawurlencode( $this->source_address ) ); if ( isset( $this->source_zoom ) ) : ?>&amp;zoom=<?php echo (int) $this->source_zoom; endif; ?>&amp;size=300x300&amp;scale=2<?php if ( !empty( $fdm_controller->settings->get_setting('fdm-google-map-api-key') ) ) : echo '&amp;key=' . esc_attr( rawurlencode( $fdm_controller->settings->get_setting('fdm-google-map-api-key') ) ); endif; ?>" alt="<?php echo esc_attr( $this->source_name ); ?>">
	<?php endif; ?>

	<div class="clearfix"></div>

</div>
