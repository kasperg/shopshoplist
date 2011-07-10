<?php 

use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Yaml\Yaml;

// Setup Dropbox autoloader
require_once __DIR__.'/vendor/dropbox/autoload.php';

// Load CFPropertyList
require_once __DIR__.'/vendor/cfpropertylist/CFPropertyList.php';

// Load Silex
require_once __DIR__.'/vendor/silex/silex.phar';

$app = new Silex\Application();
$app->register(new Silex\Extension\UrlGeneratorExtension());
// Register autoloading of Symfony components.
// This is currently only needed for Yaml support.
$app['autoloader']->registerNamespace('Symfony', __DIR__.'/vendor');

// Silex session support is broken, so use regular session instead
//https://github.com/fabpot/Silex/issues/112
//$app->register(new Silex\Extension\SessionExtension());
session_start();

$app->before(function () use ($app) {
  // Load the configuration. Fallback to default using environment variabels.
  // These can be provided by PagodaBox.
  $config_path = __DIR__ . '/config/';
  $config = $config_path . ((is_readable($config_path . 'shopshoplist.yaml')) ? 'shopshoplist.yaml' : 'shopshoplist.default.yaml');
  $app['config'] = Yaml::parse($config);
  
  // Setup Dropbox OAuth access
  $app['oauth'] = new Dropbox_OAuth_PHP($app['config']['dropbox']['consumer_key'], $app['config']['dropbox']['consumer_secret']);
  $app['dropbox'] = new Dropbox_API($app['oauth']);
});

$app->get('/', function () use ($app) {
    return '<h1>Frontpage</h1><a href="' . $app['url_generator']->generate('login')  . '">Log in using Dropbox</a>';
})->bind('frontpage');


$app->get('/login', function () use ($app) {
  // Silex session support is broken, so use regular session instead
  //$app['session']->set('oauth_tokens', $app['oauth']->getRequestToken());
  $_SESSION['oauth_tokens'] = $app['oauth']->getRequestToken();
  
  // Redirect to Dropbox auth URL with app auth URL as callback
  return $app->redirect($app['oauth']->getAuthorizeUrl($app['url_generator']->generate('auth', array(), TRUE)));
})->bind('login');

$app->get('/auth', function () use ($app) {
  // Silex session support is broken, so use regular session instead
  //$app['oauth']->setToken($app['session']->get('oauth_tokens'));
  $app['oauth']->setToken($_SESSION['oauth_tokens']);
  $_SESSION['oauth_tokens'] = $app['oauth']->getAccessToken();
  
  // We have a succesfull login so redirect to the lists page
  return $app->redirect($app['url_generator']->generate('lists'));
})->bind('auth');

$app->get('/lists', function () use ($app) {
  // Silex session support is broken, so use regular session instead
  //$app['oauth']->setToken($app['session']->get('oauth_tokens'));
  $app['oauth']->setToken($_SESSION['oauth_tokens']);
  
  $output = '';
  $dir = $app['dropbox']->getMetaData('ShopShop',false);
  foreach ($dir['contents'] as $file) {
    $plist = new CFPropertyList();
    $plist->parse($app['dropbox']->getFile($file['path']), CFPropertyList::FORMAT_BINARY);
    
    $output .= '<h1>' . array_shift(explode('.', basename($file['path']), 2)) . '</h1>';
    
    $output .= '<ul>';
    $plist = $plist->toArray();
    foreach ($plist['shoppingList'] as $entry) {
      $output .= '<li class="' . (($entry['done']) ? 'done' : '') . '">' .
                    (($entry['count']) ? '<span class="count">' . $entry['count'] . '</span> ' : '') . 
                    '<span class="name">' . $entry['name'] . '</span>
                  </li>';
    }
    $output .= '</ul>';
  }
  return $output;
})->bind('lists');

$app->get('/list/{name}', function ($name) use ($app) {
    return 'Frontpage';
})->bind('list');


$app->run();