<?php
/**
 * tools/seed_demo.php: fill the database with CLEARLY LABELLED demo data for a presentation.
 *
 * - Every demo person is named "Demo ..." and uses an email ending in @demo.barangayserve.test
 *   (a reserved, non-existent domain). No real person's information is used.
 * - The demo requests are created with the SAME functions the website uses, so reference
 *   numbers, history lines, and dates are real database records.
 * - The passwords below are for demonstration only. NEVER use this script or these
 *   passwords on a real, public website.
 *
 * USAGE:   php tools/seed_demo.php
 * It stops if the demo accounts already exist (it never creates duplicates).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This tool can only be run from the command line.\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';

const DEMO_DOMAIN = '@demo.barangayserve.test';
const DEMO_STAFF_PASSWORD    = 'DemoStaff123';
const DEMO_RESIDENT_PASSWORD = 'DemoResident123';

$accounts = [
    ['Demo Staff Member',    'staff'  . DEMO_DOMAIN,      'staff',    '09000000001', 'Barangay Hall (demo)',              DEMO_STAFF_PASSWORD],
    ['Demo Resident One',    'resident1' . DEMO_DOMAIN,   'resident', '09000000002', '123 Sample Street, Purok 1 (demo)',  DEMO_RESIDENT_PASSWORD],
    ['Demo Resident Two',    'resident2' . DEMO_DOMAIN,   'resident', '09000000003', '456 Example Road, Purok 2 (demo)',   DEMO_RESIDENT_PASSWORD],
];

foreach ($accounts as $a) {
    if (email_is_taken($a[1])) {
        fwrite(STDERR, "The demo accounts already exist ({$a[1]}). Nothing was changed.\n");
        exit(1);
    }
}

/** Find a service by name. */
function demo_service(string $name): array
{
    $stmt = db()->prepare('SELECT * FROM services WHERE name = ?');
    $stmt->execute([$name]);
    $service = $stmt->fetch();
    if (!$service) {
        fwrite(STDERR, "Service not found: $name. Import database/barangayserve.sql first.\n");
        exit(1);
    }
    return $service;
}

/**
 * Submit a demo request the same way the website does (same functions), then let the
 * demo staff member move it forward $steps times. Returns a line describing the result.
 */
function demo_request(int $residentId, string $serviceName, string $purpose, array $input, int $staffId, int $steps): string
{
    $resident = find_user_by_id($residentId);
    $service  = demo_service($serviceName);
    [, $answers] = validate_service_answers(get_service_fields((int) $service['id']), $input);

    $result = create_service_request(
        $residentId, $service,
        ['name' => $resident['full_name'], 'address' => $resident['address'], 'contact' => $resident['contact_number']],
        $purpose, $answers, bin2hex(random_bytes(16))
    );

    $status = 'Submitted';
    for ($i = 0; $i < $steps; $i++) {
        $update = staff_update_request((int) $result['id'], $staffId, 'advance', $status, suggested_remark((string) next_status($status)));
        if (!$update['ok']) {
            throw new RuntimeException($update['error']);
        }
        $status = (string) $update['status'];
    }
    return $result['reference_number'] . ' ' . $serviceName . ' (' . $status . ')';
}

// The demo requests. Each one is checked against the CURRENT service questions in the database
// BEFORE any account is created, so a mismatch stops the script without leaving half-made data.
$plan = [
    // [resident email, service, purpose, answers, how many times staff move it forward]
    ['resident1' . DEMO_DOMAIN, 'Barangay Clearance', 'Employment requirement (demo)',
        ['f_date_of_birth' => '1999-04-12', 'f_gender' => 'Male', 'f_civil_status' => 'Single', 'f_years_of_residency' => '12'], 3],
    ['resident1' . DEMO_DOMAIN, 'Certificate of Residency', 'School enrollment (demo)',
        ['f_age' => '27', 'f_gender' => 'Male', 'f_civil_status' => 'Single', 'f_years_of_residency' => '8', 'f_residence_type' => 'Homeowner'], 2],
    ['resident1' . DEMO_DOMAIN, 'Certificate of Good Moral Character', 'Scholarship application (demo)',
        ['f_age' => '27', 'f_gender' => 'Male', 'f_civil_status' => 'Single', 'f_years_of_residency' => '8', 'f_requesting_organization' => 'Sample University (demo)'], 0],
    ['resident2' . DEMO_DOMAIN, 'Certificate of Indigency', 'Medical assistance (demo)',
        ['f_age' => '34', 'f_gender' => 'Female', 'f_household_members' => '5', 'f_assistance_needed' => 'Medical or hospital'], 4],
    ['resident2' . DEMO_DOMAIN, 'Business-related Request', 'New sari-sari store (demo)',
        ['f_request_kind' => 'New business clearance', 'f_business_name' => 'Demo Sari-Sari Store',
         'f_business_address' => '456 Example Road (demo)', 'f_nature_of_business' => 'Retail store'], 1],
];
foreach ($plan as $item) {
    $service = demo_service($item[1]);
    [$errors] = validate_service_answers(get_service_fields((int) $service['id']), $item[3]);
    if ($errors) {
        fwrite(STDERR, 'The demo data does not match the questions of "' . $item[1] . '": ' . implode(' ', $errors) . "\n"
            . "Nothing was created. Re-import database/barangayserve.sql into an empty database and try again.\n");
        exit(1);
    }
}

$ids = [];
foreach ($accounts as $a) {
    // create_user(full name, email, contact, address, password, role)
    $ids[$a[1]] = create_user($a[0], $a[1], $a[3], $a[4], $a[5], $a[2]);
}
$staffId = $ids['staff' . DEMO_DOMAIN];

$created = [];
foreach ($plan as $item) {
    $created[] = demo_request($ids[$item[0]], $item[1], $item[2], $item[3], $staffId, $item[4]);
}

echo "Demo data created.\n\n";
echo "Staff log in     (/staff/login.php) : staff" . DEMO_DOMAIN . "     / " . DEMO_STAFF_PASSWORD . "\n";
echo "Resident log in  (/login.php)       : resident1" . DEMO_DOMAIN . " / " . DEMO_RESIDENT_PASSWORD . "\n";
echo "Resident log in  (/login.php)       : resident2" . DEMO_DOMAIN . " / " . DEMO_RESIDENT_PASSWORD . "\n\n";
echo "Demo requests:\n";
foreach ($created as $line) {
    echo " - $line\n";
}
