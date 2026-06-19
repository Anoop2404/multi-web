<?php

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    \App\Providers\CentralRouteServiceProvider::class,
];
