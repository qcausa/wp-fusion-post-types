<?php 

class WPF_CPT_Integrations_Base {

	public function __construct() {

		$this->init();

		if ( isset( $this->slug ) ) {
			wp_fusion_postTypes()->integrations->{$this->slug} = $this;
		}

	}
	
	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// intentionally left blank

	}

}