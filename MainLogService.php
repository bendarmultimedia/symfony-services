<?php

namespace App\Service\Log;

use App\Entity\Log;
use App\Entity\User;
use App\Repository\LogRepository;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Exception;
use Monolog\Logger;
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

class MainLogService
{
    private $em;
    private $logRepository;
    private string $url;
    public const LOG_TYPE = [
        'SYSTEM',
        'ADDED',
        'MODIFIED',
        'REMOVED',
        'NOTIFIED',
        'CONFIRMED',
        'REVOKE',
        'ERROR',
        'OTHER'
    ];

    public const ENTITIES_FOR_CUSTOM_UPDATE_LOG = [
        'Absence'
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        LogRepository $logRepository,
        LoggerInterface $loggerInterface,
        RequestStack $requestStack
    ) {
        $this->em = $entityManager;
        $this->logRepository = $logRepository;
        $this->loggerInterface = $loggerInterface;
        $this->user = $security->getUser();
        $this->itemsWithCustomEditLogs = [];

        $this->url = (isset($requestStack) && $requestStack->getMasterRequest())
            ? $requestStack->getMasterRequest()->getUri() : '';
    }

    public function itemToArray($item)
    {
        if ($this->getItemClassName($item) == 'AbsenceUserSettings') {
            // dump($this->serializeItem($item));
        }

        return json_decode(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, 3));
    }

    public function serializeItem($item, ?array $groups = ['groups' => ['normal']]): string
    {
        //todo: infinity loop anyway
        $encoder = new JsonEncoder();
        $defaultContext = [
            ['ignored_attributes' => ['user', 'importedFiles']],
            AbstractObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return $object->getId();
            },
        ];
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
        $serializer = new Serializer([$normalizer], [$encoder]);
        $result = $serializer->normalize($item, null, [AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]);
        return $serializer->serialize($result, 'json');
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

    public function findAll()
    {
        return $this->logRepository->findAll();
    }

    public function find(int $id)
    {
        return $this->logRepository->find($id);
    }

    public function findBy(array $findParams, array $resultOrder = null)
    {
        return $this->logRepository->findBy($findParams, $resultOrder);
    }

    public function findOneBy(array $findParams)
    {
        return $this->logRepository->findOneBy($findParams);
    }

    public function findEntityLogs(string $entityName, int $entityId, $resultOrder = ['id' => 'DESC'])
    {
        $findParams = [
            'entityName' => $entityName,
            'entityId' => $entityId,
        ];

        return $this->logRepository->findBy($findParams, $resultOrder);
    }

    public function saveItemLog(
        $item,
        string $logType,
        ?bool $flush = false,
        ?string $message = '',
        ?array $extra = [],
        ?int $level = null
    ) {
        $level = ((int) $level > 0) ? $level : Logger::INFO;

        if (is_object($item) && $this->isEntity($item)) {
            if (!in_array($logType, $this::LOG_TYPE)) {
                throw new Exception('Invalid $logType. Use types defined in MainLogService::LOG_TYPE.', 500);
            }
            $log = $this->setLog(
                $message,
                $logType,
                $this->itemToArray($item),
                $level,
                $extra,
                $item->getId(),
                $this->getItemClassName($item)
            );

            // $uow = $this->em->getUnitOfWork();
            // $metaData = $this->em->getClassMetadata('App\\Entity\\Log');

            // $uow->persist($log);
            // $uow->computeChangeSet($metaData, $log);
            $this->em->persist($log);
            if ($flush) {
                $this->em->flush();
                return $log->getId();
            }
        }
    }

    public function saveLog(Log $log, $flush = false)
    {
        $this->em->persist($log);

        if ($flush) {
            $this->em->flush();
            return $log->getId();
        }
    }

    public function setLog(
        string $message,
        string $logType,
        $context = [],
        ?int $level = null,
        ?array $extra = [],
        ?int $entityId = null,
        ?string $entityClassName = null
    ) {
        $level = ((int) $level > 0) ? $level : Logger::INFO;

        $log = new Log();
        $log->setMessage($message);
        $log->setLogType($logType);
        $log->setContext($context);
        $log->setLevel($level);
        $log->setLevelName(Logger::getLevelName($level));
        $log->setExtra($extra);
        $log->setEntityId($entityId);
        $log->setEntityName($entityClassName);
        $log->setUrl($this->url);

        if ($this->user && $this->user instanceof User) {
            $log->setUser($this->user);
        }
        return $log;
    }

    public function saveAddItemLog(
        $item,
        ?array $extra = [],
        ?bool $flush = false
    ) {
        $this->saveItemLog(
            $item,
            "ADDED",
            $flush,
            'New ' . $this->getItemClassName($item) . ' added',
            $extra
        );
    }

    public function saveRemoveItemLog(
        $item,
        ?array $extra = [],
        ?bool $flush = false
    ) {
        $this->saveItemLog(
            $item,
            "REMOVED",
            $flush,
            $this->getItemClassName($item) . ' was removed',
            $extra
        );
    }

    public function saveUpdateItemLog(
        $item,
        ?array $extra = [],
        ?bool $flush = false
    ) {
        $this->saveItemLog(
            $item,
            "MODIFIED",
            $flush,
            $this->getItemClassName($item) . ' was edited',
            $extra
        );
    }

    public function saveNotifiedLog(
        string $to,
        string $subject,
        string $content,
        ?array $extra = []
    ) {
        $message = "Sent email to " . $to;
        $log = $this->setLog(
            $message,
            'NOTIFIED',
            [
                'to' => $to,
                'subject' => $subject,
                'content' => $content ,
            ],
            Logger::INFO,
            $extra
        );

        $this->saveLog($log, true);
    }

    public function getItemClassName($item): ?string
    {
        return (is_object($item)) ? (new ReflectionClass($item))->getShortName() : null;
    }
}
