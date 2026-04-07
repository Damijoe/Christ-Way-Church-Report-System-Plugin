<?php
if ( ! defined( 'ABSPATH' ) ) exit;


// Handle test email action
add_action( 'admin_init', function() {
    if ( ! isset($_POST['cwr_action']) || $_POST['cwr_action'] !== 'send_test_email' ) return;
    if ( ! current_user_can('manage_options') ) return;
    check_admin_referer('cwr_admin_action');
    $to = sanitize_email( $_POST['test_email'] ?? get_option('admin_email') );
    $result = CWR_Notifications::send_test_email( $to );
    if ( $result ) {
        set_transient( 'cwr_notice', [ 'success', "Test email sent to {$to}. Check your inbox." ], 30 );
    } else {
        set_transient( 'cwr_notice', [ 'error', 'Email failed to send. Please configure SMTP below.' ], 30 );
    }
}, 5 );


// Handle clear update cache
add_action( 'admin_init', function() {
    if ( ! isset($_POST['cwr_action']) || $_POST['cwr_action'] !== 'clear_update_cache' ) return;
    if ( ! current_user_can('manage_options') ) return;
    check_admin_referer('cwr_admin_action');
    CWR_Updater::clear_cache();
    set_transient( 'cwr_notice', [ 'success', 'Update cache cleared. WordPress will now check GitHub for the latest version.' ], 30 );
}, 5 );

add_action( 'admin_menu', 'cwr_admin_menu' );
function cwr_admin_menu() {
    add_menu_page(
        'ChristWay Reports',
        'CW Reports',
        'manage_options',
        'christway-reports',
        'cwr_admin_dashboard_page',
        'dashicons-chart-bar',
        30
    );
    add_submenu_page( 'christway-reports', 'Overview',       'Overview',        'manage_options', 'christway-reports',           'cwr_admin_dashboard_page' );
    add_submenu_page( 'christway-reports', 'Registrations',  'Registrations',   'manage_options', 'christway-registrations',     'cwr_admin_registrations_page' );
    add_submenu_page( 'christway-reports', 'Manage Zones',   'Manage Zones',    'manage_options', 'christway-zones',             'cwr_admin_zones_page' );
    add_submenu_page( 'christway-reports', 'Churches',       'Churches',        'manage_options', 'christway-churches',          'cwr_admin_churches_page' );
    add_submenu_page( 'christway-reports', 'Create Admin',   'Create Admin',    'manage_options', 'christway-create-admin',      'cwr_admin_create_admin_page' );
    add_submenu_page( 'christway-reports', 'Setup Guide',    'Setup Guide',     'manage_options', 'christway-setup',             'cwr_admin_setup_page' );
    add_submenu_page( 'christway-reports', 'Email Settings', 'Email Settings', 'manage_options', 'christway-email',             'cwr_admin_email_page' );
    add_submenu_page( 'christway-reports', 'Update Settings', 'Update Settings', 'manage_options', 'christway-updates',           'cwr_admin_updates_page' );
}

add_action( 'admin_enqueue_scripts', 'cwr_admin_styles' );
function cwr_admin_styles( $hook ) {
    if ( strpos( $hook, 'christway' ) === false ) return;
    echo '<style>
        .cwr-wrap { max-width: 1100px; }
        .cwr-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:20px 24px; margin-bottom:20px; }
        .cwr-card h2 { margin-top:0; color:#1a3a5c; font-size:16px; border-bottom:1px solid #eee; padding-bottom:10px; }
        .cwr-stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px; }
        .cwr-stat { background:#f8fafc; border:1px solid #e0e0e0; border-radius:6px; padding:16px; text-align:center; border-left:4px solid #1a3a5c; }
        .cwr-stat-num { font-size:28px; font-weight:700; color:#1a3a5c; }
        .cwr-stat-label { font-size:12px; color:#666; margin-top:4px; }
        .cwr-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .cwr-badge-pending  { background:#faeeda; color:#854f0b; }
        .cwr-badge-approved { background:#eaf3de; color:#3b6d11; }
        .cwr-badge-rejected { background:#fcebeb; color:#a32d2d; }
        .cwr-table { width:100%; border-collapse:collapse; font-size:13px; }
        .cwr-table th { background:#1a3a5c; color:#fff; padding:8px 12px; text-align:left; }
        .cwr-table td { padding:8px 12px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        .cwr-table tr:hover td { background:#f8fafc; }
        .cwr-btn { display:inline-block; padding:6px 14px; border-radius:4px; border:none; cursor:pointer; font-size:13px; text-decoration:none; }
        .cwr-btn-primary { background:#1a3a5c; color:#fff; }
        .cwr-btn-success { background:#3b6d11; color:#fff; }
        .cwr-btn-danger  { background:#a32d2d; color:#fff; }
        .cwr-btn-sm { padding:4px 10px; font-size:12px; }
        .cwr-notice { padding:10px 14px; border-radius:4px; margin-bottom:16px; font-size:13px; }
        .cwr-notice-success { background:#eaf3de; color:#3b6d11; border:1px solid #c0dd97; }
        .cwr-notice-error   { background:#fcebeb; color:#a32d2d; border:1px solid #f09595; }
    </style>';
}

// Handle admin actions
function cwr_handle_admin_action() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! isset( $_POST['cwr_action'] ) ) return;
    check_admin_referer( 'cwr_admin_action' );

    $action = $_POST['cwr_action'];

    if ( $action === 'approve_reg' ) {
        $id = absint( $_POST['reg_id'] );
        $result = CWR_Auth::approve_registration( $id, get_current_user_id() );
        if ( is_wp_error( $result ) ) {
            set_transient( 'cwr_notice', [ 'error', $result->get_error_message() ], 30 );
        } else {
            set_transient( 'cwr_notice', [ 'success', 'Pastor approved and account created.' ], 30 );
        }
    }

    if ( $action === 'reject_reg' ) {
        $id     = absint( $_POST['reg_id'] );
        $reason = sanitize_textarea_field( $_POST['reason'] ?? '' );
        CWR_Auth::reject_registration( $id, get_current_user_id(), $reason );
        set_transient( 'cwr_notice', [ 'success', 'Registration rejected.' ], 30 );
    }

    if ( $action === 'create_zone' ) {
        global $wpdb;
        $name = sanitize_text_field( $_POST['zone_name'] );
        if ( $name ) {
            $wpdb->insert( $wpdb->prefix . 'cwr_zones', [ 'name' => $name, 'created_at' => current_time('mysql') ] );
            set_transient( 'cwr_notice', [ 'success', 'Zone created: ' . $name ], 30 );
        }
    }

    if ( $action === 'assign_church_zone' ) {
        global $wpdb;
        $church_id = absint( $_POST['church_id'] );
        $zone_id   = absint( $_POST['zone_id'] );
        $wpdb->update( $wpdb->prefix . 'cwr_churches', [ 'zone_id' => $zone_id ?: null ], [ 'id' => $church_id ] );
        set_transient( 'cwr_notice', [ 'success', 'Church zone updated.' ], 30 );
    }

    if ( $action === 'create_admin_user' ) {
        $name     = sanitize_text_field( $_POST['admin_name'] );
        $email    = sanitize_email( $_POST['admin_email'] );
        $password = $_POST['admin_password'];
        if ( $name && $email && $password ) {
            if ( email_exists( $email ) ) {
                set_transient( 'cwr_notice', [ 'error', 'Email already in use.' ], 30 );
            } else {
                $user_id = wp_create_user( sanitize_user( strtolower( str_replace(' ', '.', $name) ) ), $password, $email );
                if ( ! is_wp_error( $user_id ) ) {
                    wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
                    CWR_Roles::assign_role( $user_id, 'cwr_admin' );
                    set_transient( 'cwr_notice', [ 'success', "Admin account created for {$name}." ], 30 );
                } else {
                    set_transient( 'cwr_notice', [ 'error', $user_id->get_error_message() ], 30 );
                }
            }
        }
    }
}
add_action( 'admin_init', 'cwr_handle_admin_action' );

function cwr_show_notice() {
    $n = get_transient( 'cwr_notice' );
    if ( ! $n ) return;
    delete_transient( 'cwr_notice' );
    $cls = $n[0] === 'success' ? 'cwr-notice-success' : 'cwr-notice-error';
    echo "<div class='cwr-notice {$cls}'>{$n[1]}</div>";
}

function cwr_admin_dashboard_page() {
    global $wpdb;
    $total_churches  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cwr_churches WHERE status='active'" );
    $total_zones     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cwr_zones" );
    $total_pastors   = count( get_users( [ 'role' => 'cwr_pastor' ] ) );
    $pending_regs    = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cwr_registrations WHERE status='pending'" );
    $week_start      = CWR_Database::get_current_week_start();
    $total_reports   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cwr_reports WHERE week_start = %s", $week_start ) );
    $total_offering  = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_offering) FROM {$wpdb->prefix}cwr_reports WHERE week_start = %s", $week_start ) );
    $total_tithe     = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_tithe) FROM {$wpdb->prefix}cwr_reports WHERE week_start = %s", $week_start ) );
    $total_att       = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_attendance) FROM {$wpdb->prefix}cwr_reports WHERE week_start = %s", $week_start ) );
    ?>
    <div class="wrap cwr-wrap">
        <h1 style="color:#1a3a5c">ChristWay Reports — Admin Overview</h1>
        <?php cwr_show_notice(); ?>
        <div class="cwr-stat-grid">
            <div class="cwr-stat"><div class="cwr-stat-num"><?php echo $total_churches; ?></div><div class="cwr-stat-label">Total Churches</div></div>
            <div class="cwr-stat"><div class="cwr-stat-num"><?php echo $total_zones; ?></div><div class="cwr-stat-label">Zones</div></div>
            <div class="cwr-stat"><div class="cwr-stat-num"><?php echo $total_pastors; ?></div><div class="cwr-stat-label">Active Pastors</div></div>
            <div class="cwr-stat" style="border-left-color:<?php echo $pending_regs > 0 ? '#854f0b' : '#3b6d11'; ?>">
                <div class="cwr-stat-num"><?php echo $pending_regs; ?></div>
                <div class="cwr-stat-label">Pending Approvals</div>
            </div>
        </div>
        <div class="cwr-card">
            <h2>This Week (<?php echo $week_start; ?>)</h2>
            <div class="cwr-stat-grid" style="grid-template-columns:repeat(4,1fr)">
                <div class="cwr-stat"><div class="cwr-stat-num"><?php echo $total_reports; ?> / <?php echo $total_churches; ?></div><div class="cwr-stat-label">Reports Submitted</div></div>
                <div class="cwr-stat"><div class="cwr-stat-num"><?php echo number_format((int)$total_att); ?></div><div class="cwr-stat-label">Total Attendance</div></div>
                <div class="cwr-stat"><div class="cwr-stat-num">₦<?php echo number_format((float)$total_offering, 0); ?></div><div class="cwr-stat-label">Total Offering</div></div>
                <div class="cwr-stat"><div class="cwr-stat-num">₦<?php echo number_format((float)$total_tithe, 0); ?></div><div class="cwr-stat-label">Total Tithe</div></div>
            </div>
        </div>
        <div class="cwr-card">
            <h2>Quick Links</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=christway-registrations'); ?>" class="cwr-btn cwr-btn-primary">Approve Registrations <?php if($pending_regs) echo "($pending_regs pending)"; ?></a>
                &nbsp;
                <a href="<?php echo admin_url('admin.php?page=christway-zones'); ?>" class="cwr-btn cwr-btn-primary">Manage Zones</a>
                &nbsp;
                <a href="<?php echo admin_url('admin.php?page=christway-churches'); ?>" class="cwr-btn cwr-btn-primary">Assign Churches to Zones</a>
                &nbsp;
                <a href="<?php echo site_url('/?page=christway-portal'); ?>" class="cwr-btn" style="background:#666;color:#fff" target="_blank">Open Portal</a>
            </p>
        </div>
    </div>
    <?php
}

function cwr_admin_registrations_page() {
    global $wpdb;
    $status   = sanitize_text_field( $_GET['status'] ?? 'pending' );
    $reg_t    = $wpdb->prefix . 'cwr_registrations';
    $ch_t     = $wpdb->prefix . 'cwr_churches';
    $regs     = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.*, c.name as church_name FROM $reg_t r LEFT JOIN $ch_t c ON c.id = r.church_id WHERE r.status = %s ORDER BY r.created_at DESC", $status
    ) );
    ?>
    <div class="wrap cwr-wrap">
        <h1>Pastor Registrations</h1>
        <?php cwr_show_notice(); ?>
        <p>
            <a href="?page=christway-registrations&status=pending" class="cwr-btn <?php echo $status==='pending'?'cwr-btn-primary':''; ?>">Pending</a>
            <a href="?page=christway-registrations&status=approved" class="cwr-btn <?php echo $status==='approved'?'cwr-btn-primary':''; ?>">Approved</a>
            <a href="?page=christway-registrations&status=rejected" class="cwr-btn <?php echo $status==='rejected'?'cwr-btn-primary':''; ?>">Rejected</a>
        </p>
        <div class="cwr-card">
            <?php if ( empty($regs) ): ?>
                <p style="color:#666">No <?php echo $status; ?> registrations.</p>
            <?php else: ?>
            <table class="cwr-table">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Church</th><th>Registered</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ( $regs as $r ): ?>
                <tr>
                    <td><?php echo esc_html($r->first_name . ' ' . $r->last_name); ?></td>
                    <td><?php echo esc_html($r->email); ?></td>
                    <td><?php echo esc_html($r->phone); ?></td>
                    <td><?php echo esc_html($r->church_name); ?></td>
                    <td><?php echo esc_html(date('d M Y', strtotime($r->created_at))); ?></td>
                    <td><span class="cwr-badge cwr-badge-<?php echo $r->status; ?>"><?php echo ucfirst($r->status); ?></span></td>
                    <td>
                        <?php if ( $r->status === 'pending' ): ?>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('cwr_admin_action'); ?>
                            <input type="hidden" name="cwr_action" value="approve_reg">
                            <input type="hidden" name="reg_id" value="<?php echo $r->id; ?>">
                            <button class="cwr-btn cwr-btn-success cwr-btn-sm">Approve</button>
                        </form>
                        <form method="post" style="display:inline" onsubmit="return confirm('Reject this registration?')">
                            <?php wp_nonce_field('cwr_admin_action'); ?>
                            <input type="hidden" name="cwr_action" value="reject_reg">
                            <input type="hidden" name="reg_id" value="<?php echo $r->id; ?>">
                            <button class="cwr-btn cwr-btn-danger cwr-btn-sm">Reject</button>
                        </form>
                        <?php else: echo '—'; endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function cwr_admin_zones_page() {
    global $wpdb;
    $zones    = CWR_Database::get_zones();
    $ap_users = get_users( [ 'role' => 'cwr_area_pastor' ] );
    ?>
    <div class="wrap cwr-wrap">
        <h1>Manage Zones</h1>
        <?php cwr_show_notice(); ?>
        <div class="cwr-card">
            <h2>Create New Zone</h2>
            <form method="post">
                <?php wp_nonce_field('cwr_admin_action'); ?>
                <input type="hidden" name="cwr_action" value="create_zone">
                <table class="form-table"><tr>
                    <th><label for="zone_name">Zone Name</label></th>
                    <td><input type="text" name="zone_name" id="zone_name" class="regular-text" placeholder="e.g. Osogbo Zone" required></td>
                </tr></table>
                <p><button class="cwr-btn cwr-btn-primary">Create Zone</button></p>
            </form>
        </div>
        <div class="cwr-card">
            <h2>All Zones (<?php echo count($zones); ?>)</h2>
            <?php if ( empty($zones) ): ?>
                <p style="color:#666">No zones created yet.</p>
            <?php else: ?>
            <table class="cwr-table">
                <thead><tr><th>Zone</th><th>Area Pastor</th><th>Churches</th></tr></thead>
                <tbody>
                <?php foreach ( $zones as $z ):
                    $church_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cwr_churches WHERE zone_id = %d", $z->id ) );
                ?>
                <tr>
                    <td><strong><?php echo esc_html($z->name); ?></strong></td>
                    <td><?php echo $z->area_pastor_name ? esc_html($z->area_pastor_name) : '<span style="color:#999">Not assigned</span>'; ?></td>
                    <td><?php echo $church_count; ?> churches</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function cwr_admin_churches_page() {
    global $wpdb;
    $zones    = CWR_Database::get_zones();
    $churches = CWR_Database::get_churches();
    ?>
    <div class="wrap cwr-wrap">
        <h1>Assign Churches to Zones</h1>
        <?php cwr_show_notice(); ?>
        <?php if ( empty($zones) ): ?>
            <div class="cwr-card"><p>You need to <a href="<?php echo admin_url('admin.php?page=christway-zones'); ?>">create zones</a> before you can assign churches.</p></div>
        <?php else: ?>
        <div class="cwr-card">
            <p style="color:#666;font-size:13px">Select a zone for each church. Leave as "Unassigned" if not yet categorised.</p>
            <form method="post">
                <?php wp_nonce_field('cwr_admin_action'); ?>
                <input type="hidden" name="cwr_action" value="assign_church_zone">
                <table class="cwr-table">
                    <thead><tr><th>#</th><th>Church</th><th>Current Zone</th><th>Assign Zone</th></tr></thead>
                    <tbody>
                    <?php foreach ( $churches as $i => $c ):
                        $current_zone = $c->zone_id ? $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}cwr_zones WHERE id=%d",$c->zone_id)) : '';
                    ?>
                    <tr>
                        <td style="color:#999"><?php echo $i+1; ?></td>
                        <td><?php echo esc_html($c->name); ?></td>
                        <td><?php echo $current_zone ? esc_html($current_zone) : '<span style="color:#999">Unassigned</span>'; ?></td>
                        <td>
                            <select name="zone_assignments[<?php echo $c->id; ?>]" style="font-size:13px">
                                <option value="">— Unassigned —</option>
                                <?php foreach ( $zones as $z ): ?>
                                <option value="<?php echo $z->id; ?>" <?php selected($c->zone_id, $z->id); ?>><?php echo esc_html($z->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:16px"><button class="cwr-btn cwr-btn-primary">Save All Zone Assignments</button></p>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
// Override the form handler for bulk church assignments
add_action( 'admin_init', function() {
    if ( ! isset($_POST['cwr_action']) || $_POST['cwr_action'] !== 'assign_church_zone' ) return;
    if ( ! current_user_can('manage_options') ) return;
    check_admin_referer('cwr_admin_action');
    global $wpdb;
    $assignments = $_POST['zone_assignments'] ?? [];
    foreach ( $assignments as $church_id => $zone_id ) {
        $wpdb->update( $wpdb->prefix . 'cwr_churches',
            [ 'zone_id' => $zone_id ? absint($zone_id) : null ],
            [ 'id' => absint($church_id) ]
        );
    }
    set_transient( 'cwr_notice', [ 'success', 'Zone assignments saved.' ], 30 );
}, 5 );

function cwr_admin_create_admin_page() { ?>
    <div class="wrap cwr-wrap">
        <h1>Create Admin / Area Pastor Account</h1>
        <?php cwr_show_notice(); ?>
        <div class="cwr-card">
            <h2>Create CWR Admin</h2>
            <form method="post">
                <?php wp_nonce_field('cwr_admin_action'); ?>
                <input type="hidden" name="cwr_action" value="create_admin_user">
                <table class="form-table">
                    <tr><th><label>Full Name</label></th><td><input type="text" name="admin_name" class="regular-text" required></td></tr>
                    <tr><th><label>Email</label></th><td><input type="email" name="admin_email" class="regular-text" required></td></tr>
                    <tr><th><label>Password</label></th><td><input type="password" name="admin_password" class="regular-text" required minlength="8"></td></tr>
                </table>
                <p><button class="cwr-btn cwr-btn-primary">Create Admin Account</button></p>
            </form>
        </div>
        <div class="cwr-card">
            <h2>Create Area Pastor (via Portal API)</h2>
            <p style="color:#666;font-size:13px">Area Pastor accounts are best created via the portal's Admin dashboard, where you can also assign them to a zone in one step. Log in to the portal as Admin and go to <strong>Manage &rsaquo; Area Pastors</strong>.</p>
        </div>
    </div>
<?php }

function cwr_admin_setup_page() { ?>
    <div class="wrap cwr-wrap">
        <h1>Setup Guide</h1>
        <div class="cwr-card">
            <h2>Step 1 — Install Composer Dependencies</h2>
            <p>In your terminal, navigate to the plugin folder and run:</p>
            <code style="display:block;background:#f5f5f5;padding:10px;border-radius:4px">
                cd wp-content/plugins/christway-reports<br>
                composer require phpoffice/phpspreadsheet dompdf/dompdf
            </code>
            <p style="color:#666;font-size:12px">This installs the Excel and PDF export libraries.</p>
        </div>
        <div class="cwr-card">
            <h2>Step 2 — Create a Portal Page</h2>
            <ol style="line-height:2">
                <li>Go to <strong>Pages → Add New</strong></li>
                <li>Title it <em>Church Portal</em> (or any name you prefer)</li>
                <li>In the content area, add the shortcode: <code>[christway_portal]</code></li>
                <li>Publish the page</li>
                <li>Share the page URL with your pastors for login and registration</li>
            </ol>
        </div>
        <div class="cwr-card">
            <h2>Step 3 — Create Zones</h2>
            <p>Go to <strong>CW Reports → Manage Zones</strong> and create your zones (e.g. "Osogbo Zone", "Ibadan Zone").</p>
        </div>
        <div class="cwr-card">
            <h2>Step 4 — Assign Churches to Zones</h2>
            <p>Go to <strong>CW Reports → Churches</strong> and assign each of the 83 churches to a zone.</p>
        </div>
        <div class="cwr-card">
            <h2>Step 5 — Create Area Pastor Accounts</h2>
            <p>Log in to the portal as Admin and go to <strong>Manage → Area Pastors → Add New</strong>. Assign each area pastor to their zone.</p>
        </div>
        <div class="cwr-card">
            <h2>Step 6 — Pastors Self-Register</h2>
            <p>Share the portal URL with your pastors. They register using their name, email, password and select their church. You approve them under <strong>CW Reports → Registrations</strong>.</p>
        </div>
        <div class="cwr-card" style="border-left:4px solid #3b6d11;">
            <h2>You're Done!</h2>
            <p>Once approved, pastors can log in, submit weekly reports (Sunday–Saturday cycle), view their history, and receive email reminders. Area pastors see their zone. You see everything and can export to Excel or PDF.</p>
        </div>
    </div>
<?php }


function cwr_admin_email_page() { ?>
    <div class="wrap cwr-wrap">
        <h1>Email Settings & SMTP Setup</h1>
        <?php cwr_show_notice(); ?>
        <div class="cwr-card">
            <h2>Send Test Email</h2>
            <p style="color:#666;font-size:13px">Send a test email to verify your email configuration is working.</p>
            <form method="post">
                <?php wp_nonce_field('cwr_admin_action'); ?>
                <input type="hidden" name="cwr_action" value="send_test_email">
                <table class="form-table">
                    <tr><th><label>Send test to</label></th>
                    <td><input type="email" name="test_email" class="regular-text" value="<?php echo esc_attr(get_option('admin_email')); ?>"></td></tr>
                </table>
                <p><button class="cwr-btn cwr-btn-primary">Send Test Email</button></p>
            </form>
        </div>
        <div class="cwr-card">
            <h2>Fix Email Not Sending — Install WP Mail SMTP</h2>
            <p style="font-size:13px;color:#666">WordPress's default email often fails on shared hosting. The easiest fix is the free <strong>WP Mail SMTP</strong> plugin:</p>
            <ol style="line-height:2.2;font-size:13px">
                <li>Go to <strong>Plugins &rarr; Add New</strong> and search for <strong>"WP Mail SMTP"</strong></li>
                <li>Install and activate it</li>
                <li>Go to <strong>WP Mail SMTP &rarr; Settings</strong></li>
                <li>Choose your mailer. Recommended options:
                    <ul style="margin-left:20px;margin-top:6px">
                        <li><strong>Gmail / Google Workspace</strong> &mdash; best if you have a Gmail account</li>
                        <li><strong>Brevo (Sendinblue)</strong> &mdash; free 300 emails/day, easy setup</li>
                        <li><strong>Your hosting SMTP</strong> &mdash; ask your host for SMTP credentials</li>
                    </ul>
                </li>
                <li>Fill in your SMTP credentials and save</li>
                <li>Use the <strong>Email Test</strong> tab inside WP Mail SMTP to verify</li>
                <li>Come back here and click <strong>Send Test Email</strong> above to confirm</li>
            </ol>
        </div>
        <div class="cwr-card" style="border-left:4px solid #3b6d11">
            <h2>Free SMTP Option: Brevo</h2>
            <p style="font-size:13px">
                1. Create a free account at <strong>brevo.com</strong><br>
                2. Go to <strong>SMTP &amp; API</strong> &rarr; <strong>SMTP</strong><br>
                3. Copy your SMTP credentials into WP Mail SMTP<br>
                &nbsp;&nbsp;&nbsp;Host: <code>smtp-relay.brevo.com</code> &nbsp; Port: <code>587</code> &nbsp; Encryption: <code>TLS</code><br>
                4. Free plan gives 300 emails/day &mdash; more than enough
            </p>
        </div>
    </div>
<?php }


function cwr_admin_updates_page() {
    $repo = CWR_Updater::GITHUB_REPO;
    $configured = $repo !== 'YOUR-USERNAME/christway-reports';
    ?>
    <div class="wrap cwr-wrap">
        <h1>Update Settings</h1>
        <?php cwr_show_notice(); ?>
        <div class="cwr-card" style="border-left:4px solid <?php echo $configured ? '#3b6d11' : '#854f0b'; ?>">
            <h2>GitHub Repository Status</h2>
            <?php if ( $configured ): ?>
                <p style="color:#3b6d11;font-size:13px">
                    Connected to: <strong><a href="https://github.com/<?php echo esc_html($repo); ?>" target="_blank"><?php echo esc_html($repo); ?></a></strong>
                </p>
                <p style="font-size:13px;color:#666;margin-top:8px">
                    Current version: <strong><?php echo CWR_VERSION; ?></strong> &nbsp;&middot;&nbsp;
                    WordPress will check for updates every 12 hours automatically.
                </p>
            <?php else: ?>
                <p style="color:#854f0b;font-size:13px">
                    <strong>Not configured yet.</strong> Follow the steps below to set up GitHub updates.
                </p>
            <?php endif; ?>
        </div>
        <?php if ( ! $configured ): ?>
        <div class="cwr-card">
            <h2>Step 1 &mdash; Create a GitHub Repository</h2>
            <ol style="line-height:2.2;font-size:13px">
                <li>Go to <a href="https://github.com" target="_blank">github.com</a> and create a free account if you don&rsquo;t have one</li>
                <li>Click <strong>New repository</strong></li>
                <li>Name it <code>christway-reports</code></li>
                <li>Set it to <strong>Private</strong> (so only you can see it)</li>
                <li>Click <strong>Create repository</strong></li>
            </ol>
        </div>
        <div class="cwr-card">
            <h2>Step 2 &mdash; Upload the Plugin</h2>
            <ol style="line-height:2.2;font-size:13px">
                <li>Download the plugin zip from wherever you got it</li>
                <li>On your new GitHub repo page, click <strong>uploading an existing file</strong></li>
                <li>Drag and drop all the plugin files (not the zip &mdash; the actual folder contents)</li>
                <li>Click <strong>Commit changes</strong></li>
            </ol>
        </div>
        <div class="cwr-card">
            <h2>Step 3 &mdash; Set Your Repo Name in the Plugin</h2>
            <ol style="line-height:2.2;font-size:13px">
                <li>Open this file on your server:<br><code>wp-content/plugins/christway-reports/includes/class-updater.php</code></li>
                <li>Find this line near the top:<br><code>const GITHUB_REPO = 'YOUR-USERNAME/christway-reports';</code></li>
                <li>Replace <code>YOUR-USERNAME</code> with your actual GitHub username<br>
                    Example: <code>const GITHUB_REPO = 'josepholatomi/christway-reports';</code></li>
                <li>Save the file</li>
                <li>Refresh this page &mdash; the status above will turn green</li>
            </ol>
        </div>
        <?php endif; ?>
        <div class="cwr-card">
            <h2>How to Publish an Update</h2>
            <ol style="line-height:2.2;font-size:13px">
                <li>Get the new plugin zip (e.g. from Damijoe Digitals)</li>
                <li>Go to your GitHub repository</li>
                <li>Upload/replace the changed files and commit</li>
                <li>Click <strong>Releases &rarr; Create a new release</strong></li>
                <li>Set the tag to the new version number, e.g. <code>v1.0.2</code></li>
                <li>Attach the plugin <code>.zip</code> file as a release asset</li>
                <li>Click <strong>Publish release</strong></li>
                <li>Click <strong>Force Check Now</strong> below to make WordPress detect it immediately</li>
                <li>Go to <strong>Plugins</strong> &mdash; you will see <em>Update available</em> on ChristWay Reports</li>
                <li>Click <strong>Update</strong> &mdash; done. All data is preserved.</li>
            </ol>
        </div>
        <div class="cwr-card">
            <h2>Force Check for Updates Now</h2>
            <p style="font-size:13px;color:#666;margin-bottom:12px">
                WordPress checks for updates every 12 hours automatically. Use this to check immediately after publishing a new GitHub release.
            </p>
            <form method="post" style="display:inline">
                <?php wp_nonce_field('cwr_admin_action'); ?>
                <input type="hidden" name="cwr_action" value="clear_update_cache">
                <button class="cwr-btn cwr-btn-primary">Force Check Now</button>
            </form>
            <p style="font-size:12px;color:#999;margin-top:8px">After clicking, go to <a href="<?php echo admin_url('plugins.php'); ?>">Plugins page</a> to see if an update is available.</p>
        </div>
    </div>
    <?php
}
