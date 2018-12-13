<?php

namespace Deliveryman\Normalizer;


use Deliveryman\Entity\BatchRequest;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Yaml\Yaml;

class BatchRequestNormalizer implements DenormalizerInterface
{
    const CACHE_PREFIX = 'deliveryman.';

    /**
     * @var DenormalizerInterface
     */
    protected $denormalizer;

    /**
     * @var CacheItemPoolInterface|null
     */
    protected $cacheItemPool;

    /**
     * DtoNormalizer constructor.
     * @param DenormalizerInterface $denormalizer
     * @param array $serializerPaths
     * @param null|CacheItemPoolInterface $cacheItemPool
     */
    public function __construct(
        DenormalizerInterface $denormalizer,
        ?CacheItemPoolInterface $cacheItemPool = null
    ) {
        $this->denormalizer = $denormalizer;
        $this->cacheItemPool = $cacheItemPool;
    }
//
//    /**
//     * @param DtoInterface $dto
//     * @return array|null
//     * @throws \Doctrine\ORM\Mapping\MappingException
//     * @throws \Psr\Cache\InvalidArgumentException
//     */
//    protected function getDtoConfigByObject(DtoInterface $dto): ?array
//    {
//        $state = $this->em->getUnitOfWork()->getEntityState($dto, self::STATE_UNDEFINED);
//
//        $class = get_class($dto);
//        // neither new nor managed by UnitOfWork
//        if (!in_array($state, [UnitOfWork::STATE_NEW, self::STATE_UNDEFINED])) {
//            $meta = $this->em->getClassMetadata($class);
//            $class = $meta->name; // get real class name (may be proxied by Doctrine)
//        }
//
//        $item = $this->cacheItemPool->getItem($this->getCacheKeyDtoConfig($class));
//        if ($item->isHit()) {
//            return $item->get();
//        }
//
//        if ($state !== self::STATE_UNDEFINED && isset($meta)) { // can guess only for Doctrine entities
//            $fieldRefs = $this->guessFieldRefs($meta);
//        } else {
//            $fieldRefs = [];
//        }
//
//        $config = $this->findDtoConfig($class, $fieldRefs);
//        $this->cacheItemPool->save($item->set($config));
//
//        return $config;
//    }

//    /**
//     * @param $class
//     * @return array|null
//     * @throws \Doctrine\ORM\Mapping\MappingException
//     * @throws \Psr\Cache\InvalidArgumentException
//     */
//    protected function getDtoConfigByName($class): ?array
//    {
//        $item = $this->cacheItemPool->getItem($this->getCacheKeyDtoConfig($class));
//        if ($item->isHit()) {
//            return $item->get();
//        }
//
//        $meta = $this->em->getClassMetadata($class);
//        $fieldRefs = $this->guessFieldRefs($meta);
//
//        $config = $this->findDtoConfig($class, $fieldRefs);
//        if ($config !== null) {
//            $this->cacheItemPool->save($item->set($config));
//        }
//
//        return $config;
//    }

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return object|BatchRequest
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
//        var_dump($data);
//        die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");
//        die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");
//        $dtoConfig = $this->getDtoConfigByName($class);
//        $dtoEntity = $this->getSetMethodNormalizer->denormalize($data, $class, $format, $context);
        $entity = $this->denormalizer->denormalize($data, $class, $format, $context);

        var_dump($entity);
        die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");
//
//        // TODO: cache Yaml files to system
//        if (!empty($dtoConfig[self::CFG_REFERENCES])) {
//            $this->updateEntityReferences($data, $class, $context, $dtoConfig, $dtoEntity);
//        }

        return $entity;
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param null $format
     * @return bool
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $data && $type === BatchRequest::class;
    }


    /**
     * @param $class
     * @return string
     */
    protected function  getCacheKeyDtoConfig($class): string
    {
        // Key cannot contain backslashes according to PSR-6
        return self::CACHE_PREFIX . strtr($class, '\\', '_');
    }
}