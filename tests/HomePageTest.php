<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomePageTest extends WebTestCase
{
    public function testPublicHomeIncludesSharedComponents(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('header.site-header a[href="/login"]');
        self::assertSelectorExists('main .home-hero h1');
        self::assertSelectorCount(3, '.spirit-card');
        self::assertSelectorExists('footer.site-footer');
        self::assertSelectorNotExists('.account-identity');
    }

    public function testLoginRedirectsHomeAndShowsUserName(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = (new User())
            ->setEmail('camille@example.com')
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setPassword(password_hash('test-password', PASSWORD_BCRYPT, ['cost' => 4]));
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 42);

        // Keep the HTTP authentication flow real, replacing only database access.
        $provider = $this->createMock(EntityUserProvider::class);
        $provider->expects(self::once())->method('loadUserByIdentifier')->with('camille@example.com')->willReturn($user);
        $provider->method('refreshUser')->willReturn($user);
        $provider->method('supportsClass')->willReturn(true);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'camille@example.com',
            '_password' => 'test-password',
        ]);
        $client->request('POST', '/login', $form->getPhpValues() + ['_target_path' => '/admin']);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.account-name', 'Camille.Martin');
        self::assertSelectorTextContains('.header-account', 'Se déconnecter');
        self::assertSelectorNotExists('.header-account a[href="/login"]');

        $client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('section[aria-label="Informations du compte"]');
        self::assertSelectorTextContains('h1', 'Bonjour Camille.');
        self::assertInputValueSame('profile[email]', 'camille@example.com');
    }
}
