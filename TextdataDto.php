<?php


namespace Wasinger\MetaformBundle;


use Symfony\Component\Form\FormInterface;

class TextdataDto
{
    public $key;
    public $label;
    public $value;
    /** @var FormInterface */
    public $form;
    public $fielddef;

    public function __construct($key, $label, $value, FormInterface $form, $fielddef)
    {
        $this->key = $key;
        $this->label = $label;
        $this->value = $value;
        $this->form = $form;
        $this->fielddef = $fielddef;
    }
}