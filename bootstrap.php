<?php

declare(strict_types=1);

use App\Controllers\BuildCfdiFromJsonController;
use App\Middleware\AuthorizationMiddleware;
use Dufrei\ApiJsonCfdiBridge\Config;
use Dufrei\ApiJsonCfdiBridge\ConfigBuilder;
use League\Container\Container;
use League\Container\ReflectionContainer;
use Slim\App;
use Slim\Factory\AppFactory;

return (function (): App {
    $PARAMETERS = [
        'FINKON_PRODUCTION' => "",
        'FINKOK_USERNAME' => "",
        'FINKOK_PASSWORD' => "",
    ];

    if($_POST['finkok-username'] != null){
        if($_POST['finkok-password'] == null || $_POST['finkok-production'] == null){
            throw new Exception('Parametros FINKOK no configurados correctamente en la petición');
        } else {
           $PARAMETERS = [
            'FINKOK_PRODUCTION' => $_POST['finkok-production'],
            'FINKOK_USERNAME' => $_POST['finkok-username'],
            'FINKOK_PASSWORD' => $_POST['finkok-password'],
           ];
        }
    } else {
        if($_ENV['FINKOK_USERNAME'] != null && $_ENV['FINKOK_PASSWORD'] != null && $_ENV['FINKOK_PRODUCTION'] != null){
            $PARAMETERS = [
                'FINKOK_USERNAME' => $_ENV['FINKOK_USERNAME'],
                'FINKOK_PASSWORD' => $_ENV['FINKOK_PASSWORD'],
                'FINKOK_PRODUCTION' => $_ENV['FINKOK_PRODUCTION'],
            ];
        } else {
            throw new Exception('Parametros FINKOK no configurados');
        }
    }

    $container = new Container();
    $container->delegate(new ReflectionContainer());
    // $container->add(Config::class, static fn (): Config => (new ConfigBuilder($_ENV))->build());
    $container->add(Config::class, static fn (): Config => (new ConfigBuilder($PARAMETERS))->build());

    $app = AppFactory::create(container: $container);

    //removing this line Authorizathon header is not necesary
    // $app->add($container->get(AuthorizationMiddleware::class));

    $errorMiddleware = $app->addErrorMiddleware(displayErrorDetails: true, logErrors: false, logErrorDetails: false);
    $errorHandler = $errorMiddleware->getDefaultErrorHandler();
    $errorHandler->forceContentType('application/json');

    $app->post('/build-cfdi-from-json', BuildCfdiFromJsonController::class);

    return $app;
})();
