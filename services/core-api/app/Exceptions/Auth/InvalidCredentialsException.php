<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown on a failed login (unknown email or wrong password). Maps to HTTP 401 via the global
 * exception handler (it implements HttpExceptionInterface). Deliberately does not reveal whether the
 * email exists — the message is identical for both cases.
 */
final class InvalidCredentialsException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 401, message: __('api.auth.invalid_credentials'));
    }
}
