<?php


namespace Wasinger\MetaformBundle;


use Wasinger\MetaformBundle\Entity\FormData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Wasinger\MetaformBundle\Form\DummyTypeForDisplayWithoutData;

class MetaformProcessor
{
    use LoggerAwareTrait;

    const HEADING_INDICATOR_VALUE = '-----';

    /**
     * @var string
     */
    private $uploaddir;

    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    private $messages = [];
    private $processedData;
    private $renderedHtmlData;
    private $submission_id;


    function __construct(
        LoggingMailer $mailer,
        EntityManagerInterface $em,
        string $uploaddir
    ) {
        $this->mailer = $mailer;
        $this->em = $em;
        $this->uploaddir = $uploaddir;
    }

    public function processSubmittedForm(Metaform $metaform, string $action)
    {
        $this->messages = [];
        $this->processedData = null;
        $this->renderedHtmlData = '';
        $this->submission_id = null;

        // Als erstes in der Datenbank einen neuen Datensatz anlegen, um eine ID zu erhalten
        $entity = new FormData();
        $entity->setSubmitDate(new \DateTimeImmutable());
        $entity->setFormtype($metaform->getId());
        $this->em->persist($entity);
        $this->em->flush();
        $id = $entity->getId();
        $this->submission_id = $metaform->getShortId() . '-' . sprintf('%05d', $id);

        $this->processedData = $data = $this->buildData($metaform->getFieldConfiguration(), $metaform->getForm($action), $this->submission_id);

        $mailconfig = $metaform->getMailConfig();

        $entity->setReceipient($mailconfig->getAsString('to'));

        $data_html_vertical = '';
        $data_html_horizontal_headings = '<tr><th>Nummer</th>';
        $data_html_horizontal_values = '<tr><td>' . $this->submission_id . '</td>';
        $jsondata = [];
        $textdata = '';

        $in_table = false;
        $i = 1;
        $dtd = $data->getTextdata();
        $count = count($dtd);
        foreach ($dtd as $key => $field) {
            /** @var TextdataDto $field */
            if ($key == $mailconfig->senderfield) {
                $entity->setEmail($field->value);
            }

            if ($key == 'name') {
                $entity->setName($field->value);
            }

            if (!empty($field->fielddef['type']) && $field->fielddef['type'] == 'heading') { // Hack für Zwischenüberschriften, wird in $this->buildData() gesetzt
                if ($i < $count) { // verhindere Überschrift als Letztes, wenn keine Daten mehr kommen
//                            if ($in_table) $data_html_vertical .= '</table>';
//                            $in_table = false;
                    $tag = $field->form->getConfig()->getOption('html_tag');
                    $data_html_vertical .= '<tr><th colspan="2"><'.$tag.'>' . $field->label . '</'.$tag.'></th></tr>';
                    if ($textdata != '') {
                        $textdata .= "\n";
                    }
                    $textdata .= "\n## " . mb_strtoupper($field->label) . " ##\n";
                }
            } else if (!empty($field->fielddef['type']) && $field->fielddef['type'] == 'spacer') {
                if ($i < $count) { // verhindere Überschrift als Letztes, wenn keine Daten mehr kommen
//                            if ($in_table) $data_html_vertical .= '</table>';
//                            $in_table = false;
                    $data_html_vertical .= '<tr><th colspan="2">&nbsp;</th></tr>';
                    if ($textdata != '') {
                        $textdata .= "\n\n";
                    }
                }
            } else if (!empty($field->fielddef['type']) && $field->fielddef['type'] == 'htmltext') {
                $data_html_vertical .= '<tr><td colspan="2">' . $field->label . '</td></tr>';
                if ($textdata != '') {
                    $textdata .= "\n" . TextUtil::asText($field->label) . "\n";
                }
            } else if (!($field->form instanceof DummyTypeForDisplayWithoutData)) {
                if (!empty($field->value)) {
                    $rendered = $this->renderTextdataDto($field);
                    $valuestring = $rendered->getText();
                    $valuehtml = $rendered->getHtml();
                    if (!empty($valuestring)) {
                        $data_html_vertical .= '<tr><th>' . $field->label . '</th><td>' . $valuehtml . '</td></tr>';
                        $textdata .= "\n*" . $field->label . "*\n" . $valuestring . "\n";
                    }
                } else {
                    $valuestring = '';
                }

                // JSON-Daten: Alle Datenfelder, auch leere!
                if (is_array($field->value)) {
                    $jsondata[$key] = [];
                    foreach ($field->value as $vkey => $vvalue) {
                        if ($vvalue instanceof TextdataDto) {
                            $jsondata[$key][$vkey] = $vvalue->value;
                        } else {
                            $jsondata[$key][$vkey] = $vvalue;
                        }
                    }
                } else {
                    $jsondata[$key] = $field->value;
                }

                // Horizontale Tabelle: Auch alle Datenfelder, auch leere!
                $data_html_horizontal_headings .= '<th>' . $key . '</th>';
                $data_html_horizontal_values .= '<td>' . nl2br(\strip_tags($valuestring)) . '</td>';
            }
            $i++;
        }
        $data_html_vertical =
            '<table>'
            . $data_html_vertical
            . '</table>';

        $data_html_horizontal_headings .= '</tr>';
        $data_html_horizontal_values .= '</tr>';
        $data_html_horizontal = '<table>'
            . $data_html_horizontal_headings
            . $data_html_horizontal_values
            . '</table>';

        $entity->setData(json_encode($jsondata));
        $this->renderedHtmlData = $data_html_vertical;

        // Mail an Empfänger
        $subject = '[' . $this->submission_id . '] ' . $mailconfig->subject;

        $html_recipient = $data_html_vertical;

        $text_recipient = TextUtil::asText($mailconfig->text_pre) . "\n"
            . $textdata . "\n"
            . TextUtil::asText($mailconfig->text_post);

        if (!empty($metaform->getOption('horizontal_data_table'))) {
            $html_recipient .= '<h2>Daten zum Kopieren in Excel</h2>' . $data_html_horizontal;
        }

        $email = (new TemplatedEmail())
            ->from(...$mailconfig->from)
            ->to(...$mailconfig->to)
            ->subject($subject)
            ->htmlTemplate('@WasingerMetaform/mail/formmail.html.twig')
            ->context([
                'subject' => $subject,
                'text_pre' => TextUtil::asHtml($mailconfig->text_pre, 'p'),
                'content' => $html_recipient,
                'text_post' => TextUtil::asHtml($mailconfig->text_post, 'p')
            ])
            ->text($text_recipient);

        if ($mailconfig->senderfield && !empty($dtd[$mailconfig->senderfield]->value)) {
            $email->replyTo($dtd[$mailconfig->senderfield]->value);
        }
        if ($mailconfig->cc) {
            $email->cc(...$mailconfig->cc);
        }
        if ($mailconfig->bcc) {
            $email->bcc(...$mailconfig->bcc);
        }

        foreach ($data->getAttachments() as $attachment) {
            $email->attachFromPath($this->uploaddir . '/' . $attachment['file'], $attachment['label'],
                $attachment['type']);
        }

        $success = false;

        try {
            $this->mailer->send($email);
            $entity->setMailSent(new \DateTime());

            if ($metaform->getOption('response_text_only')) {
                $successmessage = $metaform->getOption('response_text_only');
            } else {
//                $successmessage = 'Ihre Daten wurden übermittelt und unter der Bearbeitungsnummer ' . $this->submission_id . ' erfasst.';
                $successmessage = 'Die eingegebenen Daten wurden erfolgreich übermittelt.';
            }
            if ($mailconfig->copy_to_sender) {
                $successmessage .= ' Sie erhalten eine Kopie der übermittelten Daten per E-Mail.';
            }
            $success = true;
            $this->addFlash('success', $successmessage);
        } catch (TransportExceptionInterface $e) {
            if ($this->logger) $this->logger->error("Mail konnte nicht versendet werden. Fehler: " . $e->getMessage());
            $this->addFlash('danger',
                'Ein Fehler trat auf, Ihre Daten konnten leider nicht übermittelt werden.');
        }

        // Mail an Absender
        if ($success && $mailconfig->copy_to_sender && $mailconfig->senderfield && !empty($dtd[$mailconfig->senderfield]->value)) {

            $sender_email = $dtd[$mailconfig->senderfield]->value;
            $html_sender = $data_html_vertical;

            $text_sender = TextUtil::asText($mailconfig->text_pre_sender) . "\n"
                . $textdata . "\n"
                . TextUtil::asText($mailconfig->text_post_sender);

            $email = (new TemplatedEmail())
                ->from(...$mailconfig->from)
                ->to($sender_email)
                ->subject($mailconfig->subject)
                ->htmlTemplate('@WasingerMetaform/mail/formmail.html.twig')
                ->context([
                    'subject' => $mailconfig->subject,
                    'text_pre' => TextUtil::asHtml($mailconfig->text_pre_sender, 'p'),
                    'content' => $html_sender,
                    'text_post' => TextUtil::asHtml($mailconfig->text_post_sender, 'p')
                ])
                ->text($text_sender);

            foreach ($data->getAttachments() as $attachment) {
                $email->attachFromPath($this->uploaddir . '/' . $attachment['file'], $attachment['label'],
                    $attachment['type']);
            }
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                if ($this->logger) $this->logger->error("Mail konnte nicht versendet werden. Fehler: " . $e->getMessage());
                $this->addFlash('warning',
                    'Die Bestätigungs-E-Mail an ' . $sender_email . ' konnte nicht versendet werden.');
            }
        }

        $this->em->flush();
        return $success;
    }

    public function getHtmlSubmittedData()
    {
        return $this->renderedHtmlData;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getSubmissionId()
    {
        return $this->submission_id;
    }

    private function renderTextdataDto(TextdataDto $field)
    {
        $r = new RenderedTextdataDto();
        if (is_array($field->value)) {
            $vkeys = array_keys($field->value);
            if ($vkeys[0] !== 0) { // assoziatives array
                $valuearray = array_filter($field->value);
                $valuestring = '';
                $valuehtml = '';
                if (!empty($valuearray)) {
                    $valuehtml = '<dl>';
                    foreach ($valuearray as $vkey => $vvalue) {
                        if (is_array($vvalue)) {
                            $vvalue = join(', ', $vvalue);
                            if (!$vvalue) continue;
                        } elseif ($vvalue instanceof TextdataDto) {
                            $vkey = $vvalue->label;
                            $vvalue = $vvalue->value;
                            if (!$vvalue) continue;
                        }
                        $valuestring .= $vkey . ': ' . $vvalue . "\n";
                        $valuehtml .= '<dt>' . $vkey . '</dt><dd>' . \strip_tags($vvalue) . '</dd>';
                    }
                    $valuehtml .= '</dl>';
                }
            } else { // numerisches array
                if (count($field->value) > 1) {
                    $valuestring = join("\n- ", $field->value);
                    if ($valuestring != '') {
                        $valuestring = '- ' . $valuestring;
                    }
                    $valuehtml = '<ul>';
                    foreach ($field->value as $fv) {
                        $valuehtml .= '<li>' . \strip_tags($fv) . '</li>';
                    }
                    $valuehtml .= '</ul>';
                } else {
                    $valuestring = $field->value[0];
                    $valuehtml = nl2br(\strip_tags($valuestring));
                }
            }
        } else {
            $valuestring = $field->value;
            $valuehtml = nl2br(\strip_tags($valuestring));
        }
        $r->addHtml($valuehtml);
        $r->addText($valuestring);
        return $r;
    }

    /**
     * @param $formFieldDefinitions
     * @param FormInterface $form
     * @param $idstring
     * @return FormDataDto
     */
    private function buildData($formFieldDefinitions, FormInterface $form, $idstring): FormDataDto
    {
        $formdata = $form->getData();
        $data = new FormDataDto($idstring);
        foreach ($formFieldDefinitions as $name => $ffdef) {
            if (isset($ffdef['type']) && $ffdef['type'] == 'heading') {
                $data->addTextdata($name, new TextdataDto(
                    $name,
                    $ffdef['label'] ?? ucfirst($name),
                    self::HEADING_INDICATOR_VALUE,
                    $form->get($name),
                    $ffdef
                ));

            } elseif (isset($ffdef['type']) && $ffdef['type'] == 'compound') {
                $data->addTextdata($name, new TextdataDto(
                    $name,
                    $ffdef['label'] ?? ucfirst($name),
                    $this->buildData($ffdef['elements'], $form->get($name), $idstring)->getTextdata(),
                    $form->get($name),
                    $ffdef
                ));
            } else if (!($form->get($name) instanceof DummyTypeForDisplayWithoutData)) {
                if (isset($ffdef['type']) && $ffdef['type'] == 'file') {
                    $value = ($formdata[$name] ?? '');
                    if ($value instanceof UploadedFile) {
                        $basefilename = $ffdef['filename'] ?? $name;
                        $ext = $value->guessExtension();
                        if ($ext == 'jpeg') {
                            $ext = 'jpg';
                        }
                        $newFilename = $idstring . '-' . $basefilename . '.' . $ext;
                        $mimeType = $value->getMimeType();

                        try {
                            $value->move(
                                $this->uploaddir,
                                $newFilename
                            );
                        } catch (FileException $e) {
                            // ... handle exception if something happens during file upload
                            if ($this->logger) $this->logger->error($e->getMessage());
                        }
                        $data->addAttachment($name, [
                            'file' => $newFilename,
                            'label' => $newFilename,
                            'type' => $mimeType
                        ]);
//                        $data['textdata'][$name] = [
//                            'label' => $ffdef['label'] ?? ucfirst($name),
//                            'value' => 'Attachment: ' . $newFilename,
//                            'type' => 'file'
//                        ];
                        $data->addTextdata($name, new TextdataDto(
                            $name,
                            $ffdef['label'] ?? ucfirst($name),
                            'Attachment: ' . $newFilename,
                            $form->get($name),
                            $ffdef
                        ));
                    } else {
                        $data->addTextdata($name,  new TextdataDto(
                            $name,
                            $ffdef['label'] ?? ucfirst($name),
                            '',
                            $form->get($name),
                            $ffdef
                        ));
                    }
                } else {

                    if (
                        $form->get($name)->getConfig()->getCompound()
                        && $form->get($name)->getConfig()->getInheritData()
                    ) {
                        foreach ($form->get($name)->all() as $childform) {
                            $childname = $childform->getName();
                            $value = ($formdata[$childname] ?? '');
                            $type = $childform->getConfig()->getType()->getInnerType();
                            if ($value && $type instanceof CheckboxType) {
                                $value = $childform->getConfig()->getOption('value');
                            }
                            $data->addTextdata($childname, new TextdataDto(
                                $childname,
                                ($childform->getConfig()->getOption('label') ?? ucfirst($childname)),
                                $value,
                                $childform,
                                []
                            ));
                        }
                    } else if (
                        $form->get($name)->getConfig()->getCompound()
                        && !($form->get($name)->getConfig()->getType()->getInnerType() instanceof ChoiceType)
                        && is_array($formdata[$name])
                        && !empty($formdata[$name])
                        && array_keys($formdata[$name])[0] !== 0 // kein numerisches array
                    ) {
                        $values = [];
                        foreach ($form->get($name)->all() as $childform) {
                            $childname = $childform->getName();
                            $value = ($formdata[$name][$childname] ?? '');
                            $type = $childform->getConfig()->getType()->getInnerType();
                            if ($value && $type instanceof CheckboxType) {
                                $value = $childform->getConfig()->getOption('value');
                            }
                            $values[$childname] = new TextdataDto(
                                $childname,
                                $childform->getConfig()->getOption('label'),
                                $value,
                                $childform,
                                []
                            );
                        }
                        $data->addTextdata($name, new TextdataDto(
                            $name,
                            $ffdef['label'] ?? ucfirst($name),
                            $values,
                            $form->get($name),
                            $ffdef
                        ));
                    } else{
                        $value = ($formdata[$name] ?? '');

                        // value bei Checkbox-Type: ist in symfony standardmäßig immer 1,
                        // egal welchen Wert das Value-Attribut hat.
                        // Setze auf Wert des Value-Attributes.
                        if (isset($ffdef['type']) && $ffdef['type'] == 'checkbox' && !empty($ffdef['options']['value'])) {
                            if ($value) {
                                $value = $ffdef['options']['value'];
                            }
                        }

                        $data->addTextdata($name, new TextdataDto(
                            $name,
                            $ffdef['label'] ?? ucfirst($name),
                            $value,
                            $form->get($name),
                            $ffdef
                        ));
                    }
                }
            }
        }
        return $data;
    }

    private function addFlash(string $type, string $message)
    {
        $this->messages[] = ['type' => $type, 'message' => $message];
    }

}