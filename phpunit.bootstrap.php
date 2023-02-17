<?php

declare(strict_types=1);

use Medas\Cache\CachePackage;
use Medas\ServiceManager\{ServiceConfig, ServiceManager};

chdir(__DIR__);

new ServiceManager(function (): ServiceConfig {
    $config = new ServiceConfig();

    $config->addPackages([
        CachePackage::instance(),
    ]);

    return $config;
});

