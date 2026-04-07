<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_Notifications {

    public static function init() {
        // Weekly reminder - every Sunday at 6am Lagos time
        if ( ! wp_next_scheduled( 'cwr_weekly_reminder' ) ) {
            $tz   = new DateTimeZone( 'Africa/Lagos' );
            $next = new DateTime( 'next Sunday 06:00:00', $tz );
            wp_schedule_event( $next->getTimestamp(), 'weekly', 'cwr_weekly_reminder' );
        }
        add_action( 'cwr_weekly_reminder', array( __CLASS__, 'send_weekly_reminders' ) );

        // Mid-week follow-up Wednesday 9am
        if ( ! wp_next_scheduled( 'cwr_midweek_followup' ) ) {
            $tz   = new DateTimeZone( 'Africa/Lagos' );
            $next = new DateTime( 'next Wednesday 09:00:00', $tz );
            wp_schedule_event( $next->getTimestamp(), 'weekly', 'cwr_midweek_followup' );
        }
        add_action( 'cwr_midweek_followup', array( __CLASS__, 'send_midweek_followup' ) );
    }

    public static function add( $user_id, $title, $message, $type = 'info' ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'cwr_notifications', array(
            'user_id'    => $user_id,
            'title'      => $title,
            'message'    => $message,
            'type'       => $type,
            'is_read'    => 0,
            'created_at' => current_time( 'mysql' ),
        ) );
    }

    /**
     * Send email using wp_mail with proper headers
     * Uses WP SMTP plugin settings if available
     */
    public static function send_email( $to, $subject, $message ) {
        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        $from_name  = get_bloginfo( 'name' ) ?: 'Christ Way Church';
        $from_email = get_option( 'admin_email' );
        $headers[]  = "From: {$from_name} <{$from_email}>";
        return wp_mail( $to, $subject, $message, $headers );
    }

    public static function send_weekly_reminders() {
        $week_start = CWR_Database::get_current_week_start();
        $missing    = CWR_Database::churches_missing_report( $week_start );

        foreach ( $missing as $church ) {
            if ( ! $church->pastor_id ) continue;

            self::add(
                $church->pastor_id,
                'Weekly Report Due',
                'Your weekly report for the current week is due. Please log in to submit it.',
                'warning'
            );

            if ( $church->user_email ) {
                $portal_url = get_permalink( get_page_by_path( 'church-portal' ) ) ?: site_url();
                self::send_email(
                    $church->user_email,
                    'Weekly Report Reminder - Christ Way Church',
                    "Dear {$church->pastor_name},\n\n" .
                    "This is a reminder to submit your weekly report for {$church->name}.\n\n" .
                    "Please log in to the portal to submit your report:\n{$portal_url}\n\n" .
                    "God bless you.\n\nChrist Way Church"
                );
            }
        }
    }

    public static function send_midweek_followup() {
        $week_start = CWR_Database::get_current_week_start();
        $missing    = CWR_Database::churches_missing_report( $week_start );

        foreach ( $missing as $church ) {
            if ( ! $church->pastor_id ) continue;

            self::add(
                $church->pastor_id,
                'Report Reminder',
                'You have not yet submitted your report for this week. Please submit it as soon as possible.',
                'danger'
            );

            if ( $church->user_email ) {
                $portal_url = get_permalink( get_page_by_path( 'church-portal' ) ) ?: site_url();
                self::send_email(
                    $church->user_email,
                    'Report Not Yet Submitted - Christ Way Church',
                    "Dear {$church->pastor_name},\n\n" .
                    "Our records show that {$church->name} has not yet submitted a report for the current week.\n\n" .
                    "Please log in and submit your report as soon as possible:\n{$portal_url}\n\nThank you.\n\nChrist Way Church"
                );
            }
        }
    }

    public static function notify_area_pastor_new_report( $church_id, $week_start ) {
        global $wpdb;
        $church = CWR_Database::get_church( $church_id );
        if ( ! $church || ! $church->zone_id ) return;

        $zone = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cwr_zones WHERE id = %d", $church->zone_id
        ) );
        if ( ! $zone || ! $zone->area_pastor_id ) return;

        self::add(
            $zone->area_pastor_id,
            'New Report Submitted',
            "{$church->name} has submitted their weekly report.",
            'success'
        );
    }

    /**
     * Test email - called from admin panel to verify email works
     */
    public static function send_test_email( $to ) {
        return self::send_email(
            $to,
            'Test Email - Christ Way Church Portal',
            "This is a test email from the Christ Way Church Reporting Portal.\n\nIf you received this, your email is configured correctly.\n\nGod bless you."
        );
    }
}
