<?php

namespace PaymentSystem\Cast;

use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\PropertyCaster;
use EventSauce\ObjectHydrator\PropertySerializer;
use LogicException;

class CastSource implements PropertyCaster, PropertySerializer
{
    public function __construct(
        private array $typeToClassMap = []
    )
    {
    }

    /**
     * @param class-string $className
     */
    public function setMapper(string $type, string $className): static
    {
        $this->typeToClassMap[$type] = $className;

        return $this;
    }

    public function cast(mixed $value, ObjectMapper $hydrator): mixed
    {
        assert(is_array($value));

        $type = $value['type'] ?? null;

        if ($type === null) {
            throw new \InvalidArgumentException('no type provided');
        }

        $className = $this->typeToClassMap[$type] ?? null;

        if ($className === null) {
            throw new LogicException("Unable to map type '$type' to class.");
        }

        return $hydrator->hydrateObject($className, $value[$type]);
    }

    public function serialize(mixed $value, ObjectMapper $hydrator): mixed
    {
        $className = $value::class;

        if (!is_object($value)) {
            throw new LogicException("Unable to serialize $className");
        }

        $classToType = array_flip($this->typeToClassMap);

        if (!isset($classToType[$className])) {
            throw new \InvalidArgumentException("$className is not available for cast");
        }

        return [
            'type' => $classToType[$className],
            $classToType[$className] => $hydrator->serializeObjectOfType($hydrator, $className),
        ];
    }
}