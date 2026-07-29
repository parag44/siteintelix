# File Manager Desktop Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the approved three-pane SiteIntelix File Manager workspace with repaired shared icons, file-type visuals, desktop-style selection, an accessible context menu, and viewport-contained pane scrolling.

**Architecture:** Preserve the existing PHP security/services and operation-specific AJAX contracts. Add one shared sanitized SVG helper for module/page icons, convert the browser markup into a persistent three-pane shell, and keep interaction logic in the existing dependency-free File Manager script through small exported pure helpers plus one reusable context-menu controller.

**Tech Stack:** WordPress PHP, WordPress admin/Dashicons, vanilla JavaScript, scoped CSS, Node.js built-in test runner, dependency-free PHP test scripts.

---

## File Map

- `includes/class-siteintelix-admin-ui.php` — sanitize and render shared inline SVG icons; allow page headers to receive `icon_svg`.
- `includes/class-siteintelix-modules.php` — retain the plugin-owned File Manager folder SVG as the single registry source and use a valid Dashicon fallback.
- `admin/views/modules-page.php` — render module inline SVG through the shared helper.
- `admin/views/settings-page.php` — render settings-tab inline SVG through the shared helper.
- `assets/admin/css/siteintelix-admin.css` — explicitly size only SVGs inside module-card/settings-tab icon wrappers.
- `includes/modules/file-manager/views/file-manager.php` — pass the registry SVG to the shared page header.
- `includes/modules/file-manager/views/partials/toolbar.php` — add local icon markup and polish hooks without changing toolbar functions.
- `includes/modules/file-manager/views/partials/file-table.php` — remove the visible Actions column and provide the narrow row-menu column.
- `includes/modules/file-manager/views/partials/details-panel.php` — add a file-type hero icon hook and remove duplicated detail action buttons.
- `includes/modules/file-manager/views/partials/modals.php` — add one reusable accessible context-menu shell.
- `includes/modules/file-manager/assets/file-manager.js` — file classification, icon rendering, selection/opening, permission-aware menu contents, keyboard behavior, menu positioning, focus restoration, and stale-details protection.
- `includes/modules/file-manager/assets/file-manager.css` — implement the approved visual design and independent viewport scrolling.
- `tests/structural.test.mjs` — enforce shared icon, markup, CSS, and safety invariants.
- `tests/file-manager-ui.test.mjs` — unit-test pure interaction helpers and retain current safe-DOM assertions.

## Task 1: Repair the shared File Manager icon

**Files:**
- Modify: `tests/structural.test.mjs`
- Modify: `includes/class-siteintelix-admin-ui.php`
- Modify: `includes/class-siteintelix-modules.php`
- Modify: `admin/views/modules-page.php`
- Modify: `admin/views/settings-page.php`
- Modify: `assets/admin/css/siteintelix-admin.css`
- Modify: `includes/modules/file-manager/views/file-manager.php`

- [ ] **Step 1: Write the failing structural test**

Add this focused test near the existing File Manager admin UI test:

```js
test('File Manager uses one safely rendered folder SVG in every admin context', async () => {
	const [ui, registry, modules, settings, page, css] = await Promise.all([
		read('includes/class-siteintelix-admin-ui.php'),
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/modules-page.php'),
		read('admin/views/settings-page.php'),
		read('includes/modules/file-manager/views/file-manager.php'),
		read('assets/admin/css/siteintelix-admin.css'),
	]);

	assert.match(registry, /'file_manager'[\s\S]*'icon_svg'\s*=>\s*'<svg/);
	assert.match(ui, /public static function svg_icon\(/);
	assert.match(ui, /'icon_svg'\s*=>\s*''/);
	assert.match(modules, /SITEINTELIX_Admin_UI::svg_icon\(/);
	assert.match(settings, /SITEINTELIX_Admin_UI::svg_icon\(/);
	assert.match(page, /'icon_svg'\s*=>\s*\$siteintelix_fm_module\['icon_svg'\]/);
	assert.match(css, /\.sitx-module-card__icon\s*>\s*svg,[\s\S]*\.sitx-settings-tab__icon\s*>\s*svg/);
	assert.doesNotMatch(registry, /'file_manager'[\s\S]{0,600}'icon'\s*=>\s*'dashicons-open-folder'/);
});
```

- [ ] **Step 2: Run the test and confirm the red state**

Run:

```bash
node --test --test-name-pattern='one safely rendered folder SVG' tests/structural.test.mjs
```

Expected: FAIL because `svg_icon()`, page-header `icon_svg`, scoped SVG CSS, and the valid fallback are not implemented.

- [ ] **Step 3: Add the shared sanitized SVG renderer**

In `SITEINTELIX_Admin_UI`, add:

```php
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
```

Extend `page_header()` defaults and icon rendering:

```php
$defaults = array(
	'icon'        => 'dashicons-admin-generic',
	'icon_svg'    => '',
	'title'       => '',
	'description' => '',
	'badges'      => array(),
	'actions'     => array(),
);
```

```php
<?php
echo wp_kses(
	$args['icon_svg']
		? self::svg_icon( $args['icon_svg'], 'si-page-header__icon siteintelix-header__icon' )
		: self::icon( $args['icon'], 'si-page-header__icon siteintelix-header__icon' ),
	array(
		'span' => array( 'class' => true ),
		'svg'  => array( 'aria-hidden' => true, 'focusable' => true, 'viewbox' => true ),
		'path' => array( 'd' => true, 'fill' => true ),
	)
);
?>
```

- [ ] **Step 4: Route all File Manager icon output through the helper**

Change the File Manager registry fallback:

```php
'icon'     => 'dashicons-category',
'icon_svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5Zm2 3v9h14V8H5Z"/></svg>',
```

Replace the duplicated `wp_kses()` SVG blocks in Modules and Settings with:

```php
echo wp_kses(
	SITEINTELIX_Admin_UI::svg_icon( (string) $module['icon_svg'] ),
	array(
		'span' => array( 'class' => true ),
		'svg'  => array( 'aria-hidden' => true, 'focusable' => true, 'viewbox' => true ),
		'path' => array( 'd' => true, 'fill' => true ),
	)
);
```

Use `$siteintelix_tab['icon_svg']` instead of `$module['icon_svg']` in the Settings variant.

Before the File Manager header, resolve the registered module and pass both icon forms:

```php
$siteintelix_fm_modules = SITEINTELIX_Modules::get_all();
$siteintelix_fm_module  = isset( $siteintelix_fm_modules['file_manager'] ) ? $siteintelix_fm_modules['file_manager'] : array();
```

```php
'icon'     => isset( $siteintelix_fm_module['icon'] ) ? $siteintelix_fm_module['icon'] : 'dashicons-category',
'icon_svg' => isset( $siteintelix_fm_module['icon_svg'] ) ? $siteintelix_fm_module['icon_svg'] : '',
```

- [ ] **Step 5: Add wrapper-scoped SVG dimensions**

Add to `siteintelix-admin.css` next to the existing module icon rules:

```css
.sitx-module-card__icon > svg,
.sitx-settings-tab__icon > svg,
.sitx-module-card__icon > .si-icon--svg > svg,
.sitx-settings-tab__icon > .si-icon--svg > svg {
	display: block;
	width: 20px;
	height: 20px;
	flex: 0 0 20px;
}

.sitx-module-card__icon > .si-icon--svg,
.sitx-settings-tab__icon > .si-icon--svg {
	display: inline-flex;
	width: 20px;
	height: 20px;
	align-items: center;
	justify-content: center;
	flex: 0 0 20px;
}
```

- [ ] **Step 6: Run the focused test and commit**

Run:

```bash
node --test --test-name-pattern='one safely rendered folder SVG' tests/structural.test.mjs
git diff --check
```

Expected: PASS and no whitespace errors.

Commit:

```bash
git add tests/structural.test.mjs includes/class-siteintelix-admin-ui.php includes/class-siteintelix-modules.php admin/views/modules-page.php admin/views/settings-page.php assets/admin/css/siteintelix-admin.css includes/modules/file-manager/views/file-manager.php
git commit -m "fix: render file manager icons consistently"
```

## Task 2: Convert the browser markup to the desktop workspace shell

**Files:**
- Modify: `tests/structural.test.mjs`
- Modify: `includes/modules/file-manager/views/partials/toolbar.php`
- Modify: `includes/modules/file-manager/views/partials/file-table.php`
- Modify: `includes/modules/file-manager/views/partials/details-panel.php`
- Modify: `includes/modules/file-manager/views/partials/modals.php`

- [ ] **Step 1: Write the failing browser-shell assertions**

Add:

```js
test('File Manager browser exposes the approved desktop workspace shell', async () => {
	const [toolbar, table, details, modals] = await Promise.all([
		read('includes/modules/file-manager/views/partials/toolbar.php'),
		read('includes/modules/file-manager/views/partials/file-table.php'),
		read('includes/modules/file-manager/views/partials/details-panel.php'),
		read('includes/modules/file-manager/views/partials/modals.php'),
	]);

	assert.match(toolbar, /sitx-fm-toolbar__icon/);
	assert.match(toolbar, /dashicons-arrow-left-alt2/);
	assert.match(toolbar, /dashicons-plus-alt2/);
	assert.doesNotMatch(table, />Actions</);
	assert.match(table, /sitx-fm-table__menu-column/);
	assert.match(table, /Item menu/);
	assert.match(details, /data-fm-details-icon/);
	assert.doesNotMatch(details, /data-fm-details-actions/);
	assert.match(modals, /data-fm-context-menu/);
	assert.match(modals, /role="menu"/);
});
```

- [ ] **Step 2: Run the focused test and confirm it fails**

Run:

```bash
node --test --test-name-pattern='approved desktop workspace shell' tests/structural.test.mjs
```

Expected: FAIL on the missing icon hooks, menu column, details hero, and context-menu shell.

- [ ] **Step 3: Add local icons to the existing toolbar controls**

Keep every existing button and data attribute, but prepend fixed Dashicon spans:

```php
<button type="button" class="si-button si-button--secondary" data-fm-back disabled><span class="sitx-fm-toolbar__icon dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'Back', 'siteintelix' ); ?></span></button>
<button type="button" class="si-button si-button--secondary" data-fm-forward disabled><span class="sitx-fm-toolbar__icon dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'Forward', 'siteintelix' ); ?></span></button>
<button type="button" class="si-button si-button--secondary" data-fm-up><span class="sitx-fm-toolbar__icon dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'Up', 'siteintelix' ); ?></span></button>
<button type="button" class="si-button si-button--secondary" data-fm-refresh><span class="sitx-fm-toolbar__icon dashicons dashicons-update" aria-hidden="true"></span><span><?php esc_html_e( 'Refresh', 'siteintelix' ); ?></span></button>
```

Use `dashicons-plus-alt2`, `dashicons-upload`, `dashicons-category`, and `dashicons-info-outline` for New, Upload, Folders, and Details respectively. Add a decorative `dashicons-search` span inside the search label.

- [ ] **Step 4: Remove the visible Actions column and add a menu column**

Replace the final table heading with:

```php
<th scope="col" class="sitx-fm-table__menu-column"><span class="screen-reader-text"><?php esc_html_e( 'Item menu', 'siteintelix' ); ?></span></th>
```

The table keeps Name, Type, Size, Modified, Permissions, and Writable headings.

- [ ] **Step 5: Add the details hero and shared menu shell**

In the Details content, add:

```php
<div class="sitx-fm-details__hero" data-fm-details-icon aria-hidden="true"></div>
```

Keep the name, previews, and metadata, and remove the `data-fm-details-actions` container.

Append to `modals.php` before the upload input:

```php
<div class="sitx-fm-context-menu" data-fm-context-menu role="menu" aria-label="<?php esc_attr_e( 'Item actions', 'siteintelix' ); ?>" hidden></div>
```

- [ ] **Step 6: Run the focused test and commit**

Run:

```bash
node --test --test-name-pattern='approved desktop workspace shell' tests/structural.test.mjs
git diff --check
```

Expected: PASS.

Commit:

```bash
git add tests/structural.test.mjs includes/modules/file-manager/views/partials/toolbar.php includes/modules/file-manager/views/partials/file-table.php includes/modules/file-manager/views/partials/details-panel.php includes/modules/file-manager/views/partials/modals.php
git commit -m "refactor: add file manager desktop workspace shell"
```

## Task 3: Add testable file-type and context-menu decisions

**Files:**
- Modify: `tests/file-manager-ui.test.mjs`
- Modify: `includes/modules/file-manager/assets/file-manager.js`

- [ ] **Step 1: Write failing tests for pure UI decisions**

Add:

```js
test('file categories are normalized to a fixed visual allowlist', async () => {
	const { helpers } = await loadHelpers();

	assert.equal(helpers.fileCategory({ type: 'directory', name: 'plugins' }), 'folder');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'plugin.php', extension: 'PHP' }), 'php');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'admin.css' }), 'css');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'photo.webp' }), 'image');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'bundle.zip' }), 'archive');
	assert.equal(helpers.fileCategory({ type: 'file', name: '.env' }), 'text');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'unknown.xyz' }), 'file');
});

test('context actions preserve server permissions and stable labels', async () => {
	const { helpers } = await loadHelpers();
	const actions = helpers.contextActions({
		type: 'file',
		actions: ['open', 'details', 'download', 'rename', 'trash', 'arbitrary'],
	});

	assert.deepEqual(
		Array.from(actions, (action) => `${action.id}:${action.label}:${action.icon}`),
		[
			'open:Open:dashicons-external',
			'details:View details:dashicons-info-outline',
			'download:Download:dashicons-download',
			'rename:Rename:dashicons-edit',
			'trash:Move to Trash:dashicons-trash',
		],
	);
	assert.deepEqual(Array.from(helpers.contextActions({ type: 'file', actions: ['details'] }), (action) => action.id), ['details']);
});

test('primary actions and context-menu coordinates follow desktop conventions', async () => {
	const { helpers } = await loadHelpers();

	assert.equal(helpers.primaryAction({ type: 'directory', actions: ['open', 'details'] }), 'open');
	assert.equal(helpers.primaryAction({ type: 'file', actions: ['edit', 'details'] }), 'edit');
	assert.equal(helpers.primaryAction({ type: 'file', actions: ['details'] }), 'details');
	assert.deepEqual(
		helpers.clampMenuPosition({ x: 790, y: 590 }, { width: 180, height: 220 }, { width: 800, height: 600, padding: 8 }),
		{ left: 612, top: 372 },
	);
});
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run:

```bash
node --test --test-name-pattern='file categories|context actions|primary actions' tests/file-manager-ui.test.mjs
```

Expected: FAIL because the three helpers are not exported.

- [ ] **Step 3: Implement the fixed pure helpers**

Place these before the public test export:

```js
var fileCategories = {
	php: ['php', 'phtml'],
	javascript: ['js', 'mjs', 'json'],
	css: ['css', 'scss', 'sass', 'less'],
	html: ['html', 'htm', 'xml'],
	text: ['txt', 'md', 'log', 'ini', 'conf', 'config', 'env', 'htaccess'],
	image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif'],
	archive: ['zip', 'tar', 'gz', 'gzip', 'tgz', 'bz2', '7z'],
	document: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
	media: ['mp3', 'wav', 'ogg', 'mp4', 'webm', 'mov'],
};

function itemExtension(item) {
	var supplied = String((item && item.extension) || '').toLowerCase().replace(/^\./, '');
	var name = String((item && item.name) || '');
	var suffix = name.indexOf('.') === -1 ? '' : name.split('.').pop().toLowerCase();
	return supplied || suffix;
}

function fileCategory(item) {
	if (item && item.type === 'directory') {
		return 'folder';
	}
	var extension = itemExtension(item);
	if (String((item && item.name) || '').charAt(0) === '.' && !extension) {
		return 'text';
	}
	var category = Object.keys(fileCategories).find(function (key) {
		return fileCategories[key].indexOf(extension) !== -1;
	});
	return category || 'file';
}

var contextActionMap = {
	open: { id: 'open', label: 'Open', icon: 'dashicons-external' },
	view: { id: 'view', label: 'Preview', icon: 'dashicons-visibility' },
	details: { id: 'details', label: 'View details', icon: 'dashicons-info-outline' },
	edit: { id: 'edit', label: 'Edit', icon: 'dashicons-edit-page' },
	download: { id: 'download', label: 'Download', icon: 'dashicons-download' },
	rename: { id: 'rename', label: 'Rename', icon: 'dashicons-edit' },
	trash: { id: 'trash', label: 'Move to Trash', icon: 'dashicons-trash', destructive: true },
};

function contextActions(item) {
	return ((item && item.actions) || []).filter(function (id) {
		return Object.prototype.hasOwnProperty.call(contextActionMap, id);
	}).map(function (id) {
		return contextActionMap[id];
	});
}

function primaryAction(item) {
	var available = contextActions(item).map(function (action) { return action.id; });
	var order = item && item.type === 'directory'
		? ['open', 'details']
		: ['open', 'view', 'edit', 'details'];
	return order.find(function (id) { return available.indexOf(id) !== -1; }) || null;
}

function clampMenuPosition(point, menu, viewport) {
	var padding = Number(viewport.padding || 0);
	return {
		left: Math.max(padding, Math.min(Number(point.x), Number(viewport.width) - Number(menu.width) - padding)),
		top: Math.max(padding, Math.min(Number(point.y), Number(viewport.height) - Number(menu.height) - padding)),
	};
}
```

Export `fileCategory`, `contextActions`, `primaryAction`, and `clampMenuPosition` on `siteintelixFileManagerTest`.

- [ ] **Step 4: Correct dotfile normalization and pass the tests**

Ensure `.env`, `.htaccess`, and other leading-dot config names map to `text` by adding:

```js
if (name.charAt(0) === '.' && name.indexOf('.', 1) === -1) {
	return name.slice(1).toLowerCase();
}
```

at the start of `itemExtension()` before calculating the final suffix.

Run:

```bash
node --test tests/file-manager-ui.test.mjs
```

Expected: all File Manager UI tests PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/file-manager-ui.test.mjs includes/modules/file-manager/assets/file-manager.js
git commit -m "test: define file manager desktop interaction rules"
```

## Task 4: Implement selection, icons, and the reusable context menu

**Files:**
- Modify: `tests/file-manager-ui.test.mjs`
- Modify: `includes/modules/file-manager/assets/file-manager.js`

- [ ] **Step 1: Add failing source-level accessibility assertions**

Extend the safe-rendering test:

```js
assert.match(source, /data-fm-context-menu/);
assert.match(source, /event\.key\s*===\s*'F10'\s*&&\s*event\.shiftKey/);
assert.match(source, /event\.key\s*===\s*'ContextMenu'/);
assert.match(source, /setAttribute\(\s*'aria-selected'/);
assert.match(source, /addEventListener\(\s*'dblclick'/);
assert.match(source, /addEventListener\(\s*'contextmenu'/);
assert.match(source, /data-fm-row-menu/);
assert.match(source, /detailsRequestId/);
assert.doesNotMatch(source, /data-fm-details-actions/);
```

- [ ] **Step 2: Run the focused test and confirm it fails**

Run:

```bash
node --test --test-name-pattern='safe DOM assignment' tests/file-manager-ui.test.mjs
```

Expected: FAIL because the new row/menu interactions and stale-response guard are absent.

- [ ] **Step 3: Extend state and create visual icon nodes**

Add:

```js
contextOrigin: null,
contextItem: null,
detailsRequestId: 0,
```

to `state`.

Add:

```js
function fileIcon(item, large) {
	var category = fileCategory(item);
	var icon = node('span', 'sitx-fm-file-icon sitx-fm-file-icon--' + category + (large ? ' is-large' : ''));
	icon.setAttribute('aria-hidden', 'true');
	icon.appendChild(node('span', 'sitx-fm-file-icon__label', category === 'folder' ? '' : category.slice(0, 3).toUpperCase()));
	return icon;
}
```

- [ ] **Step 4: Replace row buttons and inline actions with desktop rows**

Inside `renderItems()`, create each row with:

```js
row.tabIndex = 0;
row.setAttribute('aria-selected', 'false');
row.dataset.fmItemPath = item.path;

var nameCell = node('td', 'sitx-fm-name-cell');
var nameWrap = node('span', 'sitx-fm-name');
nameWrap.appendChild(fileIcon(item, false));
nameWrap.appendChild(node('span', 'sitx-fm-name__label', item.name));
nameCell.appendChild(nameWrap);
row.appendChild(nameCell);
```

After the metadata cells, append:

```js
var menuCell = node('td', 'sitx-fm-table__menu-cell');
var menuButton = button('', function (event) {
	event.stopPropagation();
	openContextMenu(item, row, event.currentTarget);
}, 'sitx-fm-row-menu');
menuButton.setAttribute('data-fm-row-menu', '');
menuButton.setAttribute('aria-label', 'Actions for ' + item.name);
menuButton.appendChild(node('span', 'dashicons dashicons-ellipsis', ''));
menuCell.appendChild(menuButton);
row.appendChild(menuCell);
```

Bind:

```js
row.addEventListener('click', function () {
	selectItem(item, row);
});
row.addEventListener('dblclick', function () {
	selectItem(item, row);
	runPrimaryAction(item, row);
});
row.addEventListener('contextmenu', function (event) {
	event.preventDefault();
	selectItem(item, row);
	openContextMenu(item, row, event.currentTarget, { x: event.clientX, y: event.clientY });
});
row.addEventListener('keydown', function (event) {
	if (event.key === 'Enter') {
		event.preventDefault();
		runPrimaryAction(item, row);
	} else if ((event.key === 'F10' && event.shiftKey) || event.key === 'ContextMenu') {
		event.preventDefault();
		openContextMenu(item, row, row);
	}
});
```

Remove `actionButton()` and the old `sitx-fm-row-actions` creation only from the Browser renderer; Backups and Trash keep their operation buttons.

- [ ] **Step 5: Implement selection and stale-detail protection**

Update `selectItem()` to set both visual and accessibility state:

```js
entry.classList.toggle('is-selected', entry === row);
entry.setAttribute('aria-selected', entry === row ? 'true' : 'false');
```

At the start of `showDetails()`:

```js
var requestId = ++state.detailsRequestId;
var icon = select('[data-fm-details-icon]');
clear(icon);
icon.appendChild(fileIcon(item, true));
```

Before each asynchronous details/preview result mutates the DOM:

```js
if (requestId !== state.detailsRequestId || !state.selected || state.selected.path !== item.path) {
	return;
}
```

Remove all reads/writes of `data-fm-details-actions`.

- [ ] **Step 6: Implement the single reusable context menu**

Add the menu controller below. It uses only `contextActions(item)`, creates labels with `textContent`, and routes operations through the existing handlers:

```js
function closeContextMenu(restoreFocus) {
	var menu = select('[data-fm-context-menu]');
	if (!menu || menu.hidden) {
		return;
	}
	menu.hidden = true;
	clear(menu);
	if (restoreFocus && state.contextOrigin && typeof state.contextOrigin.focus === 'function') {
		state.contextOrigin.focus();
	}
	state.contextOrigin = null;
	state.contextItem = null;
}

function runPrimaryAction(item, trigger) {
	var action = primaryAction(item);
	if (action) {
		handleItemAction(action, item, trigger);
	}
}

function contextMenuItems() {
	return selectAll('[role="menuitem"]', select('[data-fm-context-menu]'));
}

function moveContextFocus(key) {
	var items = contextMenuItems();
	if (!items.length) {
		return;
	}
	var current = Math.max(0, items.indexOf(document.activeElement));
	var next = current;
	if (key === 'ArrowDown') {
		next = (current + 1) % items.length;
	} else if (key === 'ArrowUp') {
		next = (current - 1 + items.length) % items.length;
	} else if (key === 'Home') {
		next = 0;
	} else if (key === 'End') {
		next = items.length - 1;
	}
	items[next].focus();
}

function contextMenuItem(action, item, origin) {
	var element = action.id === 'download'
		? node('a', 'sitx-fm-context-menu__item')
		: node('button', 'sitx-fm-context-menu__item');
	if (action.id === 'download') {
		element.href = downloadUrl(item.path);
	} else {
		element.type = 'button';
		element.addEventListener('click', function () {
			closeContextMenu(false);
			handleItemAction(action.id, item, origin);
		});
	}
	element.setAttribute('role', 'menuitem');
	element.tabIndex = -1;
	if (action.destructive) {
		element.classList.add('is-destructive');
	}
	var icon = node('span', 'sitx-fm-context-menu__icon dashicons ' + action.icon);
	icon.setAttribute('aria-hidden', 'true');
	element.appendChild(icon);
	element.appendChild(node('span', '', action.label));
	return element;
}

function openContextMenu(item, row, origin, coordinates) {
	var menu = select('[data-fm-context-menu]');
	var actions = contextActions(item);
	if (!menu || !actions.length) {
		return;
	}
	closeContextMenu(false);
	selectItem(item, row);
	state.contextItem = item;
	state.contextOrigin = origin;
	clear(menu);
	actions.forEach(function (action) {
		if (action.destructive && menu.childNodes.length) {
			menu.appendChild(node('span', 'sitx-fm-context-menu__separator'));
		}
		menu.appendChild(contextMenuItem(action, item, origin));
	});
	menu.hidden = false;
	var rect = menu.getBoundingClientRect();
	var point = coordinates || {
		x: origin.getBoundingClientRect().right,
		y: origin.getBoundingClientRect().bottom,
	};
	var position = clampMenuPosition(
		point,
		{ width: rect.width, height: rect.height },
		{ width: global.innerWidth, height: global.innerHeight, padding: 8 }
	);
	menu.style.left = position.left + 'px';
	menu.style.top = position.top + 'px';
	if (!coordinates) {
		var first = contextMenuItems()[0];
		if (first) {
			first.focus();
		}
	}
}
```

Bind the menu's keyboard handler once in `bindBrowser()`:

```js
var menu = select('[data-fm-context-menu]');
menu.addEventListener('keydown', function (event) {
	if (event.key === 'Escape') {
		event.preventDefault();
		closeContextMenu(true);
	} else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(event.key) !== -1) {
		event.preventDefault();
		moveContextFocus(event.key);
	}
});
```

Also bind these exact dismissal listeners in `bindBrowser()`:

```js
document.addEventListener('pointerdown', function (event) {
	if (!menu.hidden && !menu.contains(event.target) && !event.target.closest('[data-fm-row-menu]')) {
		closeContextMenu(false);
	}
});
global.addEventListener('resize', function () { closeContextMenu(false); });
global.addEventListener('scroll', function () { closeContextMenu(false); }, true);
```

Call `closeContextMenu(false)` at the start of both `visit()` and `loadDirectory()`.

- [ ] **Step 7: Run tests and commit**

Run:

```bash
node --test tests/file-manager-ui.test.mjs
node --test --test-name-pattern='File Manager admin UI|desktop workspace shell' tests/structural.test.mjs
git diff --check
```

Expected: all commands PASS.

Commit:

```bash
git add tests/file-manager-ui.test.mjs includes/modules/file-manager/assets/file-manager.js
git commit -m "feat: add desktop file selection and context menu"
```

## Task 5: Match the approved visual design and constrain pane scrolling

**Files:**
- Modify: `tests/structural.test.mjs`
- Modify: `includes/modules/file-manager/assets/file-manager.css`

- [ ] **Step 1: Write failing CSS structure assertions**

Add:

```js
test('File Manager workspace matches the approved contained three-pane design', async () => {
	const css = await read('includes/modules/file-manager/assets/file-manager.css');

	assert.match(css, /\.sitx-fm-browser\s*\{[\s\S]*overflow:\s*hidden/);
	assert.match(css, /\.sitx-fm-workspace\s*\{[\s\S]*height:\s*clamp\(/);
	assert.match(css, /\.sitx-fm-tree,[\s\S]*\.sitx-fm-files,[\s\S]*\.sitx-fm-details\s*\{[\s\S]*min-height:\s*0/);
	assert.match(css, /\.sitx-fm-tree\s*\{[\s\S]*overflow-y:\s*auto/);
	assert.match(css, /\.sitx-fm-table-scroll\s*\{[\s\S]*height:\s*100%[\s\S]*overflow:\s*auto/);
	assert.match(css, /\.sitx-fm-details\s*\{[\s\S]*overflow-y:\s*auto/);
	assert.match(css, /\.sitx-fm-table thead\s*\{[\s\S]*position:\s*sticky/);
	assert.match(css, /\.sitx-fm-file-icon--folder/);
	assert.match(css, /\.sitx-fm-context-menu\s*\{[\s\S]*position:\s*fixed/);
	assert.match(css, /\.sitx-fm-table tbody tr\.is-selected/);
	assert.match(css, /@media \(max-width:\s*1100px\)/);
});
```

- [ ] **Step 2: Run the test and confirm it fails**

Run:

```bash
node --test --test-name-pattern='contained three-pane design' tests/structural.test.mjs
```

Expected: FAIL on the contained height, independent scrolling, file icon, context menu, and selected-row rules.

- [ ] **Step 3: Implement the approved workspace geometry**

Refactor the existing scoped stylesheet around these required rules:

```css
.sitx-file-manager {
	--sitx-fm-tree: 190px;
	--sitx-fm-details: 250px;
	--sitx-fm-row-height: 44px;
}

.sitx-fm-browser {
	overflow: hidden;
	border-radius: 12px;
	box-shadow: var(--si-shadow-sm);
}

.sitx-fm-workspace {
	display: grid;
	grid-template-columns: var(--sitx-fm-tree) minmax(430px, 1fr) var(--sitx-fm-details);
	height: clamp(480px, calc(100vh - 332px), 720px);
	min-height: 0;
	overflow: hidden;
}

.sitx-fm-tree,
.sitx-fm-files,
.sitx-fm-details {
	min-width: 0;
	min-height: 0;
}

.sitx-fm-tree,
.sitx-fm-details {
	overflow-y: auto;
	background: var(--si-surface);
	scrollbar-gutter: stable;
}

.sitx-fm-files {
	display: grid;
	grid-template-rows: minmax(0, 1fr) auto;
	overflow: hidden;
}

.sitx-fm-table-scroll {
	width: 100%;
	height: 100%;
	overflow: auto;
}

.sitx-fm-table thead {
	position: sticky;
	z-index: 2;
	top: 0;
	background: var(--si-surface-subtle);
}
```

- [ ] **Step 4: Implement the approved list, selection, and menu styling**

Use the mockup values:

```css
.sitx-fm-table tbody tr {
	height: var(--sitx-fm-row-height);
	transition: background-color .12s ease, box-shadow .12s ease;
}

.sitx-fm-table tbody tr:hover {
	background: #f8fbfe;
}

.sitx-fm-table tbody tr.is-selected {
	background: #eaf4ff;
	box-shadow: inset 3px 0 0 var(--si-primary);
}

.sitx-fm-name {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
	color: var(--si-text);
	font-weight: 700;
}

.sitx-fm-row-menu {
	display: inline-grid;
	width: 30px;
	height: 30px;
	padding: 0;
	place-items: center;
	border: 0;
	border-radius: 6px;
	color: var(--si-muted);
	background: transparent;
	cursor: pointer;
}

.sitx-fm-context-menu {
	position: fixed;
	z-index: 100180;
	width: 176px;
	padding: 6px;
	border: 1px solid var(--si-border);
	border-radius: 8px;
	background: var(--si-surface);
	box-shadow: 0 14px 36px rgb(15 23 42 / 22%);
}

.sitx-fm-context-menu[hidden] {
	display: none;
}

.sitx-fm-context-menu__item {
	display: flex;
	width: 100%;
	min-height: 34px;
	align-items: center;
	gap: 9px;
	padding: 0 9px;
	border: 0;
	border-radius: 5px;
	color: var(--si-text);
	background: transparent;
	text-align: left;
	cursor: pointer;
}

.sitx-fm-context-menu__item:hover,
.sitx-fm-context-menu__item:focus-visible {
	color: var(--si-primary);
	background: #eaf4ff;
}

.sitx-fm-context-menu__item.is-destructive {
	color: var(--si-danger);
}
```

Add a one-pixel separator element before `.is-destructive`.

- [ ] **Step 5: Implement the local file-icon system**

Create a 24-by-28 pixel document shape with a folded corner, a 20-by-15 pixel folder shape with a tab, and category modifiers:

```css
.sitx-fm-file-icon {
	position: relative;
	display: inline-grid;
	flex: 0 0 24px;
	width: 24px;
	height: 28px;
	place-items: end center;
	padding-bottom: 4px;
	border-radius: 3px;
	color: #fff;
	background: #64748b;
	font-size: 7px;
	font-weight: 800;
	line-height: 1;
	text-transform: uppercase;
}

.sitx-fm-file-icon--folder {
	width: 22px;
	height: 15px;
	padding: 0;
	border-radius: 3px;
	background: #4aa3df;
}

.sitx-fm-file-icon--folder::before {
	position: absolute;
	top: -4px;
	left: 1px;
	width: 10px;
	height: 5px;
	border-radius: 3px 3px 0 0;
	background: inherit;
	content: "";
}

.sitx-fm-file-icon--php { background: #777bb4; }
.sitx-fm-file-icon--javascript { background: #b58b00; }
.sitx-fm-file-icon--css { background: #2277b8; }
.sitx-fm-file-icon--html { background: #d85a32; }
.sitx-fm-file-icon--text { background: #64748b; }
.sitx-fm-file-icon--image { background: #16866f; }
.sitx-fm-file-icon--archive { background: #c79a22; }
.sitx-fm-file-icon--document { background: #2563a8; }
.sitx-fm-file-icon--media { background: #b83280; }
```

Scale `.is-large` inside the details hero and hide the small label for folders.

- [ ] **Step 6: Polish toolbar, breadcrumbs, details, and responsive drawers**

Match the approved mockup with:

- 32–34 pixel toolbar buttons and 8 pixel gaps;
- primary blue New button;
- rounded search with icon;
- unboxed breadcrumb buttons and chevron separators;
- 38–40 pixel sticky table header;
- details hero, name, and divided metadata rows;
- progressively hide Permissions/Writable and then Type at narrower desktop widths;
- at `max-width: 1100px`, use the existing fixed tree/details drawers and `grid-template-columns: minmax(0, 1fr)`;
- at `max-width: 782px`, remove WordPress sidebar offsets and keep toolbar controls wrapping;
- preserve `prefers-reduced-motion`.

Use only existing `--si-*` tokens plus the approved fixed recognition colors.

- [ ] **Step 7: Run CSS/structural tests and commit**

Run:

```bash
node --test --test-name-pattern='contained three-pane design|File Manager admin UI' tests/structural.test.mjs
git diff --check
```

Expected: PASS.

Commit:

```bash
git add tests/structural.test.mjs includes/modules/file-manager/assets/file-manager.css
git commit -m "style: match file manager desktop workspace design"
```

## Task 6: Verify regressions and perform live visual QA

**Files:**
- QA correction target: `includes/modules/file-manager/assets/file-manager.js`
- QA correction target: `includes/modules/file-manager/assets/file-manager.css`
- QA correction target: `assets/admin/css/siteintelix-admin.css`
- QA regression test: `tests/file-manager-ui.test.mjs`
- QA structural regression test: `tests/structural.test.mjs`

- [ ] **Step 1: Run the complete automated Node suite**

Run:

```bash
node --test tests/*.test.mjs
```

Expected: all tests PASS with zero failures.

- [ ] **Step 2: Run every dependency-free File Manager PHP test**

Run:

```bash
php tests/file-manager-security.php
php tests/file-manager-filesystem.php
php tests/file-manager-storage.php
php tests/file-manager-editor.php
php tests/file-manager-upload.php
php tests/file-manager-ajax.php
```

Expected: each script prints its `passed` message and exits 0.

- [ ] **Step 3: Run the remaining plugin smoke scripts**

Run:

```bash
php tests/runtime-smoke.php
php tests/debug-log-parser.php
php tests/editor-links.php
```

Expected: each script exits 0 with its success output.

- [ ] **Step 4: Perform live icon QA**

Open the local WordPress admin and verify:

1. Modules: File Manager card shows the blue folder icon at the same size/alignment as neighboring module icons.
2. All Module Settings: File Manager shows the same icon; Custom CSS & JS and Code Snippets icons no longer overlap their labels.
3. File Manager: the page header uses the same blue folder SVG.

Capture screenshots at desktop width and compare them with the user's three issue screenshots.

- [ ] **Step 5: Perform live desktop workspace QA**

In a directory with more rows than fit vertically, verify:

1. The whole WordPress page does not grow with the file count.
2. Folder Tree, File List, and Details scroll independently.
3. File-table heading remains sticky.
4. Rows and icons match the approved Visual Companion design.
5. Single-click selects and updates Details.
6. Double-click and Enter perform the primary Open action.
7. Right-click shows only permitted icon-and-name actions.
8. The three-dot button opens the same menu.
9. The menu stays inside every viewport edge.
10. Shift+F10/Menu key, arrows, Home/End, Enter/Space, Escape, outside click, and focus restoration work.
11. Protected items omit Rename and Move to Trash.
12. New, Upload, Edit, Rename, Trash, Download, and navigation retain their existing secure behavior.

- [ ] **Step 6: Perform responsive and state QA**

At widths above and below 1100 pixels and below 782 pixels, verify:

1. File List remains primary.
2. Folder Tree and Details work as slide-over drawers.
3. Toolbar wraps without page-level horizontal scrolling.
4. Loading, empty, and failure states remain contained.
5. Browser zoom at 125% and 150% remains usable.
6. Reduced-motion preference disables movement transitions.

- [ ] **Step 7: Fix only reproducible QA discrepancies test-first**

For each defect, first add a focused regression assertion to `tests/file-manager-ui.test.mjs` or `tests/structural.test.mjs`, run it to see the failure, make the smallest scoped production change, and rerun the focused test.

Do not alter PHP security services or AJAX contracts during visual correction.

- [ ] **Step 8: Run final verification and inspect the diff**

Run:

```bash
node --test tests/*.test.mjs
php tests/file-manager-security.php
php tests/file-manager-filesystem.php
php tests/file-manager-storage.php
php tests/file-manager-editor.php
php tests/file-manager-upload.php
php tests/file-manager-ajax.php
php tests/runtime-smoke.php
php tests/debug-log-parser.php
php tests/editor-links.php
git diff --check
git status --short
git diff --stat HEAD~5..HEAD
```

Expected: all tests PASS, no whitespace errors, and only the approved UI/test files plus the design/plan documentation appear in the feature diff.

- [ ] **Step 9: Commit QA corrections if any**

If QA required changes:

```bash
git add includes/modules/file-manager/assets/file-manager.js includes/modules/file-manager/assets/file-manager.css assets/admin/css/siteintelix-admin.css tests/file-manager-ui.test.mjs tests/structural.test.mjs
git commit -m "fix: polish file manager desktop workspace"
```

If no QA correction was required, do not create an empty commit.
