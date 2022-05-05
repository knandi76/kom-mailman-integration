<?php

	/**
	 * @package GM\Functions
	 */

	/**
	 * Return the Update Frequency of Mailing List data
	 *
	 * @return    int    Number of Seconds
	 * @since   1.0.0
	 */
	function gm_get_update_frequency() {
		return (int) get_site_option( 'gnumailman_update_frequency' );
	}

	/**
	 * Return the Default Timeout of Mailing List Connection Attempt
	 *
	 * @return    int    Number of Seconds
	 * @since   1.0.3
	 */
	function gm_get_default_timeout() {
		return (int) get_site_option( 'gnumailman_default_timeout' );
	}

	/**
	 * Return an array of mailing lists and settings
	 *
	 * @return array
	 * @since   1.0.0
	 */
	function gm_get_mailing_lists() {
		$list_array = maybe_unserialize( get_site_option( 'gnumailman_lists' ) );
		if ( ! is_array( $list_array ) ) {
			return array();
		} else {
			// Check to ensure each mailing list has a unique id.
			$is_update = false;

			foreach ( $list_array as $key => $list ) {
				if ( false === isset( $list['id'] ) ) {
					$is_update = true;
					// Add List Id.
					$unique_id                = gm_create_unique_id();
					$list_array[ $key ]['id'] = $unique_id;

					$list_array[ $unique_id ] = $list_array[ $key ];
					unset( $list_array[ $key ] );
				}
				if ( 32 !== strlen( $key ) ) {
					unset( $list_array[ $key ] );
				}
			}

			if ( true === $is_update ) {
				gm_set_mailing_lists( $list_array );

				return gm_get_mailing_lists();
			}
		}

		return $list_array;
	}

	/**
	 * Set an array of mailing lists to WP settings
	 *
	 * @param   array  $list_array  Array of Mailing Lists.
	 *
	 * @since   1.0.0
	 */
	function gm_set_mailing_lists( $list_array ) {
		if ( ! is_array( $list_array ) ) {
			wp_die( '$listArray is NOT a valid array' );
		}

		return update_site_option( 'gnumailman_lists', $list_array );
	}

	/**
	 * Return a single mailing list
	 *
	 * @param   int  $list_id  Mailing List Id.
	 *
	 * @return array
	 * @since   1.0.0
	 */
	function gm_get_mailing_list( $list_id ) {
		$list_array = gm_get_mailing_lists();

		foreach ( $list_array as $list ) {
			if ( $list['id'] === $list_id ) {
				return $list;
			}
		}

		wp_die( 'Invalid List Id (' . $list_id . ')' );
	}

	/**
	 * Get list of mailing lists users is subscribed to.
	 *
	 * @param   int  $user_id  WordPress User Id.
	 *
	 * @return    array    Array of Lists Subscribed To (e.g. array(1, 2, 5) )
	 * @since   1.0.0
	 */
	function gm_get_user_subscriptions( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$current_subscriptions = get_user_meta(  $user_id, 'gm_subscriptions', true );

		// Check users subscriptions against active mailing list...list.
		$mailing_lists    = gm_get_mailing_lists();
		$mailing_list_ids = array();
		foreach ( $mailing_lists as $list ) {
			$mailing_list_ids[] = $list['id'];
		}

		$data_stale = false;
		foreach ( $current_subscriptions as $key => $list_id ) {
			if ( ! in_array( $list_id, $mailing_list_ids , false ) ) {
				$data_stale = true;
				unset( $current_subscriptions[ $key ] );
			}
		}

		// Data is stale, need to update user's meta.
		if ( true === $data_stale ) {
			// Update User Metadata.
			update_user_meta( $user_id, 'gm_subscriptions', $current_subscriptions );
		}

		$last_update = get_user_meta( $user_id, 'gm_last_update', true );
		//if ( ( time() - gm_get_update_frequency() ) > $last_update ) {
			// Cache time expired...Need to Update!
			$current_subscriptions = gm_update_user_subscriptions( $user_id );
		//}

		return $current_subscriptions;
	}

	/**
	 * Query Mailman for subscriptions and update local cache
	 *
	 * @param   int  $user_id  WordPress User Id (NULL will use current user).
	 *
	 * @return    array
	 * @since   1.0.0
	 */
	function gm_update_user_subscriptions( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user                  = get_user_by( 'id', $user_id );
		$connection_failed     = false;
		$current_subscriptions = array();

		// Loop through each list updating the current subscription list (primary email address).
		foreach ( gm_get_mailing_lists() as $list ) {
			$mailman = new Mailman( $list['url'], $list['pass'], $user->user_email, $user->display_name );

			// Make sure we can connect to the mailing list?
			$ml = $mailman->canConnect();
			if ( false === $ml['connected'] ) {
				echo '<div class="error"><p>' . $ml['error'] . '</p></div>';
				$connection_failed = true;
				continue; // Failed to connect!
			}

			if ( $mailman->isUserSubscribed() ) {
				// Subscribed.
				$current_subscriptions[] = $list['id'];
			}
		}

		// Update User Metadata.
		update_user_meta( $user_id, 'gm_subscriptions',  $current_subscriptions );

		// Don't last update if there was a connection failure!
		if ( ! $connection_failed ) {
			update_user_meta( $user_id, 'gm_last_update', time() );
		}

		return $current_subscriptions;
	}

	/**
	 * Subscribe a User to a List
	 *
	 * @param   int  $list_id  Mailing List Id.
	 * @param   int  $user_id  WordPress User Id (NULL will use current user).
	 *
	 * @return    bool
	 * @since   1.0.0
	 */
	function gm_subscribe_user_list( $list_id, $user_id = null ) {

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_user_by( 'id', $user_id );

		$status = gm_subscribe( $list_id, $user->user_email, $user->display_name );


		if ( $status ) {
			$current_subscriptions   = get_user_meta( $user_id, 'gm_subscriptions', true  );

			if (empty($current_subscriptions) || !is_array($current_subscriptions)) {
				$current_subscriptions = Array();
			}
			$current_subscriptions[] = $list_id;
			$current_subscriptions = array_unique($current_subscriptions);
			update_user_meta( $user_id, 'gm_subscriptions',  $current_subscriptions );


			return true;
		}


		return false;
	}

	/**
	 * Subscribe an Email to a List
	 *
	 * @param   int     $list_id        Mailing List Id.
	 * @param   string  $email_address  Email Address of Subscriber.
	 * @param   string  $display_name   Display Name of Subscriber.
	 *
	 * @return    bool
	 * @since   1.0.5
	 */
	function gm_subscribe( $list_id, $email_address, $display_name ) {
		$list = gm_get_mailing_list( $list_id );

		$mailman = new Mailman( $list['url'], $list['pass'], $email_address, $display_name );

		return $mailman->subscribe();
	}

	/**
	 * Unsubscribe a User to a List
	 *
	 * @param   int  $list_id  Mailing List Id.
	 * @param   int  $user_id  WordPress User Id (NULL will use current user).
	 *
	 * @return    bool
	 * @since   1.0.0
	 */
	function gm_unsubscribe_user_list( $list_id, $user_id = null ) {

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_user_by( 'id', $user_id );

		$status = gm_unsubscribe( $list_id, $user->user_email );

		if ( $status ) {
			$current_subscriptions = get_user_meta( $user_id, 'gm_subscriptions', true ) ;

			$key = array_search( $list_id, $current_subscriptions );
			unset( $current_subscriptions[ $key ] );

			update_user_meta( $user_id, 'gm_subscriptions',  $current_subscriptions );

			return true;
		}

		return false;
	}

	/**
	 * Unsubscribe an Email from a List
	 *
	 * @param   int     $list_id        Mailing List Id.
	 * @param   string  $email_address  Email address.
	 *
	 * @return    bool
	 * @since   1.0.5
	 */
	function gm_unsubscribe( $list_id, $email_address ) {
		$list = gm_get_mailing_list( $list_id );

		$mailman = new Mailman( $list['url'], $list['pass'], $email_address, '' );

		return $mailman->unsubscribe();
	}

	/**
	 * Attempt to connect to a mailing list
	 *
	 * @param   int  $list_url   Mailing List URL.
	 * @param   int  $list_pass  Mailing List Password.
	 *
	 * @return    array
	 * @since   1.0.3
	 */
	function gm_connect_list( $list_url, $list_pass ) {
		$mailman = new Mailman( $list_url, $list_pass );

		return $mailman->canConnect();
	}

	/**
	 * Create a new Unique Id for a list
	 *
	 * @return    string
	 * @since   1.0.5
	 */
	function gm_create_unique_id() {
		return md5( uniqid( mt_rand(), true ) );
	}
