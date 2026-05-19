<?php
/**
 * SiteIntelix Error UI drop-in: database error template.
 *
 * Installed to wp-content/db-error.php by the SiteIntelix Error UI plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! headers_sent() ) {
	http_response_code( 500 );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Retry-After: 600' );
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

		return is_array( $config ) ? array_merge( $defaults, $config ) : $defaults;
	}
}

if ( ! function_exists( 'ceui_error_ui_send_admin_alert' ) ) {
	function ceui_error_ui_send_admin_alert( $settings, $subject, $body ) {
		if ( empty( $settings['email_alerts'] ) || empty( $settings['admin_email'] ) || defined( 'SITEINTELIX_ERROR_UI_ALERT_SENT' ) ) {
			return;
		}

		define( 'SITEINTELIX_ERROR_UI_ALERT_SENT', true );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'X-SiteIntelix-Error-Alert: 1' );

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

		$headers = "Content-Type: text/plain; charset=UTF-8\r\n";
		@mail( $settings['admin_email'], $subject, $body, $headers );
	}
}

if ( ! function_exists( 'ceui_error_ui_current_url' ) ) {
	function ceui_error_ui_current_url() {
		$scheme = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'unknown-host';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return $scheme . '://' . $host . $uri;
	}
}

if ( ! function_exists( 'ceui_error_ui_get_database_details' ) ) {
	function ceui_error_ui_get_database_details() {
		$details = array(
			'Error type' => 'Database connection or query failure',
			'URL'        => ceui_error_ui_current_url(),
		);

		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && ! empty( $GLOBALS['wpdb']->last_error ) ) {
			$details['Message'] = $GLOBALS['wpdb']->last_error;
		} else {
			$details['Message'] = 'WordPress could not establish or complete a database request.';
		}

		return $details;
	}
}

$ceui_settings = ceui_error_ui_get_settings();
$ceui_subject  = '[SiteIntelix] Database error detected';
$ceui_details  = ceui_error_ui_get_database_details();
$ceui_body     = "A database error page was shown to a visitor.\n\n"
	. 'Site: ' . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'Unknown' ) . "\n"
	. 'URL: ' . ceui_error_ui_current_url() . "\n"
	. 'Time: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n"
	. 'Visitor IP: ' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'Unknown' ) . "\n"
	. 'Error type: ' . $ceui_details['Error type'] . "\n"
	. 'Message: ' . $ceui_details['Message'] . "\n";

ceui_error_ui_send_admin_alert( $ceui_settings, $ceui_subject, $ceui_body );

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
		<p class="ceui-kicker"><?php echo esc_html( $ceui_settings['eyebrow'] ); ?></p>
		<h1 id="ceui-title"><?php echo esc_html( $ceui_settings['title'] ); ?></h1>
		<p class="ceui-message"><?php echo nl2br( esc_html( $ceui_settings['message'] ) ); ?></p>
		<div class="ceui-actions">
			<a class="ceui-button ceui-button-primary" href="<?php echo esc_url( $ceui_settings['primary_button_url'] ); ?>"><?php echo esc_html( $ceui_settings['primary_button_text'] ); ?></a>
			<a class="ceui-button" href="<?php echo esc_url( $ceui_settings['secondary_button_url'] ); ?>"><?php echo esc_html( $ceui_settings['secondary_button_text'] ); ?></a>
		</div>
		<?php if ( ! empty( $ceui_settings['show_error_details'] ) ) : ?>
			<section class="ceui-details" aria-label="Technical error details">
				<h2>Technical error details</h2>
				<dl>
					<?php foreach ( $ceui_details as $label => $value ) : ?>
						<div>
							<dt><?php echo esc_html( $label ); ?></dt>
							<dd><?php echo esc_html( $value ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endif; ?>
		<p class="ceui-meta"><?php echo esc_html( $ceui_settings['status_meta'] ); ?></p>
	</main>
</body>
</html>
<?php
die();
