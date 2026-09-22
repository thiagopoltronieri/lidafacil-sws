<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashIsDifferentFromPlainText(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('s3nha-bem-forte');

        self::assertNotSame('s3nha-bem-forte', $hash);
        self::assertNotEmpty($hash);
    }

    public function testVerifyReturnsTrueForCorrectPassword(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('s3nha-bem-forte');

        self::assertTrue($hasher->verify('s3nha-bem-forte', $hash));
    }

    public function testVerifyReturnsFalseForWrongPassword(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('s3nha-bem-forte');

        self::assertFalse($hasher->verify('senha-errada', $hash));
    }

    public function testFreshHashDoesNotNeedRehash(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('s3nha-bem-forte');

        self::assertFalse($hasher->needsRehash($hash));
    }
}
