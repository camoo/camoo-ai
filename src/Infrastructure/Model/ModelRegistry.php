<?php

declare(strict_types=1);

namespace App\Infrastructure\Model;

use App\Domain\Driven\AiModelInterface;
use App\Domain\Driven\Attribute\Intent;
use ReflectionClass;

final class ModelRegistry
{
    /** @var array<string, AiModelInterface> */
    private array $models = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @param iterable<AiModelInterface> $models */
    public function __construct(iterable $models)
    {
        foreach ($models as $model) {
            $ref = new ReflectionClass($model);

            $intentAttr = $ref->getAttributes(Intent::class)[0] ?? null;
            if (!$intentAttr) {
                continue; // skip models without #[Intent]
            }

            /** @var Intent $meta */
            $meta = $intentAttr->newInstance();

            $this->models[$meta->name] = $model;

            foreach ($meta->aliases as $alias) {
                $this->aliases[strtolower($alias)] = $meta->name;
            }
        }
    }

    public function get(string $intent): ?AiModelInterface
    {
        $intent = strtolower($intent);

        if (isset($this->models[$intent])) {
            return $this->models[$intent];
        }

        if (isset($this->aliases[$intent])) {
            return $this->models[$this->aliases[$intent]] ?? null;
        }

        return null;
    }

    public function all(): array
    {
        return $this->models;
    }

    public function describe(): array
    {
        return array_keys($this->models);
    }

    public function getAliases(): array
    {
        $aliases = [];
        foreach ($this->models as $intent => $model) {
            $ref = new ReflectionClass($model);
            $attr = $ref->getAttributes(Intent::class)[0] ?? null;
            if ($attr) {
                /** @var Intent $meta */
                $meta = $attr->newInstance();
                $aliases[$intent] = $meta->aliases;
            }
        }

        return $aliases;
    }
}
