<?php

namespace Wasinger\MetaformBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Wasinger\MetaformBundle\Repository\FormDataRepository")
 */
class FormData
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var \DateTimeInterface
     * @ORM\Column(type="datetime_immutable", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $submit_date;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $formtype;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $receipient;

    /**
     * @var \DateTimeInterface|null
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $mail_sent;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $name;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $email;

    /**
     * @var string|null
     * @ORM\Column(type="text", nullable=true)
     */
    private $data;


    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getSubmitDate(): \DateTimeInterface
    {
        return $this->submit_date;
    }

    public function setSubmitDate(\DateTimeInterface $dateTime)
    {
        $this->submit_date = $dateTime;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData(string $data): void
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getFormtype(): string
    {
        return $this->formtype;
    }

    /**
     * @param string $formtype
     */
    public function setFormtype(string $formtype): void
    {
        $this->formtype = $formtype;
    }

    /**
     * @return string|null
     */
    public function getReceipient(): ?string
    {
        return $this->receipient;
    }

    /**
     * @param string|null $receipient
     */
    public function setReceipient(?string $receipient): void
    {
        $this->receipient = $receipient;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getMailSent(): ?\DateTimeInterface
    {
        return $this->mail_sent;
    }

    /**
     * @param \DateTimeInterface|null $mail_sent
     */
    public function setMailSent(?\DateTimeInterface $mail_sent): void
    {
        $this->mail_sent = $mail_sent;
    }
}
