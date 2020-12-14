<?php

namespace Wasinger\MetaformBundle\Controller;

use Symfony\Contracts\Translation\TranslatorInterface;
use Wasinger\MetaformBundle\Exceptions\FormNotAvailableException;
use Wasinger\MetaformBundle\MetaformProcessor;
use Wasinger\MetaformBundle\TextUtil;
use Wasinger\MetaformBundle\MetaformLoader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;

class BaseFormController extends AbstractController
{
    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var MetaformLoader
     */
    private $metaformLoader;

    /**
     * @var MetaformProcessor
     */
    private $metaformProcessor;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    private $env;

    function __construct(
        SessionInterface $session,
        MetaformLoader $metaformLoader,
        MetaformProcessor $metaformProcessor,
        string $env,
        ?TranslatorInterface $translator = null
    ) {
        $this->session = $session;
        $this->metaformLoader = $metaformLoader;
        $this->metaformProcessor = $metaformProcessor;
        $this->env = $env;
        $this->translator = $translator;
    }


    /**
     * @param Request $request
     * @param string $form_id
     * @param array $options
     * @return Response
     */
    public function processForm(Request $request, string $form_id, array $options = [])
    {
        $metaform = $this->metaformLoader->load($form_id);
        $form_title = $metaform->getTitle();

        $base_template = $options['base_template'] ?? 'base.html.twig';

        // Überprüfen auf Verfügbarkeit des Formulars
        try {
            if ($metaform->isDisabled()) {
                throw new FormNotAvailableException('Form {name} is disabled.', ['name' => $form_id]);
            }
            $now = new \DateTime();
            $date_from = $metaform->getValidFrom();
            if ($date_from && $date_from > $now) {
                    throw new FormNotAvailableException('Form {name} will be available from {date, date} {date, time, short}', ['name' => $form_id, 'date' => $date_from]);

            }
            $date_to = $metaform->getValidUntil();
            if ($date_to && $date_to < $now) {
                    throw new FormNotAvailableException('Form {name} was available until {date, date} {date, time, short}', ['name' => $form_id, 'date' => $date_to]);
            }
        } catch (FormNotAvailableException $e) {
            if ($this->env == 'prod' && !$options['debug']) {
                throw new FormNotAvailableException("This form is not yet available, or not available any more.", [], 0, $e);
            } else {
                $this->addFlash('warning', $this->trans('<b>dev environment</b>: in production, this form is currently not shown.') . ' ' . $this->trans($e->getMessageKey(), $e->getMessageData()));
            }
        }

        $form = $metaform->getForm($request->getRequestUri());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Übermittelte Formulardaten verarbeiten
            $success = $this->metaformProcessor->processSubmittedForm($metaform, $request->getRequestUri());
            if ($success) {
                // Übermittelte Formulardaten in Session speichern zur Bestätigungsanzeige
                $this->session->set('submitted_data', $this->metaformProcessor->getHtmlSubmittedData());
            }
            foreach ($this->metaformProcessor->getMessages() as $message)
            {
                $this->addFlash($message['type'], $message['message']);
            }
            return $this->redirectToRoute('form', ['formname' => $form_id]);

        } else if ($this->session->has('submitted_data')) {
            $data = $this->session->remove('submitted_data');
            $text_pre_submitted = $metaform->getOption('text_pre_submitted');
            $text_post_submitted = $metaform->getOption('text_post_submitted');
            return $this->render('@WasingerMetaform/success.html.twig', [
                'base_template' => $base_template,
                'data' => ($metaform->getOption('response_text_only') ? '' : $data),
                'title' => $form_title,
                'text_pre_submitted' => $text_pre_submitted,
                'text_pre_submitted_is_html' => TextUtil::is_html($text_pre_submitted),
                'text_post_submitted' => $text_post_submitted,
                'text_post_submitted_is_html' => TextUtil::is_html($text_post_submitted),
                'backlink' => ($options['backlink'] ?? null)
            ]);

        } else {
            $formtemplate = $form_id . '.html.twig';
            $template = file_exists($this->getParameter('wasinger_metaform.form_dir') . '/' . $formtemplate) ? '@metaforms/' . $form_id . '.html.twig' : '@WasingerMetaform/default.html.twig';
            $text_pre = $metaform->getPrefaceText();
            $text_post = $metaform->getPostfaceText();

            return $this->render($template, [
                'base_template' => $base_template,
                'form' => $form->createView(),
                'text_pre' => $text_pre,
                'text_pre_is_html' => TextUtil::is_html($text_pre),
                'text_post' => $text_post,
                'text_post_is_html' => TextUtil::is_html($text_post),
                'title' => $form_title,
                'backlink' => ($options['backlink'] ?? null)
            ]);
        }
    }

    private function trans($messageKey, $messageData = [])
    {
        if ($this->translator instanceof TranslatorInterface) {
            return $this->translator->trans($messageKey, $messageData);
        } else {
            $mf = new \MessageFormatter('en', $messageKey);
            return $mf->format($messageData);
        }
    }
}
