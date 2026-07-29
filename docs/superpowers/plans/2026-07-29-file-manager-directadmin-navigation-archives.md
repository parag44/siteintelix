# File Manager DirectAdmin Navigation and Archives Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a DirectAdmin-inspired two-pane File Manager with lazy hierarchical navigation, read-only WordPress-root browsing, compact controls, permission-aware multi-selection, and securely streamed ZIP downloads.

**Architecture:** Preserve the existing Safe Mode security, filesystem, storage, AJAX, and audit boundaries. Add focused tree and archive services, expose them through operation-specific authenticated handlers, and enhance the dependency-free browser client with pure selection/tree helpers before wiring DOM behavior. Generate archives only in owned private temporary storage, stream them without loading them into browser memory, and clean them immediately.

**Tech Stack:** WordPress 7 admin APIs, PHP 8+, `ZipArchive`, dependency-free ES5-compatible browser JavaScript, custom SiteIntelix CSS tokens, Node's built-in test runner, dependency-free PHP test scripts.

---

## File Structure

### New files

- `includes/modules/file-manager/class-siteintelix-file-manager-tree.php` — bounded, read-only immediate-child directory discovery.
- `includes/modules/file-manager/class-siteintelix-file-manager-archive.php` — archive validation, ZIP creation, exclusions, streaming, limits, and cleanup.
- `tests/file-manager-tree.php` — dependency-free tree-service coverage.
- `tests/file-manager-archive.php` — dependency-free ZIP security and cleanup coverage.

### Modified files

- `includes/modules/file-manager/class-siteintelix-file-manager-security.php` — allow the empty root path only for explicit read operations and expose operation-accurate authorization.
- `includes/modules/file-manager/class-siteintelix-file-manager-filesystem.php` — calculate every row action through authorization and report read-only state.
- `includes/modules/file-manager/class-siteintelix-file-manager-storage.php` — create and validate an owned `tmp` area and remove stale temporary archives.
- `includes/modules/file-manager/class-siteintelix-file-manager-module.php` — load new services and include stale temporary cleanup.
- `includes/modules/file-manager/class-siteintelix-file-manager-ajax.php` — register `list_tree` and archive-download handlers with narrow input parsing.
- `includes/modules/file-manager/class-siteintelix-file-manager-admin.php` — localize archive capability, nonce, limits, and new strings.
- `includes/modules/file-manager/views/partials/toolbar.php` — compact creation/upload/sort/refresh toolbar and list header.
- `includes/modules/file-manager/views/partials/folder-tree.php` — lazy tree root and branch container.
- `includes/modules/file-manager/views/partials/file-table.php` — selection column and contextual action bar.
- `includes/modules/file-manager/views/partials/details-panel.php` — on-demand Details slide-over semantics.
- `includes/modules/file-manager/views/file-manager.php` — two-pane workspace and hidden archive download target.
- `includes/modules/file-manager/assets/file-manager.js` — tree state, multi-selection, action intersection, archive form submission, sort menu, and Details slide-over.
- `includes/modules/file-manager/assets/file-manager.css` — DirectAdmin-inspired hierarchy, two-pane layout, compact controls, selected-action bar, and responsive drawers.
- `tests/file-manager-security.php` — root read/write authorization cases.
- `tests/file-manager-filesystem.php` — root listing and action filtering.
- `tests/file-manager-ajax.php` — hook, nonce, capability, and path-array invariants.
- `tests/file-manager-storage.php` — private temporary area and cleanup.
- `tests/file-manager-ui.test.mjs` — pure tree, selection, combined-action, and archive-form helpers.
- `tests/structural.test.mjs` — module loading, toolbar, two-pane, and archive safety invariants.
- `docs/file-manager.md` — document root read-only behavior, ZIP limits, exclusions, and `ZipArchive`.
- `readme.txt` — add the user-facing 2.7.3 File Manager navigation/archive note.

## Task 1: WordPress-root reads and operation-accurate row actions

**Files:**

- Modify: `tests/file-manager-security.php`
- Modify: `tests/file-manager-filesystem.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-security.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-filesystem.php`

- [ ] **Step 1: Add failing root authorization tests**

Add these assertions after the first valid read assertions in `tests/file-manager-security.php`:

```php
$root_list = $security->authorize_path( '', 'list' );
$root_tree = $security->authorize_path( '', 'tree' );
siteintelix_test_assert( ! is_wp_error( $root_list ) && untrailingslashit( wp_normalize_path( ABSPATH ) ) === $root_list, 'empty list path resolves to WordPress root' );
siteintelix_test_assert( ! is_wp_error( $root_tree ) && $root_list === $root_tree, 'empty tree path resolves to WordPress root' );
foreach ( array( 'write', 'create', 'upload', 'rename', 'trash', 'edit', 'save' ) as $root_write_operation ) {
	siteintelix_test_assert( is_wp_error( $security->authorize_path( '', $root_write_operation ) ), "empty path is rejected for {$root_write_operation}" );
}
```

- [ ] **Step 2: Add failing root listing and protected-action tests**

Create root fixtures in `tests/file-manager-filesystem.php`:

```php
mkdir( $siteintelix_test_root . '/wp-admin', 0777, true );
mkdir( $siteintelix_test_root . '/wp-includes', 0777, true );
file_put_contents( $siteintelix_test_root . '/index.php', '<?php' );
```

Add:

```php
$root_listing = $filesystem->list_directory( '', array( 'per_page' => 50 ) );
siteintelix_test_assert( ! is_wp_error( $root_listing ), 'WordPress root listing succeeds' );
siteintelix_test_assert( '' === $root_listing['path'], 'WordPress root remains the empty relative path' );
$root_items = array_column( $root_listing['items'], null, 'name' );
siteintelix_test_assert( isset( $root_items['wp-admin'] ), 'WordPress root exposes wp-admin' );
siteintelix_test_assert( in_array( 'open', $root_items['wp-admin']['actions'], true ), 'protected core directory remains browsable' );
siteintelix_test_assert( ! in_array( 'rename', $root_items['wp-admin']['actions'], true ), 'protected core directory cannot be renamed' );
siteintelix_test_assert( ! in_array( 'trash', $root_items['wp-admin']['actions'], true ), 'protected core directory cannot be trashed' );
siteintelix_test_assert( isset( $root_items['wp-config.php'] ), 'wp-config remains visible in the root listing' );
siteintelix_test_assert( array() === $root_items['wp-config.php']['actions'], 'wp-config exposes no unauthorized row action' );
siteintelix_test_assert( true === $root_items['wp-config.php']['read_only'], 'wp-config is marked read-only' );
```

- [ ] **Step 3: Run the focused tests and verify red**

Run:

```bash
php tests/file-manager-security.php
php tests/file-manager-filesystem.php
```

Expected: failures for empty-path authorization and root listing.

- [ ] **Step 4: Permit only explicit empty-path read operations**

In `SITEINTELIX_File_Manager_Security::authorize_path()`, normalize the empty root before ordinary validation:

```php
$operation = sanitize_key( $operation );
$is_empty  = is_string( $path ) && '' === trim( $path );
if ( $is_empty ) {
	if ( ! in_array( $operation, array( 'list', 'tree', 'details' ), true ) ) {
		return $this->error( 'invalid_path', __( 'The requested path is invalid.', 'siteintelix' ) );
	}
	$decoded = '';
} else {
	$decoded = $this->validate_input( $path );
	if ( is_wp_error( $decoded ) ) {
		return $decoded;
	}
}
```

Keep the existing root containment, symlink, canonicalization, allowed-root, constant, and protected-path checks unchanged after this branch.

- [ ] **Step 5: Calculate row actions through authorization**

Replace the fixed base actions in `SITEINTELIX_File_Manager_Filesystem::actions()` with:

```php
private function actions( $absolute, $directory ) {
	$actions = array();
	$checks  = $directory
		? array( 'open' => 'list', 'details' => 'details', 'archive' => 'archive' )
		: array( 'view' => 'preview', 'details' => 'details', 'download' => 'download', 'archive' => 'archive' );

	foreach ( $checks as $action => $operation ) {
		if ( ! is_wp_error( $this->security->authorize_path( $absolute, $operation ) ) ) {
			$actions[] = $action;
		}
	}
	if ( ! is_wp_error( $this->security->authorize_path( $absolute, 'rename' ) ) ) {
		$actions[] = 'rename';
	}
	if ( ! is_wp_error( $this->security->authorize_path( $absolute, 'trash' ) ) ) {
		$actions[] = 'trash';
	}
	if (
		! $directory
		&& in_array( strtolower( pathinfo( $absolute, PATHINFO_EXTENSION ) ), SITEINTELIX_File_Manager_Settings::get()['editable_extensions'], true )
		&& ! is_wp_error( $this->security->authorize_path( $absolute, 'edit' ) )
	) {
		$actions[] = 'edit';
	}
	return $actions;
}
```

When constructing each listing item, calculate once and add:

```php
$actions = $this->actions( $absolute, $is_directory );
// ...
'actions'   => $actions,
'read_only' => empty( array_intersect( $actions, array( 'edit', 'rename', 'trash' ) ) ),
```

- [ ] **Step 6: Run focused tests and verify green**

Run:

```bash
php tests/file-manager-security.php
php tests/file-manager-filesystem.php
```

Expected: both scripts print their passed messages and exit 0.

- [ ] **Step 7: Commit**

```bash
git add tests/file-manager-security.php tests/file-manager-filesystem.php includes/modules/file-manager/class-siteintelix-file-manager-security.php includes/modules/file-manager/class-siteintelix-file-manager-filesystem.php
git commit -m "fix: allow read-only WordPress root browsing"
```

## Task 2: Bounded lazy folder-tree service

**Files:**

- Create: `tests/file-manager-tree.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-tree.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-module.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-ajax.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-admin.php`
- Modify: `tests/file-manager-ajax.php`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Write the failing tree-service test**

Build `tests/file-manager-tree.php` from the dependency stubs in `tests/file-manager-filesystem.php`, then create:

```php
mkdir( $siteintelix_test_root . '/alpha/child/grandchild', 0777, true );
mkdir( $siteintelix_test_root . '/beta', 0777, true );
mkdir( $siteintelix_test_root . '/.hidden', 0777, true );
file_put_contents( $siteintelix_test_root . '/alpha/file.txt', 'not a directory' );

$tree = new SITEINTELIX_File_Manager_Tree( new SITEINTELIX_File_Manager_Security() );
$root = $tree->children( '' );
siteintelix_test_assert( ! is_wp_error( $root ), 'root tree request succeeds' );
siteintelix_test_assert( array( 'alpha', 'beta' ) === array_column( $root['children'], 'name' ), 'tree returns sorted visible directories only' );
siteintelix_test_assert( true === $root['children'][0]['has_children'], 'tree detects immediate descendants' );
siteintelix_test_assert( false === $root['children'][1]['has_children'], 'tree reports leaf directories' );
siteintelix_test_assert( 1 === $root['children'][0]['level'], 'root children use level one' );

$nested = $tree->children( 'alpha/child' );
siteintelix_test_assert( 'alpha/child' === $nested['path'], 'nested path remains relative' );
siteintelix_test_assert( array( 'grandchild' ) === array_column( $nested['children'], 'name' ), 'tree loads one branch only' );

add_filter( 'siteintelix_file_manager_directory_scan_limit', static function () {
	return 200;
} );
for ( $index = 0; $index < 201; $index++ ) {
	mkdir( $siteintelix_test_root . '/bounded-' . $index );
}
$bounded = $tree->children( '' );
siteintelix_test_assert( true === $bounded['truncated'], 'tree reports a bounded branch scan' );
```

- [ ] **Step 2: Run the tree test and verify red**

Run:

```bash
php tests/file-manager-tree.php
```

Expected: failure because `SITEINTELIX_File_Manager_Tree` does not exist.

- [ ] **Step 3: Implement the focused tree service**

Create `class-siteintelix-file-manager-tree.php` with this public boundary:

```php
defined( 'ABSPATH' ) || exit;

class SITEINTELIX_File_Manager_Tree {
	private $security;

	public function __construct( $security = null ) {
		$this->security = $security instanceof SITEINTELIX_File_Manager_Security ? $security : new SITEINTELIX_File_Manager_Security();
	}

	public function children( $path ) {
		$directory = $this->security->authorize_path( $path, 'tree' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return new WP_Error( 'siteintelix_file_manager_unreadable_directory', __( 'This directory cannot be read.', 'siteintelix' ) );
		}
		$settings = SITEINTELIX_File_Manager_Settings::get();
		$limit    = max( 200, (int) apply_filters( 'siteintelix_file_manager_directory_scan_limit', 5000 ) );
		$children = array();
		$scanned  = 0;
		try {
			foreach ( new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS ) as $item ) {
				if ( ++$scanned > $limit ) {
					break;
				}
				$name = $item->getFilename();
				if ( $item->isLink() || ! $item->isDir() || ( empty( $settings['show_hidden'] ) && 0 === strpos( $name, '.' ) ) ) {
					continue;
				}
				$relative = $this->security->relative_path( $item->getPathname() );
				if ( is_wp_error( $relative ) ) {
					continue;
				}
				$children[] = array(
					'name'         => $name,
					'path'         => $relative,
					'has_children' => $this->has_children( $item->getPathname(), $settings, $limit ),
					'read_only'    => is_wp_error( $this->security->authorize_path( $item->getPathname(), 'write' ) ),
					'level'        => substr_count( $relative, '/' ) + 1,
				);
			}
		} catch ( UnexpectedValueException $exception ) {
			return new WP_Error( 'siteintelix_file_manager_unreadable_directory', __( 'This directory cannot be read.', 'siteintelix' ) );
		}
		usort( $children, static function ( $left, $right ) {
			return strnatcasecmp( $left['name'], $right['name'] );
		} );
		$relative_directory = $this->security->relative_path( $directory );
		return array(
			'path'      => is_wp_error( $relative_directory ) ? '' : $relative_directory,
			'children'  => $children,
			'truncated' => $scanned > $limit,
		);
	}
}
```

Add the bounded immediate-child helper:

```php
private function has_children( $directory, $settings, $limit ) {
	$scanned = 0;
	try {
		foreach ( new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS ) as $item ) {
			if ( ++$scanned > $limit ) {
				return false;
			}
			$name = $item->getFilename();
			if (
				! $item->isLink()
				&& $item->isDir()
				&& ( ! empty( $settings['show_hidden'] ) || 0 !== strpos( $name, '.' ) )
			) {
				return true;
			}
		}
	} catch ( UnexpectedValueException $exception ) {
		return false;
	}
	return false;
}
```

- [ ] **Step 4: Register and expose the tree service**

Load the class before AJAX in `class-siteintelix-file-manager-module.php`:

```php
require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-tree.php';
```

Register in `SITEINTELIX_File_Manager_Ajax::init()`:

```php
add_action( 'wp_ajax_siteintelix_fm_list_tree', array( __CLASS__, 'list_tree' ) );
```

Add:

```php
public static function list_tree() {
	self::authorize( 'siteintelix_fm_list_tree' );
	self::respond( ( new SITEINTELIX_File_Manager_Tree() )->children( self::post_path() ) );
}
```

Add `list_tree` to the localized nonce action list and the client fixed-action list.

- [ ] **Step 5: Add structural and AJAX assertions**

In `tests/structural.test.mjs`, assert the module loads the tree class before AJAX and that no `RecursiveDirectoryIterator` occurs in the tree class.

In `tests/file-manager-ajax.php`, assert:

```php
siteintelix_test_assert( false !== strpos( $ajax_source, "wp_ajax_siteintelix_fm_list_tree" ), 'tree handler is registered' );
siteintelix_test_assert( false !== strpos( $ajax_source, "self::authorize( 'siteintelix_fm_list_tree' )" ), 'tree handler uses an operation-specific nonce' );
```

- [ ] **Step 6: Run focused tests**

Run:

```bash
php tests/file-manager-tree.php
php tests/file-manager-ajax.php
node --test --test-name-pattern='File Manager' tests/structural.test.mjs
```

Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add tests/file-manager-tree.php tests/file-manager-ajax.php tests/structural.test.mjs includes/modules/file-manager/class-siteintelix-file-manager-tree.php includes/modules/file-manager/class-siteintelix-file-manager-module.php includes/modules/file-manager/class-siteintelix-file-manager-ajax.php includes/modules/file-manager/class-siteintelix-file-manager-admin.php
git commit -m "feat: add lazy file manager folder tree"
```

## Task 3: Private bounded archive service

**Files:**

- Create: `tests/file-manager-archive.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-archive.php`
- Modify: `tests/file-manager-storage.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-storage.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-security.php`

- [ ] **Step 1: Add failing private temporary-storage tests**

In `tests/file-manager-storage.php`, extend the expected owned areas and add:

```php
$temporary_directory = SITEINTELIX_File_Manager_Storage::path( 'tmp' );
siteintelix_test_assert( is_dir( $temporary_directory ), 'private temporary directory is created' );
siteintelix_test_assert( ! is_link( $temporary_directory ), 'private temporary directory is not a symlink' );

$stale_archive = $temporary_directory . '/archive-' . str_repeat( 'a', 32 ) . '.zip';
file_put_contents( $stale_archive, 'stale' );
touch( $stale_archive, time() - 7200 );
$fresh_archive = $temporary_directory . '/archive-' . str_repeat( 'b', 32 ) . '.zip';
file_put_contents( $fresh_archive, 'fresh' );
SITEINTELIX_File_Manager_Storage::cleanup_temporary_archives( 3600 );
siteintelix_test_assert( ! file_exists( $stale_archive ), 'stale temporary archive is removed' );
siteintelix_test_assert( file_exists( $fresh_archive ), 'fresh temporary archive is retained' );
```

- [ ] **Step 2: Write the failing archive test**

Build `tests/file-manager-archive.php` from the security/filesystem dependency stubs. Create fixtures:

```php
mkdir( $siteintelix_test_root . '/wp-content/uploads/folder/nested', 0777, true );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/one.txt', 'one' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/folder/two.txt', 'two' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/folder/nested/three.txt', 'three' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/folder/php.ini', 'secret' );
```

Assert:

```php
siteintelix_test_assert( class_exists( 'ZipArchive' ), 'ZipArchive is available in the test runtime' );
$archive = new SITEINTELIX_File_Manager_Archive( new SITEINTELIX_File_Manager_Security() );
$result  = $archive->create(
	'wp-content/uploads',
	array( 'wp-content/uploads/one.txt', 'wp-content/uploads/folder' )
);
siteintelix_test_assert( ! is_wp_error( $result ), 'archive creation succeeds' );
siteintelix_test_assert( file_exists( $result['path'] ), 'archive exists before streaming cleanup' );
siteintelix_test_assert( 5 === $result['entries'], 'archive counts permitted files and directory records' );
siteintelix_test_assert( 1 === $result['omitted'], 'archive reports protected omission' );

$zip = new ZipArchive();
siteintelix_test_assert( true === $zip->open( $result['path'] ), 'created ZIP opens' );
$names = array();
for ( $index = 0; $index < $zip->numFiles; $index++ ) {
	$names[] = $zip->getNameIndex( $index );
}
$zip->close();
sort( $names );
siteintelix_test_assert(
	array( 'folder/', 'folder/nested/', 'folder/nested/three.txt', 'folder/two.txt', 'one.txt' ) === $names,
	'archive paths are relative and protected files are omitted'
);
siteintelix_test_assert( false === strpos( implode( "\n", $names ), $siteintelix_test_root ), 'archive never contains absolute paths' );

$outside_parent = $archive->create( 'wp-content/uploads', array( 'wp-content/plugins' ) );
siteintelix_test_assert( is_wp_error( $outside_parent ), 'selected sources must share the current parent' );
$too_many = $archive->create( 'wp-content/uploads', array_fill( 0, 101, 'wp-content/uploads/one.txt' ) );
siteintelix_test_assert( is_wp_error( $too_many ), 'archive rejects more than 100 selected sources' );

$archive->delete( $result['path'] );
siteintelix_test_assert( ! file_exists( $result['path'] ), 'archive cleanup removes the private ZIP' );
```

If symlink creation is available, assert the link and its target are absent from the ZIP. Add filters that lower entry and byte limits, then assert the service returns `archive_entry_limit` and `archive_size_limit` errors and removes partial ZIP files.

- [ ] **Step 3: Run storage/archive tests and verify red**

Run:

```bash
php tests/file-manager-storage.php
php tests/file-manager-archive.php
```

Expected: failures because `tmp`, cleanup, and archive service do not exist.

- [ ] **Step 4: Add private temporary storage**

Add `tmp` to `SITEINTELIX_File_Manager_Storage::ensure_directories()`:

```php
foreach ( array( '', 'backups', 'trash', 'meta', 'audit', 'tmp' ) as $area ) {
```

Add:

```php
public static function cleanup_temporary_archives( $maximum_age = 3600 ) {
	$directory = self::path( 'tmp' );
	if ( ! is_dir( $directory ) || is_link( $directory ) ) {
		return;
	}
	$cutoff = time() - max( 60, (int) $maximum_age );
	foreach ( new DirectoryIterator( $directory ) as $item ) {
		if (
			$item->isDot()
			|| $item->isLink()
			|| ! $item->isFile()
			|| 1 !== preg_match( '/^archive-[a-f0-9]{32}\.zip$/', $item->getFilename() )
			|| $item->getMTime() >= $cutoff
		) {
			continue;
		}
		wp_delete_file( $item->getPathname() );
	}
}
```

- [ ] **Step 5: Implement archive source authorization and exclusions**

In the security service, add:

```php
public function archive_source( $path ) {
	$absolute = $this->authorize_path( $path, 'archive' );
	if ( is_wp_error( $absolute ) ) {
		return $absolute;
	}
	$basename = strtolower( basename( $absolute ) );
	if ( in_array( $basename, array( 'wp-config.php', '.htpasswd', '.user.ini', 'php.ini', 'web.config' ), true ) ) {
		return $this->error( 'archive_source_protected', __( 'A protected item cannot be added to an archive.', 'siteintelix' ) );
	}
	$private = SITEINTELIX_File_Manager_Storage::path();
	if ( $this->contains( $private, $absolute ) ) {
		return $this->error( 'archive_source_protected', __( 'Private File Manager storage cannot be archived.', 'siteintelix' ) );
	}
	return $absolute;
}
```

Change `contains()` from private to public or add a public `contains_path( $root, $path )` wrapper so the archive service can apply the same prefix-safe containment rule without duplicating it.

- [ ] **Step 6: Implement the archive service**

Create `class-siteintelix-file-manager-archive.php` with these public methods:

```php
defined( 'ABSPATH' ) || exit;

class SITEINTELIX_File_Manager_Archive {
	private $security;

	public function __construct( $security = null ) {
		$this->security = $security instanceof SITEINTELIX_File_Manager_Security ? $security : new SITEINTELIX_File_Manager_Security();
	}

	public static function available() {
		return class_exists( 'ZipArchive' );
	}

	public function create( $current_path, $paths ) {
		if ( ! self::available() ) {
			return $this->error( 'archive_unavailable', __( 'ZIP archive support is not available on this server.', 'siteintelix' ) );
		}
		$paths = array_values( array_unique( array_filter( (array) $paths, 'is_string' ) ) );
		if ( empty( $paths ) || count( $paths ) > 100 ) {
			return $this->error( 'archive_selection_limit', __( 'Select between 1 and 100 items.', 'siteintelix' ) );
		}
		$current = $this->security->authorize_path( $current_path, 'list' );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$storage = SITEINTELIX_File_Manager_Storage::ensure_directories();
		if ( is_wp_error( $storage ) ) {
			return $storage;
		}
		$temporary = SITEINTELIX_File_Manager_Storage::path( 'tmp/archive-' . bin2hex( random_bytes( 16 ) ) . '.zip' );
		register_shutdown_function(
			static function () use ( $temporary ) {
				if ( is_file( $temporary ) && ! is_link( $temporary ) ) {
					wp_delete_file( $temporary );
				}
			}
		);
		$zip       = new ZipArchive();
		if ( true !== $zip->open( $temporary, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
			return $this->error( 'archive_create_failed', __( 'The ZIP archive could not be created.', 'siteintelix' ) );
		}
		$state = array(
			'entries' => 0,
			'bytes'   => 0,
			'omitted' => 0,
			'entry_limit' => max( 1, (int) apply_filters( 'siteintelix_file_manager_archive_entry_limit', 5000 ) ),
			'byte_limit'  => max( 1, (int) apply_filters( 'siteintelix_file_manager_archive_byte_limit', 250 * MB_IN_BYTES ) ),
		);
		$result = $this->add_sources( $zip, $current, $paths, $state );
		$closed = $zip->close();
		if ( is_wp_error( $result ) || ! $closed || 0 === $state['entries'] ) {
			$this->delete( $temporary );
			return is_wp_error( $result ) ? $result : $this->error( 'archive_empty', __( 'No permitted files were available to archive.', 'siteintelix' ) );
		}
		return array(
			'path'     => $temporary,
			'name'     => $this->download_name( $current_path, $paths ),
			'entries'  => $state['entries'],
			'bytes'    => $state['bytes'],
			'omitted'  => $state['omitted'],
		);
	}

	public function stream( $archive ) {
		if ( ! is_array( $archive ) || empty( $archive['path'] ) || empty( $archive['name'] ) ) {
			return $this->error( 'archive_invalid', __( 'The ZIP archive is invalid.', 'siteintelix' ) );
		}
		$tmp  = realpath( SITEINTELIX_File_Manager_Storage::path( 'tmp' ) );
		$path = realpath( $archive['path'] );
		if (
			false === $tmp
			|| false === $path
			|| ! $this->security->contains_path( wp_normalize_path( $tmp ), wp_normalize_path( $path ) )
			|| ! is_file( $path )
			|| is_link( $archive['path'] )
		) {
			return $this->error( 'archive_invalid', __( 'The ZIP archive is invalid.', 'siteintelix' ) );
		}
		$name = sanitize_file_name( $archive['name'] );
		if ( '' === $name || '.zip' !== strtolower( substr( $name, -4 ) ) ) {
			return $this->error( 'archive_invalid', __( 'The ZIP archive is invalid.', 'siteintelix' ) );
		}
		$length = filesize( $path );
		$handle = fopen( $path, 'rb' );
		if ( false === $length || false === $handle ) {
			return $this->error( 'archive_read_failed', __( 'The ZIP archive could not be read.', 'siteintelix' ) );
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) $length );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 65536 );
			if ( false === $chunk ) {
				fclose( $handle );
				return $this->error( 'archive_read_failed', __( 'The ZIP archive could not be read.', 'siteintelix' ) );
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary ZIP stream.
			flush();
		}
		fclose( $handle );
		return true;
	}

	public function delete( $path ) {
		$tmp = SITEINTELIX_File_Manager_Storage::path( 'tmp' );
		if ( ! is_string( $path ) || ! $this->security->contains_path( $tmp, $path ) || is_link( $path ) ) {
			return $this->error( 'archive_cleanup_failed', __( 'The temporary ZIP archive could not be removed.', 'siteintelix' ) );
		}
		if ( ! file_exists( $path ) ) {
			return true;
		}
		if ( ! is_file( $path ) ) {
			return $this->error( 'archive_cleanup_failed', __( 'The temporary ZIP archive could not be removed.', 'siteintelix' ) );
		}
		wp_delete_file( $path );
		if ( file_exists( $path ) ) {
			return $this->error( 'archive_cleanup_failed', __( 'The temporary ZIP archive could not be removed.', 'siteintelix' ) );
		}
		return true;
	}
}
```

Implement the private helpers with these exact contracts:

- `add_sources( ZipArchive $zip, $current, array $paths, array &$state )` resolves every source with `authorize_path( $path, 'archive' )`, requires `wp_normalize_path( dirname( $absolute ) ) === wp_normalize_path( $current )`, and returns `archive_parent_mismatch` immediately when that common-parent check fails. It then calls `archive_source()`; a protected source increments `omitted` and continues, an ordinary file calls `add_file()`, and a directory calls `add_directory()`.
- `add_directory( ZipArchive $zip, $directory, $current, array &$state )` first adds the selected directory itself as one trailing-slash record, then constructs `RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )` without `FOLLOW_SYMLINKS`, wraps it in `RecursiveCallbackFilterIterator`, and only then wraps it in `RecursiveIteratorIterator::SELF_FIRST`. The filter rechecks every descendant through `archive_source()` and returns `false` for symlinks, unreadable entries, protected names, and private File Manager storage so rejected directories are never descended into; it increments `omitted` once for each rejected item. Each permitted directory calculates the proposed entry total, fails with `archive_entry_limit` before exceeding the limit, adds one trailing-slash record with `addEmptyDir( entry_name(...) )`, and commits the proposed total. Permitted ordinary files go through `add_file()`. Catch `UnexpectedValueException` and return `archive_read_failed`.
- `add_file( ZipArchive $zip, $absolute, $current, array &$state )` requires an ordinary readable non-link file, obtains a non-negative `filesize()`, calculates the proposed entry and byte totals, and returns `archive_entry_limit` or `archive_size_limit` before calling `addFile()`. A false `addFile()` returns `archive_add_failed`; success commits both proposed totals. Directory and file records both count toward the 5,000-entry limit.
- `entry_name( $current, $absolute )` normalizes both paths, requires prefix-safe containment in `$current`, strips that canonical prefix, converts backslashes to `/`, removes ASCII control characters and leading slashes, rejects empty names and every `.` or `..` segment, and returns `archive_entry_invalid` for an unsafe result.
- `download_name( $current_path, array $paths )` uses `sanitize_file_name( basename( reset( $paths ) ) )` for one selected source, otherwise `sanitize_file_name( basename( $current_path ) )`; it falls back to `wordpress-files`, appends `-Ymd-His` in UTC for multiple sources, strips an existing `.zip`, and appends `.zip`.
- `error( $code, $message )` returns `new WP_Error( 'siteintelix_file_manager_' . $code, $message )`.

Do not use `extractTo()`, shell commands, `file_get_contents()` for archive bytes, `ZipArchive::FL_ENC_RAW`, or a path supplied by the browser as the temporary destination.

- [ ] **Step 7: Run archive/storage tests**

Run:

```bash
php tests/file-manager-storage.php
php tests/file-manager-archive.php
```

Expected: both pass; no `archive-*.zip` remains in the test temporary directory.

- [ ] **Step 8: Commit**

```bash
git add tests/file-manager-storage.php tests/file-manager-archive.php includes/modules/file-manager/class-siteintelix-file-manager-storage.php includes/modules/file-manager/class-siteintelix-file-manager-security.php includes/modules/file-manager/class-siteintelix-file-manager-archive.php
git commit -m "feat: add bounded private zip archives"
```

## Task 4: Archive/tree handlers and feature localization

**Files:**

- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-module.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-ajax.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-admin.php`
- Modify: `tests/file-manager-ajax.php`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Add failing handler and localization assertions**

In `tests/file-manager-ajax.php`, assert:

```php
siteintelix_test_assert( false !== strpos( $ajax_source, "admin_post_siteintelix_fm_download_archive" ), 'archive download handler is registered' );
siteintelix_test_assert( false !== strpos( $ajax_source, "check_admin_referer( 'siteintelix_fm_download_archive' )" ), 'archive download uses an operation-specific nonce' );
siteintelix_test_assert( false !== strpos( $ajax_source, "SITEINTELIX_Modules::is_enabled( 'file_manager' )" ), 'archive handler requires the enabled module' );
siteintelix_test_assert( false !== strpos( $ajax_source, 'array_slice' ), 'archive request input is bounded before service use' );
```

In `tests/structural.test.mjs`, assert the module loads archive before AJAX and the admin payload includes `archiveNonce`, `archiveAvailable`, and limits 100/5000/250 MB.
Also assert the archive stream contains `Content-Type: application/zip`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, a 65,536-byte chunk size, and no `file_get_contents()` call.

- [ ] **Step 2: Run tests and verify red**

Run:

```bash
php tests/file-manager-ajax.php
node --test --test-name-pattern='File Manager' tests/structural.test.mjs
```

Expected: archive handler/localization assertions fail.

- [ ] **Step 3: Load and register archive behavior**

In the module bootstrap, load archive before AJAX:

```php
require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-archive.php';
```

In `SITEINTELIX_File_Manager_Module::cleanup()` add:

```php
SITEINTELIX_File_Manager_Storage::cleanup_temporary_archives();
```

In AJAX `init()` register:

```php
add_action( 'admin_post_siteintelix_fm_download_archive', array( __CLASS__, 'download_archive' ) );
```

- [ ] **Step 4: Implement the authenticated archive handler**

Add:

```php
public static function download_archive() {
	if (
		! is_user_logged_in()
		|| ! SITEINTELIX_File_Manager_Security::current_user_can_manage()
		|| ! SITEINTELIX_Modules::is_enabled( 'file_manager' )
	) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'siteintelix' ) );
	}
	check_admin_referer( 'siteintelix_fm_download_archive' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() verified this request.
	$current = isset( $_POST['current_path'] ) && is_string( $_POST['current_path'] ) ? wp_unslash( $_POST['current_path'] ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() verified this request.
	$raw_paths = isset( $_POST['paths'] ) && is_array( $_POST['paths'] ) ? array_slice( wp_unslash( $_POST['paths'] ), 0, 101 ) : array();
	$paths     = array_values( array_filter( $raw_paths, 'is_string' ) );
	$service   = new SITEINTELIX_File_Manager_Archive();
	$result    = $service->create( $current, $paths );
	if ( is_wp_error( $result ) ) {
		SITEINTELIX_File_Manager_Audit::record( 'archive_download', $current, 'failure', $result->get_error_code() );
		wp_die( esc_html( $result->get_error_message() ) );
	}
	$streamed = null;
	$cleanup  = true;
	try {
		$streamed = $service->stream( $result );
	} finally {
		$cleanup = $service->delete( $result['path'] );
		if ( is_wp_error( $cleanup ) ) {
			error_log( 'SiteIntelix File Manager archive cleanup failed: ' . $cleanup->get_error_code() );
		}
	}
	if ( is_wp_error( $streamed ) ) {
		SITEINTELIX_File_Manager_Audit::record( 'archive_download', $current, 'failure', $streamed->get_error_code() );
		if ( ! headers_sent() ) {
			wp_die( esc_html( $streamed->get_error_message() ) );
		}
		exit;
	}
	SITEINTELIX_File_Manager_Audit::record( 'archive_download', $current, 'success', 'entries:' . (int) $result['entries'] . ';omitted:' . (int) $result['omitted'] );
	exit;
}
```

Ensure the archive service accepts the result array in `stream()` and uses only the already-validated `path` and `name`.

- [ ] **Step 5: Localize archive capability and limits**

Add to the admin payload:

```php
'archiveNonce' => wp_create_nonce( 'siteintelix_fm_download_archive' ),
'features'     => array(
	'editing'          => ! empty( $settings['editing_enabled'] ) && ! ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ),
	'uploads'          => ! empty( $settings['uploads_enabled'] ) && ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ),
	'overwrite'        => ! empty( $settings['allow_overwrite'] ),
	'archiveAvailable' => SITEINTELIX_File_Manager_Archive::available(),
),
'limits'       => array(
	'preview'          => (int) $settings['preview_max_bytes'],
	'edit'             => (int) $settings['edit_max_bytes'],
	'upload'           => (int) $settings['upload_max_bytes'],
	'archiveSelection' => 100,
	'archiveEntries'   => 5000,
	'archiveBytes'     => 250 * MB_IN_BYTES,
),
```

Add localized strings for archive unavailable, selection limit, archive started, selected count, and read-only.

- [ ] **Step 6: Run focused tests**

Run:

```bash
php tests/file-manager-ajax.php
node --test --test-name-pattern='File Manager' tests/structural.test.mjs
```

Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add includes/modules/file-manager/class-siteintelix-file-manager-module.php includes/modules/file-manager/class-siteintelix-file-manager-ajax.php includes/modules/file-manager/class-siteintelix-file-manager-admin.php tests/file-manager-ajax.php tests/structural.test.mjs
git commit -m "feat: expose secure file manager archives"
```

## Task 5: DirectAdmin-inspired two-pane markup

**Files:**

- Modify: `tests/structural.test.mjs`
- Modify: `includes/modules/file-manager/views/partials/toolbar.php`
- Modify: `includes/modules/file-manager/views/partials/folder-tree.php`
- Modify: `includes/modules/file-manager/views/partials/file-table.php`
- Modify: `includes/modules/file-manager/views/partials/details-panel.php`
- Modify: `includes/modules/file-manager/views/file-manager.php`

- [ ] **Step 1: Add failing markup assertions**

Update the File Manager shell test to require:

```js
assert.match(toolbar, /data-fm-new-folder/);
assert.match(toolbar, /data-fm-new-file/);
assert.match(toolbar, /data-fm-upload/);
assert.match(toolbar, /data-fm-sort-menu/);
assert.match(toolbar, /data-fm-refresh/);
assert.doesNotMatch(toolbar, /data-fm-(?:back|forward|up|toggle-details)/);
assert.match(tree, /data-fm-tree-root/);
assert.match(tree, /role="tree"/);
assert.match(table, /data-fm-select-all/);
assert.match(table, /data-fm-selection-actions/);
assert.match(table, /data-fm-selection-archive/);
assert.match(details, /role="dialog"/);
assert.match(details, /aria-modal="true"/);
assert.match(page, /data-fm-archive-target/);
```

- [ ] **Step 2: Run the structural test and verify red**

Run:

```bash
node --test --test-name-pattern='desktop workspace shell' tests/structural.test.mjs
```

Expected: missing compact-toolbar, selection, and slide-over hooks.

- [ ] **Step 3: Replace the toolbar markup**

Render exactly five compact controls:

```php
<div class="sitx-fm-toolbar si-toolbar" aria-label="<?php esc_attr_e( 'File actions', 'siteintelix' ); ?>">
	<button type="button" class="sitx-fm-tool" data-fm-new-folder><span class="dashicons dashicons-category" aria-hidden="true"></span><span><?php esc_html_e( 'New Folder', 'siteintelix' ); ?></span></button>
	<button type="button" class="sitx-fm-tool" data-fm-new-file><span class="dashicons dashicons-media-default" aria-hidden="true"></span><span><?php esc_html_e( 'New File', 'siteintelix' ); ?></span></button>
	<button type="button" class="sitx-fm-tool" data-fm-upload><span class="dashicons dashicons-upload" aria-hidden="true"></span><span><?php esc_html_e( 'Upload', 'siteintelix' ); ?></span></button>
	<div class="sitx-fm-sort">
		<button type="button" class="sitx-fm-tool" data-fm-sort-toggle aria-expanded="false" aria-controls="siteintelix-file-manager-sort"><span class="dashicons dashicons-sort" aria-hidden="true"></span><span><?php esc_html_e( 'Sort by', 'siteintelix' ); ?></span></button>
		<div id="siteintelix-file-manager-sort" class="sitx-fm-sort__menu" data-fm-sort-menu role="menu" hidden></div>
	</div>
	<button type="button" class="sitx-fm-tool" data-fm-refresh><span class="dashicons dashicons-update" aria-hidden="true"></span><span><?php esc_html_e( 'Refresh', 'siteintelix' ); ?></span></button>
</div>
<div class="sitx-fm-locationbar">
	<nav class="sitx-fm-breadcrumbs" aria-label="<?php esc_attr_e( 'Current directory', 'siteintelix' ); ?>" data-fm-breadcrumbs></nav>
	<label class="sitx-fm-search"><span class="screen-reader-text"><?php esc_html_e( 'Search current directory', 'siteintelix' ); ?></span><input type="search" data-fm-search><span class="dashicons dashicons-search" aria-hidden="true"></span></label>
</div>
```

- [ ] **Step 4: Add lazy tree and selection shells**

Use a single root list item in `folder-tree.php`:

```php
<aside id="siteintelix-file-manager-tree" class="sitx-fm-tree" data-fm-tree-panel aria-label="<?php esc_attr_e( 'Folders', 'siteintelix' ); ?>">
	<div class="sitx-fm-panel-heading"><h2><?php esc_html_e( 'Folders', 'siteintelix' ); ?></h2><button type="button" data-fm-close-tree aria-label="<?php esc_attr_e( 'Close folders', 'siteintelix' ); ?>">×</button></div>
	<ul class="sitx-fm-tree__list" data-fm-tree-root role="tree"></ul>
</aside>
```

Add to `file-table.php` before the scroll container:

```php
<div class="sitx-fm-selection-actions" data-fm-selection-actions hidden>
	<span data-fm-selection-count></span>
	<button type="button" data-fm-selection-download><?php esc_html_e( 'Download', 'siteintelix' ); ?></button>
	<button type="button" data-fm-selection-archive><?php esc_html_e( 'Archive ZIP', 'siteintelix' ); ?></button>
	<button type="button" data-fm-selection-details><?php esc_html_e( 'Details', 'siteintelix' ); ?></button>
	<button type="button" data-fm-selection-rename><?php esc_html_e( 'Rename', 'siteintelix' ); ?></button>
	<button type="button" class="is-destructive" data-fm-selection-trash><?php esc_html_e( 'Trash', 'siteintelix' ); ?></button>
	<button type="button" data-fm-selection-clear><?php esc_html_e( 'Clear selection', 'siteintelix' ); ?></button>
</div>
```

Add a screen-reader-labeled checkbox header before Name:

```php
<th scope="col" class="sitx-fm-select-column"><input type="checkbox" data-fm-select-all aria-label="<?php esc_attr_e( 'Select all visible items', 'siteintelix' ); ?>"></th>
```

- [ ] **Step 5: Convert Details to a slide-over**

Give the existing Details aside:

```php
role="dialog"
aria-modal="true"
aria-labelledby="siteintelix-file-manager-details-title"
hidden
```

Keep the existing safe preview and metadata nodes, add a visible close button, and set the heading ID.

In `file-manager.php`, change the workspace to include only tree and list. Move the Details partial after the workspace and add:

```php
<iframe class="sitx-fm-download-target" name="siteintelix-file-manager-download" data-fm-archive-target title="<?php esc_attr_e( 'File download', 'siteintelix' ); ?>" hidden></iframe>
```

- [ ] **Step 6: Run structural tests**

Run:

```bash
node --test --test-name-pattern='File Manager' tests/structural.test.mjs
php -l includes/modules/file-manager/views/partials/toolbar.php
php -l includes/modules/file-manager/views/partials/folder-tree.php
php -l includes/modules/file-manager/views/partials/file-table.php
php -l includes/modules/file-manager/views/partials/details-panel.php
php -l includes/modules/file-manager/views/file-manager.php
```

Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add tests/structural.test.mjs includes/modules/file-manager/views/partials/toolbar.php includes/modules/file-manager/views/partials/folder-tree.php includes/modules/file-manager/views/partials/file-table.php includes/modules/file-manager/views/partials/details-panel.php includes/modules/file-manager/views/file-manager.php
git commit -m "refactor: add directadmin file manager workspace"
```

## Task 6: Pure tree, selection, and action-state helpers

**Files:**

- Modify: `tests/file-manager-ui.test.mjs`
- Modify: `includes/modules/file-manager/assets/file-manager.js`

- [ ] **Step 1: Add failing helper tests**

Add:

```js
test('selection is capped and reconciled by canonical relative path', async () => {
	const { helpers } = await loadHelpers();
	const selection = helpers.createSelection(2);
	assert.equal(selection.toggle({ path: 'wp-content/a.txt' }), true);
	assert.equal(selection.toggle({ path: 'wp-content/b.txt' }), true);
	assert.equal(selection.toggle({ path: 'wp-content/c.txt' }), false);
	assert.deepEqual(Array.from(selection.paths()), ['wp-content/a.txt', 'wp-content/b.txt']);
	selection.reconcile([{ path: 'wp-content/b.txt' }]);
	assert.deepEqual(Array.from(selection.paths()), ['wp-content/b.txt']);
	selection.clear();
	assert.deepEqual(Array.from(selection.paths()), []);
});

test('combined selection actions require validity for the complete selection', async () => {
	const { helpers } = await loadHelpers();
	const file = { type: 'file', actions: ['view', 'details', 'download', 'archive', 'rename', 'trash'] };
	const folder = { type: 'directory', actions: ['open', 'details', 'archive', 'rename', 'trash'] };
	assert.deepEqual(Array.from(helpers.selectionActions([file])), ['download', 'archive', 'details', 'rename', 'trash']);
	assert.deepEqual(Array.from(helpers.selectionActions([file, folder])), ['archive', 'trash']);
	assert.deepEqual(Array.from(helpers.selectionActions([])), []);
});

test('tree helpers retain expanded ancestors and build safe levels', async () => {
	const { helpers } = await loadHelpers();
	const state = helpers.createTreeState();
	state.expand('');
	state.expand('wp-content');
	state.activate('wp-content/uploads/2026');
	assert.equal(state.isExpanded(''), true);
	assert.equal(state.isExpanded('wp-content'), true);
	assert.equal(state.isActive('wp-content/uploads/2026'), true);
	assert.deepEqual(Array.from(helpers.ancestorPaths('wp-content/uploads/2026')), ['', 'wp-content', 'wp-content/uploads']);
	assert.equal(helpers.treeLevel('wp-content/uploads'), 2);
});

test('archive form fields stay bounded and relative', async () => {
	const { helpers } = await loadHelpers();
	assert.deepEqual(
		{ ...helpers.archivePayload('wp-content/uploads', ['wp-content/uploads/a.txt', 'wp-content/uploads/folder'], 100) },
		{ current_path: 'wp-content/uploads', paths: ['wp-content/uploads/a.txt', 'wp-content/uploads/folder'] },
	);
	assert.equal(helpers.archivePayload('wp-content/uploads', Array(101).fill('wp-content/uploads/a.txt'), 100), null);
	assert.equal(helpers.archivePayload('wp-content/uploads', ['../wp-config.php'], 100), null);
});
```

- [ ] **Step 2: Run helper tests and verify red**

Run:

```bash
node --test tests/file-manager-ui.test.mjs
```

Expected: missing helper failures.

- [ ] **Step 3: Implement and export pure helpers**

Add before the document guard:

```js
function createSelection(limit) {
	var selected = new Map();
	return {
		toggle: function (item) {
			var path = String((item && item.path) || '');
			if (!path) return false;
			if (selected.has(path)) {
				selected.delete(path);
				return true;
			}
			if (selected.size >= limit) return false;
			selected.set(path, item);
			return true;
		},
		clear: function () { selected.clear(); },
		items: function () { return Array.from(selected.values()); },
		paths: function () { return Array.from(selected.keys()); },
		has: function (path) { return selected.has(String(path || '')); },
		reconcile: function (items) {
			var visible = new Set((items || []).map(function (item) { return String(item.path || ''); }));
			Array.from(selected.keys()).forEach(function (path) {
				if (!visible.has(path)) selected.delete(path);
			});
		},
	};
}

function selectionActions(items) {
	if (!items || !items.length) return [];
	var every = function (action) {
		return items.every(function (item) {
			return Array.isArray(item.actions) && item.actions.indexOf(action) !== -1;
		});
	};
	var actions = [];
	if (items.length === 1 && items[0].type === 'file' && every('download')) actions.push('download');
	if (every('archive')) actions.push('archive');
	if (items.length === 1 && every('details')) actions.push('details');
	if (items.length === 1 && every('rename')) actions.push('rename');
	if (every('trash')) actions.push('trash');
	return actions;
}

function ancestorPaths(path) {
	var ancestors = [''];
	var parts = String(path || '').split('/').filter(Boolean);
	parts.pop();
	var current = [];
	parts.forEach(function (part) {
		current.push(part);
		ancestors.push(current.join('/'));
	});
	return ancestors;
}

function treeLevel(path) {
	return String(path || '').split('/').filter(Boolean).length;
}

function createTreeState() {
	var expanded = new Set();
	var active = '';
	return {
		expand: function (path) { expanded.add(String(path || '')); },
		collapse: function (path) { expanded.delete(String(path || '')); },
		isExpanded: function (path) { return expanded.has(String(path || '')); },
		activate: function (path) { active = String(path || ''); },
		isActive: function (path) { return active === String(path || ''); },
	};
}

function archivePayload(currentPath, paths, limit) {
	if (!Array.isArray(paths) || !paths.length || paths.length > limit) return null;
	var normalized = paths.map(function (path) { return String(path || '').replace(/\\/g, '/'); });
	if (normalized.some(function (path) { return !path || /(^|\/)\.\.(\/|$)/.test(path) || path.charAt(0) === '/'; })) return null;
	return { current_path: String(currentPath || ''), paths: normalized };
}
```

Export all five helpers through `siteintelixFileManagerTest`.

- [ ] **Step 4: Run helper tests**

Run:

```bash
node --test tests/file-manager-ui.test.mjs
```

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add tests/file-manager-ui.test.mjs includes/modules/file-manager/assets/file-manager.js
git commit -m "test: define directadmin file manager interactions"
```

## Task 7: Lazy tree, multi-selection, archive download, and Details interactions

**Files:**

- Modify: `tests/file-manager-ui.test.mjs`
- Modify: `includes/modules/file-manager/assets/file-manager.js`

- [ ] **Step 1: Add failing source-level interaction assertions**

Require:

```js
assert.match(source, /request\(\s*'list_tree'/);
assert.match(source, /data-fm-tree-children/);
assert.match(source, /aria-level/);
assert.match(source, /data-fm-tree-retry/);
assert.match(source, /data-fm-select-item/);
assert.match(source, /data-fm-selection-actions/);
assert.match(source, /archivePayload/);
assert.match(source, /siteintelix-file-manager-download/);
assert.match(source, /form\.method\s*=\s*'post'/);
assert.match(source, /form\.target\s*=\s*'siteintelix-file-manager-download'/);
assert.match(source, /setDetailsOpen/);
assert.doesNotMatch(source, /data-fm-back/);
assert.doesNotMatch(source, /data-fm-forward/);
assert.doesNotMatch(source, /data-fm-up/);
```

- [ ] **Step 2: Run UI tests and verify red**

Run:

```bash
node --test tests/file-manager-ui.test.mjs
```

Expected: DOM integration assertions fail.

- [ ] **Step 3: Replace single selection with bounded selection state**

Initialize:

```js
selectionLimit: Number((data.limits && data.limits.archiveSelection) || 100),
selection: createSelection(Number((data.limits && data.limits.archiveSelection) || 100)),
tree: createTreeState(),
treeChildren: new Map(),
treeLoading: new Set(),
```

On directory changes call `state.selection.clear()`. On refresh call `state.selection.reconcile(result.items)` before rendering.

Render one checkbox per row:

```js
var selectCell = node('td', 'sitx-fm-select-cell');
var checkbox = node('input');
checkbox.type = 'checkbox';
checkbox.setAttribute('data-fm-select-item', '');
checkbox.setAttribute('aria-label', 'Select ' + item.name);
checkbox.checked = state.selection.has(item.path);
checkbox.addEventListener('click', function (event) {
	event.stopPropagation();
	if (!state.selection.toggle(item)) {
		checkbox.checked = false;
		speak('You can select up to ' + state.selectionLimit + ' items.');
		return;
	}
	updateSelectionUI();
});
selectCell.appendChild(checkbox);
row.appendChild(selectCell);
```

Keep row click as the familiar single-item active/detail behavior without clearing checkbox selection.

- [ ] **Step 4: Render and synchronize the selection action bar**

Implement `updateSelectionUI()`:

```js
function updateSelectionUI() {
	var items = state.selection.items();
	var allowed = selectionActions(items);
	var bar = select('[data-fm-selection-actions]');
	bar.hidden = items.length === 0;
	select('[data-fm-selection-count]').textContent = items.length + (items.length === 1 ? ' item selected' : ' items selected');
	['download', 'archive', 'details', 'rename', 'trash'].forEach(function (action) {
		var control = select('[data-fm-selection-' + action + ']');
		control.disabled = allowed.indexOf(action) === -1;
	});
	select('[data-fm-select-all]').checked = items.length > 0 && items.length === selectAll('[data-fm-select-item]').length;
}
```

Bind clear, header checkbox, single-item reuse, bulk-trash confirmation, and archive buttons. Bulk trash must call the existing `trash_item` endpoint serially and stop on the first failure; it must never invent a bulk backend endpoint.

- [ ] **Step 5: Implement lazy hierarchical tree rendering**

Render the root node from client-owned safe values and load children through `list_tree`.

Each node is:

```html
<li role="treeitem" aria-level="1" aria-expanded="false">
	<div class="sitx-fm-tree__row">
		<button data-fm-tree-toggle aria-label="Expand WordPress"></button>
		<button data-fm-tree-select>WordPress</button>
	</div>
	<ul role="group" data-fm-tree-children hidden></ul>
</li>
```

Use only `createElement`, `textContent`, `setAttribute`, and fixed class allowlists. `loadTreeBranch(path, group, item)`:

1. Deduplicates concurrent requests with `treeLoading`.
2. Uses cached child arrays after the first successful load.
3. Renders `has_children` chevrons and `aria-level`.
4. Inserts a branch-local Retry button on failure.
5. Preserves expanded ancestors from `ancestorPaths(state.path)`.

After a directory load, call `state.tree.activate(result.path)` and update only `aria-current` classes; do not clear/rebuild the whole tree.

- [ ] **Step 6: Implement compact toolbar and sorting**

Bind New Folder directly to the existing create modal with type `directory` and New File with type `file`. Remove history-button code and old Back/Forward/Up bindings.

Build the Sort by menu from a fixed array:

```js
[
	['name', 'asc', 'Name (A–Z)'],
	['name', 'desc', 'Name (Z–A)'],
	['type', 'asc', 'Type'],
	['type', 'desc', 'Type (reverse)'],
	['size', 'asc', 'Size (smallest)'],
	['size', 'desc', 'Size (largest)'],
	['modified', 'desc', 'Modified (newest)'],
	['modified', 'asc', 'Modified (oldest)'],
]
```

Each menu item sets `state.sort`, `state.order`, resets the page, closes the menu, announces the choice, and reloads the directory. Support Arrow Up/Down, Home/End, Enter/Space, Escape, outside click, and focus restoration using the existing context-menu pattern.

- [ ] **Step 7: Submit archives without buffering them in JavaScript**

Implement:

```js
function downloadArchive(items) {
	var payload = archivePayload(state.path, items.map(function (item) { return item.path; }), Number(data.limits.archiveSelection || 100));
	if (!payload || !data.features.archiveAvailable) {
		speak((data.i18n && data.i18n.archiveUnavailable) || 'ZIP archive support is unavailable.');
		return;
	}
	var form = document.createElement('form');
	form.method = 'post';
	form.action = String(data.downloadUrl || '');
	form.target = 'siteintelix-file-manager-download';
	form.hidden = true;
	[
		['action', 'siteintelix_fm_download_archive'],
		['_wpnonce', data.archiveNonce || ''],
		['current_path', payload.current_path],
	].forEach(function (field) {
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = field[0];
		input.value = field[1];
		form.appendChild(input);
	});
	payload.paths.forEach(function (path) {
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = 'paths[]';
		input.value = path;
		form.appendChild(input);
	});
	document.body.appendChild(form);
	form.submit();
	form.remove();
	speak((data.i18n && data.i18n.archiveStarted) || 'ZIP download started.');
}
```

Add Archive to `contextActionMap` and the stable context-action order. Direct Download remains a same-origin anchor for one file.

- [ ] **Step 8: Implement on-demand Details slide-over**

Replace desktop column toggling with:

```js
function setDetailsOpen(open, trigger) {
	var panel = select('[data-fm-details-panel]');
	panel.hidden = !open;
	panel.classList.toggle('is-open', open);
	if (open) {
		state.restoreFocus = trigger || document.activeElement;
		select('[data-fm-close-details]').focus();
	} else if (state.restoreFocus && typeof state.restoreFocus.focus === 'function') {
		state.restoreFocus.focus();
		state.restoreFocus = null;
	}
}
```

Details actions select the item, load details with the existing stale-request guard, then open the slide-over. Escape and the close button close it. At narrow widths, opening Details closes the folder drawer.

- [ ] **Step 9: Run UI tests**

Run:

```bash
node --check includes/modules/file-manager/assets/file-manager.js
node --test tests/file-manager-ui.test.mjs
```

Expected: all pass.

- [ ] **Step 10: Commit**

```bash
git add tests/file-manager-ui.test.mjs includes/modules/file-manager/assets/file-manager.js
git commit -m "feat: add directadmin file manager interactions"
```

## Task 8: DirectAdmin visual system and responsive behavior

**Files:**

- Modify: `tests/structural.test.mjs`
- Modify: `tests/file-manager-ui.test.mjs`
- Modify: `includes/modules/file-manager/assets/file-manager.css`

- [ ] **Step 1: Add failing visual invariants**

Require:

```js
assert.match(css, /grid-template-columns:\s*260px\s+minmax\(0,\s*1fr\)/);
assert.match(css, /\.sitx-fm-tree__row[\s\S]*padding-left:\s*calc\(/);
assert.match(css, /\.sitx-fm-tree__row\.is-current/);
assert.match(css, /\.sitx-fm-selection-actions/);
assert.match(css, /\.sitx-fm-details\[role="dialog"\]/);
assert.match(css, /\.sitx-fm-tool/);
assert.match(css, /@media \(max-width:\s*1100px\)/);
assert.match(css, /body\.auto-fold \.sitx-fm-tree/);
```

- [ ] **Step 2: Run the visual tests and verify red**

Run:

```bash
node --test --test-name-pattern='File Manager workspace' tests/structural.test.mjs
```

Expected: old three-pane selectors fail.

- [ ] **Step 3: Implement the two-pane layout**

Use:

```css
.sitx-fm-workspace {
	display: grid;
	grid-template-columns: 260px minmax(0, 1fr);
	height: clamp(440px, calc(100vh - 300px), 760px);
	min-height: 0;
	overflow: hidden;
}

.sitx-fm-tree {
	min-width: 0;
	overflow: auto;
	background: #fbfcfd;
	border-right: 1px solid var(--si-border);
}

.sitx-fm-files {
	display: grid;
	grid-template-rows: auto minmax(0, 1fr) auto;
	min-width: 0;
	min-height: 0;
	overflow: hidden;
}
```

Remove the desktop Details grid column and old collapsed-column custom properties.

- [ ] **Step 4: Style DirectAdmin hierarchy and compact controls**

Use 32-pixel tree rows, 18-pixel chevrons, blue folder icons, `padding-left: calc((var(--fm-level) - 1) * 20px + 8px)`, and `#dff2ff` for `.is-current`.

Use transparent compact toolbar buttons with 34-pixel minimum height, 1-pixel separators between groups, and SiteIntelix focus rings. Keep the location bar white with a bottom border and the search field aligned right.

Use 44-pixel file rows, quiet zebra striping, a 36-pixel checkbox column, sticky headers, and the existing file-type colors.

The selection action bar uses a compact blue-tinted surface, selected count at the start, action buttons after it, Trash in danger color, and Clear selection aligned to the end.

- [ ] **Step 5: Style Details and narrow-screen drawers**

Position Details as a fixed right slide-over:

```css
.sitx-fm-details[role="dialog"] {
	position: fixed;
	z-index: 100060;
	top: 32px;
	right: 0;
	bottom: 0;
	width: min(380px, 92vw);
	overflow: auto;
	background: var(--si-surface);
	box-shadow: -12px 0 34px rgb(15 23 42 / 20%);
	transform: translateX(100%);
	transition: transform var(--si-transition);
}

.sitx-fm-details[role="dialog"].is-open {
	transform: translateX(0);
}
```

At 1100 pixels, collapse the workspace to one column and retain the proven tree drawer offsets:

- Normal WordPress menu: `left: 160px`.
- Auto-folded or manually folded: `left: 36px`.
- At 782 pixels: `left: 0`.

Keep closed drawers fully outside the viewport and ensure the more-specific open selectors reset transforms. Preserve reduced-motion overrides.

- [ ] **Step 6: Run UI and structural tests**

Run:

```bash
node --test tests/file-manager-ui.test.mjs tests/structural.test.mjs
git diff --check
```

Expected: all pass and no whitespace errors.

- [ ] **Step 7: Commit**

```bash
git add tests/file-manager-ui.test.mjs tests/structural.test.mjs includes/modules/file-manager/assets/file-manager.css
git commit -m "style: match directadmin file manager layout"
```

## Task 9: Documentation, full verification, and live QA

**Files:**

- Modify: `docs/file-manager.md`
- Modify: `readme.txt`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Add failing documentation assertions**

Require both documentation files to mention:

```js
for (const phrase of [
	'WordPress root',
	'read-only',
	'ZIP',
	'ZipArchive',
	'5,000',
	'250 MB',
	'symlink',
	'temporary',
]) {
	assert.match(documentation, new RegExp(phrase, 'i'));
}
assert.match(readme, /expandable folder tree/i);
assert.match(readme, /private temporary ZIP/i);
```

- [ ] **Step 2: Run documentation test and verify red**

Run:

```bash
node --test --test-name-pattern='release documentation' tests/structural.test.mjs
```

Expected: missing documentation phrases.

- [ ] **Step 3: Document behavior and boundaries**

Add a concise `WordPress root and folder tree` section and a `ZIP downloads` section to `docs/file-manager.md`. State:

- Root browsing is read-only wherever Safe Mode protects changes.
- Tree requests are lazy and non-recursive.
- ZIP requires `ZipArchive`.
- Limits are 100 selected sources, 5,000 entries, and 250 MB uncompressed.
- Symlinks, `wp-config.php`, sensitive server config, and File Manager private storage are omitted.
- ZIPs exist only in private temporary storage during streaming and are removed.
- No extraction or Advanced Mode is included.

Add a 2.7.3 bullet to `readme.txt` describing the expandable tree, compact DirectAdmin-inspired toolbar, root read-only browsing, selection actions, and private temporary ZIP download.

- [ ] **Step 4: Run all automated verification**

Run:

```bash
node --test tests/*.test.mjs
php tests/file-manager-security.php
php tests/file-manager-filesystem.php
php tests/file-manager-tree.php
php tests/file-manager-storage.php
php tests/file-manager-archive.php
php tests/file-manager-editor.php
php tests/file-manager-upload.php
php tests/file-manager-ajax.php
php tests/runtime-smoke.php
php tests/debug-log-parser.php
php tests/editor-links.php
```

Expected: zero failures.

- [ ] **Step 5: Run syntax and repository checks**

Run:

```bash
node --check includes/modules/file-manager/assets/file-manager.js
while IFS= read -r php_file; do php -l "$php_file" || exit 1; done < <(git diff --name-only 04fe28d..HEAD -- '*.php')
git diff --check
git status --short
```

Expected: no syntax errors, no whitespace errors, and only intended documentation changes remain before the final commit.

- [ ] **Step 6: Perform authenticated live QA**

At `http://localhost:10106/wp-admin/admin.php?page=siteintelix-file-manager` verify:

1. WordPress root loads and lists core directories/files without the invalid-path notice.
2. `wp-admin`, `wp-includes`, active plugins/themes, PHP files, and protected config expose no mutation actions.
3. Root and at least three nested levels expand lazily with correct indentation, chevrons, active highlight, and ARIA.
4. New Folder, New File, Upload, Sort by, and Refresh are the only primary toolbar controls.
5. Search and breadcrumbs track the current directory.
6. Single, header, and multi-selection enforce the 100-item limit and update the action bar.
7. One permitted file downloads directly.
8. Multiple permitted files and a folder produce a ZIP with relative entry names.
9. A fixture containing a symlink and protected config omits both and records the omission count.
10. The temporary archive directory is empty after download.
11. Details opens on demand and restores focus when closed.
12. Tree/list/Details scrolling is independent.
13. Widths 1680, 1100, 960, and 760 pixels work with normal, auto-folded, and mobile WordPress navigation.
14. Right-click, three-dot, Shift+F10/Menu key, arrows, Home/End, Enter/Space, Escape, outside click, and live announcements work.

Do not create, rename, trash, or upload live site files solely for visual QA. Use test fixtures for mutation and archive-content checks.

- [ ] **Step 7: Commit documentation**

```bash
git add docs/file-manager.md readme.txt tests/structural.test.mjs
git commit -m "docs: describe directadmin file manager archives"
```

- [ ] **Step 8: Request independent review**

Use `superpowers:requesting-code-review` with base `04fe28d` and the final HEAD. Fix every Critical and Important issue, rerun the complete verification suite, and request a focused re-review before branch handoff.
