<?php


namespace Wasinger\MetaformBundle\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HeadingType extends DummyTypeForDisplayWithoutData
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'html_tag' => 'h2'
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['html_tag'] = $options['html_tag'];
    }
}