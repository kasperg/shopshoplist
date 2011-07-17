<?php

namespace LazySession;

use Silex\Application;
use Silex\ExtensionInterface;

use Symfony\Component\HttpFoundation\SessionStorage\NativeSessionStorage;

class LazySessionExtension implements ExtensionInterface
{

  public function register(Application $app)
  {
    $app['session'] = $app->share(function () use ($app) {  
      return new LazySession($app['session.storage']);
    });

    $app['session.storage'] = $app->share(function () use ($app) {
      return new NativeSessionStorage($app['session.storage.options']);
    });

    if (!isset($app['session.storage.options'])) {
      $app['session.storage.options'] = array();
    }
  }

}