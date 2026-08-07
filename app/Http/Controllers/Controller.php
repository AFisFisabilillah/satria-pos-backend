<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'API Documentation for SATRIAPOS Backend (POS & FIFO Stock Management)',
    title: 'SATRIAPOS API',
    contact: new OA\Contact(email: 'admin@satriapos.com')
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local Development Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    name: 'Authorization',
    in: 'header',
    scheme: 'bearer'
)]
abstract class Controller
{
    //
}
