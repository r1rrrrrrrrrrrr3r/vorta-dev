<?php

function storage_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
}

function uploads_dir(int $companyId): string
{
    $root = storage_root();
    if (!is_dir($root)) {
        mkdir($root, 0750, true);
    }
    $rootHt = $root . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($rootHt)) {
        file_put_contents($rootHt, "Require all denied\n");
    }
    $dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $companyId;
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht, "Require all denied\n");
    }
    return $dir;
}

function upload_save_image(array $file, int $companyId, int $maxBytes = 1048576): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed.'];
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'message' => 'Image file size must not exceed 1 MB.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'message' => 'Invalid upload.'];
    }

    $mime = mime_content_type($tmp);
    $dimensions = @getimagesize($tmp);
    if (!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 6000 || $dimensions[1] > 6000) {
        return ['ok' => false, 'message' => 'Image dimensions must not exceed 6000 by 6000 pixels.'];
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'message' => 'Unsupported image format. Use JPG, PNG, or WebP.'];
    }

    $filename = 'report_' . bin2hex(random_bytes(8)) . '.jpg';
    $dir = uploads_dir($companyId);
    $target = $dir . DIRECTORY_SEPARATOR . $filename;

    $success = false;
    $image = null;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = @imagecreatefromjpeg($tmp);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($tmp);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($tmp);
            break;
    }
    if ($image) {
        $success = imagejpeg($image, $target, 80);
        imagedestroy($image);
    }
    if (!$success) {
        return ['ok' => false, 'message' => 'Failed to process image.'];
    }

    return ['ok' => true, 'path' => $companyId . '/' . $filename];
}

function upload_absolute_path(?string $stored): ?string
{
    if (!$stored) {
        return null;
    }
    $stored = str_replace(['\\', '..'], ['/', ''], $stored);
    $stored = ltrim($stored, '/');
    if (!preg_match('#^\d+/[A-Za-z0-9._-]+$#', $stored)) {
        $base = basename($stored);
        $legacy = dirname(__DIR__) . '/uploads/' . $base;
        return is_file($legacy) ? $legacy : null;
    }
    $path = storage_root() . '/uploads/' . $stored;
    return is_file($path) ? $path : null;
}
