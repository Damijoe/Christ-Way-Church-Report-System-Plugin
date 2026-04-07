<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_Roles {

    public static function init() {
        self::add_roles();
    }

    public static function add_roles() {
        // Admin role
        if ( ! get_role( 'cwr_admin' ) ) {
            add_role( 'cwr_admin', 'CWR Admin', [
                'read'                    => true,
                'cwr_manage_churches'     => true,
                'cwr_manage_zones'        => true,
                'cwr_manage_pastors'      => true,
                'cwr_view_all_reports'    => true,
                'cwr_export_reports'      => true,
                'cwr_approve_users'       => true,
                'cwr_transfer_pastors'    => true,
                'cwr_manage_area_pastors' => true,
            ] );
        }

        // Area Pastor role
        if ( ! get_role( 'cwr_area_pastor' ) ) {
            add_role( 'cwr_area_pastor', 'CWR Area Pastor', [
                'read'                 => true,
                'cwr_view_zone_reports'=> true,
                'cwr_export_reports'   => true,
            ] );
        }

        // Pastor role
        if ( ! get_role( 'cwr_pastor' ) ) {
            add_role( 'cwr_pastor', 'CWR Pastor', [
                'read'              => true,
                'cwr_submit_report' => true,
                'cwr_view_own_reports' => true,
            ] );
        }

        // Give WP administrators all CWR caps
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = [
                'cwr_manage_churches', 'cwr_manage_zones', 'cwr_manage_pastors',
                'cwr_view_all_reports', 'cwr_export_reports', 'cwr_approve_users',
                'cwr_transfer_pastors', 'cwr_manage_area_pastors', 'cwr_view_zone_reports',
                'cwr_submit_report', 'cwr_view_own_reports',
            ];
            foreach ( $caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    public static function remove_roles() {
        remove_role( 'cwr_admin' );
        remove_role( 'cwr_area_pastor' );
        remove_role( 'cwr_pastor' );
    }

    public static function assign_role( $user_id, $role ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;
        // Remove existing CWR roles first
        $user->remove_role( 'cwr_admin' );
        $user->remove_role( 'cwr_area_pastor' );
        $user->remove_role( 'cwr_pastor' );
        $user->add_role( $role );
        return true;
    }
}
