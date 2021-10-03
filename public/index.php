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

$app->get('/', function($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
})->setName("index");

$app->get("/users", function ($request, $response) use ($usersPath) {
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $queryString = $request->getQueryParam("str");
    $filtredUsers = array_filter($users, function($user) use ($queryString) {
        $queryString = mb_strtolower($queryString);
        $userNickname = mb_strtolower($user['nickname']);
        return empty($queryString) ? true : str_contains($userNickname, $queryString);
    });
    $flash = $this->get("flash")->getMessages();
    $params = [
        'users' => $filtredUsers,
        'str' => $queryString,
        'flash' => $flash
    ];
    return $this->get("renderer")->render($response, 'users/index.phtml', $params);
})->setName("users");

$app->get("/users/new", function($request, $response) use ($usersPath) {
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $id = uniqid('user-', true);
    return $this->get("renderer")->render($response, "users/new.phtml", ['id' => $id]);
});

$app->get("/users/{id}", function ($request, $response, array $args) use ($usersPath) {
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $id = $args['id'];
    $user = collect($users)->firstWhere('id', $id);
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
        $users = json_decode(file_get_contents($usersPath), true)['users'];
        $users[] = $user;
        file_put_contents($usersPath, json_encode(['users' => $users]));
        $this->get('flash')->addMessage("success", "Пользователь добавлен");
        $url = $router->urlFor('users');
        return $response->withRedirect($url, 302);
    }

    $params = [
        'user' => $user,
        'errors' => $errors,
        'id' => $user['id']
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, '/users/new.phtml', $params);
});

$app->get("/users/{id}/edit", function($request, $response, array $args) use ($usersPath) {
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $id = $args['id'];
    $user = collect($users)->firstWhere('id', $id);
    $params = [
        'user' => $user,
        'error' => $errors,
        'id' => $id
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
        $newUsers = collect($users)->map(function ($user) use ($userData) {
            if ($user['id'] === $userData['id']) {
                $user['nickname'] = $userData['nickname'];
                $user['email'] = $userData['email'];
                return $user;
            }
            return $user;
        })->all();
        $this->get('flash')->addMessage("success", "Информация обновлена");   
        file_put_contents($usersPath, json_encode(['users' => $newUsers]));
        $url = $router->urlFor("users");
        return $response->withRedirect($url, 302);
    }

    $params = [
        'user' => $userData,
        'errors' => $errors,
        'id' => $id
    ];
    
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete("/users/{id}", function($request, $response, array $args) use ($usersPath, $router) {
    $id = $args['id'];
    $users = json_decode(file_get_contents($usersPath), true)['users'];
    $newUsers = collect($users)->filter(fn($user) => $user['id'] !== $id)->values()->all();
    file_put_contents($usersPath, json_encode(['users' => $newUsers]));
    $this->get('flash')->addMessage("success", "Пользователь удален");
    return $response->withRedirect($router->urlFor('users'), 302);
});

$app->run();
