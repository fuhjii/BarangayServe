<?php
/**
 * db.php: the database connection.
 *
 * Use it like this:   $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
 *                     $stmt->execute([$id]);
 *
 * ALWAYS pass user input as parameters (the ? marks). Never put variables
 * directly inside the SQL text: that is how SQL injection happens.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $c   = (array) config('db', []);
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'] ?? '127.0.0.1',
        (int) ($c['port'] ?? 3306),
        $c['name'] ?? 'barangayserve',
        $c['charset'] ?? 'utf8mb4'
    );

    try {
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // errors become exceptions we can catch
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
        ]);
    } catch (PDOException $e) {
        // Log the technical reason, but never show it (it can contain the user name or host).
        error_log('[BarangayServe] Database connection failed: ' . $e->getMessage());
        throw new RuntimeException('Database connection failed.');
    }

    // Make MySQL use the same clock and the same strict rules on every connection,
    // so dates are consistent and bad data is rejected instead of silently changed.
    $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');   // e.g. +08:00
    $pdo->exec("SET time_zone = '" . $offset . "'");
    $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ONLY_FULL_GROUP_BY,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    return $pdo;
}

/** Current date and time as MySQL text, in the configured time zone. */
function now_sql(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Run several database changes as ONE unit: either all of them are saved, or none.
 *
 *     $result = run_transaction(function (PDO $pdo) {
 *         // ... INSERT / UPDATE statements ...
 *         return $something;
 *     });
 *
 * If the function throws an error, everything it did is undone (rolled back) and the
 * error is passed on. If MySQL reports a "deadlock" (two requests waiting on each
 * other, which is rare), the work is simply tried again, up to $maxTries times.
 */
function run_transaction(callable $work, int $maxTries = 3)
{
    $pdo = db();
    for ($try = 1; ; $try++) {
        try {
            $pdo->beginTransaction();
            $result = $work($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && is_deadlock_error($e) && $try < $maxTries) {
                usleep(random_int(50000, 150000));   // wait 50-150 ms, then try again
                continue;
            }
            throw $e;
        }
    }
}

/** MySQL error 1213 = deadlock, 1205 = lock wait timeout. Both are safe to retry. */
function is_deadlock_error(PDOException $e): bool
{
    return isset($e->errorInfo[1]) && in_array((int) $e->errorInfo[1], [1213, 1205], true);
}

/** True when a PDOException means "duplicate value in a UNIQUE column". */
function is_duplicate_key_error(PDOException $e): bool
{
    return $e->getCode() === '23000' && isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
}

/** For duplicate-key errors: true when the error mentions the given index name. */
function duplicate_key_is(PDOException $e, string $indexName): bool
{
    return is_duplicate_key_error($e) && stripos((string) $e->getMessage(), $indexName) !== false;
}
