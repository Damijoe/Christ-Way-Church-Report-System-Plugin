<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_API {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        $ns = 'christway/v1';

        // Public
        register_rest_route( $ns, '/churches',         [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_churches' ],         'permission_callback' => '__return_true' ] );
        register_rest_route( $ns, '/weeks',            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_weeks' ],            'permission_callback' => '__return_true' ] );

        // Auth (handled via AJAX for cookie-based auth)
        register_rest_route( $ns, '/me',               [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_me' ],               'permission_callback' => [ __CLASS__, 'is_logged_in' ] ] );

        // Reports
        register_rest_route( $ns, '/reports',          [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_reports' ],          'permission_callback' => [ __CLASS__, 'is_logged_in' ] ] );
        register_rest_route( $ns, '/reports',          [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'submit_report' ],        'permission_callback' => [ __CLASS__, 'is_pastor' ] ] );
        register_rest_route( $ns, '/reports/(?P<id>\d+)', [ 'methods' => 'PUT', 'callback' => [ __CLASS__, 'update_report' ],      'permission_callback' => [ __CLASS__, 'is_pastor' ] ] );
        register_rest_route( $ns, '/reports/summary',  [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_summary' ],         'permission_callback' => [ __CLASS__, 'is_logged_in' ] ] );

        // Zones
        register_rest_route( $ns, '/zones',            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_zones' ],            'permission_callback' => [ __CLASS__, 'is_logged_in' ] ] );
        register_rest_route( $ns, '/zones',            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'create_zone' ],          'permission_callback' => [ __CLASS__, 'is_admin' ] ] );
        register_rest_route( $ns, '/zones/(?P<id>\d+)', [ 'methods' => 'PUT', 'callback' => [ __CLASS__, 'update_zone' ],         'permission_callback' => [ __CLASS__, 'is_admin' ] ] );
        register_rest_route( $ns, '/zones/(?P<id>\d+)', [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete_zone' ],      'permission_callback' => [ __CLASS__, 'is_admin' ] ] );

        // Church management
        register_rest_route( $ns, '/churches/(?P<id>\d+)', [ 'methods' => 'PUT', 'callback' => [ __CLASS__, 'update_church' ],   'permission_callback' => [ __CLASS__, 'is_admin' ] ] );

        // Pastor management
        register_rest_route( $ns, '/pastors',          [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_pastors' ],         'permission_callback' => [ __CLASS__, 'is_admin_or_area' ] ] );
        register_rest_route( $ns, '/pastors/transfer', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'transfer_pastor' ],     'permission_callback' => [ __CLASS__, 'is_admin' ] ] );
        register_rest_route( $ns, '/pastors/(?P<id>\d+)', [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'remove_pastor' ], 'permission_callback' => [ __CLASS__, 'is_admin' ] ] );

        // Registrations (admin)
        register_rest_route( $ns, '/registrations',   [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_registrations' ],   'permission_callback' => [ __CLASS__, 'is_admin' ] ] );
        register_rest_route( $ns, '/registrations/(?P<id>\d+)/approve', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'approve_registration' ], 'permission_callback' => [ __CLASS__, 'is_admin' ] ] );
        register_rest_route( $ns, '/registrations/(?P<id>\d+)/reject',  [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'reject_registration' ],  'permission_callback' => [ __CLASS__, 'is_admin' ] ] );

        // Area pastor management
        register_rest_route( $ns, '/area-pastors',    [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'create_area_pastor' ],  'permission_callback' => [ __CLASS__, 'is_admin' ] ] );
        register_rest_route( $ns, '/area-pastors',    [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_area_pastors' ],    'permission_callback' => [ __CLASS__, 'is_admin' ] ] );

        // Notifications
        register_rest_route( $ns, '/notifications',   [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_notifications' ],   'permission_callback' => [ __CLASS__, 'is_logged_in' ] ] );
        register_rest_route( $ns, '/notifications/read', [ 'methods' => 'POST,GET', 'callback' => [ __CLASS__, 'mark_notifications_read' ], 'permission_callback' => [ __CLASS__, 'is_logged_in' ] ] );

        // Report comments
        register_rest_route( $ns, '/reports/(?P<id>\d+)/comments',  [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_comments' ],    'permission_callback' => [ __CLASS__, 'is_logged_in' ] ] );
        register_rest_route( $ns, '/reports/(?P<id>\d+)/comments',  [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'add_comment' ],     'permission_callback' => [ __CLASS__, 'is_logged_in' ] ] );
        register_rest_route( $ns, '/comments/(?P<id>\d+)',          [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete_comment' ], 'permission_callback' => [ __CLASS__, 'is_admin' ] ] );

        // Exports
        register_rest_route( $ns, '/export/excel',    [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'export_excel' ],        'permission_callback' => [ __CLASS__, 'can_export' ] ] );
        register_rest_route( $ns, '/export/pdf',      [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'export_pdf' ],          'permission_callback' => [ __CLASS__, 'can_export' ] ] );
    }

    // ---- Permission callbacks ----
    public static function is_logged_in() { return is_user_logged_in(); }
    public static function is_pastor()    { return current_user_can( 'cwr_submit_report' ); }
    public static function is_admin()     { return current_user_can( 'cwr_manage_churches' ); }
    public static function is_admin_or_area() {
        return current_user_can( 'cwr_manage_churches' ) || current_user_can( 'cwr_view_zone_reports' );
    }
    public static function can_export()   { return current_user_can( 'cwr_export_reports' ); }

    // ---- Endpoints ----

    public static function get_me( $req ) {
        $user_id = get_current_user_id();
        $role    = cwr_get_user_role( $user_id );
        $user    = get_userdata( $user_id );
        $church  = $role === 'pastor' ? CWR_Database::get_current_church( $user_id ) : null;
        $all_churches = $role === 'pastor' ? CWR_Database::get_all_churches_for_pastor( $user_id ) : null;
        global $wpdb;
        $zone = null;
        if ( $role === 'area_pastor' ) {
            $zone = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cwr_zones WHERE area_pastor_id = %d", $user_id ) );
        }
        return rest_ensure_response( [
            'id'           => $user_id,
            'name'         => $user->display_name,
            'email'        => $user->user_email,
            'role'         => $role,
            'church'       => $church,
            'all_churches' => $all_churches,
            'zone'         => $zone,
            'phone'        => get_user_meta( $user_id, 'cwr_phone', true ),
        ] );
    }

    public static function get_churches( $req ) {
        $zone_id = $req->get_param( 'zone_id' );
        return rest_ensure_response( CWR_Database::get_churches( $zone_id ) );
    }

    public static function get_weeks( $req ) {
        $weeks = CWR_Database::get_weeks_list( 12 );
        // Ensure clean ASCII labels to avoid encoding issues
        foreach ( $weeks as &$w ) {
            $w['label'] = date( 'd M', strtotime( $w['week_start'] ) ) . ' - ' . date( 'd M Y', strtotime( $w['week_end'] ) );
        }
        return rest_ensure_response( $weeks );
    }

    public static function get_reports( $req ) {
        $user_id   = get_current_user_id();
        $role      = cwr_get_user_role( $user_id );
        $week      = sanitize_text_field( $req->get_param( 'week_start' ) );
        $church_id = absint( $req->get_param( 'church_id' ) );
        $zone_id   = absint( $req->get_param( 'zone_id' ) );

        if ( $role === 'pastor' ) {
            if ( $week ) {
                // Week-specific fetch (used by Submit page to check if already submitted)
                return rest_ensure_response( CWR_Database::get_reports_for_pastor_week( $user_id, $week ) );
            }
            // Full history fetch (used by My Reports page)
            return rest_ensure_response( CWR_Database::get_reports_for_pastor( $user_id ) );
        }

        if ( $role === 'area_pastor' ) {
            global $wpdb;
            $zone = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cwr_zones WHERE area_pastor_id = %d", $user_id ) );
            if ( ! $zone ) return rest_ensure_response( [] );
            return rest_ensure_response( CWR_Database::get_reports_for_zone( $zone->id, $week ?: null ) );
        }

        // Admin — full access
        return rest_ensure_response( CWR_Database::get_all_reports(
            $week     ?: null,
            $zone_id  ?: null,
            $church_id?: null
        ) );
    }

    public static function submit_report( $req ) {
        $user_id = get_current_user_id();
        $church  = CWR_Database::get_current_church( $user_id );
        if ( ! $church ) return new WP_Error( 'no_church', 'You are not assigned to a church.', [ 'status' => 403 ] );

        $week_start = sanitize_text_field( $req->get_param( 'week_start' ) );
        if ( ! $week_start ) $week_start = CWR_Database::get_current_week_start();

        // Validate week format
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $week_start ) ) {
            return new WP_Error( 'invalid_week', 'Invalid week format.', [ 'status' => 400 ] );
        }

        $week_end      = CWR_Database::get_week_end( $week_start );
        $sunday_att    = absint( $req->get_param( 'sunday_attendance' ) );
        $midweek_att   = absint( $req->get_param( 'midweek_attendance' ) );
        $total_att     = $sunday_att + $midweek_att;
        $offering      = floatval( $req->get_param( 'total_offering' ) );
        $tithe         = floatval( $req->get_param( 'total_tithe' ) );
        $other         = floatval( $req->get_param( 'other_income' ) );
        $salvations    = absint( $req->get_param( 'salvations' ) );
        $first_timers  = absint( $req->get_param( 'first_timers' ) );
        $notes         = sanitize_textarea_field( $req->get_param( 'notes' ) );

        global $wpdb;
        $table = $wpdb->prefix . 'cwr_reports';

        $existing = CWR_Database::get_report( $church->id, $week_start );

        $data = [
            'church_id'          => $church->id,
            'pastor_id'          => $user_id,
            'week_start'         => $week_start,
            'week_end'           => $week_end,
            'sunday_attendance'  => $sunday_att,
            'midweek_attendance' => $midweek_att,
            'total_attendance'   => $total_att,
            'total_offering'     => $offering,
            'total_tithe'        => $tithe,
            'other_income'       => $other,
            'salvations'         => $salvations,
            'first_timers'       => $first_timers,
            'notes'              => $notes,
            'status'             => 'submitted',
            'submitted_at'       => current_time( 'mysql' ),
        ];

        if ( $existing ) {
            $wpdb->update( $table, $data, [ 'id' => $existing->id ] );
            $report_id = $existing->id;
            $message   = 'Report updated successfully.';
        } else {
            $wpdb->insert( $table, $data );
            $report_id = $wpdb->insert_id;
            $message   = 'Report submitted successfully.';
        }

        // Notify area pastor
        CWR_Notifications::notify_area_pastor_new_report( $church->id, $week_start );

        return rest_ensure_response( [ 'id' => $report_id, 'message' => $message ] );
    }

    public static function update_report( $req ) {
        return self::submit_report( $req );
    }

    public static function get_summary( $req ) {
        $user_id  = get_current_user_id();
        $role     = cwr_get_user_role( $user_id );
        $week     = sanitize_text_field( $req->get_param( 'week_start' ) ) ?: CWR_Database::get_current_week_start();

        global $wpdb;
        $r = $wpdb->prefix . 'cwr_reports';
        $c = $wpdb->prefix . 'cwr_churches';

        $zone_filter = '';
        if ( $role === 'area_pastor' ) {
            $zone = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cwr_zones WHERE area_pastor_id = %d", $user_id ) );
            if ( $zone ) $zone_filter = $wpdb->prepare( "AND c.zone_id = %d", $zone->id );
        }

        $totals = $wpdb->get_row( "
            SELECT
                COUNT(DISTINCT r.id)          as reports_submitted,
                SUM(r.sunday_attendance)      as total_sunday,
                SUM(r.midweek_attendance)     as total_midweek,
                SUM(r.total_attendance)       as total_attendance,
                SUM(r.total_offering)         as total_offering,
                SUM(r.total_tithe)            as total_tithe,
                SUM(r.other_income)           as other_income,
                SUM(r.salvations)             as total_salvations,
                SUM(r.first_timers)           as total_first_timers
            FROM $r r
            INNER JOIN $c c ON c.id = r.church_id
            WHERE r.week_start = '$week' $zone_filter
        " );

        $total_churches_q = "SELECT COUNT(*) FROM $c WHERE status = 'active'";
        if ( $zone_filter ) $total_churches_q .= " $zone_filter";
        $total_churches = $wpdb->get_var( $total_churches_q );

        $missing = CWR_Database::churches_missing_report( $week, $role === 'area_pastor' && isset( $zone ) ? $zone->id : null );

        return rest_ensure_response( [
            'week_start'       => $week,
            'totals'           => $totals,
            'total_churches'   => (int) $total_churches,
            'missing_count'    => count( $missing ),
            'missing_churches' => array_map( function($m) { return [ 'id' => $m->id, 'name' => $m->name ]; }, $missing ),
        ] );
    }

    public static function get_zones( $req ) {
        return rest_ensure_response( CWR_Database::get_zones() );
    }

    public static function create_zone( $req ) {
        global $wpdb;
        $name = sanitize_text_field( $req->get_param( 'name' ) );
        $desc = sanitize_textarea_field( $req->get_param( 'description' ) );
        if ( ! $name ) return new WP_Error( 'missing', 'Zone name is required.', [ 'status' => 400 ] );
        $wpdb->insert( $wpdb->prefix . 'cwr_zones', [
            'name'        => $name,
            'description' => $desc,
            'created_at'  => current_time( 'mysql' ),
        ] );
        return rest_ensure_response( [ 'id' => $wpdb->insert_id, 'message' => 'Zone created.' ] );
    }

    public static function update_zone( $req ) {
        global $wpdb;
        $id             = absint( $req->get_param( 'id' ) );
        $name           = sanitize_text_field( $req->get_param( 'name' ) );
        $desc           = sanitize_textarea_field( $req->get_param( 'description' ) );
        $area_pastor_id = absint( $req->get_param( 'area_pastor_id' ) );
        $data = [];
        if ( $name )           $data['name']           = $name;
        if ( $desc )           $data['description']    = $desc;
        if ( $area_pastor_id ) $data['area_pastor_id'] = $area_pastor_id;
        $wpdb->update( $wpdb->prefix . 'cwr_zones', $data, [ 'id' => $id ] );
        return rest_ensure_response( [ 'message' => 'Zone updated.' ] );
    }

    public static function delete_zone( $req ) {
        global $wpdb;
        $id = absint( $req->get_param( 'id' ) );
        // Unassign churches first
        $wpdb->update( $wpdb->prefix . 'cwr_churches', [ 'zone_id' => null ], [ 'zone_id' => $id ] );
        $wpdb->delete( $wpdb->prefix . 'cwr_zones', [ 'id' => $id ] );
        return rest_ensure_response( [ 'message' => 'Zone deleted.' ] );
    }

    public static function update_church( $req ) {
        global $wpdb;
        $id      = absint( $req->get_param( 'id' ) );
        $zone_id = $req->get_param( 'zone_id' );
        $name    = sanitize_text_field( $req->get_param( 'name' ) );
        $data = [];
        if ( $name ) $data['name'] = $name;
        if ( $zone_id !== null ) $data['zone_id'] = $zone_id ? absint( $zone_id ) : null;
        $wpdb->update( $wpdb->prefix . 'cwr_churches', $data, [ 'id' => $id ] );
        return rest_ensure_response( [ 'message' => 'Church updated.' ] );
    }

    public static function get_pastors( $req ) {
        global $wpdb;
        $pc = $wpdb->prefix . 'cwr_pastor_churches';
        $cc = $wpdb->prefix . 'cwr_churches';
        $u  = $wpdb->users;
        $um = $wpdb->usermeta;

        $zone_id = absint( $req->get_param( 'zone_id' ) );
        $zone_sql = $zone_id ? $wpdb->prepare( "AND c.zone_id = %d", $zone_id ) : '';

        return rest_ensure_response( $wpdb->get_results( "
            SELECT u.ID as id, u.display_name as name, u.user_email as email,
                   c.id as church_id, c.name as church_name, c.zone_id,
                   z.name as zone_name,
                   um.meta_value as phone
            FROM $u u
            INNER JOIN {$wpdb->usermeta} ur ON ur.user_id = u.ID AND ur.meta_key = '{$wpdb->prefix}capabilities' AND ur.meta_value LIKE '%cwr_pastor%'
            LEFT JOIN $pc pc ON pc.pastor_id = u.ID AND pc.is_current = 1
            LEFT JOIN $cc c  ON c.id = pc.church_id $zone_sql
            LEFT JOIN {$wpdb->prefix}cwr_zones z ON z.id = c.zone_id
            LEFT JOIN $um um ON um.user_id = u.ID AND um.meta_key = 'cwr_phone'
            ORDER BY u.display_name ASC
        " ) );
    }

    public static function transfer_pastor( $req ) {
        global $wpdb;
        $pastor_id     = absint( $req->get_param( 'pastor_id' ) );
        $to_church_id  = absint( $req->get_param( 'to_church_id' ) );
        $reason        = sanitize_textarea_field( $req->get_param( 'reason' ) );
        $admin_id      = get_current_user_id();

        if ( ! $pastor_id || ! $to_church_id ) {
            return new WP_Error( 'missing', 'Pastor ID and destination church are required.', [ 'status' => 400 ] );
        }

        $pc = $wpdb->prefix . 'cwr_pastor_churches';

        // Get current church
        $current = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $pc WHERE pastor_id = %d AND is_current = 1", $pastor_id
        ) );

        $from_church_id = $current ? $current->church_id : null;

        // Mark old as not current
        if ( $current ) {
            $wpdb->update( $pc, [
                'is_current' => 0,
                'removed_at' => current_time( 'mysql' ),
            ], [ 'id' => $current->id ] );
        }

        // Assign new church
        $wpdb->insert( $pc, [
            'pastor_id'   => $pastor_id,
            'church_id'   => $to_church_id,
            'is_current'  => 1,
            'assigned_at' => current_time( 'mysql' ),
        ] );

        // Log transfer
        $wpdb->insert( $wpdb->prefix . 'cwr_transfers', [
            'pastor_id'      => $pastor_id,
            'from_church_id' => $from_church_id ?? 0,
            'to_church_id'   => $to_church_id,
            'transferred_by' => $admin_id,
            'reason'         => $reason,
            'transferred_at' => current_time( 'mysql' ),
        ] );

        update_user_meta( $pastor_id, 'cwr_church_id', $to_church_id );

        // Notify pastor
        $to_church = CWR_Database::get_church( $to_church_id );
        CWR_Notifications::add( $pastor_id, 'Church Transfer', "You have been transferred to {$to_church->name}. You still have access to your previous church reports.", 'info' );

        $pastor = get_userdata( $pastor_id );
        if ( $pastor ) {
            wp_mail(
                $pastor->user_email,
                'Church Transfer — Christ Way Church Portal',
                "Dear {$pastor->display_name},\n\nYou have been transferred to {$to_church->name}.\n\nYou will still have access to all your previous church reports.\n\nGod bless you.",
                [ 'Content-Type: text/plain; charset=UTF-8' ]
            );
        }

        return rest_ensure_response( [ 'message' => 'Pastor transferred successfully.' ] );
    }

    public static function remove_pastor( $req ) {
        global $wpdb;
        $pastor_id = absint( $req->get_param( 'id' ) );
        $pc = $wpdb->prefix . 'cwr_pastor_churches';
        $wpdb->update( $pc, [
            'is_current' => 0,
            'removed_at' => current_time( 'mysql' ),
        ], [ 'pastor_id' => $pastor_id, 'is_current' => 1 ] );

        $user = get_userdata( $pastor_id );
        if ( $user ) {
            $user->remove_role( 'cwr_pastor' );
        }
        return rest_ensure_response( [ 'message' => 'Pastor removed from church.' ] );
    }

    public static function get_registrations( $req ) {
        global $wpdb;
        $status = sanitize_text_field( $req->get_param( 'status' ) ) ?: 'pending';
        $reg    = $wpdb->prefix . 'cwr_registrations';
        $cc     = $wpdb->prefix . 'cwr_churches';
        return rest_ensure_response( $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, c.name as church_name
             FROM $reg r
             LEFT JOIN $cc c ON c.id = r.church_id
             WHERE r.status = %s
             ORDER BY r.created_at DESC", $status
        ) ) );
    }

    public static function approve_registration( $req ) {
        $id     = absint( $req->get_param( 'id' ) );
        $result = CWR_Auth::approve_registration( $id, get_current_user_id() );
        if ( is_wp_error( $result ) ) return $result;
        return rest_ensure_response( [ 'message' => 'Registration approved. Pastor account created.' ] );
    }

    public static function reject_registration( $req ) {
        $id     = absint( $req->get_param( 'id' ) );
        $reason = sanitize_textarea_field( $req->get_param( 'reason' ) );
        CWR_Auth::reject_registration( $id, get_current_user_id(), $reason );
        return rest_ensure_response( [ 'message' => 'Registration rejected.' ] );
    }

    public static function create_area_pastor( $req ) {
        $name     = sanitize_text_field( $req->get_param( 'name' ) );
        $email    = sanitize_email( $req->get_param( 'email' ) );
        $password = $req->get_param( 'password' );
        $phone    = sanitize_text_field( $req->get_param( 'phone' ) );
        $zone_id  = absint( $req->get_param( 'zone_id' ) );

        if ( ! $name || ! $email || ! $password ) {
            return new WP_Error( 'missing', 'Name, email and password are required.', [ 'status' => 400 ] );
        }
        if ( email_exists( $email ) ) {
            return new WP_Error( 'exists', 'Email already in use.', [ 'status' => 400 ] );
        }

        $user_id = wp_create_user( sanitize_user( strtolower( str_replace( ' ', '.', $name ) ) ), $password, $email );
        if ( is_wp_error( $user_id ) ) return $user_id;

        wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
        CWR_Roles::assign_role( $user_id, 'cwr_area_pastor' );
        update_user_meta( $user_id, 'cwr_phone', $phone );

        if ( $zone_id ) {
            global $wpdb;
            $wpdb->update( $wpdb->prefix . 'cwr_zones', [ 'area_pastor_id' => $user_id ], [ 'id' => $zone_id ] );
        }

        return rest_ensure_response( [ 'id' => $user_id, 'message' => 'Area pastor created.' ] );
    }

    public static function get_area_pastors( $req ) {
        $users = get_users( [ 'role' => 'cwr_area_pastor', 'orderby' => 'display_name' ] );
        global $wpdb;
        $result = [];
        foreach ( $users as $u ) {
            $zone = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cwr_zones WHERE area_pastor_id = %d", $u->ID
            ) );
            $result[] = [
                'id'    => $u->ID,
                'name'  => $u->display_name,
                'email' => $u->user_email,
                'phone' => get_user_meta( $u->ID, 'cwr_phone', true ),
                'zone'  => $zone,
            ];
        }
        return rest_ensure_response( $result );
    }

    public static function get_notifications( $req ) {
        global $wpdb;
        $user_id = get_current_user_id();
        return rest_ensure_response( $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cwr_notifications WHERE user_id = %d ORDER BY created_at DESC LIMIT 20", $user_id
        ) ) );
    }

    public static function mark_notifications_read( $req ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $wpdb->update( $wpdb->prefix . 'cwr_notifications', [ 'is_read' => 1 ], [ 'user_id' => $user_id ] );
        return rest_ensure_response( [ 'message' => 'Notifications marked as read.' ] );
    }

    public static function export_excel( $req ) {
        $week     = sanitize_text_field( $req->get_param( 'week_start' ) );
        $zone_id  = absint( $req->get_param( 'zone_id' ) );
        $church_id= absint( $req->get_param( 'church_id' ) );
        CWR_Export::export_excel( $week, $zone_id, $church_id );
    }

    public static function export_pdf( $req ) {
        $week     = sanitize_text_field( $req->get_param( 'week_start' ) );
        $zone_id  = absint( $req->get_param( 'zone_id' ) );
        $church_id= absint( $req->get_param( 'church_id' ) );
        CWR_Export::export_pdf( $week, $zone_id, $church_id );
    }

    public static function get_comments( $req ) {
        global $wpdb;
        $report_id = absint( $req->get_param( 'id' ) );
        $c  = $wpdb->prefix . 'cwr_report_comments';
        $u  = $wpdb->users;
        return rest_ensure_response( $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, u.display_name as author_name, um.meta_value as author_role
             FROM $c c
             LEFT JOIN $u u ON u.ID = c.user_id
             LEFT JOIN {$wpdb->usermeta} um ON um.user_id = c.user_id
                AND um.meta_key = '{$wpdb->prefix}capabilities'
             WHERE c.report_id = %d
             ORDER BY c.created_at ASC", $report_id
        ) ) );
    }

    public static function add_comment( $req ) {
        global $wpdb;
        $report_id = absint( $req->get_param( 'id' ) );
        $comment   = sanitize_textarea_field( $req->get_param( 'comment' ) );
        $user_id   = get_current_user_id();
        $role      = cwr_get_user_role( $user_id );

        if ( ! $comment ) {
            return new WP_Error( 'empty', 'Comment cannot be empty.', array( 'status' => 400 ) );
        }

        // Only admin and area pastors can comment on reports
        if ( $role === 'pastor' ) {
            return new WP_Error( 'forbidden', 'Pastors cannot add comments to reports.', array( 'status' => 403 ) );
        }

        $wpdb->insert( $wpdb->prefix . 'cwr_report_comments', array(
            'report_id'  => $report_id,
            'user_id'    => $user_id,
            'comment'    => $comment,
            'created_at' => current_time( 'mysql' ),
        ) );

        $comment_id = $wpdb->insert_id;

        // Notify the pastor who submitted this report
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cwr_reports WHERE id = %d", $report_id
        ) );

        if ( $report ) {
            $church   = CWR_Database::get_church( $report->church_id );
            $commenter = get_userdata( $user_id );
            $commenter_name = $commenter ? $commenter->display_name : 'Admin';
            $church_name    = $church ? $church->name : 'your church';

            CWR_Notifications::add(
                $report->pastor_id,
                'New Comment on Your Report',
                $commenter_name . ' commented on your report for ' . $church_name . ' (week of ' . $report->week_start . '): ' . wp_trim_words( $comment, 12 ),
                'info'
            );

            // Email notification
            $pastor = get_userdata( $report->pastor_id );
            if ( $pastor ) {
                CWR_Notifications::send_email(
                    $pastor->user_email,
                    'New Comment on Your Report - Christ Way Church',
                    'Dear ' . $pastor->display_name . ', ' . $commenter_name . ' commented on your report for ' . $church_name . '. Comment: ' . $comment . '. Log in: ' . site_url()
                );
            }
        }

        return rest_ensure_response( array( 'id' => $comment_id, 'message' => 'Comment added.' ) );
    }

    public static function delete_comment( $req ) {
        global $wpdb;
        $id = absint( $req->get_param( 'id' ) );
        $wpdb->delete( $wpdb->prefix . 'cwr_report_comments', array( 'id' => $id ) );
        return rest_ensure_response( array( 'message' => 'Comment deleted.' ) );
    }

}