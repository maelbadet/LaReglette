<?php

namespace App\Controller\Admin;

use App\Entity\Boulder;
use App\Enum\Grade;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BoulderCrudController extends AbstractCrudController
{
    #[IsGranted('ROLE_ADMIN')]
    public static function getEntityFqcn(): string
    {
        return Boulder::class;
    }

    public function configureFields(string $pageName): iterable
    {
        foreach (parent::configureFields($pageName) as $field) {
            if ('grade' === $field->getAsDto()->getProperty()) {
                yield ChoiceField::new('grade', 'Cotation')->setChoices(Grade::choices());
                continue;
            }
            if (in_array($field->getAsDto()->getProperty(), ['successCount', 'climberCount', 'averageRating'], true)) {
                $field->hideOnForm();
            }
            yield $field;
        }
    }
}
