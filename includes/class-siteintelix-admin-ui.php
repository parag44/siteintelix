<?php
/**
 * Shared admin UI helpers for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small rendering helpers for shared SiteIntelix admin UI.
 */
class SITEINTELIX_Admin_UI {

	/**
	 * Render a Dashicons icon container.
	 *
	 * @param string $dashicon    Dashicon class name.
	 * @param string $extra_class Extra wrapper classes.
	 * @return string
	 */
	public static function icon( $dashicon, $extra_class = '' ) {
		$classes = trim( 'si-icon ' . $extra_class );

		return '<span class="' . esc_attr( $classes ) . '"><span class="dashicons ' . esc_attr( sanitize_html_class( $dashicon ) ) . '" aria-hidden="true"></span></span>';
	}

	/**
	 * Render a fixed plugin-owned inline SVG inside the shared icon container.
	 *
	 * @param string $svg         Trusted plugin-owned SVG.
	 * @param string $extra_class Extra wrapper classes.
	 * @return string
	 */
	public static function svg_icon( $svg, $extra_class = '' ) {
		$classes = trim( 'si-icon si-icon--svg ' . $extra_class );
		$allowed = array(
			'svg'  => array(
				'aria-hidden' => true,
				'focusable'   => true,
				'viewbox'     => true,
			),
			'path' => array(
				'd'    => true,
				'fill' => true,
			),
		);

		return '<span class="' . esc_attr( $classes ) . '">' . wp_kses( (string) $svg, $allowed ) . '</span>';
	}

	/**
	 * Render a shared badge.
	 *
	 * @param string $label Badge label.
	 * @param string $type  Badge type.
	 * @param string $icon  Optional Dashicon class.
	 * @return string
	 */
	public static function badge( $label, $type = 'neutral', $icon = '' ) {
		$type    = sanitize_key( $type );
		$content = '';

		if ( $icon ) {
			$content .= '<span class="dashicons ' . esc_attr( sanitize_html_class( $icon ) ) . '" aria-hidden="true"></span>';
		}

		$content .= '<span>' . esc_html( $label ) . '</span>';

		return '<span class="si-badge si-badge--' . esc_attr( $type ) . '">' . $content . '</span>';
	}

	/**
	 * Render a shared button or link.
	 *
	 * @param array<string,mixed> $args Button args.
	 * @return string
	 */
	public static function button( $args ) {
		$defaults = array(
			'label'      => '',
			'url'        => '',
			'type'       => 'button',
			'variant'    => 'secondary',
			'icon'       => '',
			'attributes' => array(),
			'classes'    => array(),
		);

		$args       = wp_parse_args( $args, $defaults );
		$classes    = array_merge( array( 'si-button', 'si-button--' . sanitize_key( $args['variant'] ) ), (array) $args['classes'] );
		$attributes = self::attributes( $args['attributes'] );
		$content    = '';

		if ( $args['icon'] ) {
			$content .= '<span class="dashicons ' . esc_attr( sanitize_html_class( $args['icon'] ) ) . '" aria-hidden="true"></span>';
		}

		if ( '' !== (string) $args['label'] ) {
			$content .= '<span>' . esc_html( $args['label'] ) . '</span>';
		}

		if ( $args['url'] ) {
			return '<a class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '" href="' . esc_url( $args['url'] ) . '"' . $attributes . '>' . $content . '</a>';
		}

		return '<button type="' . esc_attr( $args['type'] ) . '" class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '"' . $attributes . '>' . $content . '</button>';
	}

	/**
	 * Render the shared SiteIntelix page header.
	 *
	 * @param array<string,mixed> $args Header args.
	 * @return void
	 */
	public static function page_header( $args ) {
		$defaults = array(
			'icon'        => 'dashicons-admin-generic',
			'icon_svg'    => '',
			'title'       => '',
			'description' => '',
			'badges'      => array(),
			'actions'     => array(),
		);

		$args = wp_parse_args( $args, $defaults );
		?>
		<header class="si-page-header siteintelix-header">
			<div class="si-page-header__content siteintelix-header__content">
				<div class="si-page-header__main siteintelix-header__title-group">
					<?php
					echo wp_kses(
						$args['icon_svg']
							? self::svg_icon( $args['icon_svg'], 'si-page-header__icon siteintelix-header__icon' )
							: self::icon( $args['icon'], 'si-page-header__icon siteintelix-header__icon' ),
						array(
							'span' => array(
								'aria-hidden' => true,
								'class'       => true,
							),
							'svg'  => array(
								'aria-hidden' => true,
								'focusable'   => true,
								'viewbox'     => true,
							),
							'path' => array(
								'd'    => true,
								'fill' => true,
							),
						)
					);
					?>
					<div class="si-page-header__text siteintelix-header__text">
						<h1 class="si-page-header__title siteintelix-header__title"><?php echo esc_html( $args['title'] ); ?></h1>
						<?php if ( $args['description'] ) : ?>
							<p class="si-page-header__description siteintelix-header__desc"><?php echo esc_html( $args['description'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( ! empty( $args['badges'] ) || ! empty( $args['actions'] ) ) : ?>
					<div class="si-page-header__actions siteintelix-header__actions">
						<?php
						foreach ( (array) $args['badges'] as $badge ) {
							echo wp_kses( $badge, self::allowed_html() );
						}
						foreach ( (array) $args['actions'] as $action ) {
							echo wp_kses( $action, self::allowed_html() );
						}
						?>
					</div>
				<?php endif; ?>
			</div>
		</header>
		<?php
	}

	/**
	 * Render a shared empty state.
	 *
	 * @param string $title   Empty state title.
	 * @param string $message Optional message.
	 * @param string $icon    Dashicon class.
	 * @return void
	 */
	public static function empty_state( $title, $message = '', $icon = 'dashicons-info-outline' ) {
		?>
		<div class="si-empty-state">
			<?php echo wp_kses_post( self::icon( $icon, 'si-empty-state__icon' ) ); ?>
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( $message ) : ?>
				<p><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Build escaped HTML attributes.
	 *
	 * @param array<string,mixed> $attributes Attribute map.
	 * @return string
	 */
	private static function attributes( $attributes ) {
		$output = '';

		foreach ( (array) $attributes as $name => $value ) {
			if ( null === $value || false === $value ) {
				continue;
			}

			$output .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return $output;
	}

	/**
	 * Allowed HTML for helper-generated admin UI fragments.
	 *
	 * @return array<string,array<string,bool>>
	 */
	private static function allowed_html() {
		return array(
			'a'      => array(
				'aria-label' => true,
				'class'      => true,
				'data-*'     => true,
				'href'       => true,
				'id'         => true,
				'rel'        => true,
				'target'     => true,
				'title'      => true,
			),
			'button' => array(
				'aria-label' => true,
				'class'      => true,
				'data-*'     => true,
				'disabled'   => true,
				'id'         => true,
				'name'       => true,
				'title'      => true,
				'type'       => true,
				'value'      => true,
			),
			'form'   => array(
				'action' => true,
				'class'  => true,
				'id'     => true,
				'method' => true,
			),
			'input'  => array(
				'aria-label' => true,
				'class'      => true,
				'id'         => true,
				'name'       => true,
				'type'       => true,
				'value'      => true,
			),
			'span'   => array(
				'aria-hidden' => true,
				'class'       => true,
				'data-*'      => true,
				'id'          => true,
				'title'       => true,
			),
		);
	}
}
