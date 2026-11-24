<?php
/**
 * BarangayServe configuration (EXAMPLE FILE: contains no real secrets).
 *
 * SETUP
 *   1. Copy this file and name the copy  config/config.php
 *   2. Change the values below (at least the database user and password).
 *   3. Never share config/config.php or put it in a public repository.
 *
 * Any database setting can also come from an environment variable
 * (BS_DB_HOST, BS_DB_PORT, BS_DB_NAME, BS_DB_USER, BS_DB_PASS).
 * An environment variable, when set, is used instead of the value written here.
 */

return [

    // ----- Names shown in the pages --------------------------------------
    'app_name'        => 'BarangayServe',
    'barangay_name'   => 'Barangay Sample',          // <- put your barangay's name here
    'office_location' => 'the barangay hall',        // used in the "ready for pickup" notice
    'office_hours'    => 'Monday to Friday, 8:00 AM to 5:00 PM',

    // ----- General -------------------------------------------------------
    'timezone' => 'Asia/Manila',   // all dates and times are stored and shown in this time zone
    'debug'    => false,           // true = show PHP errors on screen (use ONLY on your own computer)
    'per_page' => 10,              // rows per page in request lists
    'live_refresh_seconds' => 5,   // staff list and dashboard check for new/changed requests this often (2 to 300)

    // Browser security rule that only allows this site's own scripts and styles.
    // Leave it true. Set it to false only if a free web host injects its own
    // scripts into your pages and the site stops working.
    'content_security_policy' => true,

    // Leave empty to detect the web path automatically (works for
    // http://localhost/barangayserve/ and for the built-in PHP server).
    // Only set this if links come out wrong, for example '/barangayserve'.
    'base_url' => '',

    // ----- Database (MySQL or MariaDB) ------------------------------------
    'db' => [
        'host'    => getenv('BS_DB_HOST') ?: '127.0.0.1',
        'port'    => (int) (getenv('BS_DB_PORT') ?: 3306),
        'name'    => getenv('BS_DB_NAME') ?: 'barangayserve',
        'user'    => getenv('BS_DB_USER') ?: 'root',       // XAMPP default user
        'pass'    => getenv('BS_DB_PASS') !== false ? getenv('BS_DB_PASS') : '',   // XAMPP default: empty
        'charset' => 'utf8mb4',
    ],

    // ----- Sessions ----------------------------------------------------
    'session' => [
        'name'                 => 'barangayserve_sid',
        'idle_timeout_minutes' => 30,       // log out after this many minutes without activity
        'cookie_secure'        => 'auto',   // 'auto' = secure cookie when the site uses HTTPS
    ],

    // ----- Log-in protection ---------------------------------------------
    'login' => [
        'max_failed_attempts' => 5,    // wrong passwords allowed ...
        'lock_minutes'        => 15,   // ... within this many minutes, per email + device address
    ],
];
