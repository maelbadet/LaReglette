<?php

namespace App\Controller;

use App\Dto\BoulderFilters;
use App\Service\BoulderDisplay;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController extends AbstractController
{
    #[Route('/api/blocs', name: 'app_api', methods: ['GET'], format: 'json')]
    public function index(
        BoulderDisplay $display,
        #[MapQueryString(validationFailedStatusCode: 400)] BoulderFilters $filters = new BoulderFilters(),
    ): JsonResponse
    {
        return $this->json(['blocs' => $display->list($filters)]);
    }
}
