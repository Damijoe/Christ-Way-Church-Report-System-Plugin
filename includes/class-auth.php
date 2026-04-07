<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_Auth {

    public static function init() {
        add_action( 'wp_ajax_nopriv_cwr_register', [ __CLASS__, 'handle_register' ] );
        add_action( 'wp_ajax_nopriv_cwr_login',    [ __CLASS__, 'handle_login' ] );
        add_action( 'wp_ajax_cwr_logout',           [ __CLASS__, 'handle_logout' ] );
        add_action( 'wp_ajax_nopriv_cwr_logout',    [ __CLASS__, 'handle_logout' ] );
    }

    /**
     * Pastor self-registration
     * POST: first_name, last_name, email, phone, password, church_id
     */
    public static function handle_register() {
        // Public endpoint — no nonce required
        $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $_POST['last_name']  ?? '' );
        $email      = sanitize_email(      $_POST['email']      ?? '' );
        $phone      = sanitize_text_field( $_POST['phone']      ?? '' );
        $password   = $_POST['password']   ?? '';
        $church_id  = absint(              $_POST['church_id']  ?? 0 );

        if ( ! $first_name || ! $last_name || ! $email || ! $password || ! $church_id ) {
            wp_send_json_error( [ 'message' => 'All fields are required.' ] );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Invalid email address.' ] );
        }

        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( [ 'message' => 'Password must be at least 8 characters.' ] );
        }

        global $wpdb;
        $reg_table = $wpdb->prefix . 'cwr_registrations';

        // Check if already registered
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $reg_table WHERE email = %s", $email
        ) );
        if ( $existing || email_exists( $email ) ) {
            wp_send_json_error( [ 'message' => 'This email is already registered.' ] );
        }

        // Check if church already has a pending/approved pastor
        $church_taken = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $reg_table WHERE church_id = %d AND status IN ('pending','approved')", $church_id
        ) );
        if ( $church_taken ) {
            wp_send_json_error( [ 'message' => 'This church already has a pastor registered or pending approval.' ] );
        }

        $wpdb->insert( $reg_table, [
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'email'         => $email,
            'phone'         => $phone,
            'church_id'     => $church_id,
            'password_hash' => wp_hash_password( $password ),
            'status'        => 'pending',
            'created_at'    => current_time( 'mysql' ),
        ] );

        // Notify admin
        self::notify_admin_new_registration( $first_name, $last_name, $email, $church_id );

        wp_send_json_success( [ 'message' => 'Registration submitted. You will be notified once approved by the admin.' ] );
    }

    /**
     * Login — works for all roles
     * POST: email, password
     */
    public static function handle_login() {
        // Public endpoint — no nonce required
        $email    = sanitize_email( $_POST['email']    ?? '' );
        $password = $_POST['password'] ?? '';

        if ( ! $email || ! $password ) {
            wp_send_json_error( [ 'message' => 'Email and password are required.' ] );
        }

        $user = get_user_by( 'email', $email );

        if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            // Also check pending registrations (not yet WP users)
            global $wpdb;
            $reg = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cwr_registrations WHERE email = %s", $email
            ) );
            if ( $reg && wp_check_password( $password, $reg->password_hash ) ) {
                if ( $reg->status === 'pending' ) {
                    wp_send_json_error( [ 'message' => 'Your account is pending admin approval. You will receive an email once approved.' ] );
                }
                if ( $reg->status === 'rejected' ) {
                    wp_send_json_error( [ 'message' => 'Your registration was not approved. Please contact the admin.' ] );
                }
            }
            wp_send_json_error( [ 'message' => 'Invalid email or password.' ] );
        }

        $role = cwr_get_user_role( $user->ID );
        if ( $role === 'guest' ) {
            wp_send_json_error( [ 'message' => 'You do not have access to this portal.' ] );
        }

        // Log the user in
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        // Get church info for pastor
        $church = null;
        if ( $role === 'pastor' ) {
            $church = CWR_Database::get_current_church( $user->ID );
        }

        // Get zone info for area pastor
        $zone = null;
        if ( $role === 'area_pastor' ) {
            global $wpdb;
            $zone = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cwr_zones WHERE area_pastor_id = %d", $user->ID
            ) );
        }

        wp_send_json_success( [
            'user'    => [
                'id'           => $user->ID,
                'name'         => $user->display_name,
                'email'        => $user->user_email,
                'role'         => $role,
                'church'       => $church,
                'zone'         => $zone,
            ],
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    public static function handle_logout() {
        wp_logout();
        wp_send_json_success( [ 'redirect' => site_url() ] );
    }

    /**
     * Approve a registration — creates WP user, assigns role, links to church
     */
    public static function approve_registration( $reg_id, $approved_by ) {
        global $wpdb;
        $reg_table = $wpdb->prefix . 'cwr_registrations';
        $reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $reg_table WHERE id = %d", $reg_id ) );

        if ( ! $reg || $reg->status !== 'pending' ) {
            return new WP_Error( 'invalid', 'Registration not found or already processed.' );
        }

        // Create WP user - ensure unique username
        $base_username = sanitize_user( strtolower( $reg->first_name . '.' . $reg->last_name ) );
        $username      = $base_username;
        $counter       = 1;
        while ( username_exists( $username ) ) {
            $username = $base_username . $counter;
            $counter++;
        }

        // Use a temporary password then update with their real hash
        $temp_pass = wp_generate_password( 24 );
        $user_id   = wp_insert_user( array(
            'user_login'   => $username,
            'user_email'   => $reg->email,
            'display_name' => $reg->first_name . ' ' . $reg->last_name,
            'first_name'   => $reg->first_name,
            'last_name'    => $reg->last_name,
            'user_pass'    => $temp_pass,
            'role'         => 'cwr_pastor',
        ) );

        if ( is_wp_error( $user_id ) ) {
            // If email already exists, try to find and reuse that user
            $existing_user = get_user_by( 'email', $reg->email );
            if ( $existing_user ) {
                $user_id = $existing_user->ID;
                CWR_Roles::assign_role( $user_id, 'cwr_pastor' );
            } else {
                return $user_id;
            }
        }

        // Update password to the one the pastor registered with
        wp_set_password( $temp_pass, $user_id ); // reset first
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->users} SET user_pass = %s WHERE ID = %d",
            $reg->password_hash, $user_id
        ) );
        // Clear auth cookies cache
        clean_user_cache( $user_id );

        // Assign church
        $wpdb->insert( $wpdb->prefix . 'cwr_pastor_churches', [
            'pastor_id'   => $user_id,
            'church_id'   => $reg->church_id,
            'is_current'  => 1,
            'assigned_at' => current_time( 'mysql' ),
        ] );

        // Update registration record
        $wpdb->update( $reg_table, [
            'status'      => 'approved',
            'wp_user_id'  => $user_id,
            'reviewed_at' => current_time( 'mysql' ),
            'reviewed_by' => $approved_by,
        ], [ 'id' => $reg_id ] );

        // Store phone as user meta
        update_user_meta( $user_id, 'cwr_phone', $reg->phone );
        update_user_meta( $user_id, 'cwr_church_id', $reg->church_id );

        // Send approval email
        self::send_approval_email( $reg->email, $reg->first_name );

        // Add portal notification
        CWR_Notifications::add( $user_id, 'Account Approved', 'Your account has been approved. You can now log in and submit reports.', 'success' );

        return $user_id;
    }

    public static function reject_registration( $reg_id, $rejected_by, $reason = '' ) {
        global $wpdb;
        $reg_table = $wpdb->prefix . 'cwr_registrations';
        $reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $reg_table WHERE id = %d", $reg_id ) );
        if ( ! $reg ) return false;

        $wpdb->update( $reg_table, [
            'status'      => 'rejected',
            'reviewed_at' => current_time( 'mysql' ),
            'reviewed_by' => $rejected_by,
        ], [ 'id' => $reg_id ] );

        self::send_rejection_email( $reg->email, $reg->first_name, $reason );
        return true;
    }

    private static function notify_admin_new_registration( $first, $last, $email, $church_id ) {
        $church = CWR_Database::get_church( $church_id );
        $church_name = $church ? $church->name : 'Unknown';
        $admins = get_users( [ 'role__in' => [ 'cwr_admin', 'administrator' ] ] );
        foreach ( $admins as $admin ) {
            wp_mail(
                $admin->user_email,
                'New Pastor Registration — Christ Way Church Portal',
                "A new pastor has registered and is awaiting approval.\n\n" .
                "Name: $first $last\nEmail: $email\nChurch: $church_name\n\n" .
                "Log in to the admin panel to approve or reject this registration.\n\n" .
                site_url( '/wp-admin/' ),
                [ 'Content-Type: text/plain; charset=UTF-8' ]
            );
        }
    }

    private static function send_approval_email( $email, $first_name ) {
        wp_mail(
            $email,
            'Your Account Has Been Approved — Christ Way Church Portal',
            "Dear $first_name,\n\nYour account on the Christ Way Church Treasure House Reporting Portal has been approved.\n\n" .
            "You can now log in and start submitting weekly reports.\n\n" .
            "Portal: " . site_url() . "\n\n" .
            "God bless you.",
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }

    private static function send_rejection_email( $email, $first_name, $reason ) {
        $reason_text = $reason ? "\n\nReason: $reason" : '';
        wp_mail(
            $email,
            'Registration Update — Christ Way Church Portal',
            "Dear $first_name,\n\nUnfortunately your registration on the Christ Way Church Reporting Portal could not be approved at this time.$reason_text\n\nPlease contact the church admin for further assistance.",
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }
}
