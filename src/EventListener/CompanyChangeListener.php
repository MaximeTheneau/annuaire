<?php

namespace App\EventListener;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class CompanyChangeListener
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(NEXTJS_WEBHOOK_URL)%')] private readonly string $webhookUrl,
        #[Autowire('%env(NEXTJS_WEBHOOK_SECRET)%')] private readonly string $webhookSecret,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        if ($args->getObject() instanceof Company) {
            $this->triggerRebuild();
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        if ($args->getObject() instanceof Company) {
            $this->triggerRebuild();
        }
    }

    private function triggerRebuild(): void
    {
        if (empty($this->webhookUrl)) {
            return;
        }

        $body = json_encode(['event' => 'build']);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->webhookSecret);

        try {
            $this->httpClient->request('POST', $this->webhookUrl . '/api/webhook', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-hub-signature-256' => $signature,
                    'x-github-event' => 'build',
                ],
                'body' => $body,
            ]);
        } catch (\Throwable) {
            // Silently fail — rebuild is best-effort
        }
    }
}
