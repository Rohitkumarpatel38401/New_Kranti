<?php

namespace App\Support\Tenancy;

/**
 * The institute whose data the current request or job acts for.
 * Set only by the institute and student panels; empty for admin, website, and console.
 */
class CurrentInstitute
{
    private ?int $id = null;

    public function set(?int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function has(): bool
    {
        return $this->id !== null;
    }

    public function forget(): void
    {
        $this->id = null;
    }

    public function run(int $id, callable $callback): mixed
    {
        $previous = $this->id;
        $this->id = $id;

        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
