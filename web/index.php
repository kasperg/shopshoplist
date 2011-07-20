<?php 

// Setup Dropbox autoloader
require_once __DIR__.'/../vendor/dropbox/autoload.php';

// Load CFPropertyList
require_once __DIR__.'/../vendor/cfpropertylist/CFPropertyList.php';

// Load Silex
require_once __DIR__.'/../vendor/silex/silex.phar';

// Create the application
$app = new Silex\Application();

// Setup session handling.
// This needs to be done before the app is run.
$app['autoloader']->registerNamespace('LazySession', __DIR__.'/../vendor');
$app->register(new LazySession\LazySessionExtension());

$app->before(function () use ($app) {
  // Register autoloading of Symfony components.
  // This is currently only needed for Yaml support.
  $app['autoloader']->registerNamespace('Symfony', __DIR__.'/../vendor');

  // Load the configuration. Fallback to default using environment variabels.
  // These can be provided by PagodaBox.
  $app['config'] = $app->share(function () {
    $config_path = __DIR__ . '/../config/';
    $config = $config_path . ((is_readable($config_path . 'shopshoplist.yaml')) ? 'shopshoplist.yaml' : 'shopshoplist.default.yaml');
    return Symfony\Component\Yaml\Yaml::parse($config);
  });

  // Setup a PDO connection
  $app['pdo'] = $app->share(function() use ($app) {
    if (isset($app['config']['database']['dsn'])) {
      $dsn = $app['config']['database']['dsn'];
    } else {
      $dsn = 'mysql:dbname=' . $app['config']['database']['name'] . ';unix_socket=' . $app['config']['database']['unix_socket'];
    }
    return new Pdo($dsn, $app['config']['database']['user'], $app['config']['database']['password']);
  });

  // Use database for session storage
  $app['session.storage'] = $app->share(function () use ($app) {
    return new Symfony\Component\HttpFoundation\SessionStorage\PdoSessionStorage(
      $app['pdo'], 
      $app['session.storage.options'], 
      array('db_table' =>     'session',
            'db_id_col' =>    'id',
            'db_data_col' =>  'data',
            'db_time_col' =>  'timestamp'));
  });

  // Register Twig for templating
  $app->register(new Silex\Extension\TwigExtension(), array(
      'twig.path'       => __DIR__.'/../views',
      'twig.class_path' => __DIR__.'/../vendor/twig/lib',
  ));
  
  // Use UrlGenerator for easier URL generation in controller and templates
  $app->register(new Silex\Extension\UrlGeneratorExtension());
  $app['twig']->addExtension(new Symfony\Bridge\Twig\Extension\RoutingExtension($app['url_generator']));

  // Setup Dropbox OAuth access
  $app['oauth'] = new Dropbox_OAuth_PHP($app['config']['dropbox']['consumer_key'], $app['config']['dropbox']['consumer_secret']);
  $app['dropbox'] = new Dropbox_API($app['oauth']);
});

$app->get('/', function () use ($app) {
  // Ensure database has been setup
  $tables = array('session');
  if ($result = $app['pdo']->query('SHOW TABLES')) {
    while($table = $result->fetchColumn(0)) {
      if (($key = array_search($table, $tables)) !== FALSE) {
        unset($tables[$key]);
      }
    }
  }
  
  if (sizeof($tables) > 0) {
    throw new DatabaseException('Missing tables: '. implode(', ', $tables));
  }
  
  return $app['twig']->render('front.twig');
})->bind('frontpage');

$app->get('/setup', function () use ($app) {
  // (Re)create database structure for storing sessions
  $app['pdo']->query('DROP TABLE `session`');
  $app['pdo']->query('CREATE TABLE `session` (
                        `id` varchar(255) NOT NULL,
                        `data` text NOT NULL,
                        `timestamp` int(11) NOT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `session_id_idx` (`id`))
                      ENGINE=InnoDB DEFAULT CHARSET=utf8');
  
  $app['session']->start();
  $app['session']->setFlash('success', 'The datebase has been initialized successfully.');
  return $app->redirect($app['url_generator']->generate('frontpage'));
})->bind('setup');

$app->get('/login', function () use ($app) {
  $app['session']->set('oauth_tokens', $app['oauth']->getRequestToken());
  
  // Redirect to Dropbox auth URL with app auth URL as callback
  return $app->redirect($app['oauth']->getAuthorizeUrl($app['url_generator']->generate('auth', array(), TRUE)));
})->bind('login');

$app->get('/auth', function () use ($app) {
  $app['oauth']->setToken($app['session']->get('oauth_tokens'));
  $app['session']->set('oauth_tokens', $app['oauth']->getAccessToken());
  
  // We have a succesfull login so redirect to the first list
  $dir = $app['dropbox']->getMetaData('ShopShop',false);
  if (sizeof($dir['contents'] > 0)) {
    return $app->redirect($app['url_generator']->generate('list', array('session_id' => session_id(), 'name' => shopshop_list_name(array_shift($dir['contents'])))));  
  }
})->bind('auth');

$app->get('/rebuild', function () use ($app) {
  // Extract all data from the session, clear it and reinsert the data into the new session
  $data = $app['session']->all();
  $app['session']->invalidate();
  $app['session']->replace($data);
})->bind('rebuild');

$app->get('/logout', function () use ($app) {
  // We migrate so we keep session information available for future access
  $app['session']->migrate();
  
  return $app->redirect($app['url_generator']->generate('frontpage'));
})->bind('logout');

$app->get('/{session_id}/list/{name}', function ($session_id, $name) use ($app) {
  // Silex session storageonly allows setting session id if sessions do not use 
  // cookies so we force it instead.
  // https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpFoundation/SessionStorage/NativeSessionStorage.php#L79
  session_id($session_id);
  $app['oauth']->setToken($app['session']->get('oauth_tokens'));
  
  // Retrieve all entries in the current list
  $plist = new CFPropertyList();
  $plist->parse($app['dropbox']->getFile(shopshop_list_path($name)), CFPropertyList::FORMAT_BINARY);

  $entries = array();
  $plist = $plist->toArray();
  foreach ($plist['shoppingList'] as $entry) {
    $entries[] = array('name' => $entry['name'],
                       'count' => (($entry['count']) ? $entry['count'] : FALSE),
                       'done' => (($entry['done']) ? 'done' : ''));
  }
  
  // Retrieve all lists
  $lists = array();
  $dir = $app['dropbox']->getMetaData('ShopShop',false);
  foreach ($dir['contents'] as $file) {
    $lists[] = shopshop_list_name($file);
  }
  
  return $app['twig']->render('list.twig', array('name' => $name, 'entries' => $entries, 'lists' => $lists));
})->bind('list');

$app->error(function (\Exception $e) use ($app) {
  $messages = array();
  
  if ($e instanceof \PDOException && $e->getCode() == 1049) {
    // Unknown database
  } elseif ($e instanceof DatabaseException) {
    $messages[] = 'It seems like the database has not been initialized.';
    $messages[] = 'Have you run <a href="' . $app['url_generator']->generate('setup') . '">setup</a> yet?';
  }
  
  return $app['twig']->render('error.twig', array('messages' => $messages, 'exception' => $e));
});

$app->run();

function shopshop_list_name($file) {
  return array_shift(explode('.', basename($file['path']), 2));
}

function shopshop_list_path($name) {
  return '/ShopShop/' . $name . '.shopshop';
}

class DatabaseException extends \Exception {}