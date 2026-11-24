<?php
/**
 * layout.php: the page shell (header, navigation, messages) and error pages.
 *
 * Every page follows this pattern:
 *
 *     require_once __DIR__ . '/../includes/bootstrap.php';
 *     $user = require_resident();                      // who may open this page?
 *     ... handle forms, read the database ...
 *     page_start('Dashboard', ['area' => 'resident', 'active' => 'dashboard']);
 *     ... print the page content ...
 *     page_end();
 *
 * Options for page_start():
 *     area        'public' | 'resident' | 'staff' | 'error'   (decides the navigation bar)
 *     active      which navigation link is highlighted
 *     body_class  extra CSS class for <body>
 *     description text for the <meta description>
 *     live_refresh true = the page reloads itself when a request changes (staff pages only)
 *     live_version  latest_history_id() read BEFORE the page's own queries (used with live_refresh)
 */

function app_name(): string
{
    return (string) config('app_name', 'BarangayServe');
}

function barangay_name(): string
{
    return (string) config('barangay_name', 'Barangay Sample');
}

// ---------------------------------------------------------------------------
// Start and end of every page
// ---------------------------------------------------------------------------

function page_start(string $title, array $options = []): void
{
    $area        = (string) ($options['area'] ?? 'public');
    $active      = (string) ($options['active'] ?? '');
    $bodyClass   = trim('app-body area-' . $area . ' ' . (string) ($options['body_class'] ?? ''));
    $description = (string) ($options['description']
        ?? 'Submit barangay service requests online and follow their progress from your phone or computer.');
    $fullTitle   = $title === '' ? app_name() : $title . ' | ' . app_name();
    $themeColor  = '#123d34';

    echo '<!doctype html>' . "\n";
    echo '<html lang="en">' . "\n<head>\n";
    echo '<meta charset="utf-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<title>' . e($fullTitle) . "</title>\n";
    echo '<meta name="description" content="' . e($description) . '">' . "\n";
    echo '<meta name="theme-color" content="' . e($themeColor) . '">' . "\n";
    echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    echo '<link rel="icon" type="image/svg+xml" href="' . e(asset('img/favicon.svg')) . '">' . "\n";
    echo '<link rel="preload" as="font" type="font/woff2" crossorigin href="'
        . e(asset('vendor/fonts/atkinson-hyperlegible-next-latin-wght-normal.woff2')) . '">' . "\n";
    echo '<link rel="preload" as="font" type="font/woff2" crossorigin href="'
        . e(asset('vendor/fonts/literata-latin-wght-normal.woff2')) . '">' . "\n";
    echo '<link rel="stylesheet" href="' . e(asset('vendor/bootstrap/css/bootstrap.min.css')) . '">' . "\n";
    echo '<link rel="stylesheet" href="' . e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) . '">' . "\n";
    echo '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '">' . "\n";
    echo '</head>' . "\n";
    echo '<body class="' . e($bodyClass) . '">' . "\n";
    echo '<a class="skip-link" href="#main">Skip to main content</a>' . "\n";

    render_navbar($area, $active);

    // Staff pages that reload themselves when a request changes (see assets/js/app.js, section 8).
    $live = '';
    if (!empty($options['live_refresh'])) {
        $seconds = min(300, max(2, (int) config('live_refresh_seconds', 5)));
        $live = ' data-live-refresh="' . e(url('staff/check_new.php')) . '"'
            . ' data-live-version="' . (int) ($options['live_version'] ?? 0) . '"'
            . ' data-live-seconds="' . $seconds . '"';
    }
    echo '<main id="main" class="app-main container" tabindex="-1"' . $live . '>' . "\n";
    render_topbar($area, $active);
    render_flash_messages();
}

function page_end(): void
{
    echo "</main>\n";
    echo '<script src="' . e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) . '"></script>' . "\n";
    echo '<script src="' . e(asset('js/app.js')) . '"></script>' . "\n";
    echo "</body>\n</html>\n";
}

// ---------------------------------------------------------------------------
// Navigation bar
// ---------------------------------------------------------------------------

function nav_link(string $path, string $label, string $icon, string $key, string $active): string
{
    $isActive = ($key === $active);
    return '<li class="nav-item"><a class="nav-link' . ($isActive ? ' active' : '') . '"'
        . ($isActive ? ' aria-current="page"' : '')
        . ' href="' . e(url($path)) . '">'
        . ($icon !== '' ? '<i class="bi ' . e($icon) . ' nav-link-icon" aria-hidden="true"></i>' : '')
        . '<span class="nav-link-label">' . e($label) . '</span></a></li>';
}

/**
 * The slim bar above the page content in the resident and staff areas.
 * The account menu lives here (top right) so it always opens downward, in plain view.
 */
function render_topbar(string $area, string $active): void
{
    if (!in_array($area, ['resident', 'staff'], true)) {
        return;
    }
    $user = current_user();
    if ($user === null) {
        return;
    }
    $fullName  = trim((string) $user['full_name']);
    $firstName = explode(' ', $fullName)[0];
    $initial   = function_exists('mb_substr') ? mb_strtoupper(mb_substr($firstName, 0, 1)) : strtoupper(substr($firstName, 0, 1));

    echo '<div class="app-topbar">';
    echo '<div class="dropdown">';
    echo '<button class="account-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for ' . e($fullName) . '">';
    echo '<span class="account-avatar" aria-hidden="true">' . e($initial) . '</span>';
    echo '<span class="account-name">' . e($firstName) . '</span>';
    echo '<i class="bi bi-chevron-down account-caret" aria-hidden="true"></i>';
    echo '</button>';
    echo '<ul class="dropdown-menu dropdown-menu-end account-menu">';
    echo '<li class="account-who"><strong>' . e($fullName) . '</strong><span>' . e($user['email']) . '</span></li>';
    echo '<li><hr class="dropdown-divider"></li>';
    echo '<li><a class="dropdown-item' . ($active === 'account' ? ' active' : '') . '" href="' . e(url('account.php')) . '">'
        . '<i class="bi bi-person" aria-hidden="true"></i>My account</a></li>';
    echo '<li><form method="post" action="' . e(url('logout.php')) . '">' . csrf_field()
        . '<button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Log out</button></form></li>';
    echo '</ul></div></div>' . "\n";
}

function render_navbar(string $area, string $active): void
{
    $isStaff = ($area === 'staff');
    $user    = in_array($area, ['resident', 'staff'], true) ? current_user() : null;

    $homePath = 'index.php';
    if ($user !== null) {
        $homePath = dashboard_path($user['role']);
    }

    echo '<header class="app-header app-header-' . e($area) . '">';
    echo '<nav class="navbar navbar-expand-lg app-navbar app-navbar-' . e($area) . '" aria-label="Main navigation">';
    echo '<div class="container">';

    // Brand
    echo '<a class="navbar-brand" href="' . e(url($homePath)) . '">';
    echo '<img src="' . e(asset('img/logo.svg')) . '" alt="" width="44" height="44">';
    echo '<span class="brand-text"><span class="brand-name">' . e(app_name()) . '</span>';
    echo '<span class="brand-sub">' . e($isStaff ? 'Staff, ' . barangay_name() : barangay_name()) . '</span></span>';
    echo '</a>';

    if ($area === 'error') {
        echo '</div></nav></header>';
        return;
    }

    echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"'
        . ' aria-controls="mainNav" aria-expanded="false" aria-label="Show or hide the menu">'
        . '<span class="navbar-toggler-icon"></span></button>';
    echo '<div class="collapse navbar-collapse" id="mainNav">';

    // Links
    echo '<ul class="navbar-nav me-auto mb-2 mb-lg-0">';
    if ($area === 'resident') {
        echo nav_link('resident/dashboard.php', 'Dashboard', 'bi-house-door', 'dashboard', $active);
        echo nav_link('resident/services.php', 'Request a service', 'bi-plus-circle', 'services', $active);
        echo nav_link('resident/requests.php', 'My requests', 'bi-list-ul', 'requests', $active);
    } elseif ($isStaff) {
        echo nav_link('staff/dashboard.php', 'Dashboard', 'bi-house-door', 'dashboard', $active);
        echo nav_link('staff/requests.php', 'Requests', 'bi-list-ul', 'requests', $active);
        echo nav_link('staff/services.php', 'Services', 'bi-card-checklist', 'services', $active);
    }
    echo '</ul>';

    // Right side: log-in links for visitors. Signed-in users get their account menu in the top bar.
    if ($user === null) {
        echo '<div class="d-flex flex-wrap gap-2 nav-actions">';
        echo '<a class="btn btn-outline-primary" href="' . e(url('login.php')) . '">Log in</a>';
        echo '<a class="btn btn-primary" href="' . e(url('register.php')) . '">Create account</a>';
        echo '</div>';
    }

    echo '</div></div></nav></header>' . "\n";
}

// ---------------------------------------------------------------------------
// Messages (flash)
// ---------------------------------------------------------------------------

/** Show the messages saved with flash() by the previous page. */
function render_flash_messages(): void
{
    $types = ['success', 'danger', 'warning', 'info'];
    $messages = flash_take();
    if (!$messages) {
        return;
    }
    echo '<div class="flash-area">';
    foreach ($messages as $message) {
        $type = in_array($message['type'], $types, true) ? $message['type'] : 'info';
        $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';
        echo '<div class="alert alert-' . e($type) . ' alert-dismissible alert-mark" role="' . $role . '">';
        echo '<div>' . e($message['message']) . '</div>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close this message"></button>';
        echo '</div>';
    }
    echo '</div>';
}

// ---------------------------------------------------------------------------
// Error pages (403, 404, 500). They never show technical details to residents.
// ---------------------------------------------------------------------------

/**
 * Show a friendly error page. $debug (an exception) is shown ONLY when 'debug' is
 * true in config/config.php, which should be the case on your own computer only.
 */
function show_error_page(int $code, string $title, string $message, ?Throwable $debug = null, ?string $backPath = null, ?string $backLabel = null): void
{
    page_start($title, ['area' => 'error']);
    echo '<section class="error-page" aria-labelledby="error-title">';
    echo '<p class="error-code" aria-hidden="true">' . (int) $code . '</p>';
    echo '<h1 id="error-title">' . e($title) . '</h1>';
    echo '<p class="lead">' . e($message) . '</p>';
    echo '<a class="btn btn-primary" href="' . e(url($backPath ?? 'index.php')) . '">'
        . e($backLabel ?? 'Go to the home page') . '</a>';
    if ($debug !== null) {
        echo '<pre class="debug-box">' . e(get_class($debug) . ': ' . $debug->getMessage() . "\n"
            . $debug->getFile() . ':' . $debug->getLine() . "\n\n" . $debug->getTraceAsString()) . '</pre>';
    }
    echo '</section>';
    page_end();
}

/** Stop with a "page not found" screen. Also used when a resident asks for a request that is not theirs. */
function abort_not_found(string $message = 'We could not find that page.', ?string $backPath = null, ?string $backLabel = null): void
{
    http_response_code(404);
    show_error_page(404, 'Page not found', $message, null, $backPath, $backLabel);
    exit;
}
