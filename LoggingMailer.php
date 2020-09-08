<?php
namespace Wasinger\MetaformBundle;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class LoggingMailer implements MailerInterface
{
    private $transport;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(TransportInterface $transport, LoggerInterface $logger)
    {
        $this->transport = $transport;
        $this->logger = $logger;
    }

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        /** @var Email $message */
        if (!($message instanceof Email)) {
            throw new \InvalidArgumentException('Unser Mailer kann nur E-Mails senden');
        }
        
        $receipients = $this->stringify_addresses($message->getTo());
        $senders = $this->stringify_addresses($message->getFrom());
        $subject = $message->getSubject();
        $from = $senders;

        try {
            $sentMessage = $this->transport->send($message, $envelope);
            $real_receipients = $this->stringify_addresses($sentMessage->getEnvelope()->getRecipients());
            $this->logger->info(
                'Message successfully sent.', [
                'sent-to' => $real_receipients,
                'original-to' => $receipients,
                'message-id' => $sentMessage->getMessageId(),
                'subject' => $subject,
                'from' => $sentMessage->getEnvelope()->getSender()->getAddress(),
                'transport' => (string) $this->transport,
                'debug' => $sentMessage->getDebug()
            ]);
        } catch (TransportException $e) {
            $this->logger->error('Message could not be sent.', [
                'to' => $receipients,
                'from' => $from,
                'subject' => $subject,
                'transport' => (string) $this->transport,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * @param Address[] $addresses
     * @return string
     */
    private function stringify_addresses(array $addresses)
    {
        $r = '';
        foreach ($addresses as $a) {
            /** @var Address $a */
            if ($r != '') {
                $r .= ', ';
            }
            $r .= $a->getAddress();
        }
        return $r;
    }
}