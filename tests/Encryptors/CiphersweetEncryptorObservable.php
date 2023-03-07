<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Encryptors;

use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\CiphersweetEncryptor;

class CiphersweetEncryptorObservable extends CiphersweetEncryptor
{
    public array $callsCount = [
        'encrypt' => 0,
        'decrypt' => 0,
    ];

    protected function doEncrypt(string $entityClassName, string $fieldName, string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array
    {
        $this->callsCount['encrypt']++;
        return parent::doEncrypt($entityClassName, $fieldName, $string, $index, $filterBits, $fastIndexing);
    }

    protected function doDecrypt(string $entityClassName, string $fieldName, string $string): string
    {
        $this->callsCount['decrypt']++;
        return parent::doDecrypt($entityClassName, $fieldName, $string);
    }
}
