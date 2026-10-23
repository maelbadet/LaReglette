<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SourceController extends AbstractController
{
    #[Route('/sources', name: 'app_sources', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('components/source.html.twig');
    }
}
