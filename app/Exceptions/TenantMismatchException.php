<?php

namespace App\Exceptions;

use RuntimeException;

class TenantMismatchException extends RuntimeException
{
    public static function for(string $model, ?int $rowInstitute, int $current): self
    {
        return new self("Refusing to save {$model} of institute {$rowInstitute} while acting for institute {$current}.");
    }
}
