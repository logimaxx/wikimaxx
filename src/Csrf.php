<?php

declare(strict_types=1);

namespace WikiMaxx;

final class Csrf
{
    public static function token(Config $config): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = hash_hmac('sha256', bin2hex(random_bytes(16)), $config->sessionSecret);
        }
        return $_SESSION['_csrf'];
    }

    public static function validate(Config $config, ?string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }
}
