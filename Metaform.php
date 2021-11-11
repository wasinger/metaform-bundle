<?php

namespace Wasinger\MetaformBundle;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Validator\Constraints\Length;
use Wasinger\MetaformBundle\Form\CustomChoiceType;
use Wasinger\MetaformBundle\Form\HeadingType;
use Wasinger\MetaformBundle\Form\HtmltextType;
use Wasinger\MetaformBundle\Form\SpacerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Object that holds the configuration for a specific form.
 * Instances are created by MetaformLoader::load().
 *
 * @package Wasinger\MetaformBundle
 */
class Metaform
{
    /**
     * A "slug" string identifying the form.
     * Used in the URL and for finding configuration files.
     *
     * @var string
     */
    private $id;

    /**
     * The human readable title of the form. Displayed as heading.
     *
     * @var string
     */
    private $form_title;

    /**
     * A very short string identifying the form, to be prepended to the sequence number
     * of submitted data
     *
     * @var string
     */
    private $short_id;

    /**
     * Array with processed configuration as defined in FormConfigurationDefinition
     *
     * @var array
     */
    private $formconfig;

    /**
     * @var FormBuilderInterface
     */
    private $formbuilder;

    /**
     * Stores the built form instance
     *
     * @var FormInterface
     */
    private $built_form;

    /**
     * FormConfigurationObject constructor
     *
     * @param string $form_id
     * @param array $formconfig Array with processed configuration as defined in FormConfigurationDefinition
     * @param FormBuilderInterface $formbuilder
     */
    public function __construct(string $form_id, array $formconfig, FormBuilderInterface $formbuilder)
    {
        $this->id = $form_id;
        $this->formconfig = $formconfig;
        $this->formbuilder = $formbuilder;

        $this->form_title = ($formconfig['title'] ?? '');
        $this->short_id = ($formconfig['short_id'] ?? '');
        if (!$this->form_title || !$this->short_id) {
            $fna = explode('_', $this->id);
            $namestring = '';
            $kurzstring = '';
            foreach ($fna as $namepart) {
                $kurzstring .= substr($namepart, 0, 1);
                $namestring .= ' ' . \ucfirst($namepart);
            }
            if (!$this->form_title) {
                $this->form_title = trim($namestring);
            }
            if (!$this->short_id) {
                $this->short_id = $kurzstring;
            }
        }
    }

    public function getTitle(): string
    {
        return $this->form_title;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getShortId(): string
    {
        return $this->short_id;
    }

    public function getFieldConfiguration()
    {
        return $this->formconfig['elements'];
    }

    public function getOptions()
    {
        return $this->formconfig['options'];
    }

    public function getOption(string $name)
    {
        return ($this->formconfig['options'][$name] ?? null);
    }

    public function getForm(string $action): FormInterface
    {
        if (!$this->built_form) {
            $fb = $this->formbuilder;

            $fb->setAction($action);

            $field_definitions = $this->getFieldConfiguration();

            foreach ($field_definitions as $fieldname => $fielddef) {
                $this->buildFormfield($this->formbuilder, $fieldname, $fielddef);
            }

            if ($affirmations = $this->getOption('affirmations')) {
                foreach ($affirmations as $akey => $alabel) {
                    $fb->add($akey, CheckboxType::class, [
                        'label' => $alabel,
                        'required' => true
                    ]);
                }
            }

            $fb->add('submitbutton', SubmitType::class, ['label' => 'Absenden']);

            $this->built_form = $fb->getForm();
        }
        return $this->built_form;
    }

    public function getPrefaceText()
    {
        return trim($this->formconfig['options']['text_pre'] ?? '');
    }

    public function getPostfaceText()
    {
        return trim($this->formconfig['options']['text_post'] ?? '');
    }

    public function getMailConfig(): ?FormMailConfiguration
    {
        if (isset($this->formconfig['mail'])) {
            $mc = $this->formconfig['mail'];
        } elseif (isset($this->formconfig['options']['mail'])) {
            $mc = $this->formconfig['options']['mail'];
        }
        if (!empty($mc)) {
            return FormMailConfiguration::fromArray($mc);
        }
        return null;
    }

    public function isDisabled(): bool
    {
        return (!empty($this->formconfig['disabled']));
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return self::timeToDatetime($this->getOption('valid_from'));
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return self::timeToDatetime($this->getOption('valid_until'));
    }

    private function buildFormfield(FormBuilderInterface $fb, string $fieldname, ?array $fielddef) {
        $type = $fielddef['type'];
        $options = ($fielddef['options'] ?? []);

        // Symfony constraints
        if (!empty($options['constraints'])) {
            $constraints = [];
            foreach ($options['constraints'] as $constraint_class => $constraint_options) {
                $constraints[] = new $constraint_class($constraint_options);
            }
            $options['constraints'] = $constraints;
        }

        if (!empty($fielddef['required'])) {
            $options['constraints'][] = new NotBlank();
        }

        if (!empty($fielddef['maxlength'])) {
            $options['constraints'][] = new Length([
                'max' => $fielddef['maxlength'],
                'normalizer' => function($v) {
                    return trim(str_replace("\r\n", "\n", $v)); // DOS-Zeilenenden ersetzen, da die sonst als zwei Zeichen gezählt werden, im Browser aber nur als 1!!!
                }
            ]);
            $options['attr']['maxlength'] = $fielddef['maxlength'];
        }
        

        switch ($type) {
            case 'checkboxgroup':
                $options['choices'] = ($fielddef['choices'] ?? $fielddef['options']['choices'] ?? []);
                $options['expanded'] = true;
                $options['multiple'] = true;
                $fieldtype = (!empty($options['add_custom_choice']) ? CustomChoiceType::class : ChoiceType::class);
                break;
            case 'radio':
            case 'radiogroup':
                $options['choices'] = ($fielddef['choices'] ?? $fielddef['options']['choices'] ?? []);
                $fieldtype = (!empty($options['add_custom_choice']) ? CustomChoiceType::class : ChoiceType::class);
                $options['expanded'] = true;
                $options['multiple'] = false;
                break;
            case 'select':
                $options['choices'] = ($fielddef['choices'] ?? $fielddef['options']['choices'] ?? []);
//                    $add_empty_choice = (isset($element['add_empty_choice']) ? $element['add_empty_choice'] : null);
                $options['expanded'] = false;
                $options['multiple'] = $fielddef['options']['multiple'] ?? false;
                $fieldtype = ChoiceType::class;
                break;
            case 'checkbox':
                $fieldtype = CheckboxType::class;
//                    if (isset($element['options']['unchecked_value'])) $field->setUncheckedValueForHumans($element['options']['unchecked_value']);
                break;
            case 'textarea':
                $fieldtype = TextareaType::class;
                if (isset($options['rows'])) {
                    $options['attr']['rows'] = $options['rows'];
                    unset($options['rows']);
                }
                if (isset($options['cols'])) {
                    $options['attr']['cols'] = $options['cols'];
                    unset($options['cols']);
                }
                break;
            case 'hidden':
                $fieldtype = HiddenType::class;
                break;
            case 'email':
                $fieldtype = EmailType::class;
                if (empty($fielddef['options']['constraints'])) {
                    $options['constraints'][] = new \Symfony\Component\Validator\Constraints\Email();
                }
                break;
            case 'tel':
                $fieldtype = TelType::class;
                break;
            case 'number':
                $fieldtype = NumberType::class;
                if (!isset($fielddef['options']['html5'])) {
                    $options['html5'] = true; // set default to html5
                }
                break;
            case 'url':
                $fieldtype = UrlType::class;
                if (empty($fielddef['options']['constraints'])) {
                    $options['constraints'][] = new \Symfony\Component\Validator\Constraints\Url();
                }
                $options['default_protocol'] = null;
                break;
            case 'heading':
                $fieldtype = HeadingType::class;
                break;
            case 'spacer':
                $fieldtype = SpacerType::class;
                break;
            case 'htmltext':
                $fieldtype = HtmltextType::class;
                break;
            case 'file':
                $fieldtype = FileType::class;
                $options['label_attr'] = ['data-browse' => 'Datei auswählen...'];
                unset($options['filename']);
                break;
            case 'date':
                $fieldtype = DateType::class;
                if (!isset($options['widget'])) $options['widget'] = 'single_text';
                if (!isset($options['input'])) $options['input'] = 'string';
                break;
            case 'time':
                $fieldtype = TimeType::class;
                if (!isset($options['widget'])) $options['widget'] = 'single_text';
                if (!isset($options['input'])) $options['input'] = 'string';
                if (!isset($options['input_format'])) $options['input_format'] = 'H:i';
                break;
            case 'text':
                $fieldtype = TextType::class;
                break;
            case 'compound':
                $fieldtype = FormType::class;
                $children = $fielddef['elements'];
                break;
            default:
                if (class_exists($type)) {
                    $fieldtype = $type;
                } else {
                    $fieldtype = TextType::class;
                }
        }

        // prepare choices
        // symfony requires choices as ['label' => 'value']
        // while we get either ['value1', 'value2', ...] or ['value' => 'label']
        if (!empty($options['choices'])) {
            $choices = [];
            $i = 0;
            foreach ($options['choices'] as $key => $value) {
                if (is_int($key) && $key == $i) {
                    $choices[$value] = $value;
                } else {
                    $choices[$value] = $key;
                }
                $i++;
            }
            $options['choices'] = $choices;
        }

        // if choice_loader is given instead of choices,
        // get service instance from container
        if (!empty($options['choice_loader'])) {
            if (!\class_exists($options['choice_loader'])) throw new \InvalidArgumentException('Option choice_loader must contain a fully qualified classname');
            $options['choice_loader'] = new $options['choice_loader'];
        }

        $fb->add($fieldname, $fieldtype, $options);

        // der "compound"-Formulartyp hat children
        if (!empty($children)) {
            foreach ($children as $name => $options) {
                $this->buildFormfield($fb->get($fieldname), $name, $options);
            }
        }
    }

    /**
     * @param mixed $time
     * @return \DateTimeInterface|null
     */
    protected static function timeToDatetime($time): ?\DateTimeInterface
    {
        if ($time instanceof \DateTimeInterface) return $time;
        try {
            if (is_int($time)) {
                return \DateTimeImmutable::createFromFormat('U', $time);
            } else if (is_string($time)) {
                return new \DateTimeImmutable($time);
            } else {
                throw new \InvalidArgumentException;
            }
        } catch (\Exception $e) {
            return null;
        }
    }


}