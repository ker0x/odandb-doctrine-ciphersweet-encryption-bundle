<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Attribute;

use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class IndexableField
{
    public bool $autoRefresh = true;
    public string $indexesEntityClass;
    public array $indexesGenerationMethods;
    public string $valuePreprocessMethod;
    public bool $fastIndexing = EncryptorInterface::DEFAULT_FAST_INDEXING;
}
