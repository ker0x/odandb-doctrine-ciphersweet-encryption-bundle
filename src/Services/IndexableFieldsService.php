<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Odandb\DoctrineCiphersweetEncryptionBundle\Attribute\IndexableField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\MissingPropertyFromReflectionException;
use Doctrine\ORM\EntityManagerInterface;

class IndexableFieldsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IndexesGenerator $indexesGenerator
    ){
    }

    public function getChunksForMultiThread(string $className, int $chuncksLength): array
    {
        $repo = $this->em->getRepository($className);
        $result = $repo->createQueryBuilder('c')
            ->select('c.id')
            ->getQuery()
            ->getArrayResult();

        return array_chunk(array_column($result, 'id'), $chuncksLength);
    }

    public function buildContext(string $className, ?array $fieldNames): array
    {
        $contexts = [];

        $classMetadata = $this->em->getClassMetadata($className);

        if ($fieldNames === [] || $fieldNames === null) {
            $fieldNames = array_map(
                static function (\ReflectionProperty $refProperty): string {
                    return $refProperty->getName();
                },
                $classMetadata->getReflectionProperties()
            );
        }

        foreach ($fieldNames as $fieldName) {
            $refProperty = $classMetadata->getReflectionProperty($fieldName);

            if ($refProperty === null) {
                throw new MissingPropertyFromReflectionException(sprintf("No refProperty found for fieldname %s", $fieldName));
            }

            $indexableAttributeConfig = $refProperty->getAttributes(IndexableField::class)[0] ?? null;

            if ($indexableAttributeConfig instanceof IndexableField) {
                $contexts [] = ['refProperty' => $refProperty, 'indexableConfig' => $indexableAttributeConfig];
            }
        }

        return $contexts;
    }

    public function purgeFiltersForContextAndIds(array $fieldsContexts, ?array $ids): void
    {
        /**
         * @var \ReflectionProperty $refProperty
         * @var IndexableField $indexableAttributeConfig
         */
        foreach ($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAttributeConfig]) {
            $qb = $this->em->createQueryBuilder()
                ->delete()
                ->from($indexableAttributeConfig->indexesEntityClass, 'f');
            $qb->where('f.fieldname=:fieldname')
                ->setParameter('fieldname', $refProperty->getName());

            if ($ids !== null && $ids !== []) {
                $qb->andWhere('f.targetEntity IN (:ids)')
                    ->setParameter('ids', $ids);
            }

            $qb->getQuery()->execute();
        }
    }

    /**
     * @throws \ReflectionException
     * @throws \Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException
     */
    public function handleFilterableFieldsForChunck(string $className, ?array $ids, array $fieldsContexts, bool $needsToComputeChangeset = false): void
    {
        $criteria = $ids !== null && $ids !== [] ? ['id' => $ids] : [];
        $chunck = $this->em->getRepository($className)->findBy($criteria);
        foreach ($chunck as $entity) {
            $this->handleIndexableFieldsForEntity($entity, $fieldsContexts, $needsToComputeChangeset);
            $this->em->flush();
        }
    }

    /**
     * Permet de générer les valeurs indexables pour une entité et un contexte donné.
     *
     * @throws \Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException
     */
    public function generateIndexableValuesForEntity(object $entity, array $fieldsContexts): array
    {
        $searchIndexes = [];

        foreach ($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAttributeConfig]) {
            $value = $refProperty->getValue($entity);
            if ($value === null || $value === '') {
                continue;
            }

            $cleanValue = $value;
            $valueCleanerMethod = $indexableAttributeConfig->valuePreprocessMethod ?? null;
            if ($valueCleanerMethod !== null && (method_exists($entity, $valueCleanerMethod) || method_exists($entity::class, $valueCleanerMethod))) {
                $cleanValue = $entity->$valueCleanerMethod($value);
            }

            // On appelle le service de génération des index de filtre qui va créer la collection de pattern possibles
            // en fonction de la ou des méthodes renseignées en annotation
            // Puis récupérer chaque "blind_index" associé à enregistrer en base
            $indexesMethods = $indexableAttributeConfig->indexesGenerationMethods ?? [];

            $indexesToEncrypt = $this->indexesGenerator->generateAndEncryptFilters($cleanValue, $indexesMethods);
            $indexesToEncrypt [] = $value;
            $indexesToEncrypt = array_unique($indexesToEncrypt);

            $searchIndexes[$refProperty->getName()] = $indexesToEncrypt;
        }

        return $searchIndexes;
    }

    /**
     * @param array['refProperty' => \ReflectionProperty, 'indexableConfig' => IndexableField] $fieldsContexts
     *
     * @throws \Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException
     * @throws \ReflectionException
     */
    public function handleIndexableFieldsForEntity(object $entity, array $fieldsContexts, bool $needsToComputeChangeset = false): void
    {
        $searchIndexes = $this->generateIndexableValuesForEntity($entity, $fieldsContexts);

        /**
         * @var \ReflectionProperty $refProperty
         * @var IndexableField $indexableAttributeConfig
         */
        foreach ($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAttributeConfig]) {
            if (!isset($searchIndexes[$refProperty->getName()])) {
                continue;
            }

            $indexesToEncrypt = $searchIndexes[$refProperty->getName()];

            $indexes = $this->indexesGenerator->generateBlindIndexesFromPossibleValues(get_class($entity), $refProperty->getName(), $indexesToEncrypt, $indexableAttributeConfig->fastIndexing ?? EncryptorInterface::DEFAULT_FAST_INDEXING);

            // On crée les instances d'objet filtre et on les associe à l'entité parente
            $indexEntities = [];
            $indexEntityClass = $indexableAttributeConfig->indexesEntityClass;

            $refClass = new \ReflectionClass($indexEntityClass);
            $classMetadata = $this->em->getClassMetadata($refClass->getName());
            foreach ($indexes as $index) {
                $indexEntity = $refClass->newInstance();
                if ($indexEntity instanceof IndexedEntityInterface) {
                    $indexEntity->setIndexBi($index);
                    $indexEntity->setFieldname($refProperty->getName());
                    $indexEntity->setTargetEntity($entity);
                    $indexEntities [] = $indexEntity;

                    $this->em->persist($indexEntity);
                    if ($needsToComputeChangeset) {
                        $this->em->getUnitOfWork()->computeChangeSet($classMetadata, $indexEntity);
                    }
                }
            }
            $setter = 'set' . $refClass->getShortName();
            $entity->$setter($indexEntities);
        }
    }
}
