<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->redirectToRoute('admin_boulder_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('La Réglette');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(BoulderCrudController::class, 'Blocs', 'fas fa-mountain');
        yield MenuItem::linkTo(SectorCrudController::class, 'Secteurs', 'fas fa-map');
        yield MenuItem::linkTo(AscentCrudController::class, 'Ascensions', 'fas fa-arrow-up');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fas fa-users');
        yield MenuItem::linkToRoute('Retour au site', 'fas fa-home', 'app_home');
    }
}
