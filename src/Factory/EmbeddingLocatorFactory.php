<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Factory;

use Componenta\Cycle\App\Locator\EmbeddingLocator;

final class EmbeddingLocatorFactory
{
    public function __invoke(): EmbeddingLocator
    {
        return new EmbeddingLocator();
    }
}