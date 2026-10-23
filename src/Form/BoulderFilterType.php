<?php

namespace App\Form;

use App\Entity\Sector;
use App\Enum\Grade;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BoulderFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('sector', EntityType::class, ['class' => Sector::class, 'choice_label' => 'name', 'label' => 'Secteur', 'required' => false, 'placeholder' => 'Tous'])
            ->add('color', TextType::class, ['label' => 'Couleur', 'required' => false])
            ->add('gradeMin', ChoiceType::class, ['label' => 'Cotation minimale', 'required' => false, 'placeholder' => 'Toutes', 'choices' => Grade::choices()])
            ->add('gradeMax', ChoiceType::class, ['label' => 'Cotation maximale', 'required' => false, 'placeholder' => 'Toutes', 'choices' => Grade::choices()])
        ;
        if ($options['authenticated']) {
            $builder->add('unfinished', CheckboxType::class, ['label' => 'Pas encore réussis', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'authenticated' => true,
            'constraints' => [new Assert\Callback(static function (?array $data, ExecutionContextInterface $context): void {
                if (null !== ($data['gradeMin'] ?? null) && null !== ($data['gradeMax'] ?? null) && $data['gradeMin'] > $data['gradeMax']) {
                    $context->buildViolation('La cotation maximale doit être supérieure ou égale à la cotation minimale.')->atPath('[gradeMax]')->addViolation();
                }
            })],
        ]);
        $resolver->setAllowedTypes('authenticated', 'bool');
    }
}
