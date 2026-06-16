<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Locator;

use Componenta\ClassFinder\Attribute\DevOnly;
use Componenta\ClassFinder\Attribute\ListenTo;
use Componenta\ClassFinder\FinalizableListenerInterface;
use Componenta\ClassFinder\FinalizationStateInterface;
use Componenta\Tokenizer\ClassInfo;
use Cycle\Annotated\Annotation\Embeddable;
use Cycle\Annotated\Locator\Embedding;
use Cycle\Annotated\Locator\EmbeddingLocatorInterface;
use ReflectionClass;

#[DevOnly]
#[ListenTo(Embeddable::class)]
final class EmbeddingLocator implements EmbeddingLocatorInterface, FinalizableListenerInterface, FinalizationStateInterface
{
    use FinalizableTrait;

    /** @var array<string, Embedding> */
    private array $embeddings = [];

    public function handle(ClassInfo $info): void
    {
        if (!$info->isConcrete) {
            return;
        }

        $reflection = $info->reflector;

        if (!$reflection instanceof ReflectionClass) {
            return;
        }

        $attributes = $reflection->getAttributes(Embeddable::class);

        if ($attributes === []) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->embeddings[$info->fullyQualifiedName] = new Embedding($attribute, $reflection);
    }

    /**
     * @return array<Embedding>
     */
    public function getEmbeddings(): array
    {
        $this->ensureFinalized();

        return array_values($this->embeddings);
    }

    public function count(): int
    {
        return count($this->embeddings);
    }
}
