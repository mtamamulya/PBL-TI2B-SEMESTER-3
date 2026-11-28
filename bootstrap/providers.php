<?php

use App\BackendKel2\Workflow\WorkflowServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DataSourceServiceProvider;

return [
    AppServiceProvider::class,
    DataSourceServiceProvider::class,
    WorkflowServiceProvider::class,
];
