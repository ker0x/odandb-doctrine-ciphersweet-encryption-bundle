<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class EncryptedField
{
    public function __construct(
        public string $mappedTypedProperty,
        public int $filterBits = 32,
        public bool $indexable = true,
    ){
    }
}
