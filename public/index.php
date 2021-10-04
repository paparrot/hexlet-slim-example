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

$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

function getUsers($request): array
{
    return json_decode($request->getCookieParam("users", json_encode([])), true);
}

$app->get('/', function($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
})->setName("index");

$app->get("/users", function ($request, $response) {

    $users = getUsers($request);
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

$app->get("/users/new", function($request, $response) {
    return $this->get("renderer")->render($response, "users/new.phtml");
});

$app->get("/users/{id}", function ($request, $response, array $args)  {
    $users = getUsers($request);
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

$app->post("/users", function ($request, $response) use ($router) {

    $user = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($user);
    
    if (count($errors) === 0) {
        $id = uniqid('user-', true);
        $user['id'] = $id;

        $users = getUsers($request);

        $users[$id] = $user;

        $this->get('flash')->addMessage("success", "Пользователь добавлен");

        $url = $router->urlFor('users');

        $encodedUsers = json_encode($users);

        return $response->withHeader("Set-Cookie", "users={$encodedUsers}")->withRedirect($url, 302);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, '/users/new.phtml', $params);
});

$app->get("/users/{id}/edit", function($request, $response, array $args) {
    $users = getUsers($request);
    $id = $args['id'];
    $user = $users[$id];

    $params = [
        'user' => $user,
        'errors' => []
    ];

    return $this->get("renderer")->render($response, '/users/edit.phtml', $params);
});

$app->patch("/users/{id}", function($request, $response, array $args) use ($router) {
    $users = getUsers($request);
    $id = $args['id'];
    $user = $users[$id];
    $userData = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($userData);

    if (count($errors) === 0) {
        $user['nickname'] = $userData['nickname'];
        $user['email'] = $userData['email'];
        $users[$id] = $user;
        $this->get('flash')->addMessage("success", "Информация обновлена");

        $encodedUsers = json_encode($users);
        return $response->withHeader("Set-Cookie", "users={$encodedUsers};path=/")->withRedirect($router->urlFor("users"), 302);
    }

    $params = [
        'user' => $userData,
        'errors' => $errors
    ];
    
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete("/users/{id}", function($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $users = getUsers($request);
    unset($users[$id]);

    $encodedUsers = json_encode($users);

    $this->get('flash')->addMessage("success", "Пользователь удален");
    return $response->withHeader("Set-Cookie", "users={$encodedUsers};path=/")->withRedirect($router->urlFor('users'), 302);
});

$app->run();
