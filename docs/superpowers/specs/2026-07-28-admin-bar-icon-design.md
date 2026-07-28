# SiteIntelix Admin-Bar Icon Design

## Goal

Display a chart icon immediately before the `SiteIntelix` label in the WordPress admin bar.

## Design

The existing `siteintelix` admin-bar node will keep its ID, destination, tooltip, permissions, priority, and Debug Log and Email Log child nodes.

Its title will contain two inline elements:

- An `aria-hidden="true"` WordPress Dashicons element using `dashicons-chart-area`, exactly matching the SiteIntelix sidebar icon.
- An escaped text label containing `SiteIntelix`.

WordPress registers the `admin-bar` stylesheet with Dashicons as a dependency, so the same glyph is available in both wp-admin and frontend toolbars. The implementation will not load an image file, custom SVG, external asset, additional stylesheet, or JavaScript.

## Accessibility

- The Dashicons element is decorative and hidden from assistive technology.
- The visible text label remains present and escaped.
- The existing node tooltip remains `SiteIntelix log shortcuts`.
- Keyboard behavior and dropdown navigation remain unchanged.

## Testing

Add a structural test requiring:

- the official `dashicons dashicons-chart-area` classes inside the SiteIntelix parent node title;
- `aria-hidden="true"` on the decorative icon;
- absence of the previous custom SVG markup;
- a separate escaped `SiteIntelix` text label;
- the existing parent ID and Debug Log and Email Log child links.

Run the structural and PHP syntax checks. Do not build a ZIP unless separately requested.
