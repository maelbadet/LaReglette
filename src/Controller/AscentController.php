<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Ascent;
use App\Entity\Boulder;
use App\Form\AscentType;
use App\Repository\AscentRepository;
use App\Service\AscentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_GRIMPEUR')]
final class AscentController extends AbstractController
{
    #[Route('/ascent/create', name: 'app_ascent', methods: ['GET', 'POST'])]
    #[Route('/ascent/create', name: 'app_ascent_create', methods: ['GET', 'POST'])]
    #[Route('/create', name: 'app_ascent_legacy', methods: ['GET', 'POST'])]
    #[Route('/boulder/{id}/ascent/create', name: 'app_boulder_ascent_create', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function create(Request $request, #[CurrentUser] User $user, AscentManager $manager, ?Boulder $boulder = null): Response
    {
        if (null !== $boulder && !$boulder->isAvailable()) {
            throw $this->createNotFoundException('Ce bloc n’est plus en place.');
        }
        $ascent = (new Ascent())->setBoulder($boulder)->setCompletedAt(new \DateTimeImmutable());
        $form = $this->createForm(AscentType::class, $ascent, ['boulder' => $boulder]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ascent = $form->getData();
            $manager->create($ascent, $user);
            $this->addFlash('success', 'Votre ascension a été enregistrée.');

            return $this->redirectToRoute('app_ascent_detail', ['id' => $ascent->getId()]);
        }

        return $this->render('ascent/createAscent.html.twig', ['ascentForm' => $form]);
    }

    #[Route('/ascent/personnal', name: 'app_ascent_personnal', methods: ['GET'])]
    public function personal(#[CurrentUser] User $user, AscentRepository $ascents): Response
    {
        return $this->render('ascent/index.html.twig', [
            'ascents' => $ascents->getAscentByUser($user->getId()),
        ]);
    }

    #[Route('/ascent/personnal/{id}', name: 'app_ascent_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, #[CurrentUser] User $user, AscentRepository $ascents): Response
    {
        $ascent = $ascents->findOneBy($this->isGranted('ROLE_ADMIN') ? ['id' => $id] : ['id' => $id, 'user' => $user]);
        if (null === $ascent) {
            throw $this->createNotFoundException('Ascension introuvable.');
        }

        return $this->render('ascent/detail.html.twig', ['ascent' => $ascent]);
    }

    #[Route('/ascent/{id}/delete', name: 'app_ascent_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, #[CurrentUser] User $user, AscentRepository $ascents, AscentManager $manager): Response
    {
        $ascent = $ascents->findOneBy($this->isGranted('ROLE_ADMIN') ? ['id' => $id] : ['id' => $id, 'user' => $user]);
        if (null === $ascent) {
            throw $this->createNotFoundException('Ascension introuvable.');
        }
        if (!$this->isCsrfTokenValid('ascent_delete_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $manager->delete($ascent);

        return $this->redirectToRoute('app_ascent_personnal');
    }
}
