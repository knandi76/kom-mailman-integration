<?php


	/*******************************************
	 * GNU-Mailman Automatic Functions
	 *******************************************/

	/**
	 * On WordPress User Registration, auto subscribe user to
	 * all lists that are set to be autosubscribed.
	 *
	 * @param   int  $user_id  WordPress User Id.
	 *
	 * @since   1.0.0
	 */
	function gm_on_register( $user_id ) {
		foreach ( gm_get_mailing_lists() as $list_id => $list ) {
			if ( $list['autosub'] ) {
				// Subscribe User to List.
				gm_subscribe_user_list( $list_id, $user_id );
			}
		}
	}

	add_action( 'user_register', __NAMESPACE__ . '\\gm_on_register' );

	/**
	 * On WordPress User Delete, unsubscribe user to all the mailing
	 * lists they are current subscribed to.
	 *
	 * @param   int  $user_id  WordPress User Id.
	 *
	 * @since   1.0.0
	 */
	function gm_on_delete( $user_id ) {
		foreach ( gm_get_user_subscriptions( $user_id ) as $list_id ) {
			// Unsubscribe User to List.
			gm_unsubscribe_user_list( $list_id, $user_id );
		}
	}

	add_action( 'delete_user', __NAMESPACE__ . '\\gm_on_delete' );

