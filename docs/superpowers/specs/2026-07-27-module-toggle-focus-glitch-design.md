# Module Toggle Focus Glitch Design

## Problem

The Modules screen renders each switch as a visually hidden native checkbox followed by a styled slider. When a switch is clicked, the checkbox receives `:focus-visible`. The shared admin focus rule applies a box shadow directly to that 1×1 checkbox, producing a short underline-like artifact above the slider.

## Design

Keep the native checkbox focusable and accessible, but make its hidden rendering complete:

- Retain the existing native checkbox and label markup.
- Clip the checkbox visually and hide its overflow.
- Remove margin, border, padding, outline, and box shadow from the hidden checkbox itself.
- Keep the existing `.sitx-toggle input:focus-visible + .sitx-toggle__slider` rule so keyboard users continue to receive a visible focus indicator around the actual switch UI.
- Do not change the switch dimensions, colors, animation, AJAX behavior, or enabled/disabled state handling.

## Testing

Add a structural CSS regression test requiring:

- the hidden toggle input to use clipping and no box shadow;
- the visible slider to retain its `focus-visible` indicator.

Run the structural and admin interaction test suites. No plugin ZIP will be built.
