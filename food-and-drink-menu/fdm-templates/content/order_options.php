<div class="fdm-order-item-options">
	<ul>
		<?php foreach ( (array) $this->selected_options as $option ) { ?>
			<?php if ( ! isset( $this->ordering_options[$option] ) || ! is_array( $this->ordering_options[$option] ) ) { continue; } ?>
			<li><?php echo esc_html( $this->ordering_options[$option]['name'] . ( ! empty( $this->ordering_options[$option]['cost'] ) ? ' (' . fdm_format_price( $this->ordering_options[$option]['cost'] ) . ')' : '' ) ); ?></li>
		<?php } ?>
	</ul>
	<?php if ( $this->note ) { ?>
		<p>"<?php echo esc_html( $this->note ); ?>"</p>
	<?php } ?> 
</div>
