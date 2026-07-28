<?php
/**
 * Admin controller for Code Snippets.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Snippets_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 39 );
		add_action( 'admin_post_siteintelix_snippet_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_siteintelix_snippet_action', array( __CLASS__, 'action' ) );
		add_action( 'admin_post_siteintelix_snippet_bulk', array( __CLASS__, 'bulk' ) );
		add_action( 'admin_post_siteintelix_snippet_run_once', array( __CLASS__, 'run_once' ) );
	}

	public static function menu() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) return;
		add_submenu_page( 'siteintelix', __( 'Code Snippets', 'siteintelix' ), __( 'Code Snippets', 'siteintelix' ), 'manage_options', 'siteintelix-code-snippets', array( __CLASS__, 'render_list' ) );
		add_submenu_page( null, __( 'Add New Snippet', 'siteintelix' ), __( 'Add New Snippet', 'siteintelix' ), 'manage_options', 'siteintelix-code-snippets-new', array( __CLASS__, 'render_editor' ) );
		add_submenu_page( null, __( 'Run Snippet Once', 'siteintelix' ), '', 'manage_options', 'siteintelix-code-snippets-run-once', array( __CLASS__, 'render_run_once' ) );
	}

	private static function auth( $nonce ) {
		if ( ! SITEINTELIX_Security::can_manage_code() ) wp_die( esc_html__( 'Access denied.', 'siteintelix' ), 403 );
		check_admin_referer( $nonce );
	}

	public static function render_list() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) wp_die( esc_html__( 'Access denied.', 'siteintelix' ) );
		$args = array( 'page'=>absint($_GET['paged']??1), 'search'=>sanitize_text_field(wp_unslash($_GET['s']??'')), 'status'=>sanitize_key($_GET['status']??''), 'scope'=>sanitize_key($_GET['scope']??''), 'recent'=>isset($_GET['recent']) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		list( $items, $total ) = SITEINTELIX_Snippets_Repository::list_items( $args );
		list( , $summary_total ) = SITEINTELIX_Snippets_Repository::list_items();
		list( , $summary_active ) = SITEINTELIX_Snippets_Repository::list_items( array( 'status' => 'active' ) );
		list( , $summary_inactive ) = SITEINTELIX_Snippets_Repository::list_items( array( 'status' => 'inactive' ) );
		$summary = array( 'total' => $summary_total, 'active' => $summary_active, 'inactive' => $summary_inactive );
		include __DIR__ . '/views/list.php';
	}

	public static function render_editor() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) wp_die( esc_html__( 'Access denied.', 'siteintelix' ) );
		$id=absint($_GET['snippet']??0); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$item=$id?SITEINTELIX_Snippets_Repository::get($id):array();
		$item=wp_parse_args((array)$item,array('id'=>0,'name'=>'','code'=>'','description'=>'','scope'=>'everywhere','priority'=>10,'status'=>'inactive','tags'=>'','error_message'=>''));
		include __DIR__ . '/views/editor.php';
	}
	public static function render_run_once() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) wp_die( esc_html__( 'Access denied.', 'siteintelix' ), 403 );
		$item=SITEINTELIX_Snippets_Repository::get(absint($_GET['snippet']??0)); include __DIR__.'/views/run-once-confirm.php';
	} // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	public static function save() {
		self::auth('siteintelix_snippet_save');
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- auth() verified the action-specific nonce immediately above.
		$id=absint($_POST['snippet_id']??0); $input=SITEINTELIX_Snippets_Repository::normalize_request($_POST);
		$valid=SITEINTELIX_Snippets_Validator::validate($input['code']);
		if(is_wp_error($valid)){ wp_safe_redirect(add_query_arg('snippet_error',rawurlencode($valid->get_error_message()),wp_get_referer())); exit; }
		if('save_activate'===sanitize_key($_POST['submit_mode']??'')) $input['status']='active';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$id=$id?(SITEINTELIX_Snippets_Repository::update($id,$input)?$id:0):SITEINTELIX_Snippets_Repository::insert($input);
		wp_safe_redirect(add_query_arg(array('page'=>'siteintelix-code-snippets-new','snippet'=>$id,'saved'=>1),admin_url('admin.php'))); exit;
	}

	public static function action() {
		self::auth('siteintelix_snippet_action');
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- auth() verified the action-specific nonce immediately above.
		$id=absint($_GET['snippet']??0); $do=sanitize_key($_GET['do']??'');
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$item=SITEINTELIX_Snippets_Repository::get($id);
		if($item&&in_array($do,array('activate','deactivate'),true)) SITEINTELIX_Snippets_Repository::set_status(array($id),'activate'===$do?'active':'inactive');
		elseif($item&&'duplicate'===$do) SITEINTELIX_Snippets_Repository::duplicate($id); elseif($item&&'delete'===$do) SITEINTELIX_Snippets_Repository::delete($id);
		wp_safe_redirect(admin_url('admin.php?page=siteintelix-code-snippets')); exit;
	}
	public static function bulk() {
		self::auth('siteintelix_snippet_bulk');
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- auth() verified the action-specific nonce immediately above.
		$ids=array_map('absint',(array)($_POST['snippet_ids']??array())); $do=sanitize_key($_POST['bulk_action']??'');
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if(in_array($do,array('activate','deactivate'),true)) SITEINTELIX_Snippets_Repository::set_status($ids,'activate'===$do?'active':'inactive'); elseif('delete'===$do) foreach($ids as $id) SITEINTELIX_Snippets_Repository::delete($id);
		wp_safe_redirect(admin_url('admin.php?page=siteintelix-code-snippets')); exit;
	}
	public static function run_once() {
		self::auth('siteintelix_snippet_run_once');
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- auth() verified the action-specific nonce immediately above.
		$id=absint($_POST['snippet_id']??0); $item=SITEINTELIX_Snippets_Repository::get($id);
		if($item){ SITEINTELIX_Snippets_Repository::set_status(array($id),'running_once'); SITEINTELIX_Snippets_Runner::execute($item); SITEINTELIX_Snippets_Repository::set_status(array($id),'inactive'); }
		wp_safe_redirect(admin_url('admin.php?page=siteintelix-code-snippets')); exit;
	}
}
