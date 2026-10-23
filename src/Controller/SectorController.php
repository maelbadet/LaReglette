<?php

namespace App\Controller;

use App\Entity\Sector;
use App\Form\SectorType;
use App\Repository\SectorRepository;
use App\Service\SectorManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SectorController extends AbstractController
{
    #[Route('/sector', name: 'app_sector', methods: ['GET'])]
    public function index(SectorRepository $repository): Response
    {
        return $this->render('sector/index.html.twig', ['sectors' => $repository->findBy([], ['name' => 'ASC'])]);
    }

    #[Route('/sector/create', name: 'app_sector_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, SectorManager $manager): Response
    {
        return $this->form(new Sector(), $request, $manager);
    }

    #[Route('/sector/{id}/edit', name: 'app_sector_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Sector $sector, Request $request, SectorManager $manager): Response
    {
        return $this->form($sector, $request, $manager);
    }

    private function form(Sector $sector, Request $request, SectorManager $manager): Response
    {
        $form = $this->createForm(SectorType::class, $sector);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager->save($sector);

            return $this->redirectToRoute('app_sector_show', ['id' => $sector->getId()]);
        }

        return $this->render('sector/create.html.twig', ['form' => $form, 'sector' => $sector]);
    }

    #[Route('/sector/{id}/delete', name: 'app_sector_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Sector $sector, Request $request, SectorManager $manager): Response
    {
        if (!$this->isCsrfTokenValid('sector_delete_' . $sector->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        if (!$manager->delete($sector)) {
            $this->addFlash('error', 'Ce secteur contient des blocs et ne peut pas être supprimé.');
        }

        return $this->redirectToRoute('app_sector');
    }
}
