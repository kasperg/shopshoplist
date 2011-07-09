<?php 

use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Yaml\Yaml;

//Setup Symfony classloader and components
require_once __DIR__.'/vendor/Symfony/Component/ClassLoader/UniversalClassLoader.php';
$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
  'Symfony' => __DIR__.'/vendor',
));
$loader->register();

//Setup Dropbox autoloader
require_once __DIR__.'/vendor/dropbox/autoload.php';

//Load CFPropertyList
require_once __DIR__.'/vendor/cfpropertylist/CFPropertyList.php';

//Load Silex
require_once __DIR__.'/vendor/silex/silex.phar';

$app = new Silex\Application();

//Silex session support is broken, so use regular session instead
//https://github.com/fabpot/Silex/issues/112
//$app->register(new Silex\Extension\SessionExtension());
session_start();

$app->before(function () use ($app) {
  // Load the configuration. Fallback to default using environment variabels.
  // These can be provided by PagodaBox.
  $config_path = __DIR__ . '/config/';
  $config = $config_path . ((is_readable($config_path . 'shopshoplist.yaml')) ? 'shopshoplist.yaml' : 'shopshoplist.default.yaml');
  $app['config'] = Yaml::parse($config);
  
  $app['oauth'] = new Dropbox_OAuth_PHP($app['config']['dropbox']['consumer_key'], $app['config']['dropbox']['consumer_secret']);
  $app['dropbox'] = new Dropbox_API($app['oauth']);
});

$app->get('/', function () use ($app) {
    return '<h1>Frontpage</h1><a href="login">Log in using Dropbox</a>';
});


$app->get('/login', function () use ($app) {
  //Silex session support is broken, so use regular session instead
  //$app['session']->set('oauth_tokens', $app['oauth']->getRequestToken());
  $_SESSION['oauth_tokens'] = $app['oauth']->getRequestToken();
  return $app->redirect($app['oauth']->getAuthorizeUrl('http://localhost/ssl/auth'));
});

$app->get('/auth', function () use ($app) {
  //Silex session support is broken, so use regular session instead
  //$app['oauth']->setToken($app['session']->get('oauth_tokens'));
  $app['oauth']->setToken($_SESSION['oauth_tokens']);
  $_SESSION['oauth_tokens'] = $app['oauth']->getAccessToken();
  return $app->redirect('http://localhost/ssl/lists');
});

$app->get('/lists', function () use ($app) {
  //Silex session support is broken, so use regular session instead
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
});

$app->get('/list/{name}', function ($name) use ($app) {
    return 'Frontpage';
});


$app->run();