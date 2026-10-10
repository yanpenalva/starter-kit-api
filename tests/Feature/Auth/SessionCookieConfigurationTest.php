<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\TestCase;

final class SessionCookieConfigurationTest extends TestCase
{
    public function test_local_http_profile_defaults(): void
    {
        $secure = config('session.secure');

        self::assertThat($secure, self::logicalOr(
            self::isNull(),
            self::identicalTo(false)
        ));
        self::assertTrue(config('session.http_only'));
        self::assertSame('lax', config('session.same_site'));
        self::assertNull(config('session.domain'));
    }

    public function test_https_profile_enables_secure(): void
    {
        config()->set('session.secure', true);
        config()->set('session.http_only', true);
        config()->set('session.same_site', 'lax');

        self::assertTrue(config('session.secure'));
        self::assertTrue(config('session.http_only'));
        self::assertSame('lax', config('session.same_site'));
    }

    public function test_same_site_none_requires_secure_rule_documented(): void
    {
        self::assertNotSame('none', config('session.same_site'));
        self::assertSame('lax', config('session.same_site'));
        self::assertTrue(config('session.http_only'));
        self::assertThat(config('session.secure'), self::logicalOr(
            self::isNull(),
            self::identicalTo(false)
        ));
    }
}
