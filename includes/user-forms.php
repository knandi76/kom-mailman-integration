<?php

/*******************************************
 * GNU-Mailman User Profile Page
 *******************************************/

/**
 * HTML Layout for User Profile Page
 *
 * @since   1.0.0
 */
function gm_user_profile_page( $user ) {
	?>
    <h3>Mailing Lists</h3>
    <table class="form-table">
		<?php
		// Get Current Subscriptions.
		$mailing_list_subscriptions = gm_get_user_subscriptions( $user->ID );

		// Get Current Mailing Lists and Loop.
		$mailing_lists = gm_get_mailing_lists();

		foreach ( $mailing_lists as $list ) {
			$checked  = '';
			$disabled = '';
			if ( in_array( $list['id'], $mailing_list_subscriptions ) ) {
				$checked = ' checked="checked"';
			}
			/**
			 * TODO: check the kom-updated-lists
			 */
			if ( function_exists( 'kom_get_infos_for_mailing_lists' ) && ! empty ( KOM_SITE ) && ! empty( kom_get_infos_for_mailing_lists() ) ) {
				if ( in_array( $list['name'], array_keys( kom_get_infos_for_mailing_lists() ), false ) ) {
					$disabled = ' disabled="disabled"';
				}
			}
			?>
            <tr>
                <th><label for="subscribe_<?php echo $list['id']; ?>"><?php echo $list['name']; ?></label></th>
                <td><input name="subscribe[]" type="checkbox" id="subscribe_<?php echo $list['id']; ?>" value="<?php echo $list['id']; ?>" <?php echo $checked . $disabled; ?> />
                    Subscribe
                    to <?php echo $list['name']; ?> list
                </td>
            </tr>
		<?php } ?>
    </table>

	<?php
}

add_action( 'show_user_profile', 'gm_user_profile_page' );
add_action( 'edit_user_profile', 'gm_user_profile_page' );

/**
 * Function to Handle POST update of User Profile Page
 *
 * @since   1.0.0
 */
function gm_user_profile_update( $user_id, $old_user_data ) {
	$user_info = get_userdata( $user_id );

	if ( isset( $_POST['submit'] ) ) {
		// Get Current Subscriptions.
		$mailing_list_subscriptions = gm_get_user_subscriptions( $user_id );

		// If Email address changed, remove from all active lists.
		if ( $old_user_data->user_email !== $user_info->data->user_email ) {
			foreach ( $mailing_list_subscriptions as $list_id ) {
				gm_unsubscribe_user_list( $list_id, $user_id );
			}
		}

		if ( isset( $_POST['subscribe'] ) ) {
			$subscriptions = $_POST['subscribe'];
		} else {
			$subscriptions = array();
		}

		if ( ! empty( $subscriptions ) ) {
			// Loop Through POST Values.
			foreach ( $subscriptions as $list_id ) {
				if ( ! in_array( $list_id, $mailing_list_subscriptions, false ) ) {
					// Subscribe to this Mailing List.
					gm_subscribe_user_list( $list_id, $user_id );
				}
			}
		}

		if ( ! empty( $mailing_list_subscriptions  ) ) {
			// Loop Through Current Mailing List.
			foreach ( $mailing_list_subscriptions as $list_id ) {
				if ( ! in_array( $list_id, $subscriptions, false ) ) {
					// Key doesn't exist, unsubscribe to this Mailing List.
					if ( function_exists( 'kom_get_special_lists_id' ) && ! empty ( KOM_SITE ) && ! empty( kom_get_special_lists_id() ) ) {
						if ( in_array( $list_id, kom_get_special_lists_id() , false ) ) {
							continue;
						}
					}
					gm_unsubscribe_user_list( $list_id, $user_id );
				}
			}
		}
	}
}

add_action( 'profile_update', __NAMESPACE__ . '\\gm_user_profile_update', 10, 2 );

