<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\DeleteAccountType;
use App\Form\ProfileType;
use App\Service\AccountManager;
use App\Repository\BoulderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[IsGranted('ROLE_USER')]
final class ProfilController extends AbstractController
{
    public function __construct(private AccountManager $accountManager) {}

    #[Route('/profil', name: 'app_profil', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, Security $security, BoulderRepository $boulders): Response
    {
        // Les données du formulaire restent séparées du compte jusqu'à leur validation.
        $profileForm = $this->createForm(ProfileType::class, [
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
        ], ['user_id' => $user->getId()]);
        $passwordForm = $this->createForm(ChangePasswordType::class);
        $deleteForm = $this->createForm(DeleteAccountType::class);
        $profileForm->handleRequest($request);
        $passwordForm->handleRequest($request);
        $deleteForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $emailChanged = $this->accountManager->updateProfile($user, $profileForm->getData());
            if ($emailChanged) {
                $security->logout(false); // CSRF was validated by the submitted form.
                $this->addFlash('success', 'Adresse e-mail modifiée. Reconnectez-vous avec cette adresse et confirmez-la grâce au lien reçu par e-mail.');

                return $this->redirectToRoute('app_login');
            }
            $this->addFlash('success', 'Vos informations personnelles ont été enregistrées.');

            return $this->redirectToRoute('app_profil');
        }
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $this->accountManager->changePassword($user, $passwordForm->get('newPassword')->getData());
            $security->logout(false);
            $this->addFlash('success', 'Mot de passe modifié. Connectez-vous avec votre nouveau mot de passe.');

            return $this->redirectToRoute('app_login');
        }
        if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
            $this->accountManager->delete($user);
            $security->logout(false);
            $this->addFlash('success', 'Votre compte et vos ascensions ont été supprimés.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('profil/index.html.twig', [
            'user' => $user,
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'deleteForm' => $deleteForm,
            'openedBoulders' => $this->isGranted('ROLE_OUVREUR') ? $boulders->findOpenedBy($user) : [],
        ]);
    }
}
