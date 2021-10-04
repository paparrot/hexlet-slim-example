<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

session_start();


$container = new Container();
$container->set('renderer', function () {
    $phpView = new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    $phpView->setLayout("layout.php");
    return $phpView;
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$usersPath = __DIR__ . '/../data/users.json';

$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

function getUsers($request): array
{
    return json_decode($request->getCookieParam("users", json_encode([])));
}

$app->get('/', function($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
})->setName("index");

$app->get("/users", function ($request, $response) use ($usersPath) {

    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $queryString = $request->getQueryParam("str");
    $filtredUsers = collect($users)->values()->filter(function ($user) use ($queryString) {
        $qs = mb_strtolower($queryString);
        $us = mb_strtolower($user['nickname']);
        return empty($qs) ? true : str_contains($us, $qs);
    })->all();

    $flash = $this->get("flash")->getMessages();

    $params = [
        'users' => $filtredUsers,
        'str' => $queryString,
        'flash' => $flash
    ];
    
    return $this->get("renderer")->render($response, 'users/index.phtml', $params);
})->setName("users");

$app->get("/users/new", function($request, $response) use ($usersPath) {
    return $this->get("renderer")->render($response, "users/new.phtml");
});

$app->get("/users/{id}", function ($request, $response, array $args) use ($usersPath) {
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $id = $args['id'];
    $user = $users[$id];
    if (!$user) {
        return $this->get('renderer')->render($response, "/users/404.phtml")->withStatus(404);
    }
    $params = [
        'user' => $user
    ];
    return $this->get("renderer")->render($response, "/users/show.phtml", $params);
});

$app->post("/users", function($request, $response, array $args) use ($usersPath, $router) {
    $user = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($user);
    

    if (count($errors) === 0) {
        $id = uniqid('user-', true);
        $user['id'] = $id;

        $users = json_decode(file_get_contents($usersPath), true)['users'];

        $users[$id] = $user;

        file_put_contents($usersPath, json_encode(['users' => $users]));
        $this->get('flash')->addMessage("success", "Пользователь добавлен");

        $url = $router->urlFor('users');
        return $response->withRedirect($url, 302);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, '/users/new.phtml', $params);
});

$app->get("/users/{id}/edit", function($request, $response, array $args) use ($usersPath) {
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $id = $args['id'];
    $user = $users[$id];

    $params = [
        'user' => $user,
        'errors' => []
    ];

    return $this->get("renderer")->render($response, '/users/edit.phtml', $params);
});

$app->patch("/users/{id}", function($request, $response, array $args) use ($usersPath, $router) {
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $id = $args['id'];
    $userData = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($userData);

    if (count($errors) === 0) {
        $user = $users[$id];
        $user['nickname'] = $userData['nickname'];
        $user['email'] = $userData['email'];

        $this->get('flash')->addMessage("success", "Информация обновлена");

        file_put_contents($usersPath, json_encode(['users' => $newUsers]));

        $url = $router->urlFor("users");
        return $response->withRedirect($url, 302);
    }

    $params = [
        'user' => $userData,
        'errors' => $errors
    ];
    
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete("/users/{id}", function($request, $response, array $args) use ($usersPath, $router) {
    $id = $args['id'];
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    unset($users[$id]);
    file_put_contents($usersPath, json_encode(['users' => $users]));
    
    $this->get('flash')->addMessage("success", "Пользователь удален");
    return $response->withRedirect($router->urlFor('users'), 302);
});

$app->run();
