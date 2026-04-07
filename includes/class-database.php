<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Zones table
        dbDelta( "CREATE TABLE {$wpdb->prefix}cwr_zones (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(150)    NOT NULL,
            description TEXT,
            area_pastor_id BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // Churches table
        dbDelta( "CREATE TABLE {$wpdb->prefix}cwr_churches (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(200)    NOT NULL,
            zone_id     BIGINT UNSIGNED DEFAULT NULL,
            status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY zone_id (zone_id)
        ) $charset;" );

        // Pastor-Church assignments (handles transfers; pastor retains history access)
        dbDelta( "CREATE TABLE {$wpdb->prefix}cwr_pastor_churches (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pastor_id   BIGINT UNSIGNED NOT NULL,
            church_id   BIGINT UNSIGNED NOT NULL,
            is_current  TINYINT(1)      NOT NULL DEFAULT 1,
            assigned_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            removed_at  DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY pastor_id (pastor_id),
            KEY church_id (church_id)
        ) $charset;" );

        // Weekly reports
        dbDelta( "CREATE TABLE {$wpdb->prefix}cwr_reports (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            church_id       BIGINT UNSIGNED NOT NULL,
            pastor_id       BIGINT UNSIGNED NOT NULL,
            week_start      DATE            NOT NULL,
            week_end        DATE            NOT NULL,
            sunday_attendance   INT UNSIGNED NOT NULL DEFAULT 0,
            midweek_attendance  INT UNSIGNED NOT NULL DEFAULT 0,
            total_attendance    INT UNSIGNED NOT NULL DEFAULT 0,
            total_offering      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_tithe         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            other_income        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            salvations          INT UNSIGNED NOT NULL DEFAULT 0,
            first_timers        INT UNSIGNED NOT NULL DEFAULT 0,
            notes               TEXT,
            status          ENUM('submitted','draft') NOT NULL DEFAULT 'submitted',
            submitted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY church_week (church_id, week_start),
            KEY pastor_id (pastor_id),
            KEY week_start (week_start)
        ) $charset;" );

        // Pending registrations
        dbDelta( "CREATE TABLE {$wpdb->prefix}cwr_registrations (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name  VARCHAR(100)    NOT NULL,
            last_name   VARCHAR(100)    NOT NULL,
            email       VARCHAR(200)    NOT NULL,
            phone       VARCHAR(30),
            church_id   BIGINT UNSIGNED NOT NULL,
            password_hash VARCHAR(255)  NOT NULL,
            status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            wp_user_id  BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME        DEFAULT NULL,
            reviewed_by BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY email (email),
            KEY status (status)
        ) $charset;" );

        // Transfer log
        dbDelta( "CREATE TABLE {$wpdb->prefix}cwr_transfers (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pastor_id       BIGINT UNSIGNED NOT NULL,
            from_church_id  BIGINT UNSIGNED NOT NULL,
            to_church_id    BIGINT UNSIGNED NOT NULL,
            transferred_by  BIGINT UNSIGNED NOT NULL,
            reason          TEXT,
            transferred_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // Notifications
        dbDelta( "CREATE TABLE {$wpdb->prefix}cwr_notifications (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            title       VARCHAR(200)    NOT NULL,
            message     TEXT            NOT NULL,
            type        ENUM('info','warning','success','danger') NOT NULL DEFAULT 'info',
            is_read     TINYINT(1)      NOT NULL DEFAULT 0,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read)
        ) $charset;" );

        // Report comments table
        dbDelta( "CREATE TABLE {$wpdb->prefix}cwr_report_comments (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id   BIGINT UNSIGNED NOT NULL,
            user_id     BIGINT UNSIGNED NOT NULL,
            comment     TEXT            NOT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY user_id (user_id)
        ) $charset;" );

        update_option( 'cwr_db_version', CWR_DB_VERSION );

        // Seed roles and churches
        CWR_Roles::init();
        self::seed_churches();
    }

    public static function seed_churches() {
        global $wpdb;
        $table = $wpdb->prefix . 'cwr_churches';

        // Only seed if empty
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        if ( $count > 0 ) return;

        $churches = [
            'Abundant Life Chapel, Q.R.S',
            'Ado Ekiti 1 Wonderland, Afao Road',
            'Ado Ekiti 2 Idolofin',
            'Afeki Praise Chapel',
            'Ajebamidele Modomo Gateway',
            'Akure 1',
            'Akure 2',
            'Aladanla',
            'Alajobi/Safejo',
            'Arubidi',
            'Ayibiowu',
            'Benin 1',
            'Benin 2',
            'Benin 3',
            'Camp Ground',
            'Chosen Generation Sanctuary',
            'College Road',
            'Covenant House, Ado',
            'Egbejoda',
            'Eleyele',
            'Falolu',
            'Garage Olode',
            'Glory Chapel',
            'House of Grace, Itakogun',
            'House of Transformation, Arubidi',
            'House of Glory',
            'Ibadan Road',
            'Ifecity',
            'Ifedapo',
            'Ifewara',
            'Igboya',
            'Ijio',
            'Ilode Omitoto',
            'Ilorin',
            'Iloro',
            'Iloromu 1',
            'Iloromu 2',
            'Imo Ilesha',
            'Ipetu Ijesha',
            'Ipetumodu',
            'Iredapo',
            'Iremo',
            'Isolo, Lagos',
            'Kosere',
            'Meiran, Lagos',
            'Modakeke',
            'Moore',
            "My Father's House",
            'OAU',
            'Ojaja',
            'Okeooye',
            'Okeoogbo',
            'Okesookun NTA Road',
            'Okitipupa 1',
            'Okitipupa 2',
            'Olodo, Ibadan',
            'Olomilagbala Ilesha',
            'Olonade',
            'Ondo Road',
            'Ondo Town',
            'Onibuore',
            'Opa',
            'Ore',
            'Orita Challenge, Ibadan',
            'Osogbo',
            'Ita Osun Otutu/Olopo',
            'Owo',
            'Owode Ede',
            "Potter's House, Ibadan Road",
            "Potter's House, Ilode",
            'Royal Priesthood Palace, Iloro',
            'Surulere',
            'Treasure House, Iredapo',
            'Wakajaiye, Ibadan',
            'Medicon Avenue, Ilesa',
            'Modakeke 2',
            'Sanctuary of Transformation, Ibadan',
            'Idi Omo',
            'Akure 3 - Fullness of Time',
            'Idita',
            'Ondo Road - Abojupa',
            'Okinni',
            'Oniyanrin',
        ];

        foreach ( $churches as $name ) {
            $wpdb->insert( $table, [
                'name'       => $name,
                'zone_id'    => null,
                'status'     => 'active',
                'created_at' => current_time( 'mysql' ),
            ] );
        }
    }

    // ---- Utility helpers ----

    public static function get_churches( $zone_id = null ) {
        global $wpdb;
        $t = $wpdb->prefix . 'cwr_churches';
        if ( $zone_id ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $t WHERE zone_id = %d AND status = 'active' ORDER BY name ASC", $zone_id
            ) );
        }
        return $wpdb->get_results( "SELECT * FROM $t WHERE status = 'active' ORDER BY name ASC" );
    }

    public static function get_church( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cwr_churches WHERE id = %d", $id
        ) );
    }

    public static function get_zones() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT z.*, u.display_name as area_pastor_name
             FROM {$wpdb->prefix}cwr_zones z
             LEFT JOIN {$wpdb->users} u ON u.ID = z.area_pastor_id
             ORDER BY z.name ASC"
        );
    }

    public static function get_current_church( $pastor_id ) {
        global $wpdb;
        $pc = $wpdb->prefix . 'cwr_pastor_churches';
        $cc = $wpdb->prefix . 'cwr_churches';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT c.* FROM $cc c
             INNER JOIN $pc pc ON pc.church_id = c.id
             WHERE pc.pastor_id = %d AND pc.is_current = 1
             LIMIT 1", $pastor_id
        ) );
    }

    public static function get_all_churches_for_pastor( $pastor_id ) {
        global $wpdb;
        $pc = $wpdb->prefix . 'cwr_pastor_churches';
        $cc = $wpdb->prefix . 'cwr_churches';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, pc.is_current, pc.assigned_at, pc.removed_at
             FROM $cc c
             INNER JOIN $pc pc ON pc.church_id = c.id
             WHERE pc.pastor_id = %d
             ORDER BY pc.is_current DESC, pc.assigned_at DESC", $pastor_id
        ) );
    }

    public static function get_report( $church_id, $week_start ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cwr_reports
             WHERE church_id = %d AND week_start = %s", $church_id, $week_start
        ) );
    }

    public static function get_reports_for_church( $church_id, $limit = 12 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cwr_reports
             WHERE church_id = %d
             ORDER BY week_start DESC LIMIT %d", $church_id, $limit
        ) );
    }

    /**
     * Get all reports submitted by a specific pastor (by pastor_id)
     * This is the most reliable fetch — works regardless of church assignment state
     */
    public static function get_reports_for_pastor( $pastor_id, $limit = 24 ) {
        global $wpdb;
        $r = $wpdb->prefix . 'cwr_reports';
        $c = $wpdb->prefix . 'cwr_churches';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, c.name as church_name
             FROM $r r
             LEFT JOIN $c c ON c.id = r.church_id
             WHERE r.pastor_id = %d
             ORDER BY r.week_start DESC LIMIT %d",
            $pastor_id, $limit
        ) );
    }

    /**
     * Get a pastor's reports for a specific week
     * Returns array (for API consistency) with 0 or 1 items
     */
    public static function get_reports_for_pastor_week( $pastor_id, $week_start ) {
        global $wpdb;
        $r = $wpdb->prefix . 'cwr_reports';
        $c = $wpdb->prefix . 'cwr_churches';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, c.name as church_name
             FROM $r r
             LEFT JOIN $c c ON c.id = r.church_id
             WHERE r.pastor_id = %d AND r.week_start = %s
             ORDER BY r.submitted_at DESC LIMIT 1",
            $pastor_id, $week_start
        ) );
    }

    public static function get_reports_for_zone( $zone_id, $week_start = null ) {
        global $wpdb;
        $r = $wpdb->prefix . 'cwr_reports';
        $c = $wpdb->prefix . 'cwr_churches';
        $where_week = $week_start ? $wpdb->prepare( "AND r.week_start = %s", $week_start ) : '';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, c.name as church_name
             FROM $r r
             INNER JOIN $c c ON c.id = r.church_id
             WHERE c.zone_id = %d $where_week
             ORDER BY r.week_start DESC", $zone_id
        ) );
    }

    public static function get_all_reports( $week_start = null, $zone_id = null, $church_id = null ) {
        global $wpdb;
        $r  = $wpdb->prefix . 'cwr_reports';
        $c  = $wpdb->prefix . 'cwr_churches';
        $z  = $wpdb->prefix . 'cwr_zones';
        $u  = $wpdb->users;
        $where = [ '1=1' ];
        $args  = [];
        if ( $week_start ) { $where[] = 'r.week_start = %s'; $args[] = $week_start; }
        if ( $zone_id )    { $where[] = 'c.zone_id = %d';   $args[] = $zone_id; }
        if ( $church_id )  { $where[] = 'r.church_id = %d'; $args[] = $church_id; }
        $where_sql = implode( ' AND ', $where );
        $sql = "SELECT r.*, c.name as church_name, z.name as zone_name, u.display_name as pastor_name
                FROM $r r
                INNER JOIN $c c ON c.id = r.church_id
                LEFT JOIN  $z z ON z.id = c.zone_id
                LEFT JOIN  $u u ON u.ID = r.pastor_id
                WHERE $where_sql
                ORDER BY r.week_start DESC, c.name ASC";
        return $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
    }

    public static function get_current_week_start() {
        // Week starts Sunday
        $today = new DateTime( 'now', new DateTimeZone( 'Africa/Lagos' ) );
        $dow   = (int) $today->format( 'w' ); // 0 = Sunday
        $today->modify( "-{$dow} days" );
        return $today->format( 'Y-m-d' );
    }

    public static function get_week_end( $week_start ) {
        $d = new DateTime( $week_start );
        $d->modify( '+6 days' );
        return $d->format( 'Y-m-d' );
    }

    public static function get_weeks_list( $count = 8 ) {
        $weeks = [];
        $start = new DateTime( self::get_current_week_start(), new DateTimeZone( 'Africa/Lagos' ) );
        for ( $i = 0; $i < $count; $i++ ) {
            $s = clone $start;
            $s->modify( "-{$i} weeks" );
            $e = clone $s;
            $e->modify( '+6 days' );
            $weeks[] = [
                'week_start' => $s->format( 'Y-m-d' ),
                'week_end'   => $e->format( 'Y-m-d' ),
                'label'      => $s->format( 'D, d M' ) . ' – ' . $e->format( 'D, d M Y' ),
                'is_current' => $i === 0,
            ];
        }
        return $weeks;
    }

    public static function churches_missing_report( $week_start, $zone_id = null ) {
        global $wpdb;
        $c  = $wpdb->prefix . 'cwr_churches';
        $r  = $wpdb->prefix . 'cwr_reports';
        $pc = $wpdb->prefix . 'cwr_pastor_churches';
        $u  = $wpdb->users;
        $zone_sql = $zone_id ? $wpdb->prepare( 'AND c.zone_id = %d', $zone_id ) : '';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.id, c.name, u.ID as pastor_id, u.display_name as pastor_name, u.user_email
             FROM $c c
             LEFT JOIN $pc pc ON pc.church_id = c.id AND pc.is_current = 1
             LEFT JOIN $u u   ON u.ID = pc.pastor_id
             WHERE c.status = 'active' $zone_sql
               AND c.id NOT IN (
                   SELECT church_id FROM $r WHERE week_start = %s
               )
             ORDER BY c.name ASC", $week_start
        ) );
    }
}
