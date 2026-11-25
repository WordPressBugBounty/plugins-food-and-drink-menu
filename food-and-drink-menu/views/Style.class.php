<?php

/**
 * Class for any menu style
 *
 * Handles file dependencies, UI selection output, etc.
 *
 * @since 1.1
 */

class fdmStyle extends fdmBase {

	public $id = '';
	public $label = '';
	public $css = array();
	public $js = array();
	public $dependencies = array();
	public $asset_versions = array();
	
	/**
	 * Enqueue the stylesheets and javascript files
	 */
	public function enqueue_assets() {
		foreach( $this->css as $key => $file ) {
			wp_enqueue_style( 'fdm-css-' . $key, $file, array(), $this->asset_versions[ $key ] ?? FDM_VERSION );
		}
		foreach( $this->js as $key => $file ) {
			wp_enqueue_script('fdm-js-' . $key, $file, array( 'jquery' ), $this->asset_versions[ $key ] ?? FDM_VERSION, true );
		}
		do_action( 'fdm_style_assets_enqueued', $this );
	}

}

