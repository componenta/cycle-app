<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Locator;

use Componenta\ClassFinder\Exception\ListenerAlreadyFinalizedException;
use LogicException;

trait FinalizableTrait
{
    private bool $isFinalized = false;

    public bool $finalized {
        get => $this->isFinalized;
    }

    public function finalize(): void
    {
        if ($this->isFinalized) {
            throw ListenerAlreadyFinalizedException::forListener($this);
        }

        $this->isFinalized = true;
    }

    public function isFinalized(): bool
    {
        return $this->finalized;
    }

    /**
     * @throws LogicException
     */
    protected function ensureFinalized(): void
    {
        if (!$this->finalized) {
            throw new LogicException('Locator is not initialized. Run discovery first.');
        }
    }
}
