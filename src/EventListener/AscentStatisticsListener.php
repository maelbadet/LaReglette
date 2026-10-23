<?php

namespace App\EventListener;

use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Message\RecalculateBoulderStatistics;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postUpdate)]
class AscentStatisticsListener
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function postPersist(PostPersistEventArgs $event): void
    {
        if (($ascent = $event->getObject()) instanceof Ascent) {
            $this->dispatch($ascent->getBoulder());
        }
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        if (($ascent = $event->getObject()) instanceof Ascent) {
            $this->dispatch($ascent->getBoulder());
        }
    }

    public function postUpdate(PostUpdateEventArgs $event): void
    {
        $ascent = $event->getObject();
        if (!$ascent instanceof Ascent) {
            return;
        }
        $changes = $event->getObjectManager()->getUnitOfWork()->getEntityChangeSet($ascent);
        if (isset($changes['rating']) || isset($changes['boulder'])) {
            $this->dispatch($ascent->getBoulder());
            if (isset($changes['boulder']) && $changes['boulder'][0] !== $ascent->getBoulder()) {
                $this->dispatch($changes['boulder'][0]);
            }
        }
    }

    private function dispatch(?Boulder $boulder): void
    {
        if (null !== $boulder?->getId()) {
            // Doctrine's transport uses the same connection and transaction as the ascent.
            $this->bus->dispatch(new RecalculateBoulderStatistics($boulder->getId()));
        }
    }
}
