<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\ChangeRoleType;
use App\Repository\UserRepository;
use App\Service\UserRoleManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/login/role', name: 'app_change_role', methods: ['GET', 'POST'])]
    #[Route('/login/role/{id}', name: 'app_change_role_user', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function changeRole(Request $request, UserRepository $users, UserRoleManager $manager, ?User $user = null): Response
    {
        if ($request->attributes->has('id') && null === $user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }
        $form = $this->createForm(ChangeRoleType::class, [
            'user' => $user,
            'role' => null !== $user ? UserRole::forUser($user) : null,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $manager->changeRole($data['user'], $data['role']);
            $this->addFlash('success', sprintf('Le rôle de %s %s est maintenant : %s.', $data['user']->getFirstName(), $data['user']->getLastName(), $data['role']->label()));

            return $this->redirectToRoute('app_change_role');
        }

        return $this->render('security/change-role.html.twig', [
            'roleForm' => $form,
            'users' => $users->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC', 'id' => 'ASC']),
        ]);
    }
}
