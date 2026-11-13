<?php
/**
 * tools/create_staff.php: create an authorized barangay STAFF account.
 *
 * WHY A COMMAND-LINE TOOL?
 * The public registration page can only create residents. Nobody can become staff by
 * filling in a web form or by editing a form field. Staff accounts are made here, by
 * the person who manages the server (command line only; the web server blocks this folder).
 *
 * USAGE (from the project folder):
 *   php tools/create_staff.php --name="Maria Santos" --email="maria@barangay.example"
 *
 * Optional: --contact="0917 123 4567"  --address="Barangay Hall"  --password="..."
 * If you leave out --password, the script asks for it (it is better not to type
 * passwords in the command, because the command stays in the terminal history).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This tool can only be run from the command line.\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';

$options = getopt('', ['name:', 'email:', 'contact::', 'address::', 'password::', 'help']);
if (isset($options['help']) || empty($options['name']) || empty($options['email'])) {
    echo "Usage: php tools/create_staff.php --name=\"Full Name\" --email=\"staff@example.com\" [--contact=\"...\"] [--address=\"...\"] [--password=\"...\"]\n";
    exit(isset($options['help']) ? 0 : 1);
}

$name    = clean_text(trim((string) $options['name']));
$email   = normalize_email((string) $options['email']);
$contact = clean_text(trim((string) ($options['contact'] ?? '0000 000 0000')));
$address = clean_text(trim((string) ($options['address'] ?? 'Barangay Hall')));

/** Ask for a password without showing it on screen (falls back to visible input if stty is missing). */
function ask_password(string $prompt): string
{
    echo $prompt;
    $hidden = function_exists('shell_exec') && stripos(PHP_OS, 'WIN') !== 0 && @shell_exec('stty -echo 2>/dev/null') !== null;
    $line = fgets(STDIN);
    if ($hidden) {
        @shell_exec('stty echo 2>/dev/null');
        echo "\n";
    }
    return $line === false ? '' : rtrim($line, "\r\n");
}

$password = isset($options['password']) ? (string) $options['password'] : ask_password('Password for the new staff account: ');
if (!isset($options['password'])) {
    $again = ask_password('Type the password again: ');
    if ($again !== $password) {
        fwrite(STDERR, "The two passwords are not the same. No account was created.\n");
        exit(1);
    }
}

$problems = array_filter([
    validate_full_name($name),
    validate_email($email),
    validate_contact_number($contact),
    validate_address($address),
    validate_password($password),
]);
if (!$problems && email_is_taken($email)) {
    $problems[] = 'An account with this email address already exists.';
}
if ($problems) {
    fwrite(STDERR, "No account was created:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, ' - ' . $problem . "\n");
    }
    exit(1);
}

$id = create_user($name, $email, $contact, $address, $password, 'staff');
echo "Staff account created (id $id): $name <$email>\n";
echo "They can now log in at /staff/login.php\n";
