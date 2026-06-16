<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Locator;

use Componenta\ClassFinder\Attribute\DevOnly;
use Componenta\ClassFinder\Attribute\ListenTo;
use Componenta\ClassFinder\FinalizableListenerInterface;
use Componenta\ClassFinder\FinalizationStateInterface;
use Componenta\Tokenizer\ClassInfo;
use Cycle\Annotated\Annotation\Entity as EntityAttribute;
use Cycle\Annotated\Locator\Entity;
use Cycle\Annotated\Locator\EntityLocatorInterface;
use ReflectionClass;

#[DevOnly]
#[ListenTo(EntityAttribute::class)]
final class EntityLocator implements EntityLocatorInterface, FinalizableListenerInterface, FinalizationStateInterface
{
    use FinalizableTrait;

    /** @var array<string, Entity> */
    private array $entities = [];

    public function handle(ClassInfo $info): void
    {
        if (!$info->isConcrete) {
            return;
        }

        $reflection = $info->reflector;

        if (!$reflection instanceof ReflectionClass) {
            return;
        }

        $attributes = $reflection->getAttributes(EntityAttribute::class);

        if ($attributes === []) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->entities[$info->fullyQualifiedName] = new Entity($attribute, $reflection);
    }

    /**
     * @return array<Entity>
     */
    public function getEntities(): array
    {
        $this->ensureFinalized();

        return array_values($this->entities);
    }

    public function count(): int
    {
        return count($this->entities);
    }
}
