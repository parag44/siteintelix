# Maintenance Mode Artwork Media Picker Design

Date: 2026-07-29  
Plugin: SiteIntelix 2.7.3  
Module: Maintenance Mode (`coming_soon`)

## Goal

Allow an administrator to replace the built-in Maintenance Mode illustration
with an image selected from the WordPress Media Library. The existing SVG
remains the default and automatic fallback.

## Scope

This change covers:

- A new artwork selector in the Maintenance Mode settings tab.
- Secure storage and validation of a WordPress image attachment ID.
- Responsive frontend rendering of the selected attachment.
- Media Library Alt Text support.
- Automatic fallback to the existing built-in SVG.
- Tests for settings, rendering, picker behavior, and fallback handling.

This change does not add manual image URLs, uploads outside the WordPress Media
Library, image editing, cropping controls, per-device images, or a separate
Maintenance Mode customizer.

## Selected Approach

Extend the existing Maintenance Mode logo picker into a reusable settings-page
media-picker component. The logo and artwork selectors share one JavaScript
implementation while keeping separate fields and labels.

This avoids duplicated media-frame logic and preserves the existing scoped asset
loading: WordPress media scripts remain loaded only on the SiteIntelix Settings
screen when Maintenance Mode is enabled.

## Settings UI

The Maintenance Mode tab will include a new full-width “Maintenance artwork”
field directly below Logo.

The field contains:

- A photo-friendly image preview.
- A hidden `artwork_id` input.
- A “Choose from Media” button.
- A “Use Default SVG” button.

The media frame accepts images only and allows one selection. Choosing an image
updates the attachment ID and preview. “Use Default SVG” clears the attachment
ID and restores a small preview of the default state. No manual URL input is
provided.

The existing logo field retains its current behavior. Its UI may adopt the same
generic data attributes internally, but its saved settings and frontend behavior
do not change.

## Data and Validation

The Maintenance Mode option gains an `artwork_id` key with a default value of
zero.

On save:

1. Read the submitted value with `wp_unslash()`.
2. Convert it with `absint()`.
3. Keep it only when `wp_attachment_is_image()` confirms that the attachment is
   an image.
4. Store zero for an empty, missing, deleted, or non-image attachment.

The existing `manage_options` capability check and action nonce continue to
protect the settings request.

Only the attachment ID is stored. Image URLs and Alt Text are resolved from
WordPress when the maintenance page renders, so later Media Library changes are
reflected automatically.

## Frontend Rendering

The renderer resolves `artwork_id` as a WordPress image attachment:

- A valid image is rendered with `wp_get_attachment_image()` using an
  appropriate large responsive image size.
- The output retains the `sitx-maintenance__art` class and adds a modifier class
  for custom artwork.
- WordPress supplies responsive image attributes where available.
- The attachment’s Media Library Alt Text is used. If it is empty, the image is
  emitted with `alt=""`.

Custom media:

- Preserves its original aspect ratio.
- Is never cropped.
- Uses up to 520px of the available card width.
- Has a bounded responsive height and `object-fit: contain`.
- Scales down cleanly on mobile.

When no valid selected attachment exists, the current inline SVG is rendered
unchanged. The SVG retains its compact 260px maximum width and remains
decorative.

## Failure Handling

- Media scripts unavailable: the field remains visible and existing saved data
  is not modified; clicking the selector shows the existing SiteIntelix error
  feedback pattern.
- Attachment deleted after saving: frontend rendering falls back to the SVG.
- Non-image attachment submitted manually: the value is rejected and stored as
  zero.
- Image metadata or Alt Text missing: the image still renders safely with an
  empty alt attribute.
- Image URL resolution failure: the SVG is rendered instead.

No frontend warning is shown to visitors for an invalid attachment.

## Security and Privacy

- Settings changes require `manage_options`.
- The existing nonce is verified before processing.
- Attachment IDs are normalized with `absint()` and verified as images.
- No remote URL or arbitrary path is accepted.
- Frontend image HTML is produced by WordPress attachment helpers.
- Any explicitly emitted attribute is escaped for its output context.
- The feature performs no external requests and adds no personal-data storage.

## Testing

Automated coverage will verify:

- `artwork_id` is present in defaults.
- A valid image attachment ID is retained.
- Empty, negative, missing, and non-image IDs normalize to zero.
- The settings field uses a Media Library-only single-image picker.
- Selecting and clearing artwork updates the hidden field and preview.
- A valid attachment renders as `sitx-maintenance__art` custom media.
- Media Library Alt Text reaches the rendered image.
- Empty Alt Text produces an empty alt attribute.
- Invalid or deleted attachments render the original SVG.
- The custom image receives photo-friendly responsive CSS while the default SVG
  retains its compact sizing.
- Existing logo picker behavior and Maintenance Mode authorization remain intact.

The full structural, PHP, JavaScript, PluginCheck, and live WordPress bootstrap
checks will be rerun after implementation.

## Acceptance Criteria

The feature is complete when an authorized administrator can select an image
from the WordPress Media Library, save it, and see it replace the built-in
maintenance illustration without cropping. Clearing the selection or deleting
the attachment restores the built-in SVG automatically. The selected image uses
its attachment Alt Text and remains responsive across desktop and mobile
layouts.
