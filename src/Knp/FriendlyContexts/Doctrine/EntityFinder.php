<?php

namespace Knp\FriendlyContexts\Doctrine;

use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\ORM\EntityManager;
use Symfony\Component\PropertyAccess\PropertyAccess;

class EntityFinder
{
    /**
     * @var EntityResolver
     */
    private $entityResolver;

    /**
     * @param EntityResolver $entityResolver
     */
    public function __construct(EntityResolver $entityResolver)
    {
        $this->entityResolver = $entityResolver;
    }

    /**
     * @param EntityManager $entityManager
     * @param $nbr
     * @param $entityName
     * @param array $rows
     *
     * @throws \Exception
     */
    public function assertFindNumbersEntityEqualsToTable(EntityManager $entityManager, $nbr, $entityName, array $rows)
    {
        $repository = $entityManager->getRepository($entityName);

        $headers = array_shift($rows);

        for ($i = 0; $i < $nbr; ++$i) {
            $row = $rows[$i % count($rows)];
            $values = array_combine($headers, $row);

            $criterias = $this->getSearchCriterias($entityManager, $repository->getClassName(), $values);
            $object = $repository->findOneBy($criterias);

            if (is_null($object)) {
                throw new \Exception(sprintf('There is no object for the following criteria: %s', json_encode($criterias)));
            }

            $this->assertObjectsEqualsForComplexFields($entityManager, $object, $values);

            $entityManager->refresh($object);
        }
    }

    /**
     * @param EntityManager $entityManager
     * @param string        $entityClassName
     * @param array         $values
     *
     * @return array
     */
    public function getSearchCriterias($entityManager, $entityClassName, array $values)
    {
        $criterias = [];
        foreach ($values as $property => $value) {
            $metadata = $this->entityResolver->getMetadataFromProperty($entityManager, $entityClassName, $property);

            if (!in_array($metadata['type'], [DBALType::JSON_ARRAY])) {
                $criterias[$property] = $this->clean($value);
            }

            if (in_array($metadata['type'], [DBALType::DATETIME, DBALType::DATE, DBALType::DATETIMETZ])) {
                $criterias[$property] = empty($criterias[$property]) ? null : new \DateTime($criterias[$property]);
            }
        }

        return $criterias;
    }

    /**
     * @param EntityManager $entityManager
     * @param object        $entity
     * @param array         $values
     *
     * @return array
     */
    public function assertObjectsEqualsForComplexFields($entityManager, $entity, array $values)
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($values as $property => $value) {
            $metadata = $this->entityResolver->getMetadataFromProperty($entityManager, $entity, $property);

            if (in_array($metadata['type'], [DBALType::JSON_ARRAY])) {
                \PHPUnit_Framework_Assert::assertEquals(json_decode($value, true), $propertyAccessor->getValue($entity, $property));
            }
        }
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    protected function clean($value)
    {
        return trim($value) === '' ? null : $value;
    }
}
