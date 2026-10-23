<?php

namespace App\MessageHandler;

use App\Message\GenerateWeeklyBoulderReport;
use App\Service\WeeklyBoulderReport;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GenerateWeeklyBoulderReportHandler
{
    public function __construct(private WeeklyBoulderReport $report)
    {
    }

    public function __invoke(GenerateWeeklyBoulderReport $message): void
    {
        $this->report->send();
    }
}
