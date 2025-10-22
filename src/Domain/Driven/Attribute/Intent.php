<?php

declare(strict_types=1);

namespace App\Domain\Driven\Attribute;

namespace App\Domain\Driven\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Intent
{
    public function __construct(
        public string $name,
        public array $aliases = [],
        public ?string $description = null
    ) {
    }
}
