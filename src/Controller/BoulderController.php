<?php

namespace App\Controller;

use App\Entity\Boulder;
use App\Entity\Sector;
use App\Entity\User;
use App\Form\BoulderType;
use App\Form\BoulderFilterType;
use App\Repository\BoulderRepository;
use App\Service\BoulderManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BoulderController extends AbstractController
{
    #[Route('/boulder', name: 'app_boulder', methods: ['GET'])]
    #[Route('/sector/{id}', name: 'app_sector_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function index(Request $request, BoulderRepository $repository, #[CurrentUser] ?User $user, ?Sector $sector = null): Response
    {
        if (null === $user && !empty($request->query->all('boulder_filter')['unfinished'])) {
            $this->denyAccessUnlessGranted('ROLE_GRIMPEUR');
        }
        $form = $this->createForm(BoulderFilterType::class, null, ['method' => 'GET', 'authenticated' => null !== $user]);
        $form->handleRequest($request);
        $filters = $form->isSubmitted() && $form->isValid() ? $form->getData() : [];

        return $this->render('boulder/index.html.twig', [
            'sector' => $sector,
            'filterForm' => $form,
            'boulders' => $repository->findAvailable($filters ?? [], $user, $sector),
        ]);
    }

    #[Route('/sector/{id}/boulder/create', name: 'app_boulder_create', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_OUVREUR')]
    public function create(Sector $sector, Request $request, #[CurrentUser] User $user, BoulderManager $manager): Response
    {
        return $this->form((new Boulder())->setSector($sector)->setOpenedAt(new \DateTimeImmutable()), $request, $user, $manager);
    }

    #[Route('/boulder/{id}', name: 'app_boulder_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Boulder $boulder, BoulderRepository $repository): Response
    {
        return $this->render('boulder/show.html.twig', ['boulder' => $boulder, 'statistics' => $repository->statistics($boulder), 'canManage' => $this->canManage($boulder)]);
    }

    #[Route('/boulder/{id}/edit', name: 'app_boulder_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_OUVREUR')]
    public function edit(Boulder $boulder, Request $request, #[CurrentUser] User $user, BoulderManager $manager): Response
    {
        if (!$this->canManage($boulder)) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que les blocs que vous avez ouverts.');
        }

        return $this->form($boulder, $request, $user, $manager);
    }

    private function form(Boulder $boulder, Request $request, User $user, BoulderManager $manager): Response
    {
        $form = $this->createForm(BoulderType::class, $boulder);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager->save($boulder, $user);

            return $this->redirectToRoute('app_boulder_show', ['id' => $boulder->getId()]);
        }

        return $this->render('boulder/form.html.twig', ['form' => $form, 'boulder' => $boulder]);
    }

    #[Route('/boulder/{id}/dismantle', name: 'app_boulder_dismantle', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_OUVREUR')]
    public function dismantle(Boulder $boulder, Request $request, BoulderManager $manager): Response
    {
        if (!$this->canManage($boulder)) {
            throw $this->createAccessDeniedException('Vous ne pouvez démonter que les blocs que vous avez ouverts.');
        }
        if (!$this->isCsrfTokenValid('dismantle_' . $boulder->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->dismantle($boulder);

        return $this->redirectToRoute('app_boulder_show', ['id' => $boulder->getId()]);
    }

    private function canManage(Boulder $boulder): bool
    {
        $user = $this->getUser();

        return $this->isGranted('ROLE_ADMIN')
            || ($this->isGranted('ROLE_OUVREUR') && $user instanceof User && null !== $boulder->getOpenedBy() && $user->getId() === $boulder->getOpenedBy()->getId());
    }
}
