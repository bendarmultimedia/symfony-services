<?php

namespace App\Service\Files;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

class UniImportArrayService
{
    protected EntityManagerInterface $entityManager;
    protected SessionInterface $session;
    protected array $mainArray = [];
    protected array $rawArray = [];
    protected array $header = [];
    protected array $itemsLeft = [];
    protected string $entityToImport;
    protected array $objectsToImport = [];
    protected array $invalidItems = [];
    protected array $validItems = [];
    protected int $nextIndex = 0;
    protected int $importedQuantity = 0;
    protected array $relatedEntities;
    protected array $importPairs;
    protected bool $finished = false;
    protected array $setters;
    protected array $settings = [
        'firstRowAsHeader' => true,
        'delimiters'    => ['[[', ']]'],
        'functionDelimiter'    => '|',
        'setHeader' => true,
        'sessionPrefix' => 'UIA_',
        'step'  => 100,
        'entityPath' => "App\\Entity\\"
    ];
    protected array $replacedValues = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        SessionInterface $sessionInterface
    ) {
        $this->entityManager = $entityManager;
        $this->session = $sessionInterface;
    }

    public function setSettings(array $settings, $newArray = true)
    {
        if ($newArray) {
            $this->resetSession();
        }
        foreach ($settings as $key => $value) {
            if (array_key_exists($key, $this->settings)) {
                $this->settings[$key] = $value;
            }
        }
        return $this->settings;
    }

    public function getStateFromSession()
    {
        $this->settings = ($this->session->has($this->settings['sessionPrefix'] . 'settings'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'settings')
            : $this->settings;
        $this->itemsLeft = ($this->session->has($this->settings['sessionPrefix'] . 'itemsLeft'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'itemsLeft')
            : [];
        $this->header = ($this->session->has($this->settings['sessionPrefix'] . 'header'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'header')
            : [];
        $this->mainArray = ($this->session->has($this->settings['sessionPrefix'] . 'mainArray'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'mainArray')
            : [];
        $this->objectsToImport = ($this->session->has($this->settings['sessionPrefix'] . 'objectsToImport'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'objectsToImport')
            : [];
        $this->importPairs = ($this->session->has($this->settings['sessionPrefix'] . 'importPairs'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'importPairs')
            : [];
        $this->nextIndex = ($this->session->has($this->settings['sessionPrefix'] . 'nextIndex'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'nextIndex')
            : 1;
        $this->entityToImport = ($this->session->has($this->settings['sessionPrefix'] . 'entityToImport'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'entityToImport')
            : '';
        $this->relatedEntities = ($this->session->has($this->settings['sessionPrefix'] . 'relatedEntities'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'relatedEntities')
            : [];
        $this->importedQuantity = ($this->session->has($this->settings['sessionPrefix'] . 'importedQuantity'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'importedQuantity')
            : 0;
        $this->invalidItems = ($this->session->has($this->settings['sessionPrefix'] . 'invalidItems'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'invalidItems')
            : [];
        $this->validItems = ($this->session->has($this->settings['sessionPrefix'] . 'validItems'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'validItems')
            : [];
        $this->finished = ($this->session->has($this->settings['sessionPrefix'] . 'finished'))
            ? $this->session->get($this->settings['sessionPrefix'] . 'finished')
            : false;
    }

    public function saveDataInSession()
    {
        $this->session->set($this->settings['sessionPrefix'] . 'itemsLeft', $this->itemsLeft);
        $this->session->set($this->settings['sessionPrefix'] . 'header', $this->header);
        $this->session->set($this->settings['sessionPrefix'] . 'mainArray', $this->mainArray);
        $this->session->set($this->settings['sessionPrefix'] . 'objectsToImport', $this->objectsToImport);
        $this->session->set($this->settings['sessionPrefix'] . 'importPairs', $this->importPairs);
        $this->session->set($this->settings['sessionPrefix'] . 'nextIndex', $this->nextIndex);
        $this->session->set($this->settings['sessionPrefix'] . 'entityToImport', $this->entityToImport);
        $this->session->set($this->settings['sessionPrefix'] . 'relatedEntities', $this->relatedEntities);
        $this->session->set($this->settings['sessionPrefix'] . 'importedQuantity', $this->importedQuantity);
        $this->session->set($this->settings['sessionPrefix'] . 'finished', $this->finished);
    }

    public function resetSession()
    {
        if ($this->session->has($this->settings['sessionPrefix'] . 'settings')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'settings');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'itemsLeft')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'itemsLeft');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'header')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'header');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'mainArray')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'mainArray');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'objectsToImport')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'objectsToImport');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'importPairs')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'importPairs');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'nextIndex')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'nextIndex');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'entityToImport')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'entityToImport');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'relatedEntities')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'relatedEntities');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'importedQuantity')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'importedQuantity');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'relatedEntities')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'relatedEntities');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'importedQuantity')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'importedQuantity');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'validItems')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'validItems');
        }
        if ($this->session->has($this->settings['sessionPrefix'] . 'invalidItems')) {
            $this->session->remove($this->settings['sessionPrefix'] . 'invalidItems');
        }
    }

    public function checkIportIsRunning()
    {
        return (
            $this->session->has($this->settings['sessionPrefix'] . 'mainArray')
            && $this->session->has($this->settings['sessionPrefix'] . 'finished')
            && $this->session->get($this->settings['sessionPrefix'] . 'finished') == false
        );
    }

    public function saveElementInSession(string $elementName)
    {
        $this->session->set($this->settings['sessionPrefix'] . $elementName, $this->$elementName);
    }

    public function setArray(array $array): ?array
    {
        $this->rawArray = $array;

        $this->mainArray = $this->rawArray;
        if ($this->settings['setHeader']) {
            $this->setHeader();
        }
        if ($this->settings['firstRowAsHeader']) {
            $this->header = $this->rawArray[0];
            $this->reindexArray();
            unset($this->mainArray[0]);
        }
        return $this->mainArray;
    }

    public function setRawArray(array $array): ?array
    {
        $this->rawArray = $array;
        $this->mainArray = $array;

        return $this->mainArray;
    }

    /* [entityName, columnIndex]  */
    public function setRelatedEntities(array $relatedEntitiesNameArray): ?array
    {
        foreach ($relatedEntitiesNameArray as $relatedEntityInfo) {
            $this->relatedEntities[] = $relatedEntityInfo;
        }
        return $this->relatedEntities;
    }

    private function setHeader()
    {
        foreach ($this->rawArray[0] as $key => $value) {
            $this->header[] = $key;
        }
    }

    private function reindexArray()
    {
        $newArray = [];
        foreach ($this->mainArray as $row) {
            $newArrayRow = [];
            foreach ($this->header as $key => $rowName) {
                $newArrayRow[$rowName] = $row[$key];
            }
            $newArray[] = $newArrayRow;
        }
        $this->mainArray = $newArray;
    }

    public function preparePropsToImport(array $propsArray)
    {
        foreach ($propsArray as $propName => $columnIndex) {
            $this->importPairs[] = [
                'setter'    => 'set' . ucfirst($propName),
                'columnIndex'    => $columnIndex
            ];
        }
        return $this;
    }

    public function createNewColumnForModify(string $newColumnIndex, string $operationalString): array
    {
        $start = ($this->settings['firstRowAsHeader']) ? 1 : 0;
        $end = ($this->settings['firstRowAsHeader']) ? count($this->mainArray) + 1 : count($this->mainArray);
        for ($i = $start; $i < $end; $i++) {
            $this->mainArray[$i][$newColumnIndex] = $this->replaceColumnKeyByValue(
                $operationalString,
                $this->mainArray[$i]
            );
        }
        return $this->mainArray;
    }

    public function replaceColumnValue($columnKey, $oldValue, $newValue): array
    {
        $this->replacedValues[$columnKey][] = [$oldValue, $newValue];
        return $this->replacedValues;
    }

    public function updateReplacedValue($oldValue, $columnKey)
    {
        $newValue = $oldValue;
        if (isset($this->replacedValues[$columnKey])) {
            foreach ($this->replacedValues[$columnKey] as $replaceOptions) {
                $newValue = ($oldValue == $replaceOptions[0]) ? $replaceOptions[1] : $oldValue;
            }
        }
        return $newValue;
    }

    /**
     * Preparing another package before send
     *
     * @return array
     */
    public function prepareItemsPackage(): array
    {
        $this->getStateFromSession();

        return $this->prepareObjectsToImport();
    }


    public function importItemsPackage()
    {

        $invalidItems = $this->getInvalidItems();
        $validItems = $this->getValidItems();
        $importedQuantity = $this->getImportedQuantity();

        $nextIndex = $this->getNextIndex();
        $itemsLeft = $this->getItemsLeft();
        $items = $this->getObjectsToImport();
        $step = $this->settings['step'];
        $start = ($nextIndex > array_key_first($itemsLeft)) ? $nextIndex : array_key_first($itemsLeft);
        $stop = (int)($start + $step);

        $finished = false;
        for ($i = $start; $i < $stop; $i++) {
            $nextIndex = $i + 1;
            if (isset($items[$i])) {
                $send = $this->sendItemToDatabase($items[$i]);
                if ($send instanceof Throwable || !$send) {
                    $invalidItems[] = $items[$i];
                } else {
                    $validItems[] = $items[$i];
                    $importedQuantity++;
                };
                unset($itemsLeft[$i]);
            } else {
                $finished = true;
                break;
            }
        }
        $this->setNextIndex($nextIndex);
        $this->setItemsLeft($itemsLeft);
        $this->setImportedQuantity($importedQuantity);
        $this->setValidItems($validItems);
        $this->setInvalidItems($invalidItems);
        $this->setFinished($finished);

        $this->saveElementInSession('nextIndex');
        $this->saveElementInSession('itemsLeft');
        $this->saveElementInSession('invalidItems');
        $this->saveElementInSession('validItems');
        $this->saveElementInSession('importedQuantity');
        $this->saveElementInSession('finished');

        return [
            'nextIndex' => $this->nextIndex,
            'itemsLeft' => $this->itemsLeft,
            'invalidItems' => $this->invalidItems,
            'validItems' => $this->validItems,
            'importedQuantity' => $this->importedQuantity,
            'finished' => $this->finished,
            'sentQuantity'  => count($this->mainArray),
            'readQuantity'  => (count($this->mainArray)) - count($itemsLeft),
            'step'  => $step,
            'start' => $start,
            'stop'  => $stop,
        ];
    }

    public function prepareObjectsToImport(): array
    {
        $dataArray = $this->itemsLeft;
        foreach ($dataArray as $elementKey => $row) {
            $itemToImport = $this->setNewEntityObject();
            foreach ($this->importPairs as $importData) {
                $setterName = $importData['setter'];
                $value = $row[$importData['columnIndex']];
                if (isset($this->replacedValues[$importData['columnIndex']])) {
                    $value = $this->updateReplacedValue($value, $importData['columnIndex']);
                }
                $isRelatedEntity = $this->checkIsRelatedEntity($importData['columnIndex']);
                if ($isRelatedEntity) {
                    if ($value > 0) {
                        $object = $this->entityManager->getRepository($isRelatedEntity)->find($value);
                        $itemToImport->$setterName($object);
                    } else {
                        $itemToImport->$setterName(null);
                    }
                } else {
                    $value = $this->prepareValue($setterName, $value);
                    $itemToImport->$setterName($value);
                }
            }
            $this->objectsToImport[$elementKey] = $itemToImport;
        }
        return $this->objectsToImport;
    }

    private function replaceColumnKeyByValue($stringPattern, $arrayRow)
    {

        foreach ($arrayRow as $columnKey => $columnVal) {
            $stringPattern = $this->checkPattern($stringPattern, $arrayRow);
        }
        return $stringPattern;
    }


    private function checkPattern($fullString, $arrayRow)
    {
        $foundSubstring = [];
        $regex = '/'
            . $this->slashedDelimiter($this->settings['delimiters'][0])
            . '(.*?)'
            . $this->slashedDelimiter($this->settings['delimiters'][1])
            . '/';

        preg_match(
            $regex,
            $fullString,
            $foundSubstring
        );

        if (count($foundSubstring)) {
            $explodedPattern = explode($this->settings['functionDelimiter'], $foundSubstring[1]);
            if (count($explodedPattern) == 2) {
                if (function_exists($explodedPattern[1])) {
                    return str_replace(
                        $foundSubstring[0],
                        $explodedPattern[1]($arrayRow[$explodedPattern[0]]),
                        $fullString
                    );
                } else {
                    return $fullString;
                }
            } else {
                return str_replace($foundSubstring[0], $arrayRow[$foundSubstring[1]], $fullString);
            }
        } else {
            return $fullString;
        }
    }

    private function checkIsRelatedEntity($columnIndex)
    {
        foreach ($this->relatedEntities as $relatedEntityInfo) {
            if ($relatedEntityInfo['columnIndex'] == $columnIndex) {
                return $this->settings['entityPath'] . $relatedEntityInfo['entityName'];
            }
        }
    }

    private function prepareValue($setter, $value)
    {
        $docReader = new AnnotationReader();
        $propName = lcfirst(substr($setter, 3));
        $reflect = new ReflectionClass($this->entityToImport);
        $docInfos = $docReader->getPropertyAnnotations($reflect->getProperty($propName));

        if (isset($docInfos[0]->type) && $docInfos[0]->type == 'datetime') {
            return new \DateTime($value);
        } elseif (isset($docInfos[0]->type) && $docInfos[0]->type == 'integer') {
            return (int) $value;
        } else {
            return $value;
        }
    }

    private function sendItemToDatabase($item = null, $flush = true)
    {
        if ($item) {
            try {
                $this->entityManager->persist($item);
                if ($flush) {
                    $this->entityManager->flush();
                    return $item->getId();
                }
            } catch (\Throwable $th) {
                return $th;
            }
        } else {
            return new Exception('Error - błąd podczas dodawania do bazy danych', 500);
        }
    }

    private function slashedDelimiter($delimiter)
    {
        return preg_replace('/([^A-Za-z0-9\s])/', '\\\\$1', $delimiter);
    }

    public function setItemsLeft(array $itemsLeft)
    {
        $this->itemsLeft = $itemsLeft;
    }

    public function getItemsLeft(): array
    {
        return $this->itemsLeft;
    }

    public function setInvalidItems(array $invalidItems)
    {
        $this->invalidItems = $invalidItems;
    }

    public function getInvalidItems(): array
    {
        return $this->invalidItems;
    }

    public function setValidItems(array $validItems)
    {
        $this->validItems = $validItems;
    }

    public function getValidItems(): array
    {
        return $this->validItems;
    }

    public function setImportedQuantity(int $importedQuantity)
    {
        $this->importedQuantity = $importedQuantity;
    }

    public function getImportedQuantity(): int
    {
        return $this->importedQuantity;
    }

    public function setObjectsToImport(array $objectsToImport)
    {
        $this->objectsToImport = $objectsToImport;
    }

    public function getObjectsToImport(): array
    {
        return $this->objectsToImport;
    }

    private function setNewEntityObject()
    {
        $obj = new $this->entityToImport();
        return $obj;
    }

    public function getArray(): array
    {
        return $this->mainArray;
    }

    public function getNextIndex(): int
    {
        return $this->nextIndex;
    }

    public function setNextIndex(int $nextIndex)
    {
        $this->nextIndex = $nextIndex;
    }

    public function getFinished(): bool
    {
        return $this->finished;
    }

    public function setFinished($finished)
    {
        $this->finished = $finished;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getEntityToImport(): string
    {
        return $this->entityToImport;
    }

    public function setEntityToImport($entityToImport)
    {
        $this->entityToImport = $entityToImport;

        return $this;
    }
}
