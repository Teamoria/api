<?php

use App\Http\Controllers\Api\Controller;

arch('application enums are string backed and isolated')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('versioned API controllers use the controller suffix')
    ->expect('App\Http\Controllers\Api\V1')
    ->toExtend(Controller::class)
    ->toHaveSuffix('Controller');

arch('form requests use the request suffix')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');
