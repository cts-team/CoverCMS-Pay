<?php


namespace CoverCMS\Pay\Listeners;


use CoverCMS\Pay\Events;
use CoverCMS\Pay\Log;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class KernelLogSubscriber
 * @package CoverCMS\Pay\Listeners
 */
class KernelLogSubscriber implements EventSubscriberInterface
{
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            Events\PayStarting::class => ['writePayStartingLog', 256],
            Events\PayStarted::class => ['writePayStartedLog', 256],
            Events\ApiRequesting::class => ['writeApiRequestingLog', 256],
            Events\ApiRequested::class => ['writeApiRequestedLog', 256],
            Events\SignFailed::class => ['writeSignFailedLog', 256],
            Events\RequestReceived::class => ['writeRequestReceivedLog', 256],
            Events\MethodCalled::class => ['writeMethodCalledLog', 256],
        ];
    }

    /**
     * writePayStartingLog.
     *
     * @param Events\PayStarting $event
     */
    public function writePayStartingLog(Events\PayStarting $event)
    {
        Log::debug("Starting To {$event->driver}", [$event->gateway, $event->params]);
    }

    /**
     * writePayStartedLog.
     *
     * @param Events\PayStarted $event
     */
    public function writePayStartedLog(Events\PayStarted $event)
    {
        Log::info(
            "{$event->driver} {$event->gateway} Has Started",
            [$event->endpoint, $event->payload]
        );
    }

    /**
     * writeApiRequestingLog.
     *
     * @param Events\ApiRequesting $event
     */
    public function writeApiRequestingLog(Events\ApiRequesting $event)
    {
        Log::debug("Requesting To {$event->driver} Api", [$event->endpoint, $event->payload]);
    }

    /**
     * writeApiRequestedLog.
     *
     * @param Events\ApiRequested $event
     */
    public function writeApiRequestedLog(Events\ApiRequested $event)
    {
        Log::debug("Result Of {$event->driver} Api", $event->result);
    }

    /**
     * writeSignFailedLog.
     *
     * @param Events\SignFailed $event
     */
    public function writeSignFailedLog(Events\SignFailed $event)
    {
        Log::warning("{$event->driver} Sign Verify FAILED", $event->data);
    }

    /**
     * writeRequestReceivedLog.
     *
     * @param Events\RequestReceived $event
     */
    public function writeRequestReceivedLog(Events\RequestReceived $event)
    {
        Log::info("Received {$event->driver} Request", $event->data);
    }

    /**
     * writeMethodCalledLog.
     *
     * @param Events\MethodCalled $event
     */
    public function writeMethodCalledLog(Events\MethodCalled $event)
    {
        Log::info("{$event->driver} {$event->gateway} Method Has Called", [$event->endpoint, $event->payload]);
    }
}