# Debug Path and Database Manager Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Do not commit or push because the user has not authorized Git/SVN changes.

**Goal:** Expand Modern Debug Log paths across the available summary width and deliver a functional, screenshot-matched, server-rendered Database Manager dashboard without adding heavy queries or frontend dependencies.

**Architecture:** Keep the Modern Debug Log change CSS-led, with only a title attribute added for full-path discovery. Keep Database Manager state in sanitized GET parameters, transform the existing table-metadata array in PHP, render no more than 50 rows, and reuse the existing Tables view for browsing and editing.

**Tech Stack:** WordPress PHP, existing SiteIntelix PHP view conventions, CSS design tokens, Node.js structural tests, native HTML forms/links/details.

---

## File Map

- Modify `tests/structural.test.mjs`: add structural regressions for flexible paths and the lightweight Database Manager contract.
- Modify `admin/views/debug-log-page-modern.php`: expose the compact path as a tooltip without changing editor-link behavior.
- Modify `assets/admin/css/siteintelix-debug-log.css`: remove the path's fixed width ceiling and let the metadata row consume its body width.
- Modify `includes/modules/database-manager/class-siteintelix-database-manager-module.php`: sanitize dashboard controls, filter/sort/paginate existing metadata, and render the approved dashboard structure.
- Modify `assets/admin/css/siteintelix-admin.css`: implement the approved Database Manager visual system and responsive behavior.

No new JavaScript file is required. The Actions control will use native `<details>`/`<summary>` markup, keeping the dashboard fully usable without JavaScript.

### Task 1: Lock the Debug Log path behavior with a failing test

**Files:**
- Modify: `wp-content/plugins/siteintelix/tests/structural.test.mjs`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add the failing structural test**

Append:

```js
test('Modern Debug Log gives source paths the maximum available summary width', async () => {
	const [modern, css] = await Promise.all([
		read('admin/views/debug-log-page-modern.php'),
		read('assets/admin/css/siteintelix-debug-log.css'),
	]);
	const finalPathLayer = css.slice(css.lastIndexOf('Modern Debug Log maximum-width source paths'));

	assert.match(modern, /class="sitx-log-path[^"]*"[^>]*title=/);
	assert.match(finalPathLayer, /\.sitx-log-card__body[\s\S]*min-width:\s*0/);
	assert.match(finalPathLayer, /\.sitx-log-meta[\s\S]*width:\s*100%/);
	assert.match(finalPathLayer, /\.sitx-log-path[\s\S]*flex:\s*1 1 auto/);
	assert.match(finalPathLayer, /\.sitx-log-path[\s\S]*max-width:\s*none/);
	assert.doesNotMatch(finalPathLayer, /max-width:\s*min\(520px/);
});
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
node --test --test-name-pattern="maximum available summary width" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: one failing test because the final CSS layer and title attribute do not exist.

### Task 2: Implement the flexible Debug Log path

**Files:**
- Modify: `wp-content/plugins/siteintelix/admin/views/debug-log-page-modern.php`
- Modify: `wp-content/plugins/siteintelix/assets/admin/css/siteintelix-debug-log.css`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add the full-path tooltip to both compact path variants**

For the editor link and non-link span, add:

```php
title="<?php echo esc_attr( $siteintelix_file ? $siteintelix_file . ( $siteintelix_line ? ':' . (int) $siteintelix_line : '' ) : __( 'No source path', 'siteintelix' ) ); ?>"
```

Keep all displayed text escaped and leave the existing editor URL untouched.

- [ ] **Step 2: Add the cascade-final path layout**

Append to `siteintelix-debug-log.css`:

```css
/* Modern Debug Log maximum-width source paths */
.siteintelix-log-item .sitx-log-card__body {
	min-width: 0;
}

.siteintelix-log-item .sitx-log-meta {
	flex-wrap: nowrap;
	width: 100%;
}

.siteintelix-log-item .sitx-log-path {
	display: flex;
	flex: 1 1 auto;
	max-width: none;
	min-width: 0;
}

.siteintelix-log-item .sitx-log-path .dashicons {
	flex: 0 0 auto;
}
```

- [ ] **Step 3: Run the focused test and verify GREEN**

Run:

```bash
node --test --test-name-pattern="maximum available summary width" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: pass.

### Task 3: Lock the Database Manager functional dashboard contract with failing tests

**Files:**
- Modify: `wp-content/plugins/siteintelix/tests/structural.test.mjs`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add a test for sanitized, bounded controls**

Append:

```js
test('Database Manager dashboard uses bounded server-rendered controls', async () => {
	const php = await read('includes/modules/database-manager/class-siteintelix-database-manager-module.php');

	for (const parameter of ['db_search', 'db_orderby', 'db_order', 'db_per_page', 'db_page']) {
		assert.match(php, new RegExp(parameter));
	}
	assert.match(php, /array\(\s*15,\s*30,\s*50\s*\)/);
	assert.match(php, /array\(\s*'name',\s*'rows',\s*'data',\s*'index'\s*\)/);
	assert.match(php, /array_slice\s*\(/);
	assert.match(php, /min\(\s*50/);
	assert.doesNotMatch(php, /wp_ajax_siteintelix_db_dashboard/);
});
```

- [ ] **Step 2: Add a test for the visual and semantic structure**

Append:

```js
test('Database Manager dashboard renders the approved metrics, toolbar, actions, and pagination', async () => {
	const [php, css] = await Promise.all([
		read('includes/modules/database-manager/class-siteintelix-database-manager-module.php'),
		read('assets/admin/css/siteintelix-admin.css'),
	]);
	const finalDbLayer = css.slice(css.lastIndexOf('Database Manager screenshot-matched dashboard'));

	for (const marker of [
		'sitx-db-stat-icon',
		'sitx-db-dashboard-card',
		'sitx-db-dashboard-toolbar',
		'sitx-db-dashboard-search',
		'sitx-db-dashboard-actions',
		'sitx-db-dashboard-footer',
	]) {
		assert.match(php, new RegExp(marker));
		assert.match(finalDbLayer, new RegExp(`\\\\.${marker}`));
	}
	assert.match(php, /<th[^>]*>.*Actions/s);
	assert.match(php, /<details class="sitx-db-dashboard-actions"/);
	assert.match(php, /Browse rows/);
	assert.match(php, /paginate_links\s*\(/);
});
```

- [ ] **Step 3: Run both focused tests and verify RED**

Run:

```bash
node --test --test-name-pattern="Database Manager dashboard" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: both tests fail because the new control state and dashboard markup do not exist.

### Task 4: Implement sanitized dashboard state and metadata transforms

**Files:**
- Modify: `wp-content/plugins/siteintelix/includes/modules/database-manager/class-siteintelix-database-manager-module.php`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add a dashboard state normalizer**

Add a private method below `render_dashboard_view()`:

```php
private static function get_dashboard_state( $total_items ) {
	$allowed_per_page = array( 15, 30, 50 );
	$allowed_orderby  = array( 'name', 'rows', 'data', 'index' );
	$search           = isset( $_GET['db_search'] ) ? sanitize_text_field( wp_unslash( $_GET['db_search'] ) ) : '';
	$orderby          = isset( $_GET['db_orderby'] ) ? sanitize_key( wp_unslash( $_GET['db_orderby'] ) ) : 'rows';
	$order            = isset( $_GET['db_order'] ) ? sanitize_key( wp_unslash( $_GET['db_order'] ) ) : 'desc';
	$per_page         = isset( $_GET['db_per_page'] ) ? absint( wp_unslash( $_GET['db_per_page'] ) ) : 15;
	$page             = isset( $_GET['db_page'] ) ? max( 1, absint( wp_unslash( $_GET['db_page'] ) ) ) : 1;

	$orderby    = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'rows';
	$order      = in_array( $order, array( 'asc', 'desc' ), true ) ? $order : 'desc';
	$per_page   = in_array( $per_page, $allowed_per_page, true ) ? min( 50, $per_page ) : 15;
	$total_pages = max( 1, (int) ceil( max( 0, (int) $total_items ) / $per_page ) );
	$page       = min( $page, $total_pages );

	return compact( 'search', 'orderby', 'order', 'per_page', 'page', 'total_pages' );
}
```

- [ ] **Step 2: Add a pure filter/sort helper**

Add:

```php
private static function prepare_dashboard_tables( $tables, $state ) {
	$filtered = array_values(
		array_filter(
			$tables,
			static function ( $table ) use ( $state ) {
				return '' === $state['search'] || false !== stripos( (string) $table['name'], $state['search'] );
			}
		)
	);

	$key_map = array(
		'name'  => 'name',
		'rows'  => 'rows',
		'data'  => 'data_length',
		'index' => 'index_length',
	);
	$key = $key_map[ $state['orderby'] ];
	usort(
		$filtered,
		static function ( $left, $right ) use ( $key, $state ) {
			$result = 'name' === $key
				? strnatcasecmp( (string) $left[ $key ], (string) $right[ $key ] )
				: ( (int) $left[ $key ] <=> (int) $right[ $key ] );
			return 'asc' === $state['order'] ? $result : -$result;
		}
	);

	$total       = count( $filtered );
	$total_pages = max( 1, (int) ceil( $total / $state['per_page'] ) );
	$page        = min( $state['page'], $total_pages );
	$offset      = ( $page - 1 ) * $state['per_page'];

	return array(
		'items'       => array_slice( $filtered, $offset, $state['per_page'] ),
		'total'       => $total,
		'total_pages' => $total_pages,
		'page'        => $page,
	);
}
```

- [ ] **Step 3: Preserve performance behavior**

Do not change `get_tables()` and do not add SQL inside either helper. The helpers may only transform the already-loaded metadata array.

### Task 5: Render the screenshot-matched functional dashboard

**Files:**
- Modify: `wp-content/plugins/siteintelix/includes/modules/database-manager/class-siteintelix-database-manager-module.php`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Replace the metric-card markup**

Update the four existing cards to use:

```php
<div class="si-card sitx-db-stat-card sitx-db-stat-card--tables">
	<span class="sitx-db-stat-icon"><span class="dashicons dashicons-database" aria-hidden="true"></span></span>
	<div class="sitx-db-stat-copy">
		<span><?php esc_html_e( 'Total Tables', 'siteintelix' ); ?></span>
		<strong><?php echo esc_html( number_format_i18n( count( $tables ) ) ); ?></strong>
		<small><?php esc_html_e( 'Database overview', 'siteintelix' ); ?></small>
	</div>
</div>
```

Use corresponding icons and truthful context labels for records, data, and indexes.

- [ ] **Step 2: Build the toolbar as one GET form**

Render a `sitx-db-dashboard-toolbar` form with hidden `page` and `view` inputs, a labeled `db_search` input, `db_orderby`, `db_order`, and a submit button. Preserve current state in each control.

- [ ] **Step 3: Render only the current metadata slice**

Call:

```php
$initial_state = self::get_dashboard_state( count( $tables ) );
$prepared      = self::prepare_dashboard_tables( $tables, $initial_state );
$state         = array_merge(
	$initial_state,
	array(
		'page'        => $prepared['page'],
		'total_pages' => $prepared['total_pages'],
	)
);
```

Iterate over `$prepared['items']`, not all `$tables`.

- [ ] **Step 4: Add a native actions menu**

Add the Actions header and per-row markup:

```php
<details class="sitx-db-dashboard-actions">
	<summary aria-label="<?php echo esc_attr( sprintf( __( 'Actions for %s', 'siteintelix' ), $table['name'] ) ); ?>">
		<span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
	</summary>
	<div>
		<a href="<?php echo esc_url( self::get_table_url( $table['name'] ) ); ?>"><?php esc_html_e( 'Browse rows', 'siteintelix' ); ?></a>
	</div>
</details>
```

- [ ] **Step 5: Add page-size and pagination controls**

The page-size form preserves search/order state. Use `paginate_links()` with the same state encoded in the base URL. Render an accessible result range:

```php
$start = $prepared['total'] > 0 ? ( ( $state['page'] - 1 ) * $state['per_page'] ) + 1 : 0;
$end   = min( $prepared['total'], $state['page'] * $state['per_page'] );
```

- [ ] **Step 6: Add the search-empty state**

If `$prepared['items']` is empty and search is non-empty, display “No tables match your search” with a link to the dashboard URL without control parameters.

- [ ] **Step 7: Run the focused Database Manager tests**

Run:

```bash
node --test --test-name-pattern="Database Manager dashboard" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: the functional contract test passes; the visual test may remain red until CSS is added.

### Task 6: Implement the approved Database Manager visual layer

**Files:**
- Modify: `wp-content/plugins/siteintelix/assets/admin/css/siteintelix-admin.css`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add the cascade-final dashboard layer**

Append a section named `Database Manager screenshot-matched dashboard` that:

- Changes `.sitx-db-header` and `.sitx-db-tabs` to a full-width underline tab row.
- Uses a four-column `.sitx-db-stats` grid with 16–18px card padding.
- Styles `.sitx-db-stat-card` as a horizontal icon-and-copy card.
- Gives each icon tile a restrained semantic tint.
- Styles `.sitx-db-dashboard-card` as a white bordered card.
- Aligns `.sitx-db-dashboard-toolbar` heading and controls.
- Uses compact table row heights and the existing blue link color.
- Positions `.sitx-db-dashboard-actions > div` as a small anchored popover.
- Styles `.sitx-db-dashboard-footer` with page-size controls on the left and pagination on the right.
- Adds responsive breakpoints at 1200px, 900px, and 782px.

- [ ] **Step 2: Preserve legacy Tables/Edit views**

Scope all new dashboard rules under `.sitx-db-dashboard-*` or `.sitx-db-manager .sitx-db-stats`. Do not change `.sitx-db-browser`, row editor, row table, or mutation controls.

- [ ] **Step 3: Run the focused Database Manager tests and verify GREEN**

Run:

```bash
node --test --test-name-pattern="Database Manager dashboard" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: both tests pass.

### Task 7: Full verification

**Files:**
- Verify all modified production and test files.

- [ ] **Step 1: Run the complete structural suite**

```bash
node --test \
  wp-content/plugins/siteintelix/tests/structural.test.mjs \
  wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs \
  wp-content/plugins/siteintelix/tests/admin-interactions.test.mjs \
  wp-content/plugins/siteintelix/tests/email-log-performance.test.mjs
```

Expected: zero failures.

- [ ] **Step 2: Run PHP syntax checks**

```bash
php -l wp-content/plugins/siteintelix/includes/modules/database-manager/class-siteintelix-database-manager-module.php
php -l wp-content/plugins/siteintelix/admin/views/debug-log-page-modern.php
```

Expected: no syntax errors.

- [ ] **Step 3: Run the runtime smoke test when the Local site is available**

```bash
php wp-content/plugins/siteintelix/tests/runtime-smoke.php
```

Expected: pass. If the script requires a running WordPress environment and the Local site is unavailable, report that constraint without claiming a runtime pass.

- [ ] **Step 4: Inspect the live pages without destructive actions**

Verify the Modern Debug Log summary at desktop and narrow widths. Verify Database Manager search, sorting, page size, pagination, native actions, Tables navigation, and empty-search state. Do not edit or delete database rows.

- [ ] **Step 5: Report completion without committing**

List modified files, test counts, any unavailable live checks, and explicitly confirm that no Git/SVN commit or push was performed.
