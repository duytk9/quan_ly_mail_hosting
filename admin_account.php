<?php

declare(strict_types=1);

/*
 * Compatibility wrapper for the secure admin account utility.
 *
 * Supported password sources:
 * - --password-stdin
 * - --password-file
 * - --password-env
 *
 * Unsafe argv password passing remains blocked unless
 * MAILPANEL_ALLOW_INSECURE_ARG_PASSWORD is explicitly enabled.
 * Refusing --password protects shell history, process listings, and logs.
 */

require __DIR__ . '/scripts/admin_account.php';
