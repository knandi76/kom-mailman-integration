<?php

namespace kom_mailman_integration {
	/**
	 * GNU-Mailman Integration
	 *
	 * @package   Mailman
	 * @author    Ryan Gyure <me@ryan.gy>
	 * @license   GPL-2.0+
	 * @link      http://blog.ryan.gy/applications/wordpress/gnu-mailman/
	 * @copyright 2014 Ryan Gyure
	 */

	/**
	 * Main Plugin class.
	 *
	 * @package Mailman
	 * @author  Ryan Gyure <me@ryan.gy>
	 */
	class Mailman {

		/**
		 * Subscription Action: do unsubscription
		 *
		 * @since   1.0.0
		 * @var     int
		 */
		const USER_MAILMAN_REGISTER_DO_UNSUBSCRIBE = - 1;

		/**
		 * Subscription Status: unsubscribed
		 *
		 * @since   1.0.0
		 * @var     int
		 */
		const USER_MAILMAN_REGISTER_UNSUBSCRIBED = 0;

		/**
		 * Subscription Status: subscribed but temporarily disabled
		 *
		 * @since   1.0.0
		 * @var     int
		 */
		const USER_MAILMAN_REGISTER_SUBSCRIBED_DISABLED = 1;

		/**
		 * Subscription Status: subscribed, receive digests
		 *
		 * @since   1.0.0
		 * @var     int
		 * @todo    Need to complete functionality associated with this value.
		 */
		const USER_MAILMAN_REGISTER_SUBSCRIBED_DIGEST = 2;

		/**
		 * Subscription Status: subscribed, normal delivery
		 *
		 * @since   1.0.0
		 * @var     int
		 */
		const USER_MAILMAN_REGISTER_SUBSCRIBED_NORMAL = 3;

		/**
		 * Mailing List URL
		 *
		 * @since   1.0.0
		 * @var     string
		 */
		private $_mailingListUrl;

		/**
		 * Mailing List Password
		 *
		 * @since   1.0.0
		 * @var     string
		 */
		private $_mailingListPassword;

		/**
		 * Full Name of User
		 *
		 * @since   1.0.0
		 * @var     string
		 */
		private $_fullName;

		/**
		 * Email Address of User
		 *
		 * @since   1.0.0
		 * @var     string
		 */
		private $_emailAddress;

		/**
		 * Initialize the plugin by setting the mailing list
		 * URL and password as well as the user's email
		 * and user's full name.
		 *
		 * @since     1.0.0
		 */
		function __construct(
			$mailingListUrl,
			$mailingListPassword,
			$emailAddress = null,
			$fullName = null
		) {
			$this->_mailingListUrl      = $mailingListUrl;
			$this->_mailingListPassword = $mailingListPassword;

			if ( $this->_mailingListUrl == '' ) {
				throw new Exception( 'Mailing List URL Must Be Specified' );
			}

			if ( $this->_mailingListPassword == '' ) {
				throw new Exception( 'Mailing List Password Must Be Specified' );
			}

			$this->_emailAddress = $emailAddress;
			$this->_fullName     = $fullName;
		}

		/**
		 * Check if user is subscribed to List
		 *
		 * @return    bool
		 * @since    1.0.0
		 */
		public function isUserSubscribed() {
			try {
				// HTTP Request
				$sub = $this->_mailman_get_subscription();
			}
			catch ( Exception $e ) {
				// Unable to connect for some reason.
				return false;
			}

			if ( $sub == self::USER_MAILMAN_REGISTER_SUBSCRIBED_NORMAL or
			     $sub == self::USER_MAILMAN_REGISTER_SUBSCRIBED_DIGEST
			) {
				return true;
			}

			return false;
		}

		/**
		 * Subscribe User to List
		 *
		 * @return    boolean
		 * @since    1.0.0
		 */
		public function subscribe() {
			return $this->_mailman_subscription_update(
				self::USER_MAILMAN_REGISTER_SUBSCRIBED_NORMAL
			);
		}

		/**
		 * Unsubscribe User to List
		 *
		 * @return    boolean
		 * @since   1.0.0
		 */
		public function unsubscribe() {
			return $this->_mailman_subscription_update(
				self::USER_MAILMAN_REGISTER_DO_UNSUBSCRIBE
			);
		}

		/**
		 * Check to see if we can connect to a mailing list
		 *
		 * @return    array
		 * @since    1.0.3
		 */
		public function canConnect() {
			$regurl = rtrim( $this->_mailingListUrl, '/' ) . '/members?findmember=.';
			$regurl .= "&setmemberopts_btn&adminpw=" . urlencode( $this->_mailingListPassword );

			$error     = '';
			$connected = false;

			try {
				// HTTP Request
				$this->_mailman_parse_http( $regurl );
				$connected = true;
			}
			catch ( Exception $e ) {
				$error = $e->getMessage();
			}

			return array( 'connected' => $connected, 'error' => $error );
		}

		private function _mailman_get_subscription() {
			$regurl = rtrim( $this->_mailingListUrl, '/' ) . '/members?findmember=' . urlencode( preg_quote( $this->_emailAddress ) );
			$regurl .= "&setmemberopts_btn&adminpw=" . urlencode( $this->_mailingListPassword );

			$str_email = preg_quote( urlencode( $this->_emailAddress ) );

			// HTTP Request
			$httpreq = $this->_mailman_parse_http( $regurl );

			$subscription = array();
			if ( $httpreq->umr_ok ) {
				$subscription['mod']    = 0;
				$subscription['status'] = self::USER_MAILMAN_REGISTER_UNSUBSCRIBED;

				if ( preg_match( '/INPUT .*name="' . $str_email . '_unsub"/i', $httpreq->data ) ) {
					$subscription['status'] = self::USER_MAILMAN_REGISTER_SUBSCRIBED_NORMAL;
					if ( preg_match( '/INPUT .*name="' . $str_email . '_digest".* value="on"/i', $httpreq->data ) ) {
						$subscription['status'] = self::USER_MAILMAN_REGISTER_SUBSCRIBED_DIGEST;
					}
					if ( preg_match( '/INPUT .*name="' . $str_email . '_mod".* value="on"/i', $httpreq->data ) ) {
						$subscription['mod'] = 1;
					}
					if ( preg_match( '/INPUT .*name="' . $str_email . '_nomail".* value="on" CHECKED >(\[\w\])/i', $httpreq->data, $match ) ) {
						$subscription['status'] = self::USER_MAILMAN_REGISTER_SUBSCRIBED_DISABLED;

						switch ( $match[1] ) {
							case '[A]':
								$subscription['error'] = 'Delivery for list was disabled by the list administrator.';
								break;

							case '[B]':
								$subscription['error'] = 'Delivery for list was disabled by the system probably due to excessive bouncing from the member\'s address.';
								break;

							default:
								$subscription['error'] = 'Delivery for list was disabled for an unknown reason.';
								break;
						}
					}
				}
			} else {
				die( $httpreq->umr_usrmsg );

				return false;
			}

			return $subscription['status'];
		}

		private function _mailman_subscription_update( $actionType ) {
			$msg    = '';
			$regurl = rtrim( $this->_mailingListUrl, '/' ) . '/members';

			switch ( $actionType ) {
				// Unsubscribe
				case self::USER_MAILMAN_REGISTER_DO_UNSUBSCRIBE:
					/** @todo These Mailman settings should be moved to the admin interface * */
					$regurl .= '/remove?send_unsub_ack_to_this_batch=0';
					$regurl .= '&send_unsub_notifications_to_list_owner=1';
					$regurl .= '&unsubscribees_upload=' . urlencode( $this->_emailAddress );
					$msg    .= 'Unsubscription to ';
					break;

				// New subscription
				case self::USER_MAILMAN_REGISTER_SUBSCRIBED_NORMAL:

					// If Full Name exists, use that
					if ( $this->_fullName == '' ) {
						$email = urlencode( $this->_emailAddress );
					} else {
						$email = urlencode( $this->_fullName . ' <' . $this->_emailAddress . '>' );
					}

					/** @todo These Mailman settings should be moved to the admin interface * */
					$regurl .= '/add?subscribe_or_invite=0';
					$regurl .= '&send_welcome_msg_to_this_batch=0';
					$regurl .= '&notification_to_list_owner=1';
					$regurl .= '&subscribees_upload=' . $email;

					$msg .= 'Subscription to ';
					break;

				default:
					die( 'Unknown list subscription request.' );

					return false;
			}
			$regurl .= '&adminpw=' . urlencode( $this->_mailingListPassword );

			// HTTP Request
			$httpreq = $this->_mailman_parse_http( $regurl );
			if ( ! $httpreq->umr_ok ) {
				wp_die( $httpreq->umr_usrmsg );

				return false;
			}

			return true;
		}

		/**
		 * Query Mailman Server
		 *
		 * @param   string  $regurl
		 *
		 * @return    stdClass
		 * @since   1.0.0
		 */
		private function _mailman_parse_http( $regurl ) {
			// Get cURL resource
			$curl           = curl_init();
			$defaultTimeout = gm_get_default_timeout();
			$defaultTimeout = ( is_int( $defaultTimeout ) and
			                    $defaultTimeout > 0 ) ? $defaultTimeout : 30;

			// Set some options - we are passing in a useragent too here
			curl_setopt_array(
				$curl, array(
					CURLOPT_RETURNTRANSFER => 1,
					CURLOPT_URL            => $regurl,
					CURLOPT_USERAGENT      => 'GNU-Mailman-Wordpress',
				)
			);
			curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, 0 );
			curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, 0 );
			curl_setopt( $curl, CURLOPT_TIMEOUT, $defaultTimeout );

			// Send the request
			$httpobj         = new stdClass();
			$httpobj->umr_ok = 1;
			$httpobj->data   = curl_exec( $curl );
			$httpobj->code   = 200;

			// Check for errors
			if ( ! curl_exec( $curl ) ) {
				$httpobj->code = 400;
				throw new Exception( 'Error: "' . curl_error( $curl ) . '" - Code: ' . curl_errno( $curl ) );
			}

			// Close request to clear up some resources
			curl_close( $curl );

			// Error Checking
			if ( $httpobj->code <> 200 || ! preg_match( '/INPUT .*name="(findmember|setmemberopts)_btn"/i', $httpobj->data ) ) {
				if ( preg_match( '/<input type="password".* name="adminpw"/i', $httpobj->data ) ) {
					throw new Exception( 'The administrator web password for List (' . $this->_mailingListUrl . ') is invalid.' );
				}

				if ( preg_match( '/No such list/i', $httpobj->data ) ) {
					throw new Exception( 'The List (' . $this->_mailingListUrl . ') does not exist.' );
				}

				throw new Exception( 'Sorry, mailing List (' . $this->_mailingListUrl . ') registration is currently unavailable. Please, try again shortly.' );
			}

			return $httpobj;
		}

		/**
		 * Set User's Email Address
		 *
		 * @param   string  $emailAddress  User's Email Address
		 *
		 * @return    Mailman
		 * @since   1.0.0
		 */
		public function setEmailAddress( $emailAddress ) {
			$this->_emailAddress = $emailAddress;

			return $this;
		}

		/**
		 * Return User's Email Address
		 *
		 * @return    string    User's Email Address
		 * @since   1.0.0
		 */
		public function getEmailAddress() {
			return $this->_emailAddress;
		}

		/**
		 * Set User's Full Name
		 *
		 * @param   string  $fullName  User's Full Name
		 *
		 * @return    Mailman
		 * @since   1.0.0
		 */
		public function setFullName( $fullName ) {
			$this->_fullName = $fullName;

			return $this;
		}

		/**
		 * Return User's Full Name
		 *
		 * @return    string    User's Full Name
		 * @since   1.0.0
		 */
		public function getFullName() {
			return $this->_fullName;
		}
	}
}
