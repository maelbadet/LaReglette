<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminDashboardTest extends WebTestCase
{
    public function testAdminOpensBoulderListByDefault(): void
    {
        $client = static::createClient();
        $user = (new User())
            ->setEmail('admin@example.com')
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setPassword('unused')
            ->setRoles(['ROLE_ADMIN']);

        $provider = $this->createStub(EntityUserProvider::class);
        $provider->method('refreshUser')->willReturn($user);
        $provider->method('supportsClass')->willReturn(true);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);
        $client->loginUser($user);
        $client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/boulder');
    }

    public function testAnonymousVisitorMustLogIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }
}
