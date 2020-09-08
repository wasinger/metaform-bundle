<?php


namespace Wasinger\MetaformBundle;


use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FormDataDto
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var TextdataDto[]
     */
    private $textdata = [];
    private $attachments = [];

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function addTextdata(string $name, TextdataDto $value)
    {
        $this->textdata[$name] = $value;
    }

    public function addAttachment($name, $attachment)
    {
        $this->attachments[$name] = $attachment;
    }

    /**
     * @return TextdataDto[]
     */
    public function getTextdata()
    {
        return $this->textdata;
    }

    public function getAttachments()
    {
        return $this->attachments;
    }
}