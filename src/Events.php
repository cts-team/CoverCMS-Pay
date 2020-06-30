<?php


namespace CoverCMS\Pay;


use Exception;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class Events
 * @package CoverCMS\Pay
 * @method static Event dispatch(Event $event)                                Dispatches an event to all registered listeners
 * @method static array getListeners($eventName = null)                       Gets the listeners of a specific event or all listeners sorted by descending priority.
 * @method static int|void getListenerPriority($eventName, $listener)         Gets the listener priority for a specific event.
 * @method static bool hasListeners($eventName = null)                        Checks whether an event has any registered listeners.
 * @method static void addListener($eventName, $listener, $priority = 0)      Adds an event listener that listens on the specified events.
 * @method static removeListener($eventName, $listener)                       Removes an event listener from the specified events.
 * @method static void addSubscriber(EventSubscriberInterface $subscriber)    Adds an event subscriber.
 * @method static void removeSubscriber(EventSubscriberInterface $subscriber)
 */
class Events
{
    /**
     * dispatcher.
     *
     * @var EventDispatcher
     */
    protected static $dispatcher;

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return call_user_func_array([self::getDispatcher(), $method], $args);
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([self::getDispatcher(), $method], $args);
    }

    /**
     * @param EventDispatcher $dispatcher
     */
    public static function setDispatcher(EventDispatcher $dispatcher)
    {
        self::$dispatcher = $dispatcher;
    }

    /**
     * @return EventDispatcher
     */
    public static function getDispatcher(): EventDispatcher
    {
        if (self::$dispatcher) {
            return self::$dispatcher;
        }

        return self::$dispatcher = self::createDispatcher();
    }

    /**
     * @return EventDispatcher
     */
    public static function createDispatcher(): EventDispatcher
    {
        return new EventDispatcher();
    }
}