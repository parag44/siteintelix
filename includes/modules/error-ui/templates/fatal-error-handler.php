<?php
/**
 * SiteIntelix Error UI drop-in: fatal error handler.
 *
 * Installed to wp-content/fatal-error-handler.php by the SiteIntelix Error UI plugin.
 */

if ( ! class_exists( 'WP_Fatal_Error_Handler' ) && defined( 'ABSPATH' ) && defined( 'WPINC' ) ) {
	require_once ABSPATH . WPINC . '/class-wp-fatal-error-handler.php';
}

if ( ! function_exists( 'ceui_error_ui_escape' ) ) {
	function ceui_error_ui_escape( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'ceui_error_ui_get_settings' ) ) {
	function ceui_error_ui_get_settings() {
		$defaults = array(
			'eyebrow'               => 'Temporary service interruption',
			'title'                 => 'We got an error, and we are working on it.',
			'message'               => 'Do not worry, your data is safe with us. Our team has been notified and is already working to bring everything back online.',
			'primary_button_text'   => 'Try again',
			'primary_button_url'    => '/',
			'secondary_button_text' => 'Contact support',
			'secondary_button_url'  => 'mailto:support@example.com',
			'status_meta'           => 'Service status · Temporary error',
			'email_alerts'          => '1',
			'show_error_details'    => '0',
			'admin_email'           => '',
		);

		$config_file = __DIR__ . '/siteintelix-error-ui-config.php';
		$config      = is_readable( $config_file ) ? include $config_file : array();
		$settings    = is_array( $config ) ? array_merge( $defaults, $config ) : $defaults;

		if ( empty( $settings['admin_email'] ) && function_exists( 'get_option' ) ) {
			$settings['admin_email'] = get_option( 'admin_email' );
		}

		return $settings;
	}
}

if ( ! function_exists( 'ceui_error_ui_current_url' ) ) {
	function ceui_error_ui_current_url() {
		$scheme = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'unknown-host';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

		return $scheme . '://' . $host . $uri;
	}
}

if ( ! function_exists( 'ceui_error_ui_send_admin_alert' ) ) {
	function ceui_error_ui_send_admin_alert( $settings, $error, $handled ) {
		if ( empty( $settings['email_alerts'] ) || empty( $settings['admin_email'] ) || defined( 'SITEINTELIX_ERROR_UI_ALERT_SENT' ) ) {
			return;
		}

		define( 'SITEINTELIX_ERROR_UI_ALERT_SENT', true );

		$subject = '[SiteIntelix] Fatal error detected';
		$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'X-SiteIntelix-Error-Alert: 1' );
		$body    = "A fatal error page was shown to a visitor.\n\n"
			. 'Site: ' . ( function_exists( 'home_url' ) ? home_url( '/' ) : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'Unknown' ) ) . "\n"
			. 'URL: ' . ceui_error_ui_current_url() . "\n"
			. 'Time: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n"
			. 'Visitor IP: ' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'Unknown' ) . "\n"
			. 'Recovery mode handled: ' . ( function_exists( 'is_wp_error' ) && is_wp_error( $handled ) ? 'No' : 'Yes' ) . "\n\n"
			. 'Error type: ' . ( isset( $error['type'] ) ? $error['type'] : 'Unknown' ) . "\n"
			. 'Message: ' . ( isset( $error['message'] ) ? $error['message'] : 'Unknown' ) . "\n"
			. 'File: ' . ( isset( $error['file'] ) ? $error['file'] : 'Unknown' ) . "\n"
			. 'Line: ' . ( isset( $error['line'] ) ? $error['line'] : 'Unknown' ) . "\n";

		if ( class_exists( 'SITEINTELIX_Email_Log_Module' ) ) {
			SITEINTELIX_Email_Log_Module::insert_log(
				array(
					'status'  => 'sent',
					'to'      => $settings['admin_email'],
					'subject' => $subject,
					'message' => $body,
					'headers' => $headers,
				)
			);
		}

		if ( function_exists( 'wp_mail' ) ) {
			wp_mail( $settings['admin_email'], $subject, $body, $headers );
			return;
		}

		@mail( $settings['admin_email'], $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\n" );
	}
}

if ( ! function_exists( 'ceui_error_ui_format_error_details' ) ) {
	function ceui_error_ui_format_error_details( $error ) {
		if ( ! is_array( $error ) ) {
			return array();
		}

		$details = array(
			'Error type' => isset( $error['type'] ) ? $error['type'] : 'Unknown',
			'Message'    => isset( $error['message'] ) ? $error['message'] : 'Unknown',
			'File'       => isset( $error['file'] ) ? $error['file'] : 'Unknown',
			'Line'       => isset( $error['line'] ) ? $error['line'] : 'Unknown',
		);

		return array_filter(
			$details,
			function ( $value ) {
				return '' !== trim( (string) $value );
			}
		);
	}
}

if ( ! function_exists( 'ceui_error_ui_render_page' ) ) {
	function ceui_error_ui_render_page( $settings, $error = null ) {
		$error_details = ! empty( $settings['show_error_details'] ) ? ceui_error_ui_format_error_details( $error ) : array();
		?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title>Temporary service interruption</title>
	<style>
		:root { color-scheme: light; --ceui-bg: #f4f7fb; --ceui-card: #ffffff; --ceui-text: #111827; --ceui-muted: #617089; --ceui-border: #dde6f0; --ceui-primary: #2563eb; --ceui-shadow: 0 22px 60px rgba(15, 23, 42, 0.12); }
		* { box-sizing: border-box; }
		body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--ceui-bg); color: var(--ceui-text); }
		main { width: min(680px, 100%); padding: clamp(28px, 5vw, 52px); border: 1px solid var(--ceui-border); border-radius: 18px; background: var(--ceui-card); box-shadow: var(--ceui-shadow); text-align: center; }
		.ceui-kicker { margin: 0 0 14px; color: var(--ceui-primary); font-size: 13px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
		h1 { margin: 0; font-size: clamp(30px, 5vw, 44px); line-height: 1.1; letter-spacing: -0.04em; }
		.ceui-message { margin: 18px auto 0; max-width: 560px; color: var(--ceui-muted); font-size: 17px; line-height: 1.7; }
		.ceui-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 30px; }
		.ceui-button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border: 1px solid var(--ceui-border); border-radius: 10px; background: #fff; color: var(--ceui-text); font-weight: 700; text-decoration: none; }
		.ceui-button-primary { border-color: var(--ceui-primary); background: var(--ceui-primary); color: #fff; }
		.ceui-details { margin: 28px 0 0; padding: 18px; border: 1px solid #f6caca; border-radius: 12px; background: #fff7f7; text-align: left; }
		.ceui-details h2 { margin: 0 0 12px; color: #991b1b; font-size: 15px; line-height: 1.4; letter-spacing: 0; }
		.ceui-details dl { display: grid; gap: 10px; margin: 0; }
		.ceui-details dt { color: #7f1d1d; font-size: 12px; font-weight: 800; text-transform: uppercase; }
		.ceui-details dd { margin: 3px 0 0; color: #111827; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px; line-height: 1.5; overflow-wrap: anywhere; }
		.ceui-meta { margin: 26px 0 0; color: #8a97aa; font-size: 13px; }
	</style>
</head>
<body>
	<main aria-labelledby="ceui-title">
		<p class="ceui-kicker"><?php echo ceui_error_ui_escape( $settings['eyebrow'] ); ?></p>
		<h1 id="ceui-title"><?php echo ceui_error_ui_escape( $settings['title'] ); ?></h1>
		<p class="ceui-message"><?php echo nl2br( ceui_error_ui_escape( $settings['message'] ) ); ?></p>
		<div class="ceui-actions">
			<a class="ceui-button ceui-button-primary" href="<?php echo ceui_error_ui_escape( $settings['primary_button_url'] ); ?>"><?php echo ceui_error_ui_escape( $settings['primary_button_text'] ); ?></a>
			<a class="ceui-button" href="<?php echo ceui_error_ui_escape( $settings['secondary_button_url'] ); ?>"><?php echo ceui_error_ui_escape( $settings['secondary_button_text'] ); ?></a>
		</div>
		<?php if ( ! empty( $error_details ) ) : ?>
			<section class="ceui-details" aria-label="Technical error details">
				<h2>Technical error details</h2>
				<dl>
					<?php foreach ( $error_details as $label => $value ) : ?>
						<div>
							<dt><?php echo ceui_error_ui_escape( $label ); ?></dt>
							<dd><?php echo ceui_error_ui_escape( $value ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endif; ?>
		<p class="ceui-meta"><?php echo ceui_error_ui_escape( $settings['status_meta'] ); ?></p>
	</main>
</body>
</html>
		<?php
	}
}

if ( ! class_exists( 'CEUI_Fatal_Error_Handler' ) && class_exists( 'WP_Fatal_Error_Handler' ) ) {
	/**
	 * Custom fatal error handler.
	 */
	class CEUI_Fatal_Error_Handler extends WP_Fatal_Error_Handler {
		/**
		 * Displays the fatal error template.
		 *
		 * @param array         $error   Error information retrieved by WordPress.
		 * @param true|WP_Error $handled Whether recovery mode handled the error.
		 */
		protected function display_error_template( $error, $handled ) {
			if ( ! headers_sent() ) {
				if ( function_exists( 'status_header' ) ) {
					status_header( 500 );
				} else {
					http_response_code( 500 );
				}
				header( 'Content-Type: text/html; charset=utf-8' );
			}

			$settings = ceui_error_ui_get_settings();
			ceui_error_ui_send_admin_alert( $settings, $error, $handled );
			ceui_error_ui_render_page( $settings, $error );
		}
	}
}

return class_exists( 'CEUI_Fatal_Error_Handler' ) ? new CEUI_Fatal_Error_Handler() : null;
