<?php

namespace PHPSTORM_META {

    use Medas\Cache\CacheManager;

    override(CacheManager::create(), map([
        '' => '@',
    ]));
}
