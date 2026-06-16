<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Factory;

use Componenta\Cycle\App\Locator\EntityLocator;
use Psr\Container\ContainerInterface;

final class EntityLocatorFactory
{
    public function __invoke(ContainerInterface $container): EntityLocator
    {
        return new EntityLocator();
    }
}