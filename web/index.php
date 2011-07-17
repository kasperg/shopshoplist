<?php 

use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Yaml\Yaml;

// Setup Dropbox autoloader
require_once __DIR__.'/../vendor/dropbox/autoload.php';

// Load CFPropertyList
require_once __DIR__.'/../vendor/cfpropertylist/CFPropertyList.php';

// Load Silex
require_once __DIR__.'/../vendor/silex/silex.phar';

$app = new Silex\Application();
$app->register(new Silex\Extension\UrlGeneratorExtension());
$app->register(new Silex\Extension\TwigExtension(), array(
    'twig.path'       => __DIR__.'/../views',
    'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));

// Register autoloading of Symfony components.
// This is currently only needed for Yaml support.
$app['autoloader']->registerNamespace('Symfony', __DIR__.'/../vendor');

// Silex session support is broken, so use regular session instead
//https://github.com/fabpot/Silex/issues/112
//$app->register(new Silex\Extension\SessionExtension());
session_start();

$app->before(function () use ($app) {
  // Add routing helpers in Twig here as the request context must be defined.
  $app['twig']->addExtension(new RoutingExtension($app['url_generator']));
  
  // Load the configuration. Fallback to default using environment variabels.
  // These can be provided by PagodaBox.
  $config_path = __DIR__ . '/../config/';
  $config = $config_path . ((is_readable($config_path . 'shopshoplist.yaml')) ? 'shopshoplist.yaml' : 'shopshoplist.default.yaml');
  $app['config'] = Yaml::parse($config);
  
  // Setup Dropbox OAuth access
  $app['oauth'] = new Dropbox_OAuth_PHP($app['config']['dropbox']['consumer_key'], $app['config']['dropbox']['consumer_secret']);
  $app['dropbox'] = new Dropbox_API($app['oauth']);
});

$app->get('/', function () use ($app) {
    return $app['twig']->render('front.twig');
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
  
  // We have a succesfull login so redirect to the first list
  $dir = $app['dropbox']->getMetaData('ShopShop',false);
  if (sizeof($dir['contents'] > 0)) {
    return $app->redirect($app['url_generator']->generate('list', array('name' => shopshop_list_name(array_shift($dir['contents'])))));  
  }
})->bind('auth');

$app->get('/logout', function () use ($app) {
  session_destroy();
  return $app->redirect($app['url_generator']->generate('frontpage'));
})->bind('logout');

$app->get('/list/{name}', function ($name) use ($app) {
  // Silex session support is broken, so use regular session instead
  //$app['oauth']->setToken($app['session']->get('oauth_tokens'));
  $app['oauth']->setToken($_SESSION['oauth_tokens']);

  $plist = new CFPropertyList();
  $plist->parse($app['dropbox']->getFile(shopshop_list_path($name)), CFPropertyList::FORMAT_BINARY);

  $entries = array();
  $plist = $plist->toArray();
  foreach ($plist['shoppingList'] as $entry) {
    $entries[] = array('name' => $entry['name'],
                       'count' => (($entry['count']) ? $entry['count'] : FALSE),
                       'done' => (($entry['done']) ? 'done' : ''));
  }
  
  $lists = array();
  $dir = $app['dropbox']->getMetaData('ShopShop',false);
  foreach ($dir['contents'] as $file) {
    $lists[] = shopshop_list_name($file);
  }
  
  return $app['twig']->render('list.twig', array('name' => $name, 'entries' => $entries, 'lists' => $lists));
})->bind('list');

$app->run();

function shopshop_list_name($file) {
  return array_shift(explode('.', basename($file['path']), 2));
}

function shopshop_list_path($name) {
  return '/ShopShop/' . $name . '.shopshop';
}