<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public const OPENER_REFERENCE = 'user-opener';
    public const CLIMBER_REFERENCE = 'user-climber';

    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);
        $adminuser = new User();
        $adminuser->setEmail('admin@admin.fr');
        $adminuser->setRoles(['ROLE_ADMIN']);
        $adminuser->setFirstName('Admin');
        $adminuser->setLastName('account');
        $adminuser->setIsVerified(true);
        $adminuser->setPassword('$2a$12$NYf9LhOXUvu9x6Q5pUA38eqjTkLmYsjzL3AKGTsQyEJdSoSTRoEW2'); // password: admin
        $manager->persist($adminuser);
        $manager->flush();

        $openuser = new User();
        $openuser->setEmail('open@open.fr');
        $openuser->setRoles(['ROLE_OUVREUR']);
        $openuser->setFirstName('Open');
        $openuser->setLastName('account');
        $openuser->setIsVerified(true);
        $openuser->setPassword('$2a$12$cFNpLDVr2idHObPKVdmUgeKiVdqC0zbchR3/BSGn8qGQlNLwDOX1u'); // password: test
        $manager->persist($openuser);
        $this->addReference(self::OPENER_REFERENCE, $openuser);
        $manager->flush();

        $normaluser = new User();
        $normaluser->setEmail('test@test.fr');
        $normaluser->setRoles(['ROLE_GRIMPEUR']);
        $normaluser->setFirstName('Test');
        $normaluser->setLastName('account');
        $normaluser->setIsVerified(true);
        $normaluser->setPassword('$2a$12$cFNpLDVr2idHObPKVdmUgeKiVdqC0zbchR3/BSGn8qGQlNLwDOX1u'); // password: test
        $manager->persist($normaluser);
        $this->addReference(self::CLIMBER_REFERENCE, $normaluser);
        $manager->flush();
    }
}
