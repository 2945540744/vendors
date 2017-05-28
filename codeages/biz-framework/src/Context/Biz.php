<?php

namespace Codeages\Biz\Framework\Context;

use Pimple\Container;
use Doctrine\DBAL\DriverManager;
use Pimple\ServiceProviderInterface;
use Codeages\Biz\Framework\Dao\DaoProxy;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class Biz extends Container
{
    protected $providers = [];
    protected $booted    = false;

    public function __construct(array $values = [])
    {
        parent::__construct();

        $this['debug']                 = false;
        $this['logger']                = null;
        $this['migration.directories'] = new \ArrayObject();

        $this['autoload.aliases'] = new \ArrayObject(['' => '']);

        $this['autoload.object_maker.service'] = function ($biz) {
            return function ($namespace, $name) use ($biz) {
                $class = "{$namespace}\\Service\\Impl\\{$name}Impl";

                return new $class($biz);
            };
        };

        $this['autoload.object_maker.dao'] = function ($biz) {
            return function ($namespace, $name) use ($biz) {
                $class = "{$namespace}\\Dao\\Impl\\{$name}Impl";

                return new DaoProxy($biz, new $class($biz));
            };
        };

        $this['autoloader'] = function ($biz) {
            return new ContainerAutoloader($biz, $biz['autoload.aliases'], [
                'service' => $biz['autoload.object_maker.service'],
                'dao'     => $biz['autoload.object_maker.dao']
            ]);
        };

        $this['dispatcher'] = function ($biz) {
            return new EventDispatcher();
        };

        $this['callback_resolver'] = function ($biz) {
            return new CallbackResolver($biz);
        };

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }
    }

    public function register(ServiceProviderInterface $provider, array $values = [])
    {
        $this->providers[] = $provider;
        parent::register($provider, $values);

        return $this;
    }

    public function boot($options = [])
    {
        if (true === $this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            if ($provider instanceof EventListenerProviderInterface) {
                $provider->subscribe($this, $this['dispatcher']);
            }

            if ($provider instanceof BootableProviderInterface) {
                $provider->boot($this);
            }
        }

        $this['db'] = function ($kernel) {
            $cfg = $kernel['database'];

            return DriverManager::getConnection(
                [
                'wrapperClass' => 'Codeages\Biz\Framework\Dao\Connection',
                'dbname'       => $cfg['name'],
                'user'         => $cfg['user'],
                'password'     => $cfg['password'],
                'host'         => $cfg['host'],
                'driver'       => $cfg['driver'],
                'charset'      => $cfg['charset']
                ]
            );
        };

        $this->registerProviders();

        $this->booted = true;
    }

    public function on($eventName, $callback, $priority = 0)
    {
        if ($this->booted) {
            $this['dispatcher']->addListener($eventName, $this['callback_resolver']->resolveCallback($callback), $priority);

            return;
        }

        $this->extend('dispatcher', function (EventDispatcherInterface $dispatcher, $app) use ($callback, $priority, $eventName) {
            $dispatcher->addListener($eventName, $app['callback_resolver']->resolveCallback($callback), $priority);

            return $dispatcher;
        });
    }

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this['dispatcher'];
    }

    /**
     * @param  string       $eventName
     * @param  string|GenericEvent $event
     * @param  array        $arguments
     * @return GenericEvent
     */
    public function dispatch($eventName, $event, array $arguments = [])
    {
        if (!$event instanceof GenericEvent) {
            $event = new GenericEvent($event, $arguments);
        }
        
        return $this->getEventDispatcher()->dispatch($eventName, $event);
    }

    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->getEventDispatcher()->addSubscriber($subscriber);
        return $this;
    }

    public function addEventSubscribers(array $subscribers)
    {
        foreach ($subscribers as $subscriber) {
            if (!$subscriber instanceof EventSubscriberInterface) {
                throw new \RuntimeException('subscriber type error');
            }

            $this->getEventDispatcher()->addSubscriber($subscriber);
        }

        return $this;
    }

    abstract public function registerProviders();

    public function service($alias)
    {
        return $this['autoloader']->autoload('service', $alias);
    }

    public function dao($alias)
    {
        return $this['autoloader']->autoload('dao', $alias);
    }
}
