<?php
/**
 * validation.php: rules for the information users type in.
 *
 * Every validate_...() function returns an error message in plain language,
 * or null when the value is acceptable. The server ALWAYS validates, even though
 * the HTML forms also have browser checks, because browser checks can be bypassed.
 */

function validate_full_name(string $name): ?string
{
    if ($name === '') {
        return 'Please enter your full name.';
    }
    if (text_length($name) < 2 || text_length($name) > 100) {
        return 'Your name must be between 2 and 100 characters.';
    }
    // Letters (including Ñ and accents), spaces, periods, apostrophes, and hyphens.
    if (!preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'\\-]*$/u", $name)) {
        return 'Your name can only contain letters, spaces, periods, apostrophes, and hyphens.';
    }
    return null;
}

function validate_email(string $email): ?string
{
    if ($email === '') {
        return 'Please enter your email address.';
    }
    if (text_length($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address, for example juan@example.com.';
    }
    return null;
}

function validate_contact_number(string $number): ?string
{
    if ($number === '') {
        return 'Please enter your contact number.';
    }
    // Exactly 11 digits, numbers only (for example 09171234567).
    if (!preg_match('/^[0-9]{11}$/', $number)) {
        return 'The contact number must be exactly 11 digits, numbers only, for example 09171234567.';
    }
    return null;
}

function validate_address(string $address): ?string
{
    if ($address === '') {
        return 'Please enter your address.';
    }
    if (text_length($address) < 5 || text_length($address) > 255) {
        return 'Your address must be between 5 and 255 characters.';
    }
    return null;
}

/** Password rule: 8 to 72 bytes, with at least one letter and one number. */
function validate_password(string $password): ?string
{
    if ($password === '') {
        return 'Please enter a password.';
    }
    if (strlen($password) < 8) {
        return 'Your password must have at least 8 characters.';
    }
    if (strlen($password) > 72) {
        return 'Your password must not be longer than 72 characters.';   // bcrypt only reads 72 bytes
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Your password must contain at least one letter and one number.';
    }
    return null;
}

function validate_purpose(string $purpose): ?string
{
    if ($purpose === '') {
        return 'Please enter the purpose of your request.';
    }
    if (text_length($purpose) < 3 || text_length($purpose) > 255) {
        return 'The purpose must be between 3 and 255 characters.';
    }
    return null;
}

/** A remark written by staff (shown to the resident). Required when $required is true. */
function validate_remark(string $remark, bool $required): ?string
{
    if ($remark === '') {
        return $required ? 'Please write a remark (it will be shown to the resident).' : null;
    }
    if (text_length($remark) > 500) {
        return 'The remark must not be longer than 500 characters.';
    }
    return null;
}

/** Check a YYYY-MM-DD date string. */
function is_valid_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
    return $parsed !== false && !$hasErrors && $parsed->format('Y-m-d') === $date;
}
