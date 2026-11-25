<div <?php echo fdm_format_classes( $this->classes ); ?> data-menu-id='<?php echo esc_attr( $this->menu->id ); ?>' data-fdm-instance='<?php echo esc_attr( $this->menu->render_instance_id ); ?>'>

	<h3 class='fdm-filtering-header'>
		<?php echo esc_html( $this->get_label( 'label-filtering' ) ); ?>
	</h3>

	<?php if ( $this->enable_section_filtering && $this->sections ) : ?>
		<div class='fdm-filtering-section fdm-filtering-menu-section'>
			<label class='fdm-filtering-label' for='fdm-section-filter-<?php echo esc_attr( $this->menu->render_instance_id ); ?>'><?php esc_html_e( 'Menu section', 'food-and-drink-menu' ); ?></label>
			<select id='fdm-section-filter-<?php echo esc_attr( $this->menu->render_instance_id ); ?>' class='fdm-filtering-section-input' data-menu-id='<?php echo esc_attr( $this->menu->id ); ?>'>
				<option value=''><?php esc_html_e( 'All sections', 'food-and-drink-menu' ); ?></option>
				<?php foreach ( $this->sections as $slug => $title ) : ?>
					<option value='<?php echo esc_attr( $slug ); ?>'><?php echo esc_html( $title ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>

	<?php global $fdm_controller;
	if ( $fdm_controller->permissions->can_execute( 'filtering' ) ) {
		$fdm_controller->implementation->call( 'filtering', array( 'operation' => 'controls', 'view' => $this ) );
	} ?>

	<?php if ( $this->enable_sorting ) : ?>
		<div class='fdm-filtering-section fdm-filtering-sorting-section'>
			<label class='fdm-filtering-label fdm-filtering-sorting-label'>
				<?php echo esc_html( $this->get_label( 'label-sorting' ) ); ?>
			</label> 
		
			<select class='fdm-filtering-sorting-input' >
				<option value=''></option>
				<?php if ( in_array( 'name', $this->sorting_types, true ) ) : ?>
					<option value='name_asc' ><?php echo esc_html( $this->get_label( 'label-name-asc' ) ); ?></option>
					<option value='name_desc' ><?php echo esc_html( $this->get_label( 'label-name-desc' ) ); ?></option>
				<?php endif; ?>
				<?php if ( $fdm_controller->permissions->can_execute( 'filtering' ) ) {
					$fdm_controller->implementation->call( 'filtering', array( 'operation' => 'sorting_options', 'view' => $this ) );
				} ?>
			</select>

		</div>
	<?php endif; ?>

</div>
