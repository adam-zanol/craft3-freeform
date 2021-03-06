<?php

namespace Solspace\Freeform\Library\Connections;

use craft\base\Element;
use craft\elements\Entry;
use craft\fields\BaseRelationField;
use craft\models\FieldLayout;
use Solspace\Freeform\Library\Connections\Transformers\AbstractFieldTransformer;
use Solspace\Freeform\Library\Connections\Transformers\TransformerInterface;
use Solspace\Freeform\Library\DataObjects\ConnectionResult;

class Entries extends AbstractConnection
{
    /** @var int */
    protected $section;

    /** @var int */
    protected $entryType;

    /** @var bool */
    protected $disabled = false;

    /**
     * @return array
     */
    protected static function getSuppressableErrorFieldHandles(): array
    {
        return ['slug'];
    }

    /**
     * @inheritDoc
     */
    public function isConnectable(): bool
    {
        return $this->getSection() !== null && $this->getEntryType() !== null;
    }

    /**
     * @return int|null
     */
    public function getSection()
    {
        return $this->castToInt($this->section);
    }

    /**
     * @return int|null
     */
    public function getEntryType()
    {
        return $this->castToInt($this->entryType);
    }

    /**
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->castToBool($this->disabled, false);
    }

    /**
     * @param TransformerInterface[] $transformers
     *
     * @return Element
     */
    protected function buildElement(array $transformers): Element
    {
        $entry = new Entry([
            'sectionId' => $this->getSection(),
            'typeId'    => $this->getEntryType(),
        ]);

        $fieldLayout = $entry->getFieldLayout();
        if (null === $fieldLayout) {
            $fieldLayout = new FieldLayout();
        }

        foreach ($transformers as $transformer) {
            $field = $fieldLayout->getFieldByHandle($transformer->getCraftFieldHandle());

            $entry->{$transformer->getCraftFieldHandle()} = $transformer->transformValueFor($field);
        }

        if (!$entry->slug) {
            $entry->slug = $entry->title;
        }

        $currentSiteId = \Craft::$app->sites->currentSite->id;
        $siteIds       = $entry->getSection()->getSiteIds();
        if (in_array($currentSiteId, $siteIds, false)) {
            $siteId = $currentSiteId;
        } else {
            $siteId = reset($siteIds);
        }

        $entry->siteId  = $siteId;
        $entry->enabled = !$this->isDisabled();

        return $entry;
    }

    /**
     * @param Element|Entry $element
     * @param array         $transformers
     */
    protected function beforeValidate(Element $element, array $transformers)
    {
        $type = $element->getType();

        if (!$type->hasTitleField && !$element->title) {
            // If no title is present - generate one to remove errors
            $element->title = sha1(uniqid('', true) . time());
            $element->slug  = $element->title;
        }
    }

    /**
     * @param Element          $element
     * @param ConnectionResult $result
     * @param array            $transformers
     */
    protected function beforeConnect(Element $element, ConnectionResult $result, array $transformers)
    {
    }

    /**
     * @param Element                    $element
     * @param ConnectionResult           $result
     * @param AbstractFieldTransformer[] $keyValuePairs
     */
    protected function afterConnect(Element $element, ConnectionResult $result, array $keyValuePairs)
    {
        $this->applyRelations($element, $keyValuePairs);
    }
}
