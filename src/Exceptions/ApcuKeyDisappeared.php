<?php

declare(strict_types=1);

namespace Medas\Cache\Exceptions;

use Medas\Core\Exceptions\BaseException;

class ApcuKeyDisappeared extends BaseException
{
    public function __construct(string $key)
    {
        parent::__construct($key);
    }

    public function pattern(): string
    {
        return 'APCu key "%s" disappeared';
    }
}
