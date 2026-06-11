<?php

declare(strict_types=1);

namespace Medas\Cache\Exceptions;

use Medas\Core\Exceptions\BaseException;

class ValueContainsService extends BaseException
{
    public function __construct(string|array $key)
    {
        parent::__construct(is_string($key) ? $key : '[' . implode(' | ', $key) . ']');
    }

    public function pattern(): string
    {
        return 'value to store for key %s contains a Service object';
    }
}
