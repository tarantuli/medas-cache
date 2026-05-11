<?php

declare(strict_types=1);

namespace Medas\Cache\Exceptions;

use Medas\Core\Exceptions\BaseException;

class RedisKeyDisappeared extends BaseException
{
    public function __construct(string $key)
    {
        parent::__construct($key);
    }

    public function pattern(): string
    {
        return 'Redis key "%s" disappeared';
    }
}
