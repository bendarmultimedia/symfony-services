<?php

namespace App\EventListener;

use App\Repository\LogRepository;
use App\Service\Log\MainLogService;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class LogEventListener
{
    private $em;
    private MainLogService $logService;
    private array $itemsWithCustomEditLogs;

    public const ENTITY_LOG_SERVICE_NAMESPACE = "App\\Service\\Log\\";

    public const ENTITIES_FOR_CUSTOM_UPDATE_LOG = [
        'EntityNameWithCustomUpdateLog'
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        LogRepository $logRepository,
        LoggerInterface $loggerInterface,
        RequestStack $requestStack,
        MainLogService $logService
    ) {
        $this->em = $entityManager;
        $this->logRepository = $logRepository;
        $this->loggerInterface = $loggerInterface;
        $this->user = $security->getUser();
        $this->itemsWithCustomEditLogs = [];
        $this->logService = $logService;
    }

    private function setSerializer()
    {
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
        return $this->serializer;
    }

    public function serializeItem($item, ?array $groups = ['groups' => ['normal']]): string
    {
        $encoder = new JsonEncoder();
        $defaultContext = [
            AbstractObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return $object->getId();
            },
        ];
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
        $serializer = new Serializer([$normalizer], [$encoder]);
        $result = $serializer->normalize($item, null, [AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]);
        return $serializer->serialize($result, 'json');
    }

    public function entityToArray($item)
    {
        return json_decode(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    /**
     * @param string|object $class
     *
     * @return bool
     */
    public function isEntity($class): bool
    {
        if (is_object($class)) {
            $class = ($class instanceof Proxy)
                ? get_parent_class($class)
                : get_class($class);
        }

        return !$this->em->getMetadataFactory()->isTransient($class);
    }

    public function getItemClassName($item): ?string
    {
        return (is_object($item)) ? (new ReflectionClass($item))->getShortName() : null;
    }

    public function checkEntityHasCustomUpdateLog($eventArgs)
    {
        return (in_array($this->getItemClassName($eventArgs->getEntity()), $this::ENTITIES_FOR_CUSTOM_UPDATE_LOG));
    }

    public function checkItemHasCustomUpdateLog($item)
    {
        return (
            isset($this->itemsWithCustomEditLogs[$item->getId()])
            && $this->itemsWithCustomEditLogs[$item->getId()]['item'] == $item
        );
    }

    public function processEvntArgs($eventArgs)
    {
        $this->em  = $eventArgs->getEntityManager();
        $uow = $this->em->getUnitOfWork();
        return $uow;
    }

    public function saveItemCustomUpdateLog($itemInfo, $flush = false)
    {
        $serviceName = $this::ENTITY_LOG_SERVICE_NAMESPACE . $this->getItemClassName($itemInfo['item']) . "LogService";
        $entityLogService = new $serviceName($this->logService);
        $entityLogService->saveItemCustomCustomUpdateLog($itemInfo['item'], $itemInfo['eventArgs'], $flush);
        unset($entityLogService);
    }

    /**
     * Gets all the entities to flush
     *
     * @param Event\PreUpdateEventArgs $eventArgs Event args
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs)
    {
        // $uow = $this->processEvntArgs($eventArgs);
        if ($this->checkEntityHasCustomUpdateLog($eventArgs)) {
            $this->itemsWithCustomEditLogs[$eventArgs->getEntity()->getId()] =
                [
                    'item' => $eventArgs->getEntity(),
                    'eventArgs' => $eventArgs
                ];
        }
    }

    /**
     * Gets all the entities to flush
     *
     * @param Event\OnFlushEventArgs $eventArgs Event args
     */
    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        $uow = $this->processEvntArgs($eventArgs);
        $this->entityInsertBuffer = $uow->getScheduledEntityInsertions();
        $this->entityUpdateBuffer = $uow->getScheduledEntityUpdates();
        $this->entityRemoveBuffer = $uow->getScheduledEntityDeletions();
    }

    /**
     * Gets all the entities to flush
     *
     * @param Event\OnFlushEventArgs $eventArgs Event args
     */
    public function postFlush(PostFlushEventArgs $eventArgs)
    {
        // Insertions
        $inserts = 0;
        foreach ($this->entityInsertBuffer as $item) {
            if ($this->getItemClassName($item) !== 'Log') {
                $inserts++;
                $flush = ($inserts == count($this->entityInsertBuffer));
                $this->logService->saveAddItemLog(
                    $item,
                    $extra = [],
                    $flush
                );
            }
        }

        // Updates
        $updates = 0;
        foreach ($this->entityUpdateBuffer as $item) {
            if ($this->getItemClassName($item) !== 'Log') {
                $updates++;
                $flush = ($updates == count($this->entityUpdateBuffer));
                if (!$this->checkItemHasCustomUpdateLog($item)) {
                    $this->logService->saveUpdateItemLog(
                        $item,
                        $extra = [],
                        $flush
                    );
                } else {
                    $this->saveItemCustomUpdateLog($this->itemsWithCustomEditLogs[$item->getId()], $flush);
                }
            }
        }

        // Deletions
        $deletions = 0;
        foreach ($this->entityRemoveBuffer as $item) {
            if ($this->getItemClassName($item) !== 'Log') {
                $deletions++;
                $flush = ($deletions == count($this->entityRemoveBuffer));
                $this->logService->saveRemoveItemLog(
                    $item,
                    $extra = [],
                    $flush
                );
            }
        }
    }
}
