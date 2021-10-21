<?php

namespace App\Service\Log;

use App\Service\Log\MainLogService;

class EntityLogService
{
    public const ENTITY_LOG_SERVICE_NAMESPACE = "App\\Service\\Log\\";

    public function __construct(MainLogService $mainLogService)
    {
        $this->mainLogService = $mainLogService;
        $this->logServiceStack = [];
    }

    public function getLogServiceByEntityName(string $entityName): object
    {
        $serviceName = $this::ENTITY_LOG_SERVICE_NAMESPACE . $entityName . "LogService";
        try {
            if (!isset($this->logServiceStack[$entityName])) {
                $logService = new $serviceName($this->mainLogService);
                $this->logServiceStack[$entityName] = $logService;
            }
            return (is_object($this->logServiceStack[$entityName])) ? $this->logServiceStack[$entityName] : null;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function destroyEntityLogService(string $entityName): void
    {
        if (isset($this->logServiceStack[$entityName]) && is_object($this->logServiceStack[$entityName])) {
            unset($this->logServiceStack[$entityName]);
        }
    }
}
