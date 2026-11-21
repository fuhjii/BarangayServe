<?php
/**
 * services.php: the barangay services and the extra questions each one asks.
 *
 * Services and their questions live in the database (tables `services` and
 * `service_fields`), so barangay staff can change them in the app (Staff > Services)
 * without touching any code. This is how SRS 7.7 (Scalability) is met.
 */

/** The kinds of questions staff can add to a service. */
const FIELD_TYPES = [
    'text'     => 'Short text',
    'textarea' => 'Long text',
    'number'   => 'Number',
    'date'     => 'Date',
    'select'   => 'Choose one from a list',
];

// ---------------------------------------------------------------------------
// Reading services
// ---------------------------------------------------------------------------

/** Services residents may request (inactive ones are hidden). */
function get_active_services(): array
{
    return db()->query(
        'SELECT id, name, description, pickup_requirements FROM services WHERE is_active = 1 ORDER BY sort_order, name'
    )->fetchAll();
}

function get_service(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** All services for the staff page, with how many questions and requests each has. */
function get_services_for_staff(): array
{
    return db()->query(
        'SELECT s.*,
                (SELECT COUNT(*) FROM service_fields f   WHERE f.service_id = s.id) AS field_count,
                (SELECT COUNT(*) FROM service_requests r WHERE r.service_id = s.id) AS request_count
           FROM services s
          ORDER BY s.sort_order, s.name'
    )->fetchAll();
}

function get_service_fields(int $serviceId): array
{
    $stmt = db()->prepare('SELECT * FROM service_fields WHERE service_id = ? ORDER BY sort_order, id');
    $stmt->execute([$serviceId]);
    return $stmt->fetchAll();
}

function get_service_field(int $fieldId, int $serviceId): ?array
{
    $stmt = db()->prepare('SELECT * FROM service_fields WHERE id = ? AND service_id = ?');
    $stmt->execute([$fieldId, $serviceId]);
    return $stmt->fetch() ?: null;
}

/** The choices of a "select" question (stored one per line). */
function field_options(array $field): array
{
    $lines = preg_split('/\n/', (string) ($field['options'] ?? '')) ?: [];
    return array_values(array_filter(array_map('trim', $lines), 'strlen'));
}

// ---------------------------------------------------------------------------
// Checking a resident's answers (used when a request is submitted)
// ---------------------------------------------------------------------------

/**
 * Validate the answers to a service's questions.
 * The form fields are named f_<field_key>.
 * Returns [$errors, $answers]:
 *   $errors  = ['field_key' => 'message', ...]
 *   $answers = [['key'=>..., 'label'=>..., 'type'=>..., 'value'=>...], ...]  (saved as JSON)
 */
function validate_service_answers(array $fields, array $input): array
{
    $errors  = [];
    $answers = [];
    foreach ($fields as $field) {
        $key   = $field['field_key'];
        $raw   = $input['f_' . $key] ?? '';
        $value = is_string($raw) ? clean_text(trim($raw)) : '';

        if ($value === '') {
            if ((int) $field['is_required'] === 1) {
                $errors[$key] = 'Please fill in: ' . $field['label'] . '.';
            }
        } else {
            $problem = validate_field_value($field, $value);
            if ($problem !== null) {
                $errors[$key] = $problem;
            }
        }
        $answers[] = ['key' => $key, 'label' => $field['label'], 'type' => $field['field_type'], 'value' => $value];
    }
    return [$errors, $answers];
}

/** Check one non-empty answer against its question. */
function validate_field_value(array $field, string $value): ?string
{
    $label = $field['label'];
    switch ($field['field_type']) {
        case 'text':
            return text_length($value) > 150 ? "$label must not be longer than 150 characters." : null;

        case 'textarea':
            return text_length($value) > 500 ? "$label must not be longer than 500 characters." : null;

        case 'number':
            $min = $field['min_value'] !== null ? (int) $field['min_value'] : 0;
            $max = $field['max_value'] !== null ? (int) $field['max_value'] : 1000000;
            if (!preg_match('/^-?[0-9]{1,9}$/', $value) || (int) $value < $min || (int) $value > $max) {
                return "$label must be a whole number from $min to $max.";
            }
            return null;

        case 'date':
            if (!is_valid_date($value)) {
                return "$label must be a valid date.";
            }
            if ($value > date('Y-m-d') || $value < '1900-01-01') {
                return "$label cannot be in the future or before 1900.";
            }
            return null;

        case 'select':
            return in_array($value, field_options($field), true) ? null : "Please choose one of the listed options for $label.";
    }
    return "$label has an unknown question type.";
}

// ---------------------------------------------------------------------------
// Staff: add / edit services
// ---------------------------------------------------------------------------

/** Returns [$errors, $data] for the service form. $serviceId is 0 when adding a new service. */
function validate_service_form(array $input, int $serviceId = 0): array
{
    $errors = [];
    $name   = clean_text(trim((string) ($input['name'] ?? '')));
    $desc   = clean_text(trim((string) ($input['description'] ?? '')));
    $order  = trim((string) ($input['sort_order'] ?? '100'));
    $bring  = clean_text(trim((string) ($input['pickup_requirements'] ?? '')));

    if (text_length($name) < 3 || text_length($name) > 100) {
        $errors['name'] = 'The service name must be between 3 and 100 characters.';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM services WHERE name = ? AND id <> ?');
        $stmt->execute([$name, $serviceId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors['name'] = 'Another service already uses this name.';
        }
    }
    if (text_length($desc) > 255) {
        $errors['description'] = 'The description must not be longer than 255 characters.';
    }
    if (text_length($bring) > 255) {
        $errors['pickup_requirements'] = 'This must not be longer than 255 characters.';
    }
    if (!preg_match('/^[0-9]{1,4}$/', $order)) {
        $errors['sort_order'] = 'The display order must be a whole number from 0 to 9999.';
    }
    $data = ['name' => $name, 'description' => $desc, 'pickup_requirements' => $bring, 'sort_order' => (int) $order, 'is_active' => !empty($input['is_active']) ? 1 : 0];
    return [$errors, $data];
}

/** Insert (when $id is 0) or update a service. Returns the service id. */
function save_service(int $id, array $data): int
{
    $pdo = db();
    $now = now_sql();
    if ($id === 0) {
        $pdo->prepare('INSERT INTO services (name, description, pickup_requirements, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$data['name'], $data['description'], $data['pickup_requirements'] !== '' ? $data['pickup_requirements'] : null, $data['is_active'], $data['sort_order'], $now, $now]);
        return (int) $pdo->lastInsertId();
    }
    $pdo->prepare('UPDATE services SET name = ?, description = ?, pickup_requirements = ?, is_active = ?, sort_order = ?, updated_at = ? WHERE id = ?')
        ->execute([$data['name'], $data['description'], $data['pickup_requirements'] !== '' ? $data['pickup_requirements'] : null, $data['is_active'], $data['sort_order'], $now, $id]);
    return $id;
}

function set_service_active(int $id, bool $active): void
{
    db()->prepare('UPDATE services SET is_active = ?, updated_at = ? WHERE id = ?')->execute([$active ? 1 : 0, now_sql(), $id]);
}

// ---------------------------------------------------------------------------
// Staff: add / edit / remove the questions of a service
// ---------------------------------------------------------------------------

/** Returns [$errors, $data] for the question form. */
function validate_field_form(array $input): array
{
    $errors = [];
    $label  = clean_text(trim((string) ($input['label'] ?? '')));
    $type   = (string) ($input['field_type'] ?? '');
    $help   = clean_text(trim((string) ($input['help_text'] ?? '')));
    $order  = trim((string) ($input['sort_order'] ?? '10'));
    $min    = trim((string) ($input['min_value'] ?? ''));
    $max    = trim((string) ($input['max_value'] ?? ''));
    $options = '';

    if (text_length($label) < 3 || text_length($label) > 100) {
        $errors['label'] = 'The question must be between 3 and 100 characters.';
    }
    if (!isset(FIELD_TYPES[$type])) {
        $errors['field_type'] = 'Please choose a question type.';
    }
    if (text_length($help) > 200) {
        $errors['help_text'] = 'The hint must not be longer than 200 characters.';
    }
    if (!preg_match('/^[0-9]{1,4}$/', $order)) {
        $errors['sort_order'] = 'The display order must be a whole number from 0 to 9999.';
    }

    if ($type === 'select') {
        $lines = array_values(array_unique(array_filter(array_map('trim', preg_split('/\n/', clean_text((string) ($input['options'] ?? ''))) ?: []), 'strlen')));
        if (count($lines) < 2 || count($lines) > 30) {
            $errors['options'] = 'Enter between 2 and 30 choices, one per line.';
        } elseif (max(array_map('text_length', $lines)) > 80) {
            $errors['options'] = 'Each choice must not be longer than 80 characters.';
        }
        $options = implode("\n", $lines);
    }

    $minValue = $maxValue = null;
    if ($type === 'number') {
        foreach (['min' => $min, 'max' => $max] as $which => $text) {
            if ($text !== '' && !preg_match('/^-?[0-9]{1,9}$/', $text)) {
                $errors[$which . '_value'] = 'Enter a whole number or leave this empty.';
            }
        }
        if (!$errors) {
            $minValue = $min === '' ? null : (int) $min;
            $maxValue = $max === '' ? null : (int) $max;
            if ($minValue !== null && $maxValue !== null && $minValue > $maxValue) {
                $errors['max_value'] = 'The largest value must not be smaller than the smallest value.';
            }
        }
    }

    return [$errors, [
        'label'       => $label,
        'field_type'  => $type,
        'is_required' => !empty($input['is_required']) ? 1 : 0,
        'options'     => $options !== '' ? $options : null,
        'min_value'   => $minValue,
        'max_value'   => $maxValue,
        'help_text'   => $help !== '' ? $help : null,
        'sort_order'  => (int) $order,
    ]];
}

/** Make a short internal name such as "years_of_residency" from a question's label. */
function make_field_key(int $serviceId, string $label): string
{
    $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($label, 'UTF-8')), '_');
    $key = substr($key, 0, 40);
    if ($key === '' || !ctype_alpha($key[0])) {
        $key = 'question_' . $key;
    }
    $unique = $key;
    $count  = db()->prepare('SELECT COUNT(*) FROM service_fields WHERE service_id = ? AND field_key = ?');
    for ($i = 2; ; $i++) {
        $count->execute([$serviceId, $unique]);
        if ((int) $count->fetchColumn() === 0) {
            return $unique;
        }
        $unique = $key . '_' . $i;
    }
}

/** Add a question ($fieldId = 0) or update an existing one. */
function save_service_field(int $serviceId, int $fieldId, array $data): void
{
    $pdo = db();
    $values = [$data['label'], $data['field_type'], $data['is_required'], $data['options'], $data['min_value'], $data['max_value'], $data['help_text'], $data['sort_order']];
    if ($fieldId === 0) {
        $pdo->prepare(
            'INSERT INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute(array_merge([$serviceId, make_field_key($serviceId, $data['label'])], $values, [now_sql()]));
        return;
    }
    // The internal key (field_key) never changes, so old requests stay readable.
    $pdo->prepare(
        'UPDATE service_fields SET label = ?, field_type = ?, is_required = ?, options = ?, min_value = ?, max_value = ?, help_text = ?, sort_order = ?
          WHERE id = ? AND service_id = ?'
    )->execute(array_merge($values, [$fieldId, $serviceId]));
}

/** Removing a question does not affect old requests: they keep their own saved copy of the answers. */
function delete_service_field(int $serviceId, int $fieldId): void
{
    db()->prepare('DELETE FROM service_fields WHERE id = ? AND service_id = ?')->execute([$fieldId, $serviceId]);
}
