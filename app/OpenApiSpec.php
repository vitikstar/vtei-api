<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'VTEI Student API',
    version: '1.0.0',
    description: 'REST API для мобільного додатку студентів ВТЕІ'
)]
#[OA\SecurityScheme(
    securityScheme: 'BearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Server(url: '/api', description: 'API Server')]
class OpenApiSpec {}
