# ChristWay Church Reporting System
### WordPress Plugin — by Damijoe Digitals
Version 1.0.0

---

## QUICK INSTALL (No cPanel, No FTP, No Composer, No Terminal needed)

### Step 1 — Upload the Plugin
1. Go to **WordPress Admin → Plugins → Add New**
2. Click **"Upload Plugin"** at the top of the page
3. Click **"Choose File"** and select `christway-reports.zip`
4. Click **"Install Now"**
5. Click **"Activate Plugin"**

That's it. The plugin automatically:
- Creates all 7 database tables
- Seeds all 83 churches
- Registers the 3 user roles (Admin, Area Pastor, Pastor)
- Excel and PDF exports work immediately — no libraries needed

---

### Step 2 — Create the Portal Page
1. Go to **Pages → Add New**
2. Title it: **Church Portal** (or any name you like)
3. In the content area, paste this shortcode:
   ```
   [christway_portal]
   ```
4. Publish the page
5. Share the page URL with your pastors

---

### Step 3 — Create Your Admin Account
1. Go to **WP Admin → CW Reports → Create Admin**
2. Fill in name, email and password
3. Click **"Create Admin Account"**
4. Use those credentials to log in to the portal

---

### Step 4 — Create Zones
1. Go to **WP Admin → CW Reports → Manage Zones**
2. Create each zone (e.g. "Osogbo Zone", "Ibadan Zone", "Akure Zone")

---

### Step 5 — Assign Churches to Zones
1. Go to **WP Admin → CW Reports → Churches**
2. Use the dropdowns to assign each of the 83 churches to a zone
3. Click **"Save All Zone Assignments"**

---

### Step 6 — Create Area Pastor Accounts
1. Log in to the portal as Admin
2. Go to **Area Pastors → Add New**
3. Fill in name, email, phone, password and assign to zone

---

### Step 7 — Pastors Self-Register
Share the portal URL with your pastors. They:
1. Click **"Register here"**
2. Enter their name, email, password and select their church
3. Account is marked **Pending**

You approve them at: **WP Admin → CW Reports → Registrations**

---

## HOW IT WORKS

### Weekly Reporting Cycle
- Week runs **Sunday to Saturday** (resets each Sunday)
- Pastors submit reports anytime during the week
- Auto email reminders: **Sunday 6am** and **Wednesday 9am** (WAT)
- Pastors who haven't submitted receive both portal alert + email

### What Each Role Can Do

| Role | Permissions |
|------|-------------|
| **Pastor** | Submit weekly reports, view own church history, retain access to previous churches after transfer |
| **Area Pastor** | View all churches in their zone, see zone summaries, export reports |
| **Admin** | Full access — all reports, all zones, approve registrations, transfer/remove pastors, export Excel & PDF |

### Report Fields
Each weekly report captures:
- Sunday attendance
- Midweek attendance
- Total attendance (auto-calculated)
- Total offering (₦)
- Total tithe (₦)
- Other income (₦)
- Salvations
- First timers
- Notes / remarks

### Pastor Transfers
When a pastor is transferred to a new church:
- They are immediately linked to the new church
- They **retain full read access** to all reports from previous churches
- A transfer log is stored with date, reason and who approved it
- Pastor receives email + portal notification

### Excel Export (.xlsx)
- Opens in Excel, Google Sheets, LibreOffice
- Two sheets: Summary + Full report data
- Striped rows, header formatting, auto column widths
- Filterable columns
- Totals row at the bottom

### PDF Export
- Opens in browser as a print-ready page
- Click the **"Print / Save PDF"** button
- Choose "Save as PDF" in your print dialog
- A4 landscape layout with totals row

---

## FILE STRUCTURE

```
christway-reports/
├── christway-reports.php          ← Plugin bootstrap (activate here)
├── includes/
│   ├── class-database.php         ← DB tables + 83 churches seeded
│   ├── class-roles.php            ← WordPress user roles
│   ├── class-auth.php             ← Register, approve, login
│   ├── class-api.php              ← All REST API endpoints
│   ├── class-notifications.php    ← Email reminders + portal alerts
│   ├── class-xlsx-writer.php      ← Native PHP Excel writer (no composer)
│   ├── class-pdf-writer.php       ← Print-to-PDF HTML generator
│   └── class-export.php           ← Export controller
├── admin/
│   └── admin-page.php             ← WordPress admin dashboard
├── frontend/
│   └── build/
│       └── main.js                ← Compiled React app (ready to use)
└── assets/
    └── css/
        └── portal.css             ← Portal styles
```

---

## REQUIREMENTS
- WordPress 5.8 or higher
- PHP 7.4 or higher (PHP 8.x recommended)
- MySQL 5.7 or higher
- PHP ZipArchive extension (enabled by default on most hosts)

---

## SUPPORT
Built by **Damijoe Digitals** for Christ Way Church Treasure House.
