<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ScreenPageTest extends WebTestCase
{
    public function testScreenIsPublicAndConnectedToTheApi(): void
    {
        $client = static::createClient();
        $client->request('GET', '/screen');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body[data-controller="screen"][data-screen-url-value="/api/blocs"]');
        self::assertSelectorTextContains('h1', 'Votre prochain');
        self::assertSelectorExists('select[name="sector"]');
        self::assertSelectorExists('select[name="color"]');
        self::assertSelectorCount(19, 'select[name="gradeMin"] option');
        self::assertSelectorCount(19, 'select[name="gradeMax"] option');
        self::assertSelectorTextContains('select[name="gradeMin"]', '6A+');
        self::assertSelectorExists('template[data-screen-target="card"]');
        self::assertSelectorExists('[data-screen-target="status"][role="status"]');
        self::assertSelectorNotExists('.header-account');
        self::assertSelectorNotExists('form[action="/login"]');
    }
}
