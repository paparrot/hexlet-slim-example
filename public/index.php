<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$usersPath = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usersPath), true)['users'];

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
});

$app->get('/users', function ($request, $response) use ($users) {
    $str = $request->getQueryParam('str');
    $filtredUsers = array_filter($users, function($user) use ($users, $str) {
        return empty($str) ? true : str_contains(strtolower($user['nickname']), $str);
    });
    $params = [
        'users' => $filtredUsers,
        'str' => $str
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->post('/users', function ($request, $response) use ($users, $usersPath) {
    $userData = $request->getParsedBodyParam('user');
    $userID = empty($users) ? 0 : $userData['id'] + 1;
    $newUser = [
        'nickname' => $userData['nickname'],
        'email' => $userData['email'],
        'id' => $userID
    ];
    $users[] = $newUser;
    file_put_contents($usersPath, json_encode(['users' => $users]));
    return $response->withRedirect('/users', 302);
});

$app->get('/users/new', function ($request, $response) {
    return $this->get('renderer')->render($response, 'users/new.phtml');
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = [
        'id' => $args['id']
    ];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->run();