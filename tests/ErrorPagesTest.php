<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Bridge\Twig\ErrorRenderer\TwigErrorRenderer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig\Environment;

class ErrorPagesTest extends WebTestCase
{
    public function testUnknownPageUsesCustom404WithoutDebug(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/page-qui-nexiste-pas');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorExists('body.error-page');
        self::assertSelectorTextContains('#error-title', 'introuvable');
        self::assertSelectorTextContains('.error-code', '404');
        self::assertSelectorExists('a[href="/"]');
        self::assertSelectorNotExists('.header-account');
    }

    public function testForbiddenPageUsesCustom403WithoutDebug(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->disableReboot();
        $user = (new User())->setEmail('climber@example.com')->setFirstName('Camille')->setLastName('Martin')->setPassword('unused')->setRoles(['ROLE_GRIMPEUR']);
        $provider = $this->createStub(EntityUserProvider::class);
        $provider->method('refreshUser')->willReturn($user);
        $provider->method('supportsClass')->willReturn(true);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);
        $client->loginUser($user);
        $client->request('GET', '/login/role');

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorExists('body.error-page');
        self::assertSelectorTextContains('#error-title', 'réservée');
        self::assertSelectorTextContains('.error-code', '403');
        self::assertSelectorExists('a[href="/login"]');
    }

    public function testOtherHttpErrorsUseGenericPageAndKeepTheirStatus(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('POST', '/screen');

        self::assertResponseStatusCodeSame(405);
        self::assertSelectorExists('body.error-page');
        self::assertSelectorTextContains('.error-code', '405');
        self::assertSelectorTextContains('#error-title', 'imprévue');
    }

    public function testServerErrorsDoNotExposeExceptionDetails(): void
    {
        self::bootKernel(['debug' => false]);
        $renderer = new TwigErrorRenderer(static::getContainer()->get(Environment::class));
        $exception = $renderer->render(new \RuntimeException('private database password and internal file path'));

        self::assertSame(500, $exception->getStatusCode());
        self::assertStringContainsString('Une pause', $exception->getAsString());
        self::assertStringContainsString('error-code">500', $exception->getAsString());
        self::assertStringNotContainsString('private database password', $exception->getAsString());
        self::assertStringNotContainsString('RuntimeException', $exception->getAsString());
    }
}
