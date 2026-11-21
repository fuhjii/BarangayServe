<?php
/**
 * tests/run_tests.php: automatic tests for BarangayServe.
 *
 *   php tests/run_tests.php
 *
 * HOW IT WORKS
 *   1. It ERASES and rebuilds a separate TEST database (default name: barangayserve_test)
 *      from database/barangayserve.sql. Your real database is never touched. The script
 *      refuses to run if the test database has the same name as your real one.
 *   2. It starts PHP's built-in web server (with router.php) that uses the test database.
 *   3. It then uses the application like a browser would (real HTTP requests, cookies, forms)
 *      and checks the results in the pages AND in the database.
 *
 * REQUIREMENTS: PHP command line with the curl and pdo_mysql extensions, and a MySQL user
 * that may create databases and tables (XAMPP's "root" user works).
 *
 * Optional: BS_TEST_DB=name  to use another test database name.
 * Exit code: 0 = every test passed, 1 = at least one failed.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this file from the command line: php tests/run_tests.php\n");
}
error_reporting(E_ALL);
set_time_limit(0);
date_default_timezone_set('Asia/Manila');

$ROOT = dirname(__DIR__);
foreach (['curl', 'pdo_mysql'] as $ext) {
    if (!extension_loaded($ext)) {
        fwrite(STDERR, "The PHP extension \"$ext\" is required to run the tests.\n");
        exit(2);
    }
}
if (!is_file($ROOT . '/config/config.php')) {
    fwrite(STDERR, "Create config/config.php first (copy config/config.example.php).\n");
    exit(2);
}
$cfg    = require $ROOT . '/config/config.php';
$dbCfg  = $cfg['db'];
$testDb = (string) (getenv('BS_TEST_DB') ?: 'barangayserve_test');
if ($testDb === $dbCfg['name'] || !preg_match('/^[A-Za-z0-9_]+$/', $testDb)) {
    fwrite(STDERR, "The test database must be a different, plain name from your real database ({$dbCfg['name']}), because the tests erase it.\n");
    exit(2);
}

// ============================================================================
// Tiny test framework
// ============================================================================
$passed = 0; $failed = 0; $skipped = 0; $failures = [];

function section(string $title): void { echo "\n== $title ==\n"; }
function check(string $name, bool $ok, string $detail = ''): bool
{
    global $passed, $failed, $failures;
    if ($ok) { $passed++; echo "  PASS  $name\n"; }
    else     { $failed++; $failures[] = $name; echo "  FAIL  $name" . ($detail !== '' ? "\n        -> $detail" : '') . "\n"; }
    return $ok;
}
function skip(string $name, string $why): void { global $skipped; $skipped++; echo "  SKIP  $name ($why)\n"; }
function has(string $haystack, string $needle): bool { return strpos($haystack, $needle) !== false; }
function snippet(string $html): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#si', '', $html))));
    return substr($text, 0, 220);
}

// ============================================================================
// HTTP client (a pretend browser with its own cookies)
// ============================================================================
class Client
{
    public $jar; public $base; public $code = 0; public $body = ''; public $headers = '';
    public $path = ''; public $time = 0.0; public $chain = []; public $cookieHeader = '';
    public static $pages = [];

    public function __construct(string $base) { $this->base = $base; $this->jar = tempnam(sys_get_temp_dir(), 'bsjar'); }
    public function __destruct() { if ($this->jar && is_file($this->jar)) { @unlink($this->jar); } }

    private function once(string $method, string $url, ?array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'BarangayServe-tests',
        ]);
        if ($this->cookieHeader !== '') { curl_setopt($ch, CURLOPT_COOKIE, $this->cookieHeader); }
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data ?? [])); }
        $raw  = (string) curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return [(int) $info['http_code'], substr($raw, 0, $info['header_size']), substr($raw, $info['header_size']), (float) $info['total_time']];
    }

    public function request(string $method, string $path, ?array $data = null, bool $follow = true): self
    {
        $url = strncmp($path, 'http', 4) === 0 ? $path : $this->base . $path;
        $this->chain = []; $total = 0.0;
        for ($i = 0; $i < 8; $i++) {
            [$code, $headers, $body, $t] = $this->once($method, $url, $data);
            $total += $t;
            $this->chain[] = [$method, $url, $code];
            if ($follow && in_array($code, [301, 302, 303], true) && preg_match('/^Location:\s*(\S+)/im', $headers, $m)) {
                $url = strncmp($m[1], 'http', 4) === 0 ? $m[1] : $this->base . $m[1];
                $method = 'GET'; $data = null;
                continue;
            }
            break;
        }
        $this->code = $code; $this->headers = $headers; $this->body = $body; $this->time = $total;
        $q = parse_url($url, PHP_URL_QUERY);
        $this->path = (string) parse_url($url, PHP_URL_PATH) . ($q ? '?' . $q : '');
        if ($code === 200 && stripos($headers, 'text/html') !== false) {
            self::$pages[count(self::$pages) . ' ' . $this->path] = $body;
        }
        return $this;
    }
    public function get(string $path, bool $follow = true): self { return $this->request('GET', $path, null, $follow); }
    public function post(string $path, array $data, bool $follow = true): self { return $this->request('POST', $path, $data, $follow); }

    /** The CSRF token printed in the last page. */
    public function csrf(): string { return preg_match('/name="csrf_token" value="([0-9a-f]+)"/', $this->body, $m) ? $m[1] : ''; }

    /** Open $page, take its tokens, then POST $fields to $postTo (like a person filling in a form). */
    public function form(string $page, string $postTo, array $fields, bool $follow = true): self
    {
        $this->get($page);
        $tokens = ['csrf_token' => $this->csrf()];
        if (preg_match('/name="form_token" value="([0-9a-f]{32})"/', $this->body, $m)) { $tokens['form_token'] = $m[1]; }
        return $this->post($postTo, $fields + $tokens, $follow);
    }

    public function cookie(string $name): string
    {
        foreach (file($this->jar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = (string) preg_replace('/^#HttpOnly_/', '', $line);
            $p = explode("\t", $line);
            if (count($p) >= 7 && $p[5] === $name) { return $p[6]; }
        }
        return '';
    }
    public function location(): string { return preg_match('/^Location:\s*(\S+)/im', $this->headers, $m) ? $m[1] : ''; }
    public function ref(): string { return preg_match('/id="reference-number">(BR-\d{4}-\d{5})</', $this->body, $m) ? $m[1] : ''; }
}

/** Send several POST requests at the same moment (each with its own client/session). */
function parallelPost(array $jobs): array
{
    $mh = curl_multi_init(); $handles = [];
    foreach ($jobs as $i => $j) {
        $ch = curl_init($j['client']->base . $j['path']);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($j['data']), CURLOPT_COOKIEFILE => $j['client']->jar,
            CURLOPT_COOKIEJAR => $j['client']->jar, CURLOPT_TIMEOUT => 60]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
    do { $status = curl_multi_exec($mh, $running); if ($running) { curl_multi_select($mh, 1.0); } } while ($running && $status === CURLM_OK);
    $out = [];
    foreach ($handles as $i => $ch) {
        $raw = (string) curl_multi_getcontent($ch); $info = curl_getinfo($ch);
        $head = substr($raw, 0, $info['header_size']);
        $out[$i] = ['code' => (int) $info['http_code'], 'location' => preg_match('/^Location:\s*(\S+)/im', $head, $m) ? $m[1] : '', 'body' => substr($raw, $info['header_size'])];
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

// ============================================================================
// Database helpers and test-database reset
// ============================================================================
function connectServer(array $c, ?string $dbName = null): PDO
{
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . (int) $c['port'] . ($dbName ? ';dbname=' . $dbName : '') . ';charset=utf8mb4';
    return new PDO($dsn, (string) $c['user'], (string) $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
function runSqlFile(PDO $pdo, string $file): void
{
    $sql = (string) file_get_contents($file);
    $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);                     // comments
    $sql = (string) preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql);   // keep the test database
    foreach (preg_split('/;\s*\n/', $sql) as $statement) {
        if (trim($statement) !== '') { $pdo->exec($statement); }
    }
}
function q(string $sql, array $params = []): array { global $pdo; $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
function val(string $sql, array $params = [])      { global $pdo; $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchColumn(); }
function addUser(string $name, string $email, string $password, string $role = 'resident'): int
{
    global $pdo;
    $pdo->prepare('INSERT INTO users (full_name,email,contact_number,address,password_hash,role) VALUES (?,?,?,?,?,?)')
        ->execute([$name, $email, '09170000000', 'Test Street 1', password_hash($password, PASSWORD_DEFAULT), $role]);
    return (int) $pdo->lastInsertId();
}
function serviceId(string $name): int { return (int) val('SELECT id FROM services WHERE name = ?', [$name]); }
function countRows(string $html): int { return preg_match_all('/<td class="ref"/', $html); }
function runTool(string $script, array $args, array $env): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    foreach ($args as $a) { $cmd .= ' ' . escapeshellarg($a); }
    $envPrefix = '';
    foreach ($env as $k => $v) { $envPrefix .= $k . '=' . escapeshellarg($v) . ' '; }
    $out = []; $code = 0;
    exec('env ' . $envPrefix . $cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

// ---- Reset the test database -------------------------------------------------
echo "BarangayServe automatic tests\n";
echo "Test database: $testDb (erased and rebuilt now)\n";
try {
    $server = connectServer($dbCfg);
    $server->exec("CREATE DATABASE IF NOT EXISTS `$testDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = connectServer($dbCfg, $testDb);
} catch (Throwable $e) {
    fwrite(STDERR, "Could not connect to MySQL with the settings in config/config.php.\nThe user must be allowed to create the test database '$testDb'.\n");
    exit(2);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) { $pdo->exec("DROP TABLE IF EXISTS `$table`"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
runSqlFile($pdo, $ROOT . '/database/barangayserve.sql');

// ---- Start the application on a free port, using the test database -----------
$sock = stream_socket_server('tcp://127.0.0.1:0', $en, $em);
$port = (int) substr((string) strrchr((string) stream_socket_get_name($sock, false), ':'), 1);
fclose($sock);
$appEnv = ['BS_DB_HOST' => (string) $dbCfg['host'], 'BS_DB_PORT' => (string) $dbCfg['port'], 'BS_DB_NAME' => $testDb,
           'BS_DB_USER' => (string) $dbCfg['user'], 'BS_DB_PASS' => (string) $dbCfg['pass']];
foreach ($appEnv as $k => $v) { putenv("$k=$v"); }                 // also used by tools/*.php run below

function startServer(string $root, int $port, array $env)
{
    $log  = sys_get_temp_dir() . '/barangayserve_test_server_' . $port . '.log';
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root, $root . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'w']], $pipes, $root,
        array_merge(getenv(), $env, ['PHP_CLI_SERVER_WORKERS' => '8']));
    for ($i = 0; $i < 50; $i++) {
        $c = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2);
        if ($c) { fclose($c); return $proc; }
        usleep(100000);
    }
    return null;
}
$proc = startServer($ROOT, $port, $appEnv);
register_shutdown_function(function () use (&$proc) { if (is_resource($proc)) { proc_terminate($proc); } });
if (!$proc) { fwrite(STDERR, "The test web server did not start.\n"); exit(2); }
$base = "http://127.0.0.1:$port";
$year = date('Y');

// ============================================================================
section('1. Database design (SRS 4.3, 7.4)');
// ============================================================================
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach (['users', 'service_requests', 'request_history'] as $t) { check("Core table exists: $t", in_array($t, $tables, true)); }
$fks = array_column(q("SELECT CONSTRAINT_NAME n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?", [$testDb]), 'n');
foreach (['fk_requests_user', 'fk_history_request', 'fk_history_user'] as $fk) { check("Foreign key exists: $fk", in_array($fk, $fks, true)); }
check('users.email is UNIQUE', (int) val("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users' AND COLUMN_NAME='email' AND NON_UNIQUE=0", [$testDb]) > 0);
check('service_requests.reference_number is UNIQUE', (int) val("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='service_requests' AND COLUMN_NAME='reference_number' AND NON_UNIQUE=0", [$testDb]) > 0);
$statusType = strtolower((string) val("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='service_requests' AND COLUMN_NAME='status'", [$testDb]));
check('The seven statuses, in order (five steps, then Rejected and Cancelled)', $statusType === "enum('submitted','under review','processing','ready for pickup','completed','rejected','cancelled')", $statusType);
$svcNames = array_column(q('SELECT name FROM services ORDER BY sort_order'), 'name');
check('The seven services (the six from SRS FR-03 plus Loan Clearance) are present', $svcNames === ['Barangay Clearance', 'Loan Clearance', 'Certificate of Residency', 'Certificate of Indigency', 'Certificate of Good Moral Character', 'Business-related Request', 'Other Barangay Service'], implode(' | ', $svcNames));
check('No user accounts exist before registration', (int) val('SELECT COUNT(*) FROM users') === 0);

// Static check of the source code: history is append-only
$bad = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (substr($f->getFilename(), -4) === '.php' && strpos($f->getPathname(), '/tests/') === false && strpos($f->getPathname(), '/assets/') === false) {
        if (preg_match('/(UPDATE\s+request_history|DELETE\s+FROM\s+request_history)/i', (string) file_get_contents($f->getPathname()))) { $bad[] = $f->getPathname(); }
    }
}
check('No PHP code ever UPDATEs or DELETEs request_history', !$bad, implode(', ', $bad));

// ============================================================================
section('2. Resident registration (FR-01)');
// ============================================================================
$A = new Client($base); $B = new Client($base); $anon = new Client($base);
$emailA = 'ana.delapena@test.example'; $emailB = 'ben.reyes@test.example'; $pw = 'Passw0rd!x';

$r = $anon->get('/register.php');
check('Registration page loads', $r->code === 200 && has($r->body, 'Create a resident account'));
$n0 = (int) val('SELECT COUNT(*) FROM users');

$r = $anon->post('/register.php', ['csrf_token' => 'wrong', 'full_name' => 'X', 'email' => 'x@test.example']);
check('Registration without a valid CSRF token is refused (403)', $r->code === 403);

$r = $anon->form('/register.php', '/register.php', ['full_name' => '', 'email' => '', 'contact_number' => '', 'address' => '', 'password' => '', 'password_confirm' => '']);
check('Empty form shows a message for every required field',
    has($r->body, 'Please enter your full name.') && has($r->body, 'Please enter your email address.') && has($r->body, 'Please enter your contact number.')
    && has($r->body, 'Please enter your address.') && has($r->body, 'Please enter a password.'));
$base_ok = ['full_name' => 'Ana Dela Peña', 'email' => $emailA, 'contact_number' => '09171234567', 'address' => '12 Rizal Street, Purok 3', 'password' => $pw, 'password_confirm' => $pw];

$r = $anon->form('/register.php', '/register.php', ['email' => 'not-an-email'] + $base_ok);
check('Invalid email format is rejected', has($r->body, 'Please enter a valid email address'));
$r = $anon->form('/register.php', '/register.php', ['contact_number' => 'abc'] + $base_ok);
check('Invalid contact number is rejected', has($r->body, 'exactly 11 digits'));
$r = $anon->form('/register.php', '/register.php', ['full_name' => 'R2D2<script>'] + $base_ok);
check('Name with digits or symbols is rejected', has($r->body, 'can only contain letters'));
$r = $anon->form('/register.php', '/register.php', ['password' => 'ab1', 'password_confirm' => 'ab1'] + $base_ok);
check('Password shorter than 8 characters is rejected', has($r->body, 'at least 8 characters'));
$r = $anon->form('/register.php', '/register.php', ['password' => 'abcdefgh', 'password_confirm' => 'abcdefgh'] + $base_ok);
check('Password without a number is rejected', has($r->body, 'at least one letter and one number'));
$r = $anon->form('/register.php', '/register.php', ['password' => '12345678', 'password_confirm' => '12345678'] + $base_ok);
check('Password without a letter is rejected', has($r->body, 'at least one letter and one number'));
$r = $anon->form('/register.php', '/register.php', ['password_confirm' => 'Different1'] + $base_ok);
check('Different password confirmation is rejected', has($r->body, 'not the same'));
check('None of the invalid attempts created an account', (int) val('SELECT COUNT(*) FROM users') === $n0);

// Privilege escalation attempt: extra form fields must never choose the role.
$r = $A->form('/register.php', '/register.php', $base_ok + ['role' => 'staff', 'is_active' => '1', 'id' => '1']);
check('Valid registration redirects to the log in page with a confirmation message',
    $r->path === '/login.php' && has($r->body, 'Your account was created'), $r->path . ' ' . snippet($r->body));
check('Role injection ("role=staff") is ignored: the account is a resident', val('SELECT role FROM users WHERE email = ?', [$emailA]) === 'resident');
$hash = (string) val('SELECT password_hash FROM users WHERE email = ?', [$emailA]);
check('Password is stored as a hash (never as plain text)', $hash !== $pw && password_verify($pw, $hash) && strncmp($hash, '$', 1) === 0);
check('Account details were saved (including the letter "ñ")', val('SELECT full_name FROM users WHERE email = ?', [$emailA]) === 'Ana Dela Peña');

$r = $anon->form('/register.php', '/register.php', ['email' => strtoupper($emailA)] + $base_ok);
check('Duplicate email (even in capital letters) is refused', has($r->body, 'already exists') && (int) val('SELECT COUNT(*) FROM users WHERE email = ?', [$emailA]) === 1);

$r = $B->form('/register.php', '/register.php', ['full_name' => 'Ben Reyes', 'email' => $emailB, 'contact_number' => '09181234567', 'address' => '45 Mabini Avenue'] + ['password' => $pw, 'password_confirm' => $pw]);
check('Second resident registers', $r->path === '/login.php' && (int) val("SELECT COUNT(*) FROM users WHERE role='resident'") === 2);

// ============================================================================
section('3. Login, logout, and sessions (FR-02, 7.1)');
// ============================================================================
$r = $anon->form('/login.php', '/login.php', ['email' => $emailA, 'password' => 'WrongPass1']);
$msgWrong = has($r->body, 'The email address or password is incorrect.');
$r2 = $anon->form('/login.php', '/login.php', ['email' => 'nobody@test.example', 'password' => 'WrongPass1']);
check('Wrong password shows an error', $msgWrong && $r->code === 200);
check('Unknown email shows the SAME error (does not reveal which emails exist)', has($r2->body, 'The email address or password is incorrect.'));
$r = $anon->form('/login.php', '/login.php', ['email' => "' OR '1'='1", 'password' => "' OR '1'='1"]);
check('SQL-injection text in the login form does not log anyone in', $r->code === 200 && has($r->body, 'incorrect'));

// Too many failures: lock (even for the right password)
addUser('Lock Test', 'lock@test.example', $pw);
$L = new Client($base);
for ($i = 0; $i < 5; $i++) { $L->form('/login.php', '/login.php', ['email' => 'lock@test.example', 'password' => 'Wrong' . $i . 'xx']); }
$r = $L->form('/login.php', '/login.php', ['email' => 'lock@test.example', 'password' => $pw]);
check('After 5 wrong passwords the account is temporarily locked (even the right password fails)', has($r->body, 'Too many failed attempts'));
$pdo->exec('DELETE FROM login_attempts');

// Session fixation: the session id must change at log in
$fx = new Client($base);
$fx->get('/login.php');
$sidBefore = $fx->cookie('barangayserve_sid');
$r = $fx->post('/login.php', ['csrf_token' => $fx->csrf(), 'email' => $emailA, 'password' => $pw], false);
$sidAfter = $fx->cookie('barangayserve_sid');
check('Valid login redirects to the resident dashboard', $r->code === 302 && has($r->location(), '/resident/dashboard.php'), $r->code . ' ' . $r->location());
check('Session id is replaced at log in (prevents session fixation)', $sidBefore !== '' && $sidAfter !== '' && $sidBefore !== $sidAfter);
$fixed = new Client($base); $fixed->cookieHeader = 'barangayserve_sid=attackerchosenid1234567890abcd';
$fixed->get('/login.php');
$r = $fixed->post('/login.php', ['csrf_token' => $fixed->csrf(), 'email' => $emailA, 'password' => $pw], false);
check('A session id chosen by an attacker is not accepted', !has($r->headers, 'attackerchosenid'));

$r = $A->form('/login.php', '/login.php', ['email' => $emailA, 'password' => $pw]);
check('Resident A logs in and sees the dashboard', $r->path === '/resident/dashboard.php' && has($r->body, 'Hello, Ana'), $r->path);
check('Protected pages tell the browser not to cache them', (bool) preg_match('/Cache-Control:[^\r\n]*no-store/i', $r->headers));
$r = $B->form('/login.php', '/login.php', ['email' => $emailB, 'password' => $pw]);
check('Resident B logs in', $r->path === '/resident/dashboard.php');

// Security headers and cookie flags
$h = (new Client($base))->get('/login.php', false)->headers;
check('Security headers are sent (CSP, X-Frame-Options, nosniff)', has($h, 'Content-Security-Policy:') && has($h, 'X-Frame-Options: DENY') && has($h, 'X-Content-Type-Options: nosniff'));
check('Session cookie is HttpOnly and SameSite=Lax', (bool) preg_match('/Set-Cookie: barangayserve_sid=[^\r\n]*HttpOnly/i', $h) && (bool) preg_match('/Set-Cookie: barangayserve_sid=[^\r\n]*SameSite=Lax/i', $h));

// Logout
$sidOld = $A->cookie('barangayserve_sid');
$r = $A->get('/logout.php');
$r = $A->get('/resident/dashboard.php');
check('Opening /logout.php by a plain link does NOT log out (logout needs a POST with a token)', $r->code === 200 && has($r->body, 'Hello, Ana'));
$A->post('/logout.php', ['csrf_token' => 'forged']);
check('Logout with a forged token is refused', $A->get('/resident/dashboard.php')->code === 200);
$A->get('/resident/dashboard.php');
$r = $A->post('/logout.php', ['csrf_token' => $A->csrf()]);
check('Logout works and shows a message', $r->path === '/login.php' && has($r->body, 'You have been logged out'));
$r = $A->get('/resident/dashboard.php', false);
check('After logout the dashboard redirects to the log in page', $r->code === 302 && has($r->location(), '/login.php'));
$old = new Client($base); $old->cookieHeader = 'barangayserve_sid=' . $sidOld;
$r = $old->get('/resident/dashboard.php', false);
check('The old session id cannot be used again after logout', $r->code === 302 && has($r->location(), '/login.php'));

// "Intended page" after log in
$r = $A->get('/resident/requests.php', false);
check('A logged-out visitor asking for a private page is sent to log in', $r->code === 302 && has($r->location(), '/login.php'));
$A->get('/login.php');
$r = $A->post('/login.php', ['csrf_token' => $A->csrf(), 'email' => $emailA, 'password' => $pw]);
check('After log in the visitor returns to the page they asked for', $r->path === '/resident/requests.php', $r->path);

// ============================================================================
section('4. Staff accounts are not created on any web page (6.4, 7.1)');
// ============================================================================
[$code, $out] = runTool($ROOT . '/tools/create_staff.php', ['--name=Sam Staff', '--email=sam.staff@test.example', '--password=Staff0Pass1'], $appEnv);
check('tools/create_staff.php creates a staff account', $code === 0 && val("SELECT role FROM users WHERE email='sam.staff@test.example'") === 'staff', $out);
[$code, $out] = runTool($ROOT . '/tools/create_staff.php', ['--name=Sam Again', '--email=sam.staff@test.example', '--password=Staff0Pass1'], $appEnv);
check('The tool refuses a duplicate email', $code !== 0 && (int) val("SELECT COUNT(*) FROM users WHERE email='sam.staff@test.example'") === 1);
[$code, $out] = runTool($ROOT . '/tools/create_staff.php', ['--name=Weak Pass', '--email=weak@test.example', '--password=short'], $appEnv);
check('The tool refuses a weak password', $code !== 0 && (int) val("SELECT COUNT(*) FROM users WHERE email='weak@test.example'") === 0);
[$code, $out] = runTool($ROOT . '/tools/create_staff.php', ['--name=Tina Second', '--email=tina.staff@test.example', '--password=Staff0Pass2'], $appEnv);
check('A second staff account can be created', $code === 0);
foreach (['/tools/create_staff.php', '/config/config.php', '/includes/auth.php', '/database/barangayserve.sql', '/tests/run_tests.php', '/.gitignore'] as $blocked) {
    check("Web access is blocked: $blocked", (new Client($base))->get($blocked, false)->code === 403);
}

$S = new Client($base); $S2 = new Client($base);
$r = $S->form('/staff/login.php', '/staff/login.php', ['email' => 'sam.staff@test.example', 'password' => 'Staff0Pass1']);
check('Staff logs in at the staff log in page and reaches the staff dashboard', $r->path === '/staff/dashboard.php' && has($r->body, 'Staff dashboard'), $r->path);
$r = $S2->form('/login.php', '/login.php', ['email' => 'tina.staff@test.example', 'password' => 'Staff0Pass2']);
check('Staff who use the normal log in page are sent to the staff dashboard', $r->path === '/staff/dashboard.php', $r->path);
$C = new Client($base);
$r = $C->form('/staff/login.php', '/staff/login.php', ['email' => $emailB, 'password' => $pw]);
check('A resident cannot log in on the staff log in page', has($r->body, 'only for authorized barangay staff'));
check('...and no session was started for that resident', $C->get('/resident/dashboard.php', false)->code === 302);

// ============================================================================
section('5. Role-based access control on the server (7.1, 6.4)');
// ============================================================================
foreach (['/resident/dashboard.php', '/resident/requests.php', '/resident/services.php', '/resident/request.php?id=1', '/staff/dashboard.php', '/staff/requests.php', '/staff/request.php?id=1', '/staff/services.php', '/account.php'] as $p) {
    $r = (new Client($base))->get($p, false);
    check("Logged-out visitor is redirected away from $p", $r->code === 302 && has($r->location(), 'login.php'), $r->code . '');
}
foreach (['/staff/dashboard.php', '/staff/requests.php', '/staff/services.php', '/staff/service_edit.php', '/staff/request.php?id=1', '/staff/history.php?id=1'] as $p) {
    $r = $B->get($p, false);
    check("Resident gets 403 on $p", $r->code === 403, (string) $r->code);
}
foreach (['/resident/dashboard.php', '/resident/requests.php', '/resident/services.php'] as $p) {
    $r = $S->get($p, false);
    check("Staff gets 403 on the resident page $p", $r->code === 403, (string) $r->code);
}

// ============================================================================
section('6. Service selection and request submission (FR-03, FR-04, FR-05)');
// ============================================================================
$r = $A->get('/resident/services.php');
$allListed = true; foreach ($svcNames as $n) { if (!has($r->body, htmlspecialchars($n))) { $allListed = false; } }
check('All services are listed for the resident', $r->code === 200 && $allListed);
$sidClear = serviceId('Barangay Clearance'); $sidRes = serviceId('Certificate of Residency');
$sidInd = serviceId('Certificate of Indigency'); $sidBiz = serviceId('Business-related Request'); $sidOther = serviceId('Other Barangay Service');
$r = $A->get("/resident/request_new.php?service=$sidBiz");
check('The form changes with the selected service (business form asks for the business name)', has($r->body, 'Business name') && has($r->body, 'Nature of business') && !has($r->body, 'Civil status'));
$r = $A->get("/resident/request_new.php?service=$sidClear");
check('Clearance form asks for date of birth, civil status, years of residency', has($r->body, 'Date of birth') && has($r->body, 'Civil status') && has($r->body, 'Years of residency'));
check('The form marks required fields and has a confirmation prompt', has($r->body, 'class="req"') && has($r->body, 'data-confirm='));
$r = $A->get('/resident/request_new.php?service=99999');
check('A service that does not exist redirects back to the service list', $r->path === '/resident/services.php');

$page = "/resident/request_new.php?service=$sidClear";
$good = ['applicant_name' => 'Ana Dela Peña', 'applicant_contact' => '09171234567', 'applicant_address' => '12 Rizal Street, Purok 3',
         'purpose' => 'Job application', 'f_date_of_birth' => '1999-04-12', 'f_gender' => 'Male', 'f_civil_status' => 'Single', 'f_years_of_residency' => '12'];
$nReq = (int) val('SELECT COUNT(*) FROM service_requests');

$r = $A->form($page, $page, ['purpose' => '', 'applicant_name' => '', 'applicant_contact' => '', 'applicant_address' => '']);
check('Submitting an empty form is blocked with messages', has($r->body, 'Please fix these') && has($r->body, 'Please enter the purpose of your request.') && has($r->body, 'Please fill in: Date of birth.'));
$r = $A->form($page, $page, ['f_date_of_birth' => '2999-01-01'] + $good);
check('A future date of birth is rejected', has($r->body, 'cannot be in the future'));
$r = $A->form($page, $page, ['f_civil_status' => 'Hacker'] + $good);
check('A choice that is not in the list is rejected', has($r->body, 'Please choose one of the listed options'));
$r = $A->form($page, $page, ['f_years_of_residency' => '500'] + $good);
check('A number outside the allowed range is rejected', has($r->body, 'whole number from 0 to 120'));
$r = $A->form($page, $page, ['f_years_of_residency' => 'abc'] + $good);
check('Text in a number field is rejected', has($r->body, 'whole number'));
$r = $A->form($page, $page, ['purpose' => str_repeat('x', 300)] + $good);
check('A purpose longer than 255 characters is rejected', has($r->body, 'between 3 and 255'));
$r = $A->form($page, $page, ['purpose' => 'My typed purpose', 'f_civil_status' => 'Hacker'] + $good);
check('After an error the form keeps what the resident typed', has($r->body, 'My typed purpose'));
check('No invalid submission created a request', (int) val('SELECT COUNT(*) FROM service_requests') === $nReq);

// Valid submission
$A->get($page);
$tokenA = preg_match('/name="form_token" value="([0-9a-f]{32})"/', $A->body, $m) ? $m[1] : '';
$post = $good + ['csrf_token' => $A->csrf(), 'form_token' => $tokenA];
$t0 = microtime(true);
$r = $A->post($page, $post);
$submitTime = microtime(true) - $t0;
$ref1 = $r->ref();
check('A valid request is saved and the confirmation shows a reference number', $r->path === '/resident/request_submitted.php?id=' . (int) val('SELECT id FROM service_requests WHERE reference_number = ?', [$ref1]) && $ref1 !== '', $r->path);
check("Reference number has the format BR-YYYY-NNNNN with the current year ($year)", (bool) preg_match('/^BR-' . $year . '-\d{5}$/', $ref1), $ref1);
check('The first reference number of the year is 00001', $ref1 === "BR-$year-00001");
$row1 = q('SELECT * FROM service_requests WHERE reference_number = ?', [$ref1])[0] ?? [];
check('New request has the status "Submitted" and the submission time', ($row1['status'] ?? '') === 'Submitted' && !empty($row1['submitted_at']));
check('Request belongs to the resident who submitted it', (int) ($row1['user_id'] ?? 0) === (int) val('SELECT id FROM users WHERE email = ?', [$emailA]));
check('Service-specific answers were saved as JSON', has((string) ($row1['request_data'] ?? ''), 'civil_status') && has((string) ($row1['request_data'] ?? ''), 'Single'));
$hist1 = q('SELECT * FROM request_history WHERE request_id = ?', [(int) $row1['id']]);
check('The initial status was recorded in the history (one row, "Submitted")', count($hist1) === 1 && $hist1[0]['status'] === 'Submitted' && $hist1[0]['entry_type'] === 'submitted');
check('Submission time was within the 5-second target', $submitTime < 5.0, round($submitTime, 2) . ' s');

// Refresh / double submit
$r = $A->post($page, $post);
check('Re-sending the same form (refresh / double click) does not create a second request',
    (int) val('SELECT COUNT(*) FROM service_requests WHERE user_id = ?', [(int) $row1['user_id']]) === 1 && $r->ref() === $ref1 && has($r->body, 'already submitted'));
$r = $B->post($page, $post);
$B->get('/resident/dashboard.php');
check('Another resident cannot reuse that form token', (int) val('SELECT COUNT(*) FROM service_requests') === $nReq + 1, snippet($r->body));

// Second request by A, third by B
$page2 = "/resident/request_new.php?service=$sidRes";
$r = $A->form($page2, $page2, ['applicant_name' => 'Ana Dela Peña', 'applicant_contact' => '09171234567', 'applicant_address' => '12 Rizal Street, Purok 3',
    'purpose' => 'School enrollment', 'f_age' => '27', 'f_gender' => 'Male', 'f_civil_status' => 'Single', 'f_years_of_residency' => '8', 'f_residence_type' => 'Homeowner']);
$ref2 = $r->ref();
check('Second request gets the next number (00002)', $ref2 === "BR-$year-00002", $ref2);
$page3 = "/resident/request_new.php?service=$sidInd";
$r = $B->form($page3, $page3, ['applicant_name' => 'Ben Reyes', 'applicant_contact' => '09181234567', 'applicant_address' => '45 Mabini Avenue',
    'purpose' => 'Medical assistance', 'f_age' => '34', 'f_gender' => 'Male', 'f_household_members' => '5', 'f_assistance_needed' => 'Medical or hospital']);
$ref3 = $r->ref();
check('Request by another resident gets 00003', $ref3 === "BR-$year-00003", $ref3);
$id1 = (int) val('SELECT id FROM service_requests WHERE reference_number = ?', [$ref1]);
$id2 = (int) val('SELECT id FROM service_requests WHERE reference_number = ?', [$ref2]);
$id3 = (int) val('SELECT id FROM service_requests WHERE reference_number = ?', [$ref3]);

// Cross-site scripting: dangerous text must be shown as text
$xss = "<script>alert('xss')</script><img src=x onerror=alert(1)>";
$page4 = "/resident/request_new.php?service=$sidBiz";
$r = $B->form($page4, $page4, ['applicant_name' => 'Ben Reyes', 'applicant_contact' => '09181234567', 'applicant_address' => '45 Mabini Avenue',
    'purpose' => $xss, 'f_request_kind' => 'New business clearance', 'f_business_name' => '"><svg onload=alert(1)>', 'f_business_address' => 'Mabini Ave', 'f_nature_of_business' => 'Store']);
$ref4 = $r->ref(); $id4 = (int) val('SELECT id FROM service_requests WHERE reference_number = ?', [$ref4]);
$r = $B->get("/resident/request.php?id=$id4");
check('Script tags in the purpose and answers are shown as harmless text (resident view)',
    has($r->body, '&lt;script&gt;') && !has($r->body, "<script>alert('xss')") && !has($r->body, '<svg onload') && !has($r->body, '<img src=x'));

// ============================================================================
section('7. Request ownership: residents see only their own requests (FR-07, 7.1)');
// ============================================================================
$rOther = $B->get("/resident/request.php?id=$id1", false);
$rNone  = $B->get('/resident/request.php?id=999999', false);
check("Resident B cannot open resident A's request (404)", $rOther->code === 404, (string) $rOther->code);
check('...and the answer is identical to a request that does not exist (nothing leaks)', $rOther->code === $rNone->code && $rOther->body === $rNone->body);
check("...and A's reference number and purpose are not in the page", !has($rOther->body, $ref1) && !has($rOther->body, 'Job application'));
foreach (['/resident/history.php?id=', '/resident/request_submitted.php?id='] as $p) {
    check("Resident B cannot open A's request through $p", $B->get($p . $id1, false)->code === 404);
}
foreach (['abc', '-1', '1%20OR%201=1', '1;DROP%20TABLE%20users', '0', '99999999999'] as $bad) {
    $r = $B->get('/resident/request.php?id=' . $bad, false);
    check("Odd id \"$bad\" is handled safely (404, no error)", $r->code === 404, (string) $r->code);
}
$r = $B->get('/resident/requests.php');
check("B's request list contains only B's requests", has($r->body, $ref3) && has($r->body, $ref4) && !has($r->body, $ref1) && !has($r->body, $ref2));
$r = $A->get('/resident/requests.php');
check("A's request list contains only A's requests", has($r->body, $ref1) && has($r->body, $ref2) && !has($r->body, $ref3));
check('The list shows reference number, service, date, status, and a details link', has($r->body, 'Reference number') && has($r->body, 'Barangay Clearance') && has($r->body, 'status-badge') && has($r->body, 'View details'));

function pipelineCounts(string $html): array
{
    preg_match_all('/aria-label="(Submitted|Under Review|Processing|Ready for Pickup|Completed): (\d+) requests?\./', $html, $m, PREG_SET_ORDER);
    $out = []; foreach ($m as $x) { $out[$x[1]] = (int) $x[2]; } return $out;
}
$dbCounts = function (?int $uid) {
    $rows = $uid === null ? q('SELECT status, COUNT(*) c FROM service_requests GROUP BY status') : q('SELECT status, COUNT(*) c FROM service_requests WHERE user_id = ? GROUP BY status', [$uid]);
    $out = ['Submitted' => 0, 'Under Review' => 0, 'Processing' => 0, 'Ready for Pickup' => 0, 'Completed' => 0];
    foreach ($rows as $row) { if (isset($out[$row['status']])) { $out[$row['status']] = (int) $row['c']; } } return $out;   // the dashboard row shows the five workflow steps
};
$uidA = (int) val('SELECT id FROM users WHERE email = ?', [$emailA]); $uidB = (int) val('SELECT id FROM users WHERE email = ?', [$emailB]);
check("Resident dashboard counts equal the database (A)", pipelineCounts($A->get('/resident/dashboard.php')->body) === $dbCounts($uidA));
check("Resident dashboard counts equal the database (B)", pipelineCounts($B->get('/resident/dashboard.php')->body) === $dbCounts($uidB));
$r = $A->get("/resident/request.php?id=$id1");
check('Details page shows reference, service, submission date, current status, and progress', has($r->body, $ref1) && has($r->body, 'Barangay Clearance') && has($r->body, 'Submitted') && has($r->body, 'tracker') && has($r->body, 'History'));
check('Details page shows what the resident submitted (purpose and service answers)', has($r->body, 'Job application') && has($r->body, 'Civil status') && has($r->body, 'Single'));
$r = $A->post('/resident/request.php?id=' . $id1, ['csrf_token' => $A->csrf(), 'status' => 'Completed', 'action' => 'advance', 'expected_status' => 'Submitted']);
check('A resident cannot change a status: the page has no update form and POST does nothing', val('SELECT status FROM service_requests WHERE id = ?', [$id1]) === 'Submitted' && !has($A->get("/resident/request.php?id=$id1")->body, 'Update this request'));
$r = $B->post('/staff/request.php?id=' . $id3, ['csrf_token' => $B->csrf() ?: 'x', 'action' => 'advance', 'expected_status' => 'Submitted', 'remark' => 'hacked']);
check('A resident cannot send a staff update (403) and nothing changes', $r->code === 403 && val('SELECT status FROM service_requests WHERE id = ?', [$id3]) === 'Submitted');

// ============================================================================
section('8. Reference numbers under simultaneous submissions (FR-05, 7.4)');
// ============================================================================
$N = 12; $clients = []; $jobs = [];
$before = (int) val('SELECT COUNT(*) FROM service_requests');
for ($i = 1; $i <= $N; $i++) {
    $em = "load$i@test.example";
    $nm = 'Load Tester ' . chr(64 + $i);
    addUser($nm, $em, 'LoadTest123');
    $c = new Client($base); $c->form('/login.php', '/login.php', ['email' => $em, 'password' => 'LoadTest123']);
    $pg = "/resident/request_new.php?service=$sidOther";
    $c->get($pg);
    $tok = preg_match('/name="form_token" value="([0-9a-f]{32})"/', $c->body, $m) ? $m[1] : '';
    $jobs[$i] = ['client' => $c, 'path' => $pg, 'data' => ['csrf_token' => $c->csrf(), 'form_token' => $tok, 'applicant_name' => $nm,
        'applicant_contact' => '09171234567', 'applicant_address' => 'Load Street 1', 'purpose' => 'Load test', 'f_service_description' => 'Testing at the same time']];
    $clients[$i] = $c;
}
$results = parallelPost($jobs);
$okCount = 0; foreach ($results as $res) { if ($res['code'] === 302 && has($res['location'], 'request_submitted.php')) { $okCount++; } }
check("$N residents submitted at the same moment: every submission succeeded", $okCount === $N,
    "$okCount of $N (HTTP codes: " . implode(',', array_column($results, 'code')) . '; first reply: ' . (preg_match('#id="error-summary".*?</ul>#s', $results[1]['body'] ?? '', $mm) ? snippet($mm[0]) : snippet($results[1]['body'] ?? '')) . ')');
$refs = array_column(q('SELECT reference_number FROM service_requests WHERE id > ? ORDER BY id', [$id4]), 'reference_number');
$loadRefs = array_column(q("SELECT r.reference_number FROM service_requests r JOIN users u ON u.id = r.user_id WHERE u.email LIKE 'load%@test.example'"), 'reference_number');
check('...and every reference number is different', count($loadRefs) === $N && count(array_unique($loadRefs)) === $N);
$seq = array_map(function ($x) { return (int) substr($x, -5); }, $loadRefs); sort($seq);
check('...and the numbers are consecutive with no gaps (00005 to 00016)', $seq === range(5, 4 + $N), implode(',', $seq));
check('...and the yearly counter matches the highest number', (int) val('SELECT last_number FROM reference_counters WHERE ref_year = ?', [(int) $year]) === 4 + $N);
check('...and each request has exactly one history row', (int) val('SELECT COUNT(*) FROM service_requests r WHERE (SELECT COUNT(*) FROM request_history h WHERE h.request_id = r.id) <> 1') === 0);

// Same form sent twice at the very same moment
$c = $clients[1]; $pg = "/resident/request_new.php?service=$sidOther";
$c->get($pg); $tok = preg_match('/name="form_token" value="([0-9a-f]{32})"/', $c->body, $m) ? $m[1] : '';
$data = ['csrf_token' => $c->csrf(), 'form_token' => $tok, 'applicant_name' => 'Load Tester A', 'applicant_contact' => '09171234567', 'applicant_address' => 'Load Street 1', 'purpose' => 'Double click test', 'f_service_description' => 'Double'];
$cnt = (int) val('SELECT COUNT(*) FROM service_requests WHERE user_id = (SELECT id FROM users WHERE email = ?)', ['load1@test.example']);
parallelPost([['client' => $c, 'path' => $pg, 'data' => $data], ['client' => $c, 'path' => $pg, 'data' => $data], ['client' => $c, 'path' => $pg, 'data' => $data]]);
check('The same form sent 3 times at the same moment creates only ONE request',
    (int) val('SELECT COUNT(*) FROM service_requests WHERE user_id = (SELECT id FROM users WHERE email = ?)', ['load1@test.example']) === $cnt + 1);

// ============================================================================
section('9. Staff dashboard, search, and filters (FR-09, FR-10)');
// ============================================================================
$r = $S->get('/staff/dashboard.php');
check('Staff dashboard counts equal the database', pipelineCounts($r->body) === $dbCounts(null), json_encode(pipelineCounts($r->body)) . ' vs ' . json_encode($dbCounts(null)));
check('Dashboard shows all five statuses', count(pipelineCounts($r->body)) === 5);
$total = (int) val('SELECT COUNT(*) FROM service_requests');
check('Dashboard shows the real total', has($r->body, '<strong>' . $total . '</strong>'));

$r = $S->get('/staff/requests.php?sort=oldest');   // the first requests of this test run are on this page
check('Request list shows reference, resident name, service, date, status', has($r->body, 'Reference number') && has($r->body, 'Resident') && has($r->body, 'Ana Dela Peña') && has($r->body, 'Ben Reyes'));
check('Request list shows requests of ALL residents', has($r->body, $ref1) && has($r->body, $ref3));
$perPage = 10;
check("List is paged ($perPage per page)", countRows($r->body) === $perPage && has($r->body, 'Pages of results'));
$r2 = $S->get('/staff/requests.php?page=2');
check('Page 2 shows the remaining requests', countRows($r2->body) === $total - $perPage, countRows($r2->body) . ' rows');
check('A page number beyond the end shows the last page', countRows($S->get('/staff/requests.php?page=999')->body) === $total - $perPage);
check('Search by reference number', countRows($S->get('/staff/requests.php?q=' . urlencode($ref2))->body) === 1 && has($S->body, $ref2));
check('Search by part of the resident name (case-insensitive)', countRows($S->get('/staff/requests.php?q=' . urlencode('dela pe'))->body) === 2);
check('Search by resident name finds the right resident',
    has($S->get('/staff/requests.php?q=reyes')->body, '>' . $ref3 . '</a>') && !has($S->body, '>' . $ref1 . '</a>'));
check('Filter by service', countRows($S->get('/staff/requests.php?service=' . $sidRes)->body) === 1 && has($S->body, $ref2));
check('Filter by status', countRows($S->get('/staff/requests.php?status=Submitted')->body) === min($perPage, $dbCounts(null)['Submitted']));
check('Filter by submission date (today)', countRows($S->get('/staff/requests.php?from=' . date('Y-m-d') . '&to=' . date('Y-m-d'))->body) === min($perPage, $total));
check('Filter by a future date finds nothing', has($S->get('/staff/requests.php?from=' . date('Y-m-d', strtotime('+2 days')))->body, 'No requests match your search'));
check('Search + filter together', countRows($S->get('/staff/requests.php?q=dela&service=' . $sidClear)->body) === 1);
check('Search for "%" is treated as a normal character (finds nothing)', has($S->get('/staff/requests.php?q=%25')->body, 'No requests match'));
$r = $S->get('/staff/requests.php?q=' . urlencode("' OR '1'='1' --"));
check('SQL-injection text in the search box is harmless', $r->code === 200 && has($r->body, 'No requests match'));
$r = $S->get('/staff/requests.php?status=Hacked&service=abc&from=garbage&sort=evil');
check('Invalid filter values are ignored safely', $r->code === 200);
check('Oldest-first sorting works', preg_match('/BR-' . $year . '-00001/', $S->get('/staff/requests.php?sort=oldest')->body) === 1);

$r = $S->get("/staff/request.php?id=$id1");
check('Staff request details show resident information, service, purpose, status, date, and history',
    has($r->body, 'Ana Dela Peña') && has($r->body, 'Barangay Clearance') && has($r->body, 'Job application') && has($r->body, 'Single') && has($r->body, $ref1) && has($r->body, 'History') && has($r->body, 'ana.delapena@test.example'));
check('Staff see the status update form and the remark box', has($r->body, 'name="expected_status"') && has($r->body, 'name="remark"'));
check('Script text is escaped on the staff page too', has($S->get("/staff/request.php?id=$id4")->body, '&lt;script&gt;') && !has($S->body, "<script>alert('xss')"));

// ============================================================================
section('10. Status workflow and history (FR-06, FR-08)');
// ============================================================================
$page = "/staff/request.php?id=$id1";
$update = function (Client $who, int $id, string $action, string $expected, string $remark = '', array $extra = []) {
    $who->get("/staff/request.php?id=$id");
    return $who->post("/staff/request.php?id=$id", ['csrf_token' => $who->csrf(), 'action' => $action, 'expected_status' => $expected, 'remark' => $remark] + $extra);
};
$histRows = function (int $id) { return q('SELECT id, status, entry_type, remark, changed_by, created_at FROM request_history WHERE request_id = ? ORDER BY id', [$id]); };
$status = function (int $id) { return (string) val('SELECT status FROM service_requests WHERE id = ?', [$id]); };
$staffId = (int) val("SELECT id FROM users WHERE email = 'sam.staff@test.example'");

$snap = $histRows($id1);
$r = $update($S, $id1, 'advance', 'Submitted');
$h = $histRows($id1);
check('Staff move the request to "Under Review"', $status($id1) === 'Under Review' && has($r->body, 'is now') && has($r->body, 'Under Review'));
check('A new history row was ADDED (old rows unchanged)', count($h) === 2 && $h[0] === $snap[0] && $h[1]['status'] === 'Under Review' && (int) $h[1]['changed_by'] === $staffId);
check('A standard remark was saved when the staff left the remark empty', $h[1]['remark'] === 'Request is being checked.');
check('The history row has a date and time', (bool) strtotime((string) $h[1]['created_at']));
$r = $A->get("/resident/request.php?id=$id1");
check('The resident sees the new status immediately', has($r->body, 'Under Review') && has($r->body, 'Request is being checked.'));

$r = $update($S, $id1, 'advance', 'Submitted');
check('A stale update (page was outdated) is refused and changes nothing', $status($id1) === 'Under Review' && count($histRows($id1)) === 2 && has($r->body, 'just updated by someone else'), snippet($r->body));
$r = $update($S, $id1, 'advance', 'Under Review', 'Documents are being prepared by the secretary.', ['target' => 'Completed', 'status' => 'Completed']);
check('Staff move to "Processing" with their own remark; extra "target/status" fields are ignored (no skipping steps)',
    $status($id1) === 'Processing' && $histRows($id1)[2]['remark'] === 'Documents are being prepared by the secretary.');
$r = $update($S, $id1, 'reject', 'Processing', str_repeat('x', 501));
check('A remark longer than 500 characters is refused', $status($id1) === 'Processing' && count($histRows($id1)) === 3 && has($r->body, '500 characters'));
$r = $update($S, $id1, 'remark', 'Processing', 'Just a note.');
check('The old "remark only" action no longer exists', $status($id1) === 'Processing' && count($histRows($id1)) === 3);
$r = $update($S, $id1, 'fly-to-the-moon', 'Processing', 'x');
check('An unknown action is refused', $status($id1) === 'Processing' && count($histRows($id1)) === 3);
$r = $update($S, $id1, 'advance', 'Processing');
check('Staff move to "Ready for Pickup"', $status($id1) === 'Ready for Pickup');
$r = $A->get("/resident/request.php?id=$id1");
check('The resident is told the document is ready for pickup', has($r->body, 'Ready for pickup') && has($r->body, 'pickup-notice') && has($r->body, 'barangay hall'));
check('The resident dashboard also announces it', has($A->get('/resident/dashboard.php')->body, 'ready for pickup') && has($A->body, $ref1));
$r = $update($S, $id1, 'advance', 'Ready for Pickup');
check('Staff move to "Completed"', $status($id1) === 'Completed' && count($histRows($id1)) === 5);
$r = $update($S, $id1, 'advance', 'Completed');
check('A completed request cannot move forward', $status($id1) === 'Completed' && count($histRows($id1)) === 5 && has($r->body, 'last step'));
$r = $update($S, $id1, 'correct', 'Completed', '');
check('A correction needs an explanation', $status($id1) === 'Completed' && has($r->body, 'explain the correction'));
$r = $update($S, $id1, 'correct', 'Completed', 'Resident did not collect the document yet.');
$h = $histRows($id1);
check('A correction moves back one step and is marked as a correction in the history', $status($id1) === 'Ready for Pickup' && $h[5]['entry_type'] === 'correction' && count($h) === 6);
$update($S, $id1, 'advance', 'Ready for Pickup', 'Document received by the resident.');
$h = $histRows($id1);
check('Every earlier history row is still there, in order (nothing was overwritten)', count($h) === 7 && $h[0]['status'] === 'Submitted' && $h[1]['status'] === 'Under Review' && $h[6]['status'] === 'Completed');
$ids = array_column($h, 'id'); $sorted = $ids; sort($sorted);
check('History ids and times only increase (chronological)', $ids === $sorted);

$r = $A->get("/resident/history.php?id=$id1");
$pos = [];
foreach (['Request received.', 'Request is being checked.', 'Documents are being prepared by the secretary.', 'Resident did not collect the document yet.', 'Document received by the resident.'] as $txt) { $pos[] = strpos($r->body, $txt); }
check('The resident sees the full history with every remark, newest first', !in_array(false, $pos, true) && $pos === array_values(array_filter($pos)) && $pos === (function ($p) { rsort($p); return $p; })($pos));
check('The resident history does NOT show which staff member made a change', !has($r->body, 'Updated by') && !has($r->body, 'Sam Staff'));
check('The staff history shows who made each change', has($S->get("/staff/history.php?id=$id1")->body, 'Updated by') && has($S->body, 'Sam Staff'));

// ---- Reject (staff) and cancel (resident) ----------------------------------
$mk = function (string $ref, string $st) use ($pdo, $uidA, $sidClear) {
    $pdo->prepare("INSERT INTO service_requests (reference_number,user_id,service_id,service_type,applicant_name,applicant_address,applicant_contact,purpose,request_data,status) VALUES (?,?,?,'Barangay Clearance','Ana','Addr','09171234567','Test','[]',?)")->execute([$ref, $uidA, $sidClear, $st]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO request_history (request_id,status,entry_type,remark,changed_by) VALUES (?,?,'submitted','Request received.',?)")->execute([$id, 'Submitted', $uidA]);
    return $id;
};
$idRej = $mk('BR-2098-00001', 'Under Review');
$r = $update($S, $idRej, 'reject', 'Under Review', '');
check('Staff must give a reason to reject a request', $status($idRej) === 'Under Review' && has($r->body, 'explain why'));
$r = $update($S, $idRej, 'reject', 'Under Review', 'The address is outside our barangay.');
$h = $histRows($idRej);
check('Staff can reject a request with a reason, saved in the history', $status($idRej) === 'Rejected' && end($h)['status'] === 'Rejected' && end($h)['remark'] === 'The address is outside our barangay.');
$r = $A->get("/resident/request.php?id=$idRej");
check('The resident sees that it was rejected, with the reason, and no progress tracker', has($r->body, 'was rejected') && has($r->body, 'outside our barangay') && !has($r->body, 'class="tracker"') && !has($r->body, 'Cancel this request'));
$r = $update($S, $idRej, 'advance', 'Rejected');
check('A rejected request is closed: staff cannot change it any more', $status($idRej) === 'Rejected' && count($histRows($idRej)) === 2);
check('The staff page of a closed request has no update form', !has($S->get("/staff/request.php?id=$idRej")->body, 'name="expected_status"'));
$idReady = $mk('BR-2098-00002', 'Ready for Pickup');
$r = $update($S, $idReady, 'reject', 'Ready for Pickup', 'Too late');
check('A request that is ready for pickup cannot be rejected', $status($idReady) === 'Ready for Pickup');

$cancel = function (Client $who, int $id, string $expected, string $reason = '') {
    $who->get("/resident/request.php?id=$id");
    return $who->post("/resident/request.php?id=$id", ['csrf_token' => $who->csrf(), 'action' => 'cancel', 'expected_status' => $expected, 'reason' => $reason]);
};
$idCan = $mk('BR-2098-00003', 'Submitted');
check('The resident sees a cancel option while the request is Submitted', has($A->get("/resident/request.php?id=$idCan")->body, 'Cancel this request'));
$cancel($A, $idCan, 'Submitted', 'I applied by mistake.');
$h = $histRows($idCan);
check('A resident can cancel a Submitted request; the reason is saved in the history', $status($idCan) === 'Cancelled' && end($h)['status'] === 'Cancelled' && end($h)['remark'] === 'I applied by mistake.' && (int) end($h)['changed_by'] === $uidA);
$idCan2 = $mk('BR-2098-00004', 'Under Review');
$cancel($A, $idCan2, 'Under Review');
$h = $histRows($idCan2);
check('A resident can cancel an Under Review request (reason optional)', $status($idCan2) === 'Cancelled' && end($h)['remark'] === 'Cancelled by the resident.');
$idProc = $mk('BR-2098-00005', 'Processing');
check('There is no cancel option once staff are processing it', !has($A->get("/resident/request.php?id=$idProc")->body, 'Cancel this request'));
$cancel($A, $idProc, 'Processing');
check('Cancelling a Processing request is refused', $status($idProc) === 'Processing' && count($histRows($idProc)) === 1);
$cancel($B, $idCan2, 'Cancelled');
$A->get("/resident/request.php?id=$idCan2");
$cancel($B, $idProc, 'Processing');
check('A resident cannot cancel another resident\'s request', $status($idProc) === 'Processing');
$r = $update($S, $idCan, 'advance', 'Cancelled');
check('A cancelled request is closed: staff cannot change it', $status($idCan) === 'Cancelled');
check('Cancelled and Rejected can be filtered on the staff list', has($S->get('/staff/requests.php?status=Cancelled')->body, 'BR-2098-00003') && !has($S->body, 'BR-2098-00005'));
$bad11 = true;
foreach (['0917123456', '091712345678', '0917 123 4567', '0917abc4567', '+639171234567'] as $num) {
    $bad11 = $bad11 && has($anon->form('/register.php', '/register.php', ['contact_number' => $num] + $base_ok)->body, 'exactly 11 digits');
}
check('A contact number must be exactly 11 digits (too short, too long, spaces, letters and +63 are all refused)', $bad11);
$r = $A->get("/resident/request.php?id=$id1");
check('Completed requests show "Completed" and the full tracker', has($r->body, 'Completed') && !has($r->body, 'pickup-notice'));
check('The staff dashboard counts changed accordingly', pipelineCounts($S->get('/staff/dashboard.php')->body) === $dbCounts(null));

// XSS in a staff remark
$update($S, $id2, 'advance', 'Submitted', '<b>bold</b><script>alert(2)</script>');
$r = $A->get("/resident/request.php?id=$id2");
check('Script text in a staff remark is shown as harmless text', has($r->body, '&lt;script&gt;') && !has($r->body, '<script>alert(2)'));

// CSRF on the staff form
$S->get("/staff/request.php?id=$id3");
$r = $S->post("/staff/request.php?id=$id3", ['action' => 'advance', 'expected_status' => 'Submitted', 'remark' => '']);
check('A staff update without a CSRF token is refused (403)', $r->code === 403 && $status($id3) === 'Submitted');
$r = $S->post("/staff/request.php?id=$id3", ['csrf_token' => 'forged', 'action' => 'advance', 'expected_status' => 'Submitted']);
check('A staff update with a wrong CSRF token is refused (403)', $r->code === 403 && $status($id3) === 'Submitted');

// Two staff members at the same moment
$S->get("/staff/request.php?id=$id3"); $S2->get("/staff/request.php?id=$id3");
$res = parallelPost([
    ['client' => $S,  'path' => "/staff/request.php?id=$id3", 'data' => ['csrf_token' => $S->csrf(),  'action' => 'advance', 'expected_status' => 'Submitted', 'remark' => 'From staff one']],
    ['client' => $S2, 'path' => "/staff/request.php?id=$id3", 'data' => ['csrf_token' => $S2->csrf(), 'action' => 'advance', 'expected_status' => 'Submitted', 'remark' => 'From staff two']],
]);
check('Two staff members updating the same request at the same moment: only ONE update is applied',
    $status($id3) === 'Under Review' && count($histRows($id3)) === 2, $status($id3) . ' / ' . count($histRows($id3)) . ' rows');

// ============================================================================
section('11. Services can be managed by staff only (7.7)');
// ============================================================================
$r = $S->get('/staff/services.php');
check('Staff see the services list', $r->code === 200 && has($r->body, 'Barangay Clearance') && has($r->body, 'Add a service'));
$r = $S->form('/staff/service_edit.php', '/staff/service_edit.php', ['name' => 'Certificate of Solo Parent', 'description' => 'For solo parents.', 'sort_order' => '70', 'is_active' => '1']);
$newSid = serviceId('Certificate of Solo Parent');
check('Staff add a new service without changing any code', $newSid > 0 && has($r->path, 'service_fields.php'), $r->path);
$r = $S->form('/staff/service_edit.php', '/staff/service_edit.php', ['name' => 'Certificate of Solo Parent', 'sort_order' => '70']);
check('A duplicate service name is refused', has($r->body, 'already uses this name'));
$r = $S->form("/staff/field_edit.php?service=$newSid", "/staff/field_edit.php?service=$newSid", ['label' => 'Number of children', 'field_type' => 'number', 'is_required' => '1', 'min_value' => '1', 'max_value' => '15', 'sort_order' => '10']);
check('Staff add a question to the service', (int) val('SELECT COUNT(*) FROM service_fields WHERE service_id = ?', [$newSid]) === 1);
$r = $S->form("/staff/field_edit.php?service=$newSid", "/staff/field_edit.php?service=$newSid", ['label' => 'Pick', 'field_type' => 'select', 'options' => 'OnlyOne', 'sort_order' => '20']);
check('A choice question needs at least two choices', has($r->body, 'between 2 and 30 choices'));
$formBody = $B->get("/resident/request_new.php?service=$newSid")->body;   // (the client object is reused, so keep the body)
$listHasNew = has($B->get('/resident/services.php')->body, 'Certificate of Solo Parent');
check('The new service and its question appear for residents', $listHasNew && has($formBody, 'Number of children'));
$pg = "/resident/request_new.php?service=$newSid";
$r = $B->form($pg, $pg, ['applicant_name' => 'Ben Reyes', 'applicant_contact' => '09181234567', 'applicant_address' => '45 Mabini Avenue', 'purpose' => 'Benefits', 'f_number_of_children' => '99']);
check('The new question is validated like the built-in ones', has($r->body, 'whole number from 1 to 15'));
$r = $B->form($pg, $pg, ['applicant_name' => 'Ben Reyes', 'applicant_contact' => '09181234567', 'applicant_address' => '45 Mabini Avenue', 'purpose' => 'Benefits', 'f_number_of_children' => '2']);
check('A request for the new service can be submitted', $r->ref() !== '' && (int) val('SELECT COUNT(*) FROM service_requests WHERE service_id = ?', [$newSid]) === 1);
$S->get('/staff/services.php');
$r = $S->post('/staff/services.php', ['csrf_token' => $S->csrf(), 'service_id' => (string) $newSid, 'active' => '0']);
check('Staff can hide a service', (int) val('SELECT is_active FROM services WHERE id = ?', [$newSid]) === 0);
$r = $B->get($pg);
check('A hidden service cannot be requested any more', $r->path === '/resident/services.php' && !has($B->get('/resident/services.php')->body, 'Certificate of Solo Parent'));
check('...but the old request is still visible to its owner', has($B->get('/resident/requests.php')->body, 'Certificate of Solo Parent'));
$r = $B->post('/staff/services.php', ['csrf_token' => $B->csrf() ?: 'x', 'service_id' => (string) $sidClear, 'active' => '0']);
check('A resident cannot change services (403) and nothing changed', $r->code === 403 && (int) val('SELECT is_active FROM services WHERE id = ?', [$sidClear]) === 1);
$lcId = serviceId('Loan Clearance');
check('Loan Clearance exists with the details the barangay asks for', $lcId > 0 && (int) val("SELECT COUNT(*) FROM service_fields WHERE service_id = ? AND field_key IN ('age','gender','civil_status','lending_institution')", [$lcId]) === 4);
check('Every service has "what to bring" filled in except Other', (int) val("SELECT COUNT(*) FROM services WHERE name <> 'Other Barangay Service' AND (pickup_requirements IS NULL OR pickup_requirements = '') AND name NOT LIKE 'Certificate of Solo%'") === 0);
$lsBody = $B->get('/resident/services.php')->body;
check('Residents see what to bring on the services list', has($lsBody, 'Bring when you pick it up') && has($lsBody, 'DTI registration'));
$S->get("/staff/service_edit.php?id=$sidClear");
$r = $S->form("/staff/service_edit.php?id=$sidClear", "/staff/service_edit.php?id=$sidClear", ['name' => 'Barangay Clearance', 'description' => 'Test description.', 'pickup_requirements' => 'Valid ID and Cedula', 'sort_order' => '10', 'is_active' => '1']);
check('Staff can change what to bring', val('SELECT pickup_requirements FROM services WHERE id = ?', [$sidClear]) === 'Valid ID and Cedula');

// ============================================================================
section('12. Database integrity and the optional history guard (7.2, 7.4)');
// ============================================================================
$fails = function (callable $f): bool { try { $f(); return false; } catch (PDOException $e) { return true; } };
check('Database refuses a request for a user that does not exist (no orphans)', $fails(function () use ($pdo, $sidClear) {
    $pdo->prepare("INSERT INTO service_requests (reference_number,user_id,service_id,service_type,applicant_name,applicant_address,applicant_contact,purpose,request_data) VALUES ('BR-2099-00001',999999,?, 'x','x','x','x','x','[]')")->execute([$sidClear]); }));
check('Database refuses a history row for a request that does not exist', $fails(function () use ($pdo, $staffId) {
    $pdo->prepare("INSERT INTO request_history (request_id,status,entry_type,changed_by) VALUES (999999,'Submitted','submitted',?)")->execute([$staffId]); }));
check('Database refuses a duplicate reference number', $fails(function () use ($pdo, $uidA, $sidClear, $ref1) {
    $pdo->prepare("INSERT INTO service_requests (reference_number,user_id,service_id,service_type,applicant_name,applicant_address,applicant_contact,purpose,request_data) VALUES (?,?,?,'x','x','x','x','x','[]')")->execute([$ref1, $uidA, $sidClear]); }));
check('Database refuses a duplicate email', $fails(function () { addUser('Dup', 'ana.delapena@test.example', 'Passw0rd1'); }));
check('Database refuses a status that is not one of the five', $fails(function () use ($pdo, $id1) { $pdo->prepare("UPDATE service_requests SET status = 'Approved' WHERE id = ?")->execute([$id1]); }));
check('A user who has requests cannot be deleted (RESTRICT)', $fails(function () use ($pdo, $uidA) { $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uidA]); }));
check('A request that has history cannot be deleted (history is kept)', $fails(function () use ($pdo, $id1) { $pdo->prepare('DELETE FROM service_requests WHERE id = ?')->execute([$id1]); }));

try {
    runSqlFile($pdo, $ROOT . '/database/optional_protect_history.sql');
    $guard = true;
} catch (Throwable $e) { $guard = false; }
if ($guard) {
    check('Optional triggers: UPDATE of a history row is blocked by the database', $fails(function () use ($pdo, $id1) { $pdo->prepare("UPDATE request_history SET remark = 'tampered' WHERE request_id = ?")->execute([$id1]); }));
    check('Optional triggers: DELETE of a history row is blocked by the database', $fails(function () use ($pdo, $id1) { $pdo->prepare('DELETE FROM request_history WHERE request_id = ?')->execute([$id1]); }));
    $update($S, $id3, 'advance', 'Under Review');
    check('The application still works normally with the triggers installed', $status($id3) === 'Processing');
} else {
    skip('Optional history triggers', 'this MySQL user may not create triggers');
}

// ============================================================================
section('13. Demo data script');
// ============================================================================
[$code, $out] = runTool($ROOT . '/tools/seed_demo.php', [], $appEnv);
check('tools/seed_demo.php creates the demo accounts and requests', $code === 0 && (int) val("SELECT COUNT(*) FROM users WHERE email LIKE '%@demo.barangayserve.test'") === 3, $out);
check('Demo requests were created through the real application code (reference numbers and history)', (int) val("SELECT COUNT(*) FROM service_requests r JOIN users u ON u.id = r.user_id WHERE u.email LIKE '%@demo.barangayserve.test'") === 5
    && (int) val("SELECT COUNT(*) FROM request_history h JOIN service_requests r ON r.id = h.request_id JOIN users u ON u.id = r.user_id WHERE u.email LIKE '%@demo.barangayserve.test'") === 15);
[$code, $out] = runTool($ROOT . '/tools/seed_demo.php', [], $appEnv);
check('Running the demo script a second time refuses and creates no duplicates', $code !== 0 && (int) val("SELECT COUNT(*) FROM users WHERE email LIKE '%@demo.barangayserve.test'") === 3);
$D = new Client($base);
$r = $D->form('/login.php', '/login.php', ['email' => 'resident1@demo.barangayserve.test', 'password' => 'DemoResident123']);
check('The documented demo resident account can log in', $r->path === '/resident/dashboard.php');
$r = (new Client($base))->form('/staff/login.php', '/staff/login.php', ['email' => 'staff@demo.barangayserve.test', 'password' => 'DemoStaff123']);
check('The documented demo staff account can log in', $r->path === '/staff/dashboard.php');

// ============================================================================
section('14. Speed with a larger database (SRS 5.2)');
// ============================================================================
$pdo->beginTransaction();
$ins  = $pdo->prepare("INSERT INTO service_requests (reference_number,user_id,service_id,service_type,applicant_name,applicant_address,applicant_contact,purpose,request_data,status,submitted_at) VALUES (?,?,?,?,?,?,?,?,'[]',?,?)");
$insH = $pdo->prepare("INSERT INTO request_history (request_id,status,entry_type,remark,changed_by,created_at) VALUES (?,?,?,?,?,?)");
$statuses = ['Submitted', 'Under Review', 'Processing', 'Ready for Pickup', 'Completed'];
for ($i = 1; $i <= 3000; $i++) {
    $st = $statuses[$i % 5]; $when = date('Y-m-d H:i:s', time() - $i * 600);
    $ins->execute([sprintf('BR-2024-%05d', $i), $uidA, $sidClear, 'Barangay Clearance', 'Ana Dela Peña', 'Test Address', '09171234567', 'Bulk test', $st, $when]);
    $insH->execute([(int) $pdo->lastInsertId(), $st, 'submitted', 'bulk', $staffId, $when]);
}
$pdo->commit();
$bigId = (int) val("SELECT id FROM service_requests WHERE reference_number = 'BR-2024-00001'");
$pdo->beginTransaction();
for ($i = 0; $i < 60; $i++) { $insH->execute([$bigId, 'Processing', 'remark', "Remark number $i", $staffId, date('Y-m-d H:i:s', time() - 100000 + $i * 60)]); }
$pdo->commit();
$tot = (int) val('SELECT COUNT(*) FROM service_requests');
$tm = function (string $label, Client $c, string $path, float $limit) {
    $c->get($path); $t = $c->time;
    check(sprintf('%s: %.2f s (limit %.0f s)', $label, $t, $limit), $c->code === 200 && $t < $limit);
};
echo "  ($tot requests in the test database)\n";
$tm('Staff dashboard', $S, '/staff/dashboard.php', 3);
$tm('Staff request list', $S, '/staff/requests.php', 3);
$tm('Staff search by name', $S, '/staff/requests.php?q=dela', 3);
$tm('Staff filter by status and date', $S, '/staff/requests.php?status=Processing&from=2020-01-01', 3);
$tm('Resident request list (3000+ own requests)', $A, '/resident/requests.php', 3);
$tm('Resident dashboard', $A, '/resident/dashboard.php', 3);
$tm('Request history with 61 entries', $S, "/staff/history.php?id=$bigId", 3);
$t0 = microtime(true); $X = new Client($base); $X->form('/login.php', '/login.php', ['email' => $emailA, 'password' => $pw]); $loginTime = microtime(true) - $t0;
check(sprintf('Log in (including the password check): %.2f s (limit 3 s)', $loginTime), $X->path === '/resident/dashboard.php' && $loginTime < 3);

// ============================================================================
section('15. Page quality: security policy, accessibility, mobile');
// ============================================================================
$problems = ['inline' => [], 'viewport' => [], 'h1' => [], 'label' => [], 'hash' => [], 'lang' => []];
foreach (Client::$pages as $key => $html) {
    if (preg_match('/\sstyle\s*=/i', $html) || preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $html) || preg_match('/\son[a-z]+\s*=\s*["\']/i', $html)) { $problems['inline'][] = $key; }
    if (!has($html, 'name="viewport"')) { $problems['viewport'][] = $key; }
    if (!has($html, '<html lang="en"')) { $problems['lang'][] = $key; }
    if (substr_count($html, '<h1') !== 1) { $problems['h1'][] = $key; }
    if (has($html, '$2y$')) { $problems['hash'][] = $key; }
    if (preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $el) {
            if (stripos($el[2], 'type="hidden"') !== false || !preg_match('/\sid="([^"]+)"/', $el[2], $idm)) { continue; }
            if (!has($html, 'for="' . $idm[1] . '"')) { $problems['label'][] = $key . ' #' . $idm[1]; }
        }
    }
}
$nPages = count(Client::$pages);
echo "  ($nPages pages were collected during the tests)\n";
check('No page uses inline scripts, inline styles, or on...= attributes (works with the strict security policy)', !$problems['inline'], implode('; ', array_slice($problems['inline'], 0, 3)));
check('Every page has the mobile viewport tag', !$problems['viewport'], implode('; ', array_slice($problems['viewport'], 0, 3)));
check('Every page declares its language', !$problems['lang']);
check('Every page has exactly one main heading (h1)', !$problems['h1'], implode('; ', array_slice($problems['h1'], 0, 3)));
check('Every form field has a label', !$problems['label'], implode('; ', array_slice($problems['label'], 0, 3)));
check('No page ever shows a password hash', !$problems['hash']);
$all = implode("\n", Client::$pages);
check('No page shows raw SQL or PHP error text', !preg_match('/SQLSTATE|PDOException|Stack trace|Warning:|Notice:|Fatal error|Parse error/i', $all));

// ============================================================================
section('16. Friendly error when the database is down');
// ============================================================================
if (!empty($cfg['debug'])) {
    skip('Error page without technical details', 'config.php has debug = true; set it to false to run this test');
} else {
    $badPort = (int) substr((string) strrchr((string) stream_socket_get_name($s2 = stream_socket_server('tcp://127.0.0.1:0', $e1, $e2), false), ':'), 1); fclose($s2);
    $badProc = startServer($ROOT, $badPort, ['BS_DB_PASS' => 'definitely-the-wrong-password-123']);
    if ($badProc) {
        $r = (new Client("http://127.0.0.1:$badPort"))->form('/login.php', '/login.php', ['email' => 'someone@test.example', 'password' => 'whatever123'], false);   // a log-in needs the database
        check('With an unreachable database the visitor sees a friendly 500 page', $r->code === 500 && has($r->body, 'Something went wrong'));
        check('...without the database password, user name, SQL, or file paths', !preg_match('/definitely-the-wrong|SQLSTATE|Access denied|PDOException|using password|' . preg_quote($ROOT, '/') . '/i', $r->body));
        proc_terminate($badProc);
    } else { skip('Bad-database server', 'could not start a second server'); }
}

// ============================================================================
echo "\n----------------------------------------------\n";
echo "Passed: $passed   Failed: $failed   Skipped: $skipped\n";
if ($failed > 0) { echo "Failed tests:\n"; foreach ($failures as $f) { echo " - $f\n"; } }
echo $failed === 0 ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n";
exit($failed === 0 ? 0 : 1);
