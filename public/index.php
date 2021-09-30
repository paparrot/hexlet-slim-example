<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$usersPath = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usersPath), true)['users'];

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
})->setName("home");

$app->get('/users', function ($request, $response) use ($users) {
    $str = $request->getQueryParam('str');
    $filtredUsers = array_filter($users, function($user) use ($users, $str) {
        return empty($str) ? true : str_contains(strtolower($user['nickname']), $str);
    });
    $messages = $this->get("flash")->getMessages();
    $params = [
        'users' => $filtredUsers,
        'str' => $str,
        'flash' => $messages
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName("users");

$app->post('/users', function ($request, $response) use ($users, $usersPath, $router) {
    $newUser = $request->getParsedBodyParam('user');
    $users[] = $newUser;
    $this->get('flash')->addMessage("success", "Пользователь добавлен");
    file_put_contents($usersPath, json_encode(['users' => $users]));
    return $response->withRedirect($router->urlFor('users') , 302);
})->setName("addUser");

$app->get('/users/new', function ($request, $response) use ($users) {
    $id = count($users);
    return $this->get('renderer')->render($response, 'users/new.phtml', ['id'=> $id]);
})->setName("userForm");

$app->get('/users/{id}', function ($request, $response, $args) use ($users) {
    $id = $args['id'];
    $user = collect($users)->firstWhere('id', $id);
    if(empty($user)) {
        return $this->get('renderer')->render($response, 'users/404.phtml')->withStatus(404);
    }
    $params = [
        'user' => $user,
    ];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName("user");

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName("courses");

$app->run();