<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthenticationPagesTest extends WebTestCase
{
    public function testLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Heureux de vous revoir.');
        self::assertSelectorExists('header.site-header');
        self::assertSelectorExists('footer.site-footer');
        self::assertSelectorExists('form.auth-form input[name="_csrf_token"]');
        self::assertSelectorExists('input[autocomplete="current-password"]');
        self::assertSelectorExists('a[href="/register"]');
    }
}
