<?php

declare(strict_types=1);

namespace Medas\Cache\Exceptions;

use Medas\Core\Exceptions\BaseException;

class ValueContainsService extends BaseException
{
    public function __construct(string|array $key, object $service)
    {
        parent::__construct(
            is_string($key) ? $key : '[' . implode(' | ', $key) . ']',
            $service::class
        );
    }

    public function pattern(): string
    {
        return 'value to store for key %s contains a Service object %s';
    }
}
