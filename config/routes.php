<?php

use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Define the Slim application routes
 *
 * Here we define several Slim application routes that respond
 * to appropriate HTTP request methods. In this example, the second
 * argument for `Slim::get`, `Slim::post`, `Slim::put`, `Slim::patch`, and `Slim::delete`
 * is an anonymous function.
 */
$app = app();

// Default page
$app->get('/', function (Request $request, Response $response) {
    /** @var $this \Slim\Container */
    return $this->get(\App\Controller\HomeController::class)->indexPage($request, $response);
});

// Json request
$app->post('/index/load', function (Request $request, Response $response) {
    /** @var $this \Slim\Container */
    return $this->get(\App\Controller\HomeController::class)->load($request, $response);
});

// Login
// No auth check for this actions
// Option: _auth = false (no authentication and authorization)
$app->post('/login', function (Request $request, Response $response) {
    /** @var $this \Slim\Container */
    return $this->get(\App\Controller\LoginController::class)->loginSubmit($request, $response);
})->setArgument('_auth', false);

$app->get('/login', function (Request $request, Response $response) {
    /** @var $this \Slim\Container */
    return $this->get(\App\Controller\LoginController::class)->loginPage($request, $response);
})->setArgument('_auth', false)->setName('login');

$app->get('/logout', function (Request $request, Response $response) {
    /** @var $this \Slim\Container */
    return $this->get(\App\Controller\LoginController::class)->logout($request, $response);
})->setArgument('_auth', false);

// Users
$app->get('/users', function (Request $request, Response $response) {
    /** @var $this \Slim\Container */
    return $this->get(\App\Controller\UserController::class)->indexPage($request, $response);
});

// This route will only match if {id} is numeric
$app->get('/users/{id:[0-9]+}', function (Request $request, Response $response) {
    /** @var $this \Slim\Container */
    return $this->get(\App\Controller\UserController::class)->editPage($request, $response);
});

// Sub-Resource
$app->get('/users/{id:[0-9]+}/reviews', function (Request $request, Response $response, $args) {
    /** @var $this \Slim\Container */
    return $this->get(\App\Controller\UserController::class)->reviewPage($request, $response, $args);
});
