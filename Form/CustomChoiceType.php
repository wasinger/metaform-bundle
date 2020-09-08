<?php
namespace Wasinger\MetaformBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomChoiceType extends AbstractType
{
    const OTHER_VALUE = 9999;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('multiple', false)
            ->setDefault('choices', [])
            ->setDefault('expanded', true)
            ->setDefault('add_custom_choice', 'Other')
        ;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = $options['choices'];
        $other_label = $options['add_custom_choice'] . ':';
        $choices[$other_label] = self::OTHER_VALUE;

        $builder
            ->add('choice', ChoiceType::class, [
                'multiple' => $options['multiple'],
                'choices' =>$choices,
                'expanded' => $options['expanded'],
                'required' => $options['required'],
                'label' => false
            ])
            ->add('other', TextType::class, [
                'label' => $other_label,
                'required' => false
            ])
        ;
        $builder->addModelTransformer(new CallbackTransformer(
            function ($data) {
                /* Transformation von Model zu Form wird bei uns nicht gebraucht */
            },
            function ($data) use ($other_label) {
                /* Transformation von Form zu Model */
                $choice = $data['choice'];
                $other = $data['other'];
                if (is_array($choice)) {
                    $other_key = \array_search(CustomChoiceType::OTHER_VALUE, $choice);
                    if ($other_key !== false) {
                        $choice[$other_key] = $other_label . ' ' . $other;
                    }
                } else {
                    if ($choice == CustomChoiceType::OTHER_VALUE) {
                        $choice = $other_label . ' ' . $other;
                    }
                }
                return $choice;
            }
        ));
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

        });
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

        });
    }

    public function getBlockPrefix()
    {
        return 'customchoice';
    }

}