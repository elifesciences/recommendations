<?php

namespace eLife\Recommendations\EventSubscriber;

use Crell\ApiProblem\ApiProblem;
use eLife\ApiProblem\ApiProblemHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiProblemExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 0]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $apiProblem = new ApiProblem($exception->getMessage() ?: 'Error');
        $apiProblem->setStatus($exception->getStatusCode());

        $event->setResponse((new ApiProblemHandler())->handle($apiProblem));
    }
}
