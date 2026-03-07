<?php

namespace App\Service;

use App\Entity\Contact;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MillecodeContactMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(string:MILLECODE_CONTACT_RECIPIENT)%')]
        private readonly string $contactRecipient,
        #[Autowire('%env(string:MILLECODE_MAIL_FROM)%')]
        private readonly string $mailFrom,
        #[Autowire('%env(string:MILLECODE_MAIL_FROM_NAME)%')]
        private readonly string $mailFromName,
    ) {
    }

    public function sendContactEmails(Contact $contact): void
    {
        $clientName = $this->sanitizeHeader((string) $contact->getNom());
        $clientEmail = $this->sanitizeEmail((string) $contact->getEmail());
        $clientPhone = $this->sanitizeHeader((string) $contact->getPhone());
        $clientSubject = $this->sanitizeHeader((string) $contact->getSujet());
        $clientMessage = trim((string) $contact->getMessage());

        $adminEmail = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->to(new Address($this->contactRecipient, 'Millecode'))
            ->replyTo(new Address($clientEmail, $clientName))
            ->subject(sprintf('Nouveau message site Millecode • %s', $clientSubject))
            ->htmlTemplate('emails/contact_admin_notification.html.twig')
            ->context([
                'contact' => $contact,
                'clientName' => $clientName,
                'clientEmail' => $clientEmail,
                'clientPhone' => $clientPhone,
                'clientSubject' => $clientSubject,
                'clientMessage' => $clientMessage,
                'sentAt' => new \DateTimeImmutable(),
            ]);

        try {
            $this->mailer->send($adminEmail);

            $token = $contact->getConfirmationToken();

            if (!is_string($token) || trim($token) === '') {
                throw new \RuntimeException('Le token de confirmation est vide ou absent sur le contact.');
            }

            $confirmationUrl = $this->urlGenerator->generate(
                'app_contact_confirm_email',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $clientConfirmation = (new TemplatedEmail())
                ->from(new Address($this->mailFrom, $this->mailFromName))
                ->to(new Address($clientEmail, $clientName))
                ->subject('Millecode • Confirmez votre adresse e-mail')
                ->htmlTemplate('emails/contact_client_confirmation.html.twig')
                ->context([
                    'contact' => $contact,
                    'clientName' => $clientName,
                    'clientEmail' => $clientEmail,
                    'clientPhone' => $clientPhone,
                    'clientSubject' => $clientSubject,
                    'clientMessage' => $clientMessage,
                    'confirmationUrl' => $confirmationUrl,
                    'sentAt' => new \DateTimeImmutable(),
                ]);

            $this->mailer->send($clientConfirmation);

            $this->logger->info('Emails de contact Millecode envoyés avec succès.', [
                'contact_email' => $clientEmail,
                'contact_subject' => $clientSubject,
            ]);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Erreur SMTP/Mailer lors de l’envoi des emails Millecode.', [
                'error' => $exception->getMessage(),
                'contact_email' => $clientEmail,
                'contact_subject' => $clientSubject,
            ]);

            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('Erreur générale lors de l’envoi des emails Millecode.', [
                'error' => $exception->getMessage(),
                'contact_email' => $clientEmail,
                'contact_subject' => $clientSubject,
            ]);

            throw $exception;
        }
    }

    private function sanitizeHeader(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s{2,}/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function sanitizeEmail(string $email): string
    {
        $email = str_replace(["\r", "\n"], '', trim(mb_strtolower($email)));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse e-mail invalide.');
        }

        return $email;
    }
}