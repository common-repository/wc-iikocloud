<?php

namespace WPWC\iikoCloud;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Exception\TelegramException;

class Logs {

	private static $logs;

	/**
	 * Initialization.
	 */
	public static function init() {
		self::$logs = new WP_Error();
	}

	/**
	 * Add an error message.
	 *
	 * @param string $message Error message.
	 */
	public static function add_error( string $message ) {
		self::$logs->add( WC_IIKOCLOUD_PREFIX . 'errors', $message );
	}

	/**
	 * Add a notice message.
	 *
	 * @param string $message Notice message.
	 */
	public static function add_notice( string $message ) {
		self::$logs->add( WC_IIKOCLOUD_PREFIX . 'notices', $message );
	}

	/**
	 * Get all error messages.
	 *
	 * @return array Error strings on success, or empty array if there are none.
	 */
	public static function get_errors(): array {
		return self::$logs->get_error_messages( WC_IIKOCLOUD_PREFIX . 'errors' );
	}

	/**
	 * Get all notice messages.
	 *
	 * @return array Notice strings on success, or empty array if there are none.
	 */
	public static function get_notices(): array {
		return self::$logs->get_error_messages( WC_IIKOCLOUD_PREFIX . 'notices' );
	}

	/**
	 * Plugin logs.
	 * Don't use keys 'errors' and 'notices' in $add_info array.
	 *
	 * @param array $add_info
	 *
	 * @return array All logs.
	 */
	public static function get_logs( array $add_info = [] ): array {

		$logs            = [];
		$logs['errors']  = ! empty( self::get_errors() ) ? self::get_errors() : null;
		$logs['notices'] = ! empty( self::get_notices() ) ? self::get_notices() : null;

		if ( is_array( $add_info ) && ! empty( $add_info ) ) {
			$logs = array_merge( $logs, $add_info );
		}

		return $logs;
	}

	/**
	 * Add WooCommerce log record.
	 *
	 * @param mixed $message Log message.
	 * @param string $context Additional information for log handlers. Default value is 'common'.
	 * @param string $level One of the following:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 * Default value is 'debug'
	 */
	public static function add_wc_log( $message, string $context = 'common', string $level = 'debug' ) {

		if ( 'yes' !== get_option( WC_IIKOCLOUD_PREFIX . 'debug_mode' ) ) {
			return;
		}

		$logger  = wc_get_logger();
		$message = wc_print_r( $message, true );
		$context = [ 'source' => 'wc-iikocloud-' . $context ];

		switch ( $level ) {
			case 'emergency':
				$logger->emergency( $message, $context );
				break;
			case 'alert':
				$logger->alert( $message, $context );
				break;
			case 'critical':
				$logger->critical( $message, $context );
				break;
			case 'error':
				$logger->error( $message, $context );
				break;
			case 'warning':
				$logger->warning( $message, $context );
				break;
			case 'notice':
				$logger->notice( $message, $context );
				break;
			case 'info':
				$logger->info( $message, $context );
				break;
			case 'debug':
				$logger->debug( $message, $context );
				break;
			default:
				$logger->debug( $message, $context );
		}

		// $logger->info( str_repeat( '-', 100 ), $context );
	}

	/**
	 * Add error to Logs based on wp_error information.
	 *
	 * @param $wp_error_obj
	 * @param $message
	 */
	public static function log_wp_error( $wp_error_obj, $message ) {

		$wp_error_code    = $wp_error_obj->get_error_code();
		$wp_error_message = $wp_error_obj->get_error_message();
		$wp_error_data    = $wp_error_obj->get_error_data();
		$error_message    = sprintf( '%1$s: (%2$s) %3$s %4$s',
			$message, $wp_error_code, $wp_error_message, $wp_error_data
		);

		self::add_error( $error_message );
		self::add_wc_log( $error_message, 'import', 'error' );
	}

	/**
	 * Send Email.
	 *
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 */
	public static function send_email( string $subject, string $message ) {

		$email = sanitize_text_field( get_option( WC_IIKOCLOUD_PREFIX . 'debug_emails' ) );
		$email = ! empty( $email ) ? $email : get_bloginfo( 'admin_email' );

		if ( ! wp_mail( $email, $subject, $message ) ) {
			Logs::add_wc_log( "Send email error. Email addresses: $email", 'email', 'error' );
			Logs::add_wc_log( $subject, 'email', 'error' );
			Logs::add_wc_log( $message, 'email', 'error' );
		}
	}

	/**
	 * Send message to Telegram bot.
	 *
	 * Get BOTToken - https://github.com/php-telegram-bot/core
	 * Get chat_id - https://api.telegram.org/bot<YourBOTToken>/getUpdates
	 *
	 * @param string $message Chat message.
	 */
	public static function send_to_telegram_bot( string $message ) {

		if ( empty( $message ) ) {
			return;
		}

		$telegram_settings = get_option( WC_IIKOCLOUD_PREFIX . 'telegram' );
		$bot_token         = sanitize_text_field( $telegram_settings['bot_token'] );
		$bot_user_name     = sanitize_key( $telegram_settings['bot_user_name'] );
		$chat_id           = sanitize_key( $telegram_settings['chat_id'] );

		if ( empty( $bot_token ) || empty( $bot_user_name ) || empty( $chat_id ) ) {
			return;
		}

		try {
			// Create Telegram API object.
			$telegram = new Telegram( $bot_token, $bot_user_name );

			// Send the message.
			$result = Request::sendMessage( [
				'chat_id' => $chat_id,
				'text'    => sanitize_text_field( $message ),
			] );

		} catch ( TelegramException $e ) {
			self::add_wc_log( $e->getMessage(), 'telegram', 'error' );
		}
	}
}
