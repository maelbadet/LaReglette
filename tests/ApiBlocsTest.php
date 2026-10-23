<?php

namespace App\Tests;

use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiBlocsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private Sector $sector;
    private Boulder $red;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite.');
        }
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);
        if ('pdo_sqlite' !== $this->manager->getConnection()->getParams()['driver']
            || !($this->manager->getConnection()->getParams()['memory'] ?? false)) {
            self::markTestSkipped('Requires an isolated SQLite memory database.');
        }
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        $this->sector = (new Sector())->setName('La Dalle');
        $other = (new Sector())->setName('Le Toit');
        $user = (new User())->setEmail('private@example.com')->setPassword('private-hash')->setFirstName('Camille')->setLastName('Martin');
        foreach ([$this->sector, $other, $user] as $entity) {
            $this->manager->persist($entity);
        }
        $this->red = $this->block('Rouge', 'rouge', 3, $this->sector);
        $this->block('Bleu', 'bleu', 4, $this->sector);
        $this->block('Autre secteur', 'rouge', 3, $other);
        $this->block('Démonté', 'rouge', 3, $this->sector)->setDismantledAt(new \DateTimeImmutable('-1 day'));
        $this->block('À venir', 'rouge', 3, $this->sector)->setOpenedAt(new \DateTimeImmutable('+1 day'));
        $this->block('Sans ouverture', 'rouge', 3, $this->sector)->setOpenedAt(null);
        $this->block('Démontage prévu', 'vert', 5, $this->sector)->setDismantledAt(new \DateTimeImmutable('+1 day'));
        foreach ([2, 1] as $days) {
            $ascent = (new Ascent())->setBoulder($this->red)->setUser($user)->setCompletedAt(new \DateTimeImmutable('-'.$days.' days'))->setRating(4)->setComment('Commentaire privé');
            $this->manager->persist($ascent);
        }
        $this->manager->flush();
        $this->manager->clear();
    }

    public function testPublicResponseIncludesAvailableBlocksAndTheirSuccesses(): void
    {
        $this->client->request('GET', '/api/blocs');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $blocks = $this->payload()['blocs'];
        self::assertCount(4, $blocks);
        self::assertEqualsCanonicalizing(['Rouge', 'Bleu', 'Autre secteur', 'Démontage prévu'], array_column($blocks, 'name'));
        $red = array_values(array_filter($blocks, static fn (array $block): bool => 'Rouge' === $block['name']))[0];
        self::assertSame(['id' => $this->sector->getId(), 'name' => 'La Dalle'], $red['sector']);
        self::assertSame(0, $red['successCount']);
        self::assertNull($red['averageRating']);
        self::assertCount(2, $red['ascents']);
        self::assertSame(4, $red['ascents'][0]['rating']);
        self::assertGreaterThan($red['ascents'][1]['completedAt'], $red['ascents'][0]['completedAt']);
        foreach ($blocks as $block) {
            self::assertSame(['id', 'name', 'color', 'grade', 'gradeLabel', 'openedAt', 'sector', 'successCount', 'averageRating', 'ascents'], array_keys($block));
            foreach ($block['ascents'] as $ascent) {
                self::assertSame(['id', 'completedAt', 'rating'], array_keys($ascent));
            }
        }
        self::assertStringNotContainsString('private@example.com', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('private-hash', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Commentaire privé', $this->client->getResponse()->getContent());
    }

    public function testFiltersCombineAndUnknownSectorReturnsEmptyList(): void
    {
        foreach ([
            ['color' => 'rouge'],
            ['grade' => 3],
            ['sector' => $this->sector->getId(), 'color' => 'rouge', 'grade' => 3],
        ] as $filters) {
            $this->client->request('GET', '/api/blocs', $filters);
            self::assertResponseIsSuccessful();
            self::assertCount(isset($filters['sector']) ? 1 : 2, $this->payload()['blocs']);
        }
        $this->client->request('GET', '/api/blocs', ['sector' => 999999]);
        self::assertResponseIsSuccessful();
        self::assertSame(['blocs' => []], $this->payload());
    }

    public function testInvalidFiltersReturnJsonErrors(): void
    {
        foreach ([
            ['grade' => 0], ['grade' => 19], ['grade' => 'abc'], ['grade' => ['3']],
            ['sector' => -1], ['color' => str_repeat('a', 51)], ['unfinished' => '1'],
        ] as $filters) {
            $this->client->request('GET', '/api/blocs', $filters);
            self::assertResponseStatusCodeSame(400);
            self::assertResponseHeaderSame('content-type', 'application/json');
            self::assertIsArray($this->payload());
        }
    }

    public function testOnlyGetIsAllowed(): void
    {
        $this->client->request('POST', '/api/blocs');
        self::assertResponseStatusCodeSame(405);
    }

    private function payload(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function block(string $name, string $color, int $grade, Sector $sector): Boulder
    {
        $boulder = (new Boulder())->setName($name)->setColor($color)->setGrade($grade)->setSector($sector)->setOpenedAt(new \DateTimeImmutable('-10 days'));
        $this->manager->persist($boulder);

        return $boulder;
    }
}
