<?php

namespace App\Form;

use App\Entity\Boulder;
use App\Enum\Grade;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BoulderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', null, ['label' => 'Nom'])
            ->add('color', null, ['label' => 'Couleur'])
            ->add('grade', ChoiceType::class, ['label' => 'Cotation', 'choices' => Grade::choices(), 'placeholder' => 'Choisissez une cotation'])
            ->add('openedAt', DateTimeType::class, ['label' => 'Ouverture', 'input' => 'datetime_immutable', 'widget' => 'single_text']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Boulder::class]);
    }
}
