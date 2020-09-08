<?php
namespace Wasinger\MetaformBundle\Exceptions;

/**
 * Base class for exceptions whose messages are meant to be passed through the translator
 * and shown to the end user
 *
 */
class TranslatableUserMessageException extends \Exception
{
    private $messageKey;

    private $messageData = [];

    public function __construct(string $message = '', array $messageData = [], int $code = 0, \Throwable $previous = null)
    {
        $messageKey = $message;

        // Interpolate message date into default message placeholders
        // for default (untranslated) display via $exception->getMessage().
        $mf = new \MessageFormatter('en', $message);
        $message = $mf->format($messageData);
        parent::__construct($message, $code, $previous);

        $this->setSafeMessage($messageKey, $messageData);
    }

    /**
     * Sets a message that will be shown to the user.
     *
     * @param string $messageKey  The message or message key
     * @param array  $messageData Data to be passed into the translator
     */
    public function setSafeMessage(string $messageKey, array $messageData = [])
    {
        $this->messageKey = $messageKey;
        $this->messageData = $messageData;
    }
    /**
     * Message key to be used by the translation component.
     *
     * @return string
     */
    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    /**
     * Message data to be used by the translation component.
     *
     * @return array
     */
    public function getMessageData(): array
    {
        return $this->messageData;
    }

}