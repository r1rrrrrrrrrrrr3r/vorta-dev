<?php

function app_fail(string $publicMessage, int $status = 400, ?Throwable $e = null): void
{
    if ($e) {
        error_log($e->getMessage());
    }
    http_response_code($status);
    echo htmlspecialchars($publicMessage, ENT_QUOTES, 'UTF-8');
    exit;
}

function app_json_fail(string $publicMessage, int $status = 400, ?Throwable $e = null): void
{
    if ($e) {
        error_log($e->getMessage());
    }
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'ok' => false, 'message' => $publicMessage]);
    exit;
}

function app_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
