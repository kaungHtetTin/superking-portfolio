<?php

use App\Controllers\SiteController;
use App\Controllers\AdminController;
$router->get('/', [SiteController::class, 'home']);
$router->get('/products', [SiteController::class, 'products']);
$router->get('/products/{id}', [SiteController::class, 'product']);
$router->get('/company', [SiteController::class, 'company']);
$router->get('/contact', [SiteController::class, 'contact']);
$router->get('/admin/login', [AdminController::class, 'login']);
$router->post('/admin/login', [AdminController::class, 'authenticate']);
$router->post('/admin/logout', [AdminController::class, 'logout'], true);
$router->get('/admin', [AdminController::class, 'dashboard'], true);
$router->get('/admin/settings', [AdminController::class, 'settings'], true);
$router->post('/admin/settings', [AdminController::class, 'saveSettings'], true);
$router->get('/admin/account', [AdminController::class, 'account'], true);
$router->post('/admin/account', [AdminController::class, 'saveAccount'], true);
$router->get('/admin/{type}', [AdminController::class, 'listing'], true);
$router->get('/admin/{type}/create', [AdminController::class, 'form'], true);
$router->get('/admin/{type}/{id}/edit', [AdminController::class, 'form'], true);
$router->post('/admin/{type}/save', [AdminController::class, 'save'], true);
$router->post('/admin/{type}/{id}/delete', [AdminController::class, 'delete'], true);
