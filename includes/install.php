<?php


	/*******************************************
	 * GNU-Mailman Installation Functions
	 *******************************************/

	/**
	 * Function to setup plugin defaults on plugin activation
	 *
	 * @since   1.0.0
	 */
	function gm_options_install() {

		// Set Default Frequency (1 Hour).
		add_site_option( 'gnumailman_update_frequency', 60 * 60 );
		// Set Timeout (30 Seconds).
		add_site_option( 'gnumailman_default_timeout', 30 );

	}

	register_activation_hook( KM_PLUGIN_FILE, __NAMESPACE__ . '\\gm_options_install' );

