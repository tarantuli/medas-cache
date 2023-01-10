<?php

declare(strict_types=1);

namespace Medas\Cache\Exceptions;

use Medas\Core\Exceptions\BaseException;

class CacheTypeNotSupported extends BaseException
{
    public function __construct(string $type)
    {
        parent::__construct($type);
    }

    public function pattern(): string
    {
        return 'cache type %s is not supported';
    }
}
