<?php 

use Symfony\Component\ClassLoader\UniversalClassLoader;

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

$app->get('/', function () use ($app) {
    return 'Frontpage';
});

$app->get('/lists', function () use ($app) {
  $oauth = new Dropbox_OAuth_PHP('a', 'b');
  $dropbox = new Dropbox_API($oauth);

  $tokens = $dropbox->getToken('foo', 'bar');

  // You are recommended to save these tokens, note that you don't
  // need to save the username and password, so just ask your user the 
  // first time and then destroy them.

  $oauth->setToken($tokens);
  
  $output = '';
  
  $dir = $dropbox->getMetaData('ShopShop',false);
  foreach ($dir['contents'] as $file) {
    $plist = new CFPropertyList();
    $plist->parse($dropbox->getFile($file['path']), CFPropertyList::FORMAT_BINARY);
    
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