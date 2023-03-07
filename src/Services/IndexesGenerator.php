<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\IndexesGeneratorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\ValueStartingByGenerator;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\ValueEndingByGenerator;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class IndexesGenerator implements ServiceSubscriberInterface
{
    public function __construct(
        protected ContainerInterface $container,
        protected EncryptorInterface $encryptor,
    ) {
    }

    /**
     * @required
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'ValueStartingByGenerator' => '?'.ValueStartingByGenerator::class,
            'ValueEndingByGenerator' => '?'.ValueEndingByGenerator::class,
        ];
    }

    public function generateAndEncryptFilters(string $value, array $methods): array
    {
        $possibleValuesAr = [$value];

        foreach ($methods as $method) {
            $method .= 'Generator';

            if (!$this->container->has($method)) {
                throw new UndefinedGeneratorException(sprintf("No generator found for method %s", $method));
            }

            $generator = $this->container->get($method);
            if ($generator instanceof IndexesGeneratorInterface === false) {
                throw new \TypeError(sprintf("The generator is not an instance of %s", IndexesGeneratorInterface::class));
            }

            $possibleValues = $generator->generate($value);
            array_push($possibleValuesAr, ...$possibleValues);
        }

        return $possibleValuesAr;
    }

    public function generateBlindIndexesFromPossibleValues(string $entityName, string $fieldName, array $possibleValues, bool $fastIndexing): array
    {
        $possibleValues = array_unique($possibleValues);

        $indexes = [];
        foreach ($possibleValues as $pValue) {
            if ($pValue === '' || $pValue === null) {
                continue;
            }
            $indexes[] = $this->encryptor->getBlindIndex($entityName, $fieldName, $pValue, EncryptorInterface::DEFAULT_FILTER_BITS, $fastIndexing);
        }

        return $indexes;
    }
}
