<?php

declare(strict_types=1);

use MailPanel\Bootstrap\Environment;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__, 2));

header('X-Robots-Tag: noindex, nofollow, noarchive');

$appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));
$enabled = filter_var($_ENV['MAILPANEL_ENABLE_QA_PREVIEW'] ?? getenv('MAILPANEL_ENABLE_QA_PREVIEW') ?: false, FILTER_VALIDATE_BOOL);
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
$forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
$isLocalRequest = in_array($remoteAddress, ['127.0.0.1', '::1'], true) && $forwardedFor === '' && $forwardedHost === '';
$strictLocalPreview = filter_var($_ENV['MAILPANEL_QA_PREVIEW_LOCAL_ONLY'] ?? getenv('MAILPANEL_QA_PREVIEW_LOCAL_ONLY') ?: true, FILTER_VALIDATE_BOOL);
$previewKey = (string) ($_ENV['MAILPANEL_QA_PREVIEW_KEY'] ?? getenv('MAILPANEL_QA_PREVIEW_KEY') ?: '');
$requestPreviewKey = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_MAILPANEL_QA_PREVIEW_KEY'] ?? '');

if (
    !$enabled
    || $appEnv === 'production'
    || ($strictLocalPreview && !$isLocalRequest)
    || $previewKey === ''
    || !hash_equals($previewKey, $requestPreviewKey)
) {
    http_response_code(404);
    echo 'Not found';
    return;
}

header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><meta charset="utf-8"><title>MailPanel QA Preview</title><h1>MailPanel QA Preview</h1>';
