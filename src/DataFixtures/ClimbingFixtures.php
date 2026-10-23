<?php

namespace App\DataFixtures;

use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use App\Enum\Grade;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ClimbingFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $openers = [$this->getReference(AppFixtures::OPENER_REFERENCE, User::class)];
        foreach ([['lea.ouvreur@example.com', 'Léa', 'Moreau'], ['hugo.ouvreur@example.com', 'Hugo', 'Petit']] as [$email, $firstName, $lastName]) {
            $openers[] = $this->createUser($manager, $email, $firstName, $lastName, 'ROLE_OUVREUR');
        }

        $climbers = [$this->getReference(AppFixtures::CLIMBER_REFERENCE, User::class)];
        $namedClimbers = [
            1 => ['camille.grimpeur@example.com', 'Camille', 'Martin'],
            2 => ['nicolas.grimpeur@example.com', 'Nicolas', 'Bernard'],
            3 => ['sarah.grimpeur@example.com', 'Sarah', 'Durand'],
        ];
        for ($number = 1; $number <= 19; ++$number) {
            [$email, $firstName, $lastName] = $namedClimbers[$number] ?? [sprintf('grimpeur%02d@example.com', $number), sprintf('Grimpeur %02d', $number), 'Démo'];
            $climbers[] = $this->createUser($manager, $email, $firstName, $lastName, 'ROLE_GRIMPEUR');
        }

        $colors = ['vert', 'bleu', 'jaune', 'orange', 'rouge', 'violet', 'noir'];
        $successCounts = [15, 14, 13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 2, 1, 1, 1];
        $comments = ['Belle ligne, très agréable à grimper.', 'Un beau défi technique !', 'Il fallait trouver la bonne méthode.', 'Une sortie qui demande de la précision.'];
        $today = new \DateTimeImmutable('today');

        foreach (['La Dalle', 'Le Dévers', 'Le Toit', 'Les Arêtes'] as $sectorIndex => $sectorName) {
            $sector = (new Sector())->setName($sectorName);
            $manager->persist($sector);

            for ($localIndex = 0; $localIndex < 10; ++$localIndex) {
                $index = $sectorIndex * 10 + $localIndex;
                $grade = ($index % count(Grade::cases())) + 1;
                // Openings span the last 59 days, including recent and older blocks.
                $ageInDays = 59 - (int) floor($index * 58 / 39);
                $openedAt = $today->modify(sprintf('-%d days', $ageInDays));
                $boulder = (new Boulder())
                    ->setName(sprintf('%s — Bloc %02d', $sectorName, $localIndex + 1))
                    ->setGrade($grade)
                    ->setColor($colors[min(6, intdiv(($grade - 1) * 7, count(Grade::cases())))])
                    ->setOpenedAt($openedAt);
                $sector->addBoulder($boulder);
                $openers[$index % 3]->addBoulder($boulder);
                $manager->persist($boulder);

                // Exactly ten retired blocks; their ascents precede dismantling.
                $lastPossibleAscent = $today;
                if (0 === $index % 4) {
                    $dismantledAt = $openedAt->modify(sprintf('+%d days', max(1, intdiv($ageInDays, 2))));
                    $boulder->setDismantledAt($dismantledAt);
                    $lastPossibleAscent = $dismantledAt;
                }

                // Two active blocks at the highest grade have never been completed.
                $count = \in_array($index, [17, 35], true) ? 0 : $successCounts[$grade - 1];
                // Another active block has successes, but none in the last fortnight.
                if (5 === $index) {
                    $lastPossibleAscent = $today->modify('-16 days');
                }
                $start = $openedAt->getTimestamp() + 3600;
                $end = $lastPossibleAscent->getTimestamp() - 3600;

                for ($attempt = 0; $attempt < $count; ++$attempt) {
                    $climber = $climbers[($index * 3 + $attempt) % \count($climbers)];
                    $timestamp = $start + (int) floor(($end - $start) * ($attempt + 1) / ($count + 1));
                    $ascent = (new Ascent())
                        ->setCompletedAt($today->setTimestamp($timestamp))
                        ->setRating(3 + ($index + $attempt) % 3)
                        ->setComment($comments[($index + $attempt) % \count($comments)]);
                    $climber->addAscent($ascent);
                    $boulder->addAscent($ascent);
                    $manager->persist($ascent);
                }
            }
        }

        $manager->flush();
    }

    private function createUser(ObjectManager $manager, string $email, string $firstName, string $lastName, string $role): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles([$role])
            ->setIsVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'test'));
        $manager->persist($user);

        return $user;
    }
}
