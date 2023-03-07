<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

class PropertyHydratorService
{
    public function __construct(
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        private ?PropertyAccessorInterface $propertyAccessor = null
    ) {
        $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
    }

    public function getMappedFieldValueAsString(object $entity, ?string $propertyName, mixed $value): string
    {
        if ($propertyName !== null) {
            $value = $this->propertyAccessor->getValue($entity, $propertyName);
        }

        return (string) $value;
    }

    public function setValueToMappedField(object $entity, string $value, ?string $propertyName): void
    {
        if ($propertyName !== null) {
            $propertyInfoType = $this->propertyInfoExtractor->getTypes($entity::class, $propertyName)[0];
            $targetType = $propertyInfoType->getBuiltinType();

            if ($targetType !== Type::BUILTIN_TYPE_STRING) {
                settype($value, $targetType);
            }

            $this->propertyAccessor->setValue($entity, $propertyName, $value);
        }
    }
}
