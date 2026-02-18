<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class TwoFactorAccessSubscriber implements EventSubscriberInterface
{
    private array $allowedRoutes = [
        'app_login',
        'app_logout',
        'app_register_pro',
        'app_register_confirm',
        'app_register_password',
        'app_two_factor',
        'app_two_factor_resend',
        'app_confirm_login',
        'app_confirm_login_verify',
        'app_reset_password_request',
        'app_reset_password',
        'app_change_password',
        'app_change_password_confirm',
    ];

    public function __construct(private readonly RouterInterface $router)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if ($route && in_array($route, $this->allowedRoutes, true)) {
            return;
        }

        $session = $request->getSession();
        if ($session->get('login_confirm_pending')) {
            $event->setResponse(new RedirectResponse($this->router->generate('app_confirm_login')));
            return;
        }

        if ($session->get('two_factor_pending')) {
            $event->setResponse(new RedirectResponse($this->router->generate('app_two_factor')));
        }
    }
}
