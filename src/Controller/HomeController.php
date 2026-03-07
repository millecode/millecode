<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Service\MillecodeContactMailer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        MillecodeContactMailer $millecodeContactMailer,
        RateLimiterFactoryInterface $contactFormLimiter,
        LoggerInterface $logger,
    ): Response {
        $session = $request->getSession();

        if (!$session->has('millecode_contact_started_at')) {
            $session->set('millecode_contact_started_at', time());
        }

        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $honeypot = trim((string) $request->request->get('_millecode_hp', ''));
            $startedAt = (int) $request->request->get('_millecode_started_at', 0);
            $submittedData = (array) $request->request->all($form->getName());

            $rawEmail = mb_strtolower(trim((string) ($submittedData['email'] ?? 'anonymous')));
            $clientIp = $request->getClientIp() ?? 'unknown';
            $limiter = $contactFormLimiter->create($clientIp . '|' . sha1($rawEmail));
            $limit = $limiter->consume(1);

            if ($honeypot !== '') {
                return $this->redirectWithModal(
                    'danger',
                    'Envoi impossible',
                    'Votre demande n’a pas pu être traitée. Veuillez réessayer.'
                );
            }

            if ($startedAt <= 0 || (time() - $startedAt) < 3) {
                return $this->redirectWithModal(
                    'danger',
                    'Envoi trop rapide',
                    'Veuillez patienter quelques secondes avant d’envoyer le formulaire.'
                );
            }

            if (!$limit->isAccepted()) {
                return $this->redirectWithModal(
                    'warning',
                    'Trop de tentatives',
                    'Vous avez effectué plusieurs envois en peu de temps. Merci de réessayer un peu plus tard.'
                );
            }

            if ($form->isValid()) {
                $this->normalizeContactData($contact);

                if ($this->looksLikeSpam($contact)) {
                    return $this->redirectWithModal(
                        'danger',
                        'Envoi refusé',
                        'Votre message ressemble à une soumission automatisée. Merci de reformuler votre demande.'
                    );
                }

                try {
                    $contact->setCreatedAt(new DateTimeImmutable());
                    $contact->setStatus(false);
                    $contact->setConfirmationToken(bin2hex(random_bytes(32)));

                    $entityManager->persist($contact);
                    $entityManager->flush();

                    $millecodeContactMailer->sendContactEmails($contact);

                    $session->set('millecode_contact_started_at', time());

                    return $this->redirectWithModal(
                        'success',
                        'Message envoyé avec succès',
                        'Merci, votre demande a bien été enregistrée et transmise à Millecode. Un e-mail de confirmation vous a été envoyé.'
                    );
                } catch (\Throwable $exception) {
                    $logger->error('Erreur lors du traitement du formulaire de contact Millecode.', [
                        'message' => $exception->getMessage(),
                        'exception_class' => $exception::class,
                        'trace' => $exception->getTraceAsString(),
                    ]);

                    return $this->redirectWithModal(
                        'danger',
                        'Échec de l’envoi',
                        'Votre demande a été enregistrée, mais l’envoi de l’e-mail a échoué. Vérifiez le token de confirmation, le sender Brevo et les logs applicatifs.'
                    );
                }
            }
        }

        return $this->render('home/index.html.twig', [
            'contactForm' => $form->createView(),
            'millecode_contact_started_at' => $session->get('millecode_contact_started_at', time()),
        ]);
    }

    #[Route('/contact/confirm/{token}', name: 'app_contact_confirm_email', methods: ['GET'])]
    public function confirmEmail(
        string $token,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $contact = $entityManager->getRepository(Contact::class)->findOneBy([
            'confirmationToken' => $token,
        ]);

        if (!$contact) {
            $this->addFlash('millecode_contact_modal', [
                'type' => 'danger',
                'title' => 'Lien invalide',
                'message' => 'Le lien de confirmation est invalide ou expiré.',
            ]);

            return $this->redirect($this->generateUrl('app_home') . '#millecode-contact');
        }

        if ($contact->isStatus()) {
            $this->addFlash('millecode_contact_modal', [
                'type' => 'warning',
                'title' => 'E-mail déjà confirmé',
                'message' => 'Cette adresse e-mail a déjà été confirmée.',
            ]);

            return $this->redirect($this->generateUrl('app_home') . '#millecode-contact');
        }

        $contact->setStatus(true);
        $contact->setConfirmationToken(null);

        $entityManager->flush();

        $this->addFlash('millecode_contact_modal', [
            'type' => 'success',
            'title' => 'Félicitations',
            'message' => 'Vous avez bien confirmé votre adresse e-mail.',
        ]);

        return $this->redirect($this->generateUrl('app_home') . '#millecode-contact');
    }

    private function redirectWithModal(string $type, string $title, string $message): RedirectResponse
    {
        $this->addFlash('millecode_contact_modal', [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);

        return $this->redirect($this->generateUrl('app_home') . '#millecode-contact');
    }

    private function normalizeContactData(Contact $contact): void
    {
        $contact->setNom($this->cleanSingleLine((string) $contact->getNom()));
        $contact->setEmail(mb_strtolower(trim((string) $contact->getEmail())));
        $contact->setPhone($this->cleanSingleLine((string) $contact->getPhone()));
        $contact->setSujet($this->cleanSingleLine((string) $contact->getSujet()));
        $contact->setMessage($this->cleanMultiline((string) $contact->getMessage()));
    }

    private function cleanSingleLine(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s{2,}/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function cleanMultiline(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace("/[ \t]+\n/", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim($value);
    }

    private function looksLikeSpam(Contact $contact): bool
    {
        $content = mb_strtolower(
            $contact->getNom() . ' ' .
            $contact->getEmail() . ' ' .
            $contact->getSujet() . ' ' .
            $contact->getMessage()
        );

        $urlCount = preg_match_all('/https?:\/\/|www\./i', $content);
        $emailCount = preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $content);
        $repeatedChar = preg_match('/(.)\1{9,}/u', $content) === 1;

        if ($urlCount !== false && $urlCount >= 3) {
            return true;
        }

        if ($emailCount !== false && $emailCount >= 3) {
            return true;
        }

        return $repeatedChar;
    }
}