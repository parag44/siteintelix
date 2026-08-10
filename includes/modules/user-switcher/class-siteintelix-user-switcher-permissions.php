<?php
/**
 * User Switcher permission policy.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates operator capabilities and switchable targets.
 */
class SITEINTELIX_User_Switcher_Permissions {

	const CAPABILITY = 'siteintelix_switch_users';

	/**
	 * Whether a user may operate User Switcher.
	 *
	 * @param WP_User|int|null $user User or ID. Defaults to current user.
	 * @return bool
	 */
	public static function can_operate( $user = null ) {
		$user = $user instanceof WP_User ? $user : get_user_by( 'id', $user ? absint( $user ) : get_current_user_id() );
		return $user instanceof WP_User && user_can( $user, self::CAPABILITY );
	}

	/**
	 * Whether the target is an administrator.
	 *
	 * @param WP_User $target_user Target user.
	 * @return bool
	 */
	public static function is_administrator_target( $target_user ) {
		return $target_user instanceof WP_User && in_array( 'administrator', (array) $target_user->roles, true );
	}

	/**
	 * Determine whether the current operator may switch to a target.
	 *
	 * @param WP_User|int      $target_user  Target user or ID.
	 * @param WP_User|int|null $current_user Operator user or ID.
	 * @return bool
	 */
	public static function can_switch_to( $target_user, $current_user = null ) {
		$target_user  = $target_user instanceof WP_User ? $target_user : get_user_by( 'id', absint( $target_user ) );
		$current_user = $current_user instanceof WP_User ? $current_user : get_user_by( 'id', $current_user ? absint( $current_user ) : get_current_user_id() );

		if ( ! $target_user instanceof WP_User || ! $current_user instanceof WP_User || ! self::can_operate( $current_user ) ) {
			return false;
		}
		if ( is_multisite() && ! is_super_admin( $current_user->ID ) && ! is_user_member_of_blog( $current_user->ID, get_current_blog_id() ) ) {
			return false;
		}

		$can_switch = true;
		$settings   = SITEINTELIX_User_Switcher_Settings::get_settings();

		if ( $target_user->ID === $current_user->ID || empty( $target_user->ID ) || empty( $target_user->roles ) ) {
			$can_switch = false;
		}

		if ( $can_switch && is_multisite() && ! is_user_member_of_blog( $target_user->ID, get_current_blog_id() ) ) {
			$can_switch = false;
		}

		$is_super_admin = is_multisite() && is_super_admin( $target_user->ID );
		if ( $can_switch && $is_super_admin ) {
			$can_switch = false;
		}

		$is_administrator = self::is_administrator_target( $target_user );
		if ( $can_switch && $is_administrator ) {
			$operator_is_administrator = in_array( 'administrator', (array) $current_user->roles, true ) || ( is_multisite() && is_super_admin( $current_user->ID ) );
			$can_switch                = ! empty( $settings['allow_administrators'] ) && $operator_is_administrator;
		} elseif ( $can_switch && ! array_intersect( (array) $target_user->roles, (array) $settings['allowed_target_roles'] ) ) {
			$can_switch = false;
		}

		$protected_roles = empty( $settings['allow_administrators'] ) ? array( 'administrator' ) : array();
		/**
		 * Filter roles protected from User Switcher.
		 *
		 * @param string[] $protected_roles Protected role slugs.
		 * @param WP_User  $target_user     Target user.
		 * @param WP_User  $current_user    Operator.
		 */
		$protected_roles = (array) apply_filters( 'siteintelix_user_switcher_protected_roles', $protected_roles, $target_user, $current_user );
		if ( $can_switch && array_intersect( array_map( 'sanitize_key', $protected_roles ), (array) $target_user->roles ) ) {
			$can_switch = false;
		}

		/**
		 * Filter whether a target may be switched into.
		 *
		 * This filter may explicitly opt into a multisite super-administrator
		 * target. Operator capability checks are always enforced separately.
		 *
		 * @param bool    $can_switch   Whether the target is switchable.
		 * @param WP_User $target_user  Target user.
		 * @param WP_User $current_user Operator.
		 */
		$can_switch = (bool) apply_filters( 'siteintelix_user_switcher_can_switch', $can_switch, $target_user, $current_user );

		// Capability, identity, and role presence are non-overridable invariants.
		return $can_switch
			&& self::can_operate( $current_user )
			&& $target_user->ID !== $current_user->ID
			&& ! empty( $target_user->roles );
	}

	/**
	 * Synchronize capabilities for roles selected in module settings.
	 *
	 * Only capabilities originally added by SiteIntelix are removed later.
	 *
	 * @param string[] $role_slugs Selected operator roles.
	 * @return void
	 */
	public static function sync_operator_roles( $role_slugs ) {
		$managed  = get_option( SITEINTELIX_User_Switcher_Settings::MANAGED_ROLES_OPTION, array() );
		$managed  = is_array( $managed ) ? array_map( 'sanitize_key', $managed ) : array();
		$selected = array_values( array_unique( array_map( 'sanitize_key', (array) $role_slugs ) ) );

		foreach ( $selected as $role_slug ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}
			if ( ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
				$managed[] = $role_slug;
			}
		}

		foreach ( array_diff( $managed, $selected ) as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role ) {
				$role->remove_cap( self::CAPABILITY );
			}
		}

		$managed = array_values( array_intersect( array_unique( $managed ), $selected ) );
		update_option( SITEINTELIX_User_Switcher_Settings::MANAGED_ROLES_OPTION, $managed, false );
	}
}
