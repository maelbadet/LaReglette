<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;

class ConfirmationEmailTest extends KernelTestCase
{
    public function testConfirmationLinksPreserveTheSignedUrl(): void
    {
        self::bootKernel();
        $signedUrl = 'https://example.com/verify/email?expires=123&signature=test&token=abc';
        $html = static::getContainer()->get(Environment::class)->render('registration/confirmation_email.html.twig', [
            'signedUrl' => $signedUrl,
            'expiresAtMessageKey' => '%count% hour|%count% hours',
            'expiresAtMessageData' => ['%count%' => 1],
        ]);
        $crawler = new Crawler($html);

        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertCount(2, $crawler->filter('a'));
        foreach ($crawler->filter('a') as $link) {
            self::assertSame($signedUrl, $link->getAttribute('href'));
        }
        self::assertSame('Confirmer mon adresse e-mail', $crawler->filter('a')->first()->text());
        self::assertStringContainsString('1 heure', $crawler->filter('body')->text());
    }
}
