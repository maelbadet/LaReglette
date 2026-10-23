<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('statistics')]
final readonly class RecalculateBoulderStatistics
{
    public function __construct(public int $boulderId)
    {
    }
}
