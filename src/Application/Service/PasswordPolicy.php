<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use Rez\Application\Config\UsersConfig;
use Rez\Domain\Exception\PasswordTooLongException;
use Rez\Domain\Exception\PasswordTooShortException;

/**
 * Length is the only rule (NIST SP 800-63B): no required mix of character classes, which
 * only pushes people toward `Password1!`. Applies to passwords being set, never to logging
 * in — an account created before the policy keeps working with its old password.
 */
final class PasswordPolicy
{
    public function __construct(
        private readonly UsersConfig $config,
    ) {
    }

    /**
     * @throws PasswordTooShortException
     * @throws PasswordTooLongException
     */
    public function assertAllows(string $plainText): void
    {
        $length = mb_strlen($plainText, 'UTF-8');

        if ($length < $this->config->passwordMinLength) {
            throw new PasswordTooShortException($this->config->passwordMinLength);
        }

        if ($length > $this->config->passwordMaxLength) {
            throw new PasswordTooLongException($this->config->passwordMaxLength);
        }
    }
}
