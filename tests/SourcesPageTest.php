<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SourcesPageTest extends WebTestCase
{
    public function testSourcesArePublicAndLinkedFromFooter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sources');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sources & crédits');
        self::assertSelectorExists('footer a[href="/sources"]');
        self::assertSelectorExists('a[href="https://symfony.com/bundles/EasyAdminBundle/current/crud.html"]');
        self::assertSelectorExists('a[href="https://symfony.com/doc/current/security.html"]');
        self::assertSelectorTextContains('.sources-ai', 'code fonctionnel');
    }
}
