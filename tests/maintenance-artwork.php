<?php
/**
 * Maintenance artwork security and rendering tests.
 *
 * @package SiteIntelix
 */

define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_image_ids   = array( 17 );
$siteintelix_image_html  = array(
	17 => '<img width="520" height="320" src="maintenance.jpg" class="sitx-maintenance__art sitx-maintenance__art--custom" alt="Technicians maintaining the website">',
);
$siteintelix_attachment_calls = array();

function __( $text ) {
	return $text;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_attachment_is_image( $attachment_id ) {
	global $siteintelix_image_ids;
	return in_array( (int) $attachment_id, $siteintelix_image_ids, true );
}

function wp_get_attachment_image( $attachment_id, $size, $icon, $attributes ) {
	global $siteintelix_attachment_calls, $siteintelix_image_html;
	$siteintelix_attachment_calls[] = compact( 'attachment_id', 'size', 'icon', 'attributes' );
	return $siteintelix_image_html[ $attachment_id ] ?? '';
}

function siteintelix_maintenance_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/modules/coming-soon/class-siteintelix-coming-soon-module.php';

$sanitize = new ReflectionMethod( 'SITEINTELIX_Coming_Soon_Module', 'sanitize_artwork_id' );
siteintelix_maintenance_assert( 17 === $sanitize->invoke( null, '17' ), 'Valid image IDs are retained.' );
siteintelix_maintenance_assert( 0 === $sanitize->invoke( null, '99' ), 'Non-image attachment IDs are rejected.' );
siteintelix_maintenance_assert( 0 === $sanitize->invoke( null, '-17' ), 'Negative attachment IDs are rejected.' );
siteintelix_maintenance_assert( 0 === $sanitize->invoke( null, '' ), 'Empty attachment IDs normalize to zero.' );

$render = new ReflectionMethod( 'SITEINTELIX_Coming_Soon_Module', 'render_maintenance_art' );
ob_start();
$render->invoke( null, array( 'artwork_id' => 17 ) );
$custom_output = ob_get_clean();
siteintelix_maintenance_assert( false !== strpos( $custom_output, 'Technicians maintaining the website' ), 'WordPress attachment Alt Text reaches custom artwork output.' );
siteintelix_maintenance_assert( false !== strpos( $custom_output, 'sitx-maintenance__art--custom' ), 'Custom artwork uses the photo-friendly modifier.' );
siteintelix_maintenance_assert( 'large' === $siteintelix_attachment_calls[0]['size'], 'Custom artwork requests the responsive large image size.' );

$siteintelix_image_html[17] = '<img src="maintenance.jpg" class="sitx-maintenance__art sitx-maintenance__art--custom" alt="">';
ob_start();
$render->invoke( null, array( 'artwork_id' => 17 ) );
$empty_alt_output = ob_get_clean();
siteintelix_maintenance_assert( false !== strpos( $empty_alt_output, 'alt=""' ), 'Empty Media Library Alt Text remains an empty alt attribute.' );

ob_start();
$render->invoke( null, array( 'artwork_id' => 99 ) );
$fallback_output = ob_get_clean();
siteintelix_maintenance_assert( false !== strpos( $fallback_output, '<svg class="sitx-maintenance__art"' ), 'Invalid or deleted artwork falls back to the built-in SVG.' );

fwrite( STDOUT, "Maintenance artwork tests passed.\n" );
