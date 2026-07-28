<?php
/**
 * Shared image upload handling.
 *
 * Validation is by actual image content (getimagesize), never by the
 * client-supplied filename or MIME type — a file called "photo.jpg" that
 * is really PHP must never be accepted. Combined with the .htaccess rule
 * that blocks script execution under /uploads, that keeps the folder safe.
 */
require_once __DIR__ . '/functions.php';

const UPLOAD_MAX_BYTES = 8 * 1024 * 1024;   // 8 MB

/** Extensions we accept, keyed by the constant getimagesize() reports. */
function upload_allowed_types(): array
{
    return [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];
}

/**
 * Move an uploaded image into a folder under /uploads.
 *
 * @param  array  $file     One entry from $_FILES
 * @param  string $dir      Absolute destination directory
 * @param  string $prefix   Filename prefix, e.g. 'hero' or 'vehicle'
 * @param  string|null $error  Set to a human-readable message on failure
 * @return string|null      Stored filename, or null (no file / failed)
 */
function upload_image(array $file, string $dir, string $prefix, ?string &$error = null): ?string
{
    $error = null;

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;                      // nothing submitted — not an error
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = match ((int)$file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'That image is larger than the server allows. Please upload a smaller file.',
            UPLOAD_ERR_PARTIAL   => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                'The server could not save the file. Check folder permissions.',
            default => 'The image could not be uploaded (error ' . (int)$file['error'] . ').',
        };
        return null;
    }

    if (($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        $error = 'That image is larger than 8 MB. Please compress it and try again.';
        return null;
    }

    // Must be a real image, and must be one of our formats.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $error = 'That file is not a readable image.';
        return null;
    }

    $allowed = upload_allowed_types();
    $ext     = $allowed[$info[2]] ?? null;
    if ($ext === null) {
        $error = 'Please upload a JPG, PNG, WebP or GIF image.';
        return null;
    }

    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        $error = 'The upload folder could not be created. Check folder permissions.';
        return null;
    }
    if (!is_writable($dir)) {
        $error = 'The upload folder is not writable. Check folder permissions.';
        return null;
    }

    $name = sprintf('%s-%s-%s.%s',
        preg_replace('/[^a-z0-9-]/i', '', $prefix) ?: 'image',
        date('Ymd-His'),
        bin2hex(random_bytes(4)),
        $ext);

    if (!@move_uploaded_file($file['tmp_name'], rtrim($dir, '/\\') . '/' . $name)) {
        $error = 'The image could not be saved. Check folder permissions.';
        return null;
    }

    return $name;
}

/** Delete a previously uploaded file, ignoring anything outside $dir. */
function upload_delete(string $dir, ?string $filename): void
{
    $filename = trim((string)$filename);
    if ($filename === '') {
        return;
    }
    $path = rtrim($dir, '/\\') . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

// ── Site imagery (hero, about) ─────────────────────────────────────

function site_image_path(): string { return ROOT_PATH . '/uploads/site'; }
function site_image_url_base(): string { return '/uploads/site'; }

/**
 * Public URL for a site image stored in a setting, or null when unset
 * or the file has gone missing.
 */
function site_image_url(string $setting_key): ?string
{
    $name = trim(setting($setting_key, ''));
    if ($name === '') {
        return null;
    }
    if (preg_match('~^https?://~i', $name)) {
        return $name;
    }
    $file = site_image_path() . '/' . basename($name);
    return is_file($file)
        ? site_image_url_base() . '/' . rawurlencode(basename($name))
        : null;
}
