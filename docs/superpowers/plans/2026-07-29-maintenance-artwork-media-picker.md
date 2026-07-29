# Maintenance Mode Artwork Media Picker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let administrators select a Maintenance Mode illustration from the WordPress Media Library while preserving the current SVG as the default and fallback.

**Architecture:** Extend the existing Maintenance Mode option with a validated image attachment ID. Generalize the settings-page logo picker into one reusable media-picker component, then let the frontend renderer choose between WordPress attachment HTML and the existing inline SVG. Keep all media scripts scoped to the existing SiteIntelix Settings screen.

**Tech Stack:** WordPress PHP APIs, WordPress Media Library (`wp.media`), vanilla JavaScript, SiteIntelix CSS tokens, Node.js structural/DOM tests, standalone PHP tests.

---

## File Map

- Create `tests/maintenance-artwork.php` — standalone PHP regression tests for attachment validation, custom artwork output, Alt Text behavior supplied by WordPress, and SVG fallback.
- Modify `tests/admin-interactions.test.mjs` — DOM-level media-picker tests for independent logo and artwork controls.
- Modify `tests/structural.test.mjs` — structural assertions for settings markup, responsive image helpers, fallback SVG, and scoped media loading.
- Modify `includes/modules/coming-soon/class-siteintelix-coming-soon-module.php` — settings field, save validation, attachment resolution, and frontend artwork rendering.
- Modify `assets/admin/js/siteintelix-settings.js` — reusable media-picker initializer supporting both logo and artwork.
- Modify `assets/admin/css/siteintelix-admin.css` — generic picker styles plus a wider artwork preview.
- Modify `readme.txt` — add the Maintenance artwork selector to the 2.7.3 changelog.

### Task 1: Backend Validation and Frontend Artwork Selection

**Files:**
- Create: `tests/maintenance-artwork.php`
- Modify: `includes/modules/coming-soon/class-siteintelix-coming-soon-module.php`

- [ ] **Step 1: Write the failing PHP regression test**

Create `tests/maintenance-artwork.php` with minimal WordPress stubs, load the real
module, and test its focused private helpers through reflection:

```php
<?php
/**
 * Maintenance artwork security and rendering tests.
 *
 * @package SiteIntelix
 */

define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_image_ids = array( 17 );
$siteintelix_image_html = array(
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
```

- [ ] **Step 2: Run the PHP test and verify RED**

Run:

```bash
php tests/maintenance-artwork.php
```

Expected: FAIL because `sanitize_artwork_id()` does not exist and
`render_maintenance_art()` does not accept settings or render attachments.

- [ ] **Step 3: Add the minimal backend implementation**

In `SITEINTELIX_Coming_Soon_Module`:

1. Add `'artwork_id' => 0` to `get_settings()` defaults.
2. Add the validator:

```php
/**
 * Normalize a submitted Maintenance artwork attachment ID.
 *
 * @param mixed $value Submitted value.
 * @return int
 */
private static function sanitize_artwork_id( $value ) {
	$raw_id = (int) wp_unslash( $value );
	if ( $raw_id <= 0 ) {
		return 0;
	}

	$attachment_id = absint( $raw_id );
	return wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
}
```

3. In `handle_save_settings()`, add:

```php
'artwork_id' => self::sanitize_artwork_id( $_POST['artwork_id'] ?? 0 ),
```

4. Pass settings to the artwork renderer:

```php
<?php self::render_maintenance_art( $settings ); ?>
```

5. Change the renderer signature and prepend the custom-attachment branch:

```php
/**
 * Render selected Maintenance artwork or the built-in illustration.
 *
 * @param array<string,mixed> $settings Settings.
 * @return void
 */
private static function render_maintenance_art( $settings ) {
	$artwork_id = self::sanitize_artwork_id( $settings['artwork_id'] ?? 0 );
	if ( $artwork_id ) {
		$image = wp_get_attachment_image(
			$artwork_id,
			'large',
			false,
			array(
				'class'    => 'sitx-maintenance__art sitx-maintenance__art--custom',
				'decoding' => 'async',
			)
		);
		if ( $image ) {
			echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by wp_get_attachment_image() for a validated image attachment.
			return;
		}
	}

	// Keep the existing inline SVG exactly as the fallback.
```

- [ ] **Step 4: Run the PHP test and verify GREEN**

Run:

```bash
php tests/maintenance-artwork.php
```

Expected: `Maintenance artwork tests passed.`

- [ ] **Step 5: Commit the backend behavior**

```bash
git add tests/maintenance-artwork.php includes/modules/coming-soon/class-siteintelix-coming-soon-module.php
git commit -m "feat: render selectable maintenance artwork"
```

### Task 2: Settings Markup and Reusable Media Picker

**Files:**
- Modify: `tests/admin-interactions.test.mjs`
- Modify: `includes/modules/coming-soon/class-siteintelix-coming-soon-module.php`
- Modify: `assets/admin/js/siteintelix-settings.js`

- [ ] **Step 1: Add a failing reusable-picker DOM test**

Append this focused fixture and test to `tests/admin-interactions.test.mjs`:

```js
function createSettingsMediaPickerFixture() {
	const frames = [];

	function createButton() {
		let clickListener = null;
		return {
			addEventListener(type, listener) {
				if ('click' === type) { clickListener = listener; }
			},
			click() {
				clickListener();
			},
		};
	}

	function createPreview() {
		let text = '';
		return {
			children: [],
			appendChild(child) {
				this.children.push(child);
			},
			get textContent() {
				return text;
			},
			set textContent(value) {
				text = value;
				if ('' === value) { this.children = []; }
			},
		};
	}

	function createPicker(size, withUrl) {
		const select = createButton();
		const remove = createButton();
		const preview = createPreview();
		const id = { value: '' };
		const url = withUrl ? { value: '', addEventListener() {} } : undefined;
		const attributes = {
			'data-media-title': 'Choose Image',
			'data-media-button': 'Use this image',
			'data-media-size': size,
		};
		const children = {
			'[data-siteintelix-media-select]': select,
			'[data-siteintelix-media-remove]': remove,
			'[data-siteintelix-media-preview]': preview,
			'[data-siteintelix-media-id]': id,
			'[data-siteintelix-media-url]': url ?? null,
		};

		return {
			select,
			remove,
			preview,
			id,
			url,
			classList: { toggle() {} },
			getAttribute(name) {
				return attributes[name] ?? null;
			},
			querySelector(selector) {
				return children[selector] ?? null;
			},
		};
	}

	const logo = createPicker('medium', true);
	const artwork = createPicker('large', false);
	const documentObject = {
		createElement() {
			return { alt: '', src: '' };
		},
		querySelectorAll(selector) {
			return '[data-siteintelix-maintenance-media-picker]' === selector ? [logo, artwork] : [];
		},
	};
	const windowObject = {
		wp: {
			media(config) {
				let selected = null;
				let selectListener = null;
				const frame = {
					config,
					on(type, listener) {
						if ('select' === type) { selectListener = listener; }
					},
					open() {},
					state() {
						return {
							get() {
								return {
									first() {
										return { toJSON: () => selected };
									},
								};
							},
						};
					},
					choose(item) {
						selected = item;
						selectListener();
					},
				};
				frames.push(frame);
				return frame;
			},
		},
	};

	return {
		logo,
		artwork,
		frames,
		async load() {
			const script = await read('assets/admin/js/siteintelix-settings.js');
			const moduleObject = { exports: {} };
			vm.runInNewContext(script, { module: moduleObject });
			moduleObject.exports.initMaintenanceMediaPickers(documentObject, windowObject);
		},
	};
}

test('Maintenance logo and artwork use independent image-only media pickers', async () => {
	const fixture = createSettingsMediaPickerFixture();
	await fixture.load();

	fixture.logo.select.click();
	assert.equal(fixture.frames[0].config.multiple, false);
	assert.equal(fixture.frames[0].config.library.type, 'image');
	fixture.frames[0].choose({ id: 11, url: 'logo-full.png', sizes: { medium: { url: 'logo-medium.png' } } });
	assert.equal(fixture.logo.id.value, 11);
	assert.equal(fixture.logo.url.value, 'logo-medium.png');

	fixture.artwork.select.click();
	assert.equal(fixture.frames[1].config.multiple, false);
	assert.equal(fixture.frames[1].config.library.type, 'image');
	fixture.frames[1].choose({ id: 17, url: 'art-full.jpg', sizes: { large: { url: 'art-large.jpg' } } });
	assert.equal(fixture.artwork.id.value, 17);
	assert.equal(fixture.artwork.url, undefined);
	assert.equal(fixture.artwork.preview.children[0].src, 'art-large.jpg');

	fixture.artwork.remove.click();
	assert.equal(fixture.artwork.id.value, '');
	assert.equal(fixture.artwork.preview.children.length, 0);
	assert.equal(fixture.logo.id.value, 11, 'clearing artwork must not change the logo');
});
```

- [ ] **Step 2: Run the DOM test and verify RED**

Run:

```bash
node --test --test-name-pattern='Maintenance logo and artwork use independent' tests/admin-interactions.test.mjs
```

Expected: FAIL because the settings script initializes only the legacy logo
selector and does not create an artwork frame.

- [ ] **Step 3: Add generic media-picker markup**

In `render_settings_section()`:

1. Resolve an artwork preview URL:

```php
$artwork_id          = absint( $settings['artwork_id'] );
$artwork_preview_url = $artwork_id ? wp_get_attachment_image_url( $artwork_id, 'medium_large' ) : '';
```

2. Convert the Logo wrapper to generic picker data attributes while preserving
its current hidden ID and URL inputs:

```php
<div
	class="sitx-maintenance-media-picker"
	data-siteintelix-maintenance-media-picker
	data-media-title="<?php esc_attr_e( 'Choose Logo', 'siteintelix' ); ?>"
	data-media-button="<?php esc_attr_e( 'Use this logo', 'siteintelix' ); ?>"
	data-media-size="medium"
>
```

Use these generic child attributes:

```php
data-siteintelix-media-preview
data-siteintelix-media-id
data-siteintelix-media-url
data-siteintelix-media-select
data-siteintelix-media-remove
```

3. Insert the artwork field immediately below Logo:

```php
<div class="sitx-form-field sitx-form-field--full sitx-maintenance-artwork-field">
	<span><?php esc_html_e( 'Maintenance artwork', 'siteintelix' ); ?></span>
	<p class="description"><?php esc_html_e( 'Choose an image from the Media Library, or keep the built-in illustration.', 'siteintelix' ); ?></p>
	<div
		class="sitx-maintenance-media-picker sitx-maintenance-media-picker--artwork"
		data-siteintelix-maintenance-media-picker
		data-media-title="<?php esc_attr_e( 'Choose Maintenance Artwork', 'siteintelix' ); ?>"
		data-media-button="<?php esc_attr_e( 'Use this artwork', 'siteintelix' ); ?>"
		data-media-size="large"
	>
		<div class="sitx-maintenance-media-preview sitx-maintenance-media-preview--artwork" data-siteintelix-media-preview>
			<?php if ( $artwork_preview_url ) : ?>
				<img src="<?php echo esc_url( $artwork_preview_url ); ?>" alt="">
			<?php else : ?>
				<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
			<?php endif; ?>
		</div>
		<div class="sitx-maintenance-media-controls">
			<input type="hidden" name="artwork_id" value="<?php echo esc_attr( $artwork_id ); ?>" data-siteintelix-media-id>
			<div class="sitx-maintenance-media-actions">
				<button type="button" class="si-button si-button--secondary" data-siteintelix-media-select><?php esc_html_e( 'Choose from Media', 'siteintelix' ); ?></button>
				<button type="button" class="si-button si-button--ghost" data-siteintelix-media-remove><?php esc_html_e( 'Use Default SVG', 'siteintelix' ); ?></button>
			</div>
		</div>
	</div>
</div>
```

- [ ] **Step 4: Generalize the settings-page JavaScript**

Define this reusable function above `initSettings()` in
`siteintelix-settings.js`:

```js
function initMaintenanceMediaPickers(documentObject, windowObject) {
	Array.prototype.slice.call(
		documentObject.querySelectorAll('[data-siteintelix-maintenance-media-picker]')
	).forEach(function (picker) {
		var select = picker.querySelector('[data-siteintelix-media-select]');
		var remove = picker.querySelector('[data-siteintelix-media-remove]');
		var preview = picker.querySelector('[data-siteintelix-media-preview]');
		var idInput = picker.querySelector('[data-siteintelix-media-id]');
		var urlInput = picker.querySelector('[data-siteintelix-media-url]');
		var mediaFrame;

		function preferredUrl(item) {
			var size = picker.getAttribute('data-media-size') || 'medium';
			return item && item.sizes && item.sizes[size] ? item.sizes[size].url : (item.url || '');
		}

		function setPreview(url) {
			preview.textContent = '';
			if (url) {
				var image = documentObject.createElement('img');
				image.src = url;
				image.alt = '';
				preview.appendChild(image);
			}
			picker.classList.toggle('has-image', !!url);
		}

		select.addEventListener('click', function () {
			if (!windowObject.wp || !windowObject.wp.media) { return; }
			if (!mediaFrame) {
				mediaFrame = windowObject.wp.media({
					title: picker.getAttribute('data-media-title') || 'Choose Image',
					button: { text: picker.getAttribute('data-media-button') || 'Use this image' },
					library: { type: 'image' },
					multiple: false
				});
				mediaFrame.on('select', function () {
					var item = mediaFrame.state().get('selection').first().toJSON();
					var url = preferredUrl(item);
					idInput.value = item.id || '';
					if (urlInput) { urlInput.value = url; }
					setPreview(url);
				});
			}
			mediaFrame.open();
		});

		remove.addEventListener('click', function () {
			idInput.value = '';
			if (urlInput) { urlInput.value = ''; }
			setPreview('');
		});

		if (urlInput) {
			urlInput.addEventListener('input', function () {
				setPreview(urlInput.value.trim());
			});
		}
	});
}
```

Delete the old single-logo picker block. Call
`initMaintenanceMediaPickers(documentObject, windowObject);` near the end of
`initSettings()`. Export both functions for the existing Node test pattern:

```js
if (typeof module !== 'undefined' && module.exports) {
	module.exports = {
		initSettings: initSettings,
		initMaintenanceMediaPickers: initMaintenanceMediaPickers
	};
}
```

The early return when `windowObject.wp.media` is unavailable deliberately leaves
both stored IDs untouched.

- [ ] **Step 5: Run the focused DOM test and verify GREEN**

Run:

```bash
node --test --test-name-pattern='Maintenance logo and artwork use independent' tests/admin-interactions.test.mjs
```

Expected: PASS.

- [ ] **Step 6: Commit the settings behavior**

```bash
git add tests/admin-interactions.test.mjs includes/modules/coming-soon/class-siteintelix-coming-soon-module.php assets/admin/js/siteintelix-settings.js
git commit -m "feat: add maintenance artwork media picker"
```

### Task 3: Photo-Friendly Settings and Frontend Styling

**Files:**
- Modify: `tests/structural.test.mjs`
- Modify: `assets/admin/css/siteintelix-admin.css`
- Modify: `includes/modules/coming-soon/class-siteintelix-coming-soon-module.php`

- [ ] **Step 1: Add failing structural assertions**

Append this test to `tests/structural.test.mjs`:

```js
test('Maintenance artwork is Media Library-only with responsive SVG fallback', async () => {
	const [module, settingsJs, css, main] = await Promise.all([
		read('includes/modules/coming-soon/class-siteintelix-coming-soon-module.php'),
		read('assets/admin/js/siteintelix-settings.js'),
		read('assets/admin/css/siteintelix-admin.css'),
		read('siteintelix.php'),
	]);

	assert.match(module, /name="artwork_id"/);
	assert.match(module, /wp_attachment_is_image/);
	assert.match(module, /wp_get_attachment_image\([\s\S]*?\$artwork_id[\s\S]*?'large'/);
	assert.match(module, /sitx-maintenance__art--custom/);
	assert.match(module, /<svg class="sitx-maintenance__art"/);
	assert.doesNotMatch(module, /name="artwork_url"/);
	assert.match(settingsJs, /library:\s*\{\s*type:\s*'image'\s*\}/);
	assert.match(settingsJs, /multiple:\s*false/);
	assert.match(css, /\.sitx-maintenance-media-preview--artwork/);
	assert.match(module, /sitx-maintenance__art--custom\{[^}]*max-width:min\(520px,100%\)/);
	assert.match(main, /siteintelix-settings[\s\S]*wp_enqueue_media\(\)/);
});
```

- [ ] **Step 2: Run the structural test and verify RED**

Run:

```bash
node --test --test-name-pattern='Maintenance artwork is Media Library-only' tests/structural.test.mjs
```

Expected: FAIL because the generic settings classes and photo-friendly frontend
modifier CSS are not yet complete.

- [ ] **Step 3: Generalize the admin picker CSS**

Replace the logo-specific selector block with generic selectors:

```css
.sitx-maintenance-media-picker {
	align-items: flex-start;
	display: grid;
	gap: var(--si-space-4);
	grid-template-columns: 120px minmax(0, 1fr);
	width: 100%;
}

.sitx-maintenance-media-preview {
	align-items: center;
	background: var(--si-surface-muted);
	border: 1px dashed var(--si-border-strong);
	border-radius: var(--si-radius-lg);
	color: var(--si-muted);
	display: flex;
	height: 96px;
	justify-content: center;
	overflow: hidden;
	width: 120px;
}

.sitx-maintenance-media-preview--artwork {
	aspect-ratio: 16 / 9;
	height: auto;
	width: min(320px, 100%);
}

.sitx-maintenance-media-preview img {
	display: block;
	height: 100%;
	object-fit: contain;
	padding: var(--si-space-2);
	width: 100%;
}

.sitx-maintenance-media-controls {
	display: flex;
	flex-direction: column;
	gap: var(--si-space-3);
	min-width: 0;
}

.sitx-maintenance-media-controls input[type="url"] {
	width: 100%;
}

.sitx-maintenance-media-actions {
	display: flex;
	flex-wrap: wrap;
	gap: var(--si-space-2);
}

@media (max-width: 782px) {
	.sitx-maintenance-media-picker {
		grid-template-columns: 1fr;
	}
}
```

- [ ] **Step 4: Add photo-friendly frontend modifier CSS**

Keep the existing base `.sitx-maintenance__art` rule for the SVG and add:

```css
.sitx-maintenance__art--custom{border-radius:12px;max-height:360px;max-width:min(520px,100%);object-fit:contain}
```

In the existing mobile media query, retain the SVG’s `max-width:220px` but
override custom media afterward:

```css
.sitx-maintenance__art--custom{max-height:300px;max-width:100%}
```

This ordering is required so the custom modifier wins over the compact SVG rule.

- [ ] **Step 5: Run the structural test and verify GREEN**

Run:

```bash
node --test --test-name-pattern='Maintenance artwork is Media Library-only' tests/structural.test.mjs
```

Expected: PASS.

- [ ] **Step 6: Run all feature tests**

Run:

```bash
php tests/maintenance-artwork.php
node --test tests/admin-interactions.test.mjs tests/structural.test.mjs
```

Expected: all tests pass with zero failures.

- [ ] **Step 7: Commit the styling**

```bash
git add tests/structural.test.mjs assets/admin/css/siteintelix-admin.css includes/modules/coming-soon/class-siteintelix-coming-soon-module.php
git commit -m "style: support photo-friendly maintenance artwork"
```

### Task 4: Release Notes and Complete Verification

**Files:**
- Modify: `readme.txt`

- [ ] **Step 1: Update the 2.7.3 changelog**

Add this bullet under the existing 2.7.3 entry in `readme.txt`:

```text
* Added a Media Library artwork selector for Maintenance Mode with responsive image output and automatic built-in SVG fallback.
```

- [ ] **Step 2: Run the complete automated test suite**

Run:

```bash
node --test tests/*.test.mjs
for test_file in tests/*.php; do php "$test_file" || exit 1; done
```

Expected: every Node and standalone PHP test passes.

- [ ] **Step 3: Run syntax checks**

Run:

```bash
while IFS= read -r php_file; do php -l "$php_file" >/dev/null || exit 1; done < <(rg --files -g '*.php')
while IFS= read -r js_file; do node --check "$js_file" || exit 1; done < <(rg --files -g '*.js' -g '*.mjs')
```

Expected: exit status 0 with no syntax errors.

- [ ] **Step 4: Run PluginCheck and diff validation**

Run:

```bash
php '/Users/joomshaper/Local Sites/server-info/app/public/wp-content/plugins/plugin-check/vendor/bin/phpcs' \
	--standard=PluginCheck \
	--extensions=php \
	--ignore='tests/*' \
	.
git diff --check
```

Expected: PluginCheck returns zero findings and `git diff --check` prints
nothing.

- [ ] **Step 5: Perform the live WordPress smoke test**

Using the Local environment variables for site `7QtrlWtxz`, run:

```bash
wp eval '
$settings = get_option( SITEINTELIX_Coming_Soon_Module::SETTINGS_OPTION, array() );
echo class_exists( "SITEINTELIX_Coming_Soon_Module" ) ? "module=loaded\n" : "module=missing\n";
echo array_key_exists( "artwork_id", wp_parse_args( $settings, array( "artwork_id" => 0 ) ) ) ? "artwork=available\n" : "artwork=missing\n";
'
```

Expected:

```text
module=loaded
artwork=available
```

Then manually open SiteIntelix → Settings → Maintenance Mode and verify:

1. The media frame accepts one image.
2. The artwork preview updates independently of Logo.
3. Saving persists the selection.
4. A logged-out maintenance request renders the selected image and its Alt Text.
5. “Use Default SVG” restores the built-in illustration.

- [ ] **Step 6: Commit documentation**

```bash
git add readme.txt
git commit -m "docs: note maintenance artwork selector"
```

- [ ] **Step 7: Review the completed branch**

Run:

```bash
git status --short --branch
git log -6 --oneline --decorate
```

Expected: a clean `2.7.3` working tree containing the design, plan, feature,
tests, and release-note commits. Do not build a ZIP, merge, or publish unless the
user asks.
