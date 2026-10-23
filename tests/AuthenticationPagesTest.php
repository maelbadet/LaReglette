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

    public function testRegistrationPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créez votre compte.');
        self::assertSelectorExists('.form-columns input[autocomplete="given-name"]');
        self::assertSelectorExists('header.site-header');
        self::assertSelectorExists('footer.site-footer');
        self::assertSelectorExists('.form-columns input[autocomplete="family-name"]');
        self::assertSelectorExists('input[autocomplete="new-password"]');
        self::assertSelectorExists('.form-checkbox input[type="checkbox"]');
        self::assertSelectorExists('form.auth-form button[type="submit"]');
        self::assertSelectorExists('a[href="/login"]');
    }
}
