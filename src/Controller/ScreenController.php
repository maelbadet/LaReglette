<?php

namespace App\Controller;

use App\Enum\Grade;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ScreenController extends AbstractController
{
    #[Route('/screen', name: 'app_screen', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('screen/screen-display.html.twig', ['grades' => Grade::choices()]);
    }
}
