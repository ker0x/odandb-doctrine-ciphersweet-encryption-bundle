<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors;

use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\EncryptedField;

class CiphersweetEncryptor implements EncryptorInterface
{
    private array $cache;
    private array $biCache;

    public function __construct(private readonly CipherSweet $engine)
    {
        $this->cache = [];
        $this->biCache = [];
    }

    public function prepareForStorage(object $entity, string $fieldName, string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array
    {
        $entityClassName = $entity::class;

        $output = [];
        if (isset($this->cache[$entityClassName][$fieldName][$string])) {
            $output[] = $this->cache[$entityClassName][$fieldName][$string];
            if ($index) {
                $output[] = [$fieldName.'_bi' => $this->getBlindIndex($entityClassName, $fieldName, $string, $filterBits, $fastIndexing)];
            } else {
                $output[] = [];
            }

            return $output;
        }

        return $this->doEncrypt($entityClassName, $fieldName, $string, $index, $filterBits, $fastIndexing);
    }

    protected function doEncrypt(string $entityClassName, string $fieldName, string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array
    {
        $encryptedField =  (new EncryptedField($this->engine, $entityClassName, $fieldName));
        if ($index) {
            $encryptedField->addBlindIndex(
                new BlindIndex($fieldName.'_bi', [], $filterBits, $fastIndexing)
            );
        }

        $result = $encryptedField->prepareForStorage($string);

        $this->cache[$entityClassName][$fieldName][$string] = $result[0];
        $this->cache[$entityClassName][$fieldName][$result[0]] = $string;

        if ($index) {
            $this->biCache[$entityClassName][$fieldName][$string] = $result[1][$fieldName.'_bi'];
        }

        return $result;
    }

    public function decrypt(string $entityClassName, string $fieldName, string $string, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        if (isset($this->cache[$entityClassName][$fieldName][$string])) {
            return $this->cache[$entityClassName][$fieldName][$string];
        }

        return $this->doDecrypt($entityClassName, $fieldName, $string);
    }

    protected function doDecrypt(string $entityClassName, string $fieldName, string $string): string
    {
        $decryptedValue = (new EncryptedField($this->engine, $entityClassName, $fieldName))
            ->decryptValue($string);

        $this->cache[$entityClassName][$fieldName][$string] = $decryptedValue;
        $this->cache[$entityClassName][$fieldName][$decryptedValue] = $string;

        return $decryptedValue;
    }

    public function getBlindIndex($entityName, $fieldName, string $value, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        if (isset($this->biCache[$entityName][$fieldName][$value])) {
            return $this->biCache[$entityName][$fieldName][$value];
        }

        return $this->doGetBlindIndex($entityName, $fieldName, $value, $filterBits, $fastIndexing);
    }

    private function doGetBlindIndex($entityName, $fieldName, string $value, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        $index = (new EncryptedField($this->engine, $entityName, $fieldName))
            ->addBlindIndex(
                new BlindIndex($fieldName.'_bi', [], $filterBits, $fastIndexing)
            )
            ->getBlindIndex($value, $fieldName.'_bi');

        $this->biCache[$entityName][$fieldName][$value] = $index;

        return $index;
    }

    public function getPrefix(): string
    {
        return $this->engine->getBackend()->getPrefix();
    }
}
