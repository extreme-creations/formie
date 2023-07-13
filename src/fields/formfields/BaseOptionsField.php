<?php
namespace verbb\formie\fields\formfields;

use verbb\formie\Formie;
use verbb\formie\base\FormFieldTrait;
use verbb\formie\models\IntegrationField;

use Craft;
use craft\base\ElementInterface;
use craft\fields\BaseOptionsField as CraftBaseOptionsField;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\OptionData;
use craft\fields\data\SingleOptionFieldData;
use craft\helpers\Json;

use yii\db\Schema;

abstract class BaseOptionsField extends CraftBaseOptionsField
{
    // Traits
    // =========================================================================

    use FormFieldTrait {
        getFrontEndInputOptions as traitGetFrontendInputOptions;
        getDefaultValue as traitGetDefaultValue;
        defineValueForIntegration as traitDefineValueForIntegration;
    }


    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $searchable = true;

    /**
     * @var string vertical or horizontal layout
     */
    public $layout;


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        foreach ($this->options as &$option) {
            unset($option['isNew']);
        }
    }

    /**
     * @inheritDoc
     */
    public function getContentColumnType(): string
    {
        if (Formie::$plugin->getSettings()->enableLargeFieldStorage) {
            return Schema::TYPE_TEXT;
        }
        
        return parent::getContentColumnType();
    }

    /**
     * @inheritDoc
     */
    public function getValue(ElementInterface $element)
    {
        $value = $element->getFieldValue($this->handle);

        if ($value instanceof SingleOptionFieldData) {
            return $value->value;
        } else if ($value instanceof MultiOptionsFieldData) {
            $values = [];
            foreach ($value as $selectedValue) {
                /** @var OptionData $selectedValue */
                $values[] = $selectedValue->value;
            }

            return $values;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultValue()
    {
        $value = $this->traitGetDefaultValue() ?? $this->defaultValue;

        // If the default value from the parent field (query params, etc) is empty, use the default values
        // set in the field option settings.
        if ($value === '') {
            $value = [];

            foreach ($this->options() as $option) {
                if (!empty($option['isDefault'])) {
                    $value[] = $option['value'];
                }
            }

            if (!$this->multi) {
                $value = $value[0] ?? '';
            }
        }

        try {
            $options = [];
            $optionValues = [];
            $optionLabels = [];

            foreach ($this->options() as $option) {
                if (!isset($option['optgroup'])) {
                    $options[] = new OptionData($option['label'], $option['value'], false, true);
                    $optionValues[] = (string)$option['value'];
                    $optionLabels[] = (string)$option['label'];
                }
            }

            if ($this->multi) {
                $selectedOptions = [];

                $selectedValues = !is_array($value) ? [$value] : $value;

                foreach ($selectedValues as $selectedValue) {
                    $index = array_search($selectedValue, $optionValues, true);
                    $valid = $index !== false;
                    $label = $valid ? $optionLabels[$index] : null;
                    $selectedOptions[] = new OptionData($label, $selectedValue, true, $valid);
                }

                return new MultiOptionsFieldData($selectedOptions);
            } else {
                $index = array_search($value, $optionValues, true);
                $valid = $index !== false;
                $label = $valid ? $optionLabels[$index] : null;

                return new SingleOptionFieldData($label, $value, true, $valid);
            }
        } catch (\Throwable $e) {
            Formie::error(Craft::t('formie', '{handle}: “{message}” {file}:{line}', [
                'handle' => $this->handle,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
        }

        return $value;
    }

    /**
     * Validates the options.
     */
    public function validateOptions()
    {
        $labels = [];
        $values = [];
        $hasDuplicateLabels = false;
        $hasDuplicateValues = false;
        $optgroup = '__root__';

        foreach ($this->options as &$option) {
            // Ignore optgroups
            if (array_key_exists('optgroup', $option)) {
                $optgroup = $option['optgroup'];
                continue;
            }

            $label = (string)$option['label'];
            $value = (string)$option['value'];

            if (isset($labels[$optgroup][$label])) {
                $option['hasDuplicateLabels'] = true;
                $hasDuplicateLabels = true;
            }

            if (isset($values[$value])) {
                $option['hasDuplicateValues'] = true;
                $hasDuplicateValues = true;
            }
            $labels[$optgroup][$label] = $values[$value] = true;
        }

        if ($hasDuplicateLabels) {
            $this->addError('options', Craft::t('app', 'All option labels must be unique.'));
        }
        if ($hasDuplicateValues) {
            $this->addError('options', Craft::t('app', 'All option values must be unique.'));
        }
    }

    /**
     * @inheritDoc
     */
    public function getSavedSettings(): array
    {
        return $this->getSettings();
    }

    /**
     * @inheritDoc
     */
    public function beforeSave(bool $isNew): bool
    {
        // Fix an error with migrating from Freeform/Sprout where the default value is set.
        // Can eventually remove this!
        $this->defaultValue = null;

        return parent::beforeSave($isNew);
    }


    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function defineValueAsString($value, ElementInterface $element = null)
    {
        if ($value instanceof MultiOptionsFieldData) {
            return implode(', ', array_map(function($item) {
                return $item->value;
            }, (array)$value));
        }

        return $value->value ?? '';
    }

    /**
     * @inheritDoc
     */
    protected function defineValueForIntegration($value, $integrationField, $integration, ElementInterface $element = null, $fieldKey = '')
    {
        // If mapping to an array, extract just the values
        if ($integrationField->getType() === IntegrationField::TYPE_ARRAY) {
            if ($value instanceof MultiOptionsFieldData) {
                return array_map(function($item) {
                    return $item->value;
                }, (array)$value);
            }

            return [$value->value];
        }

        // Fetch the default handling
        return $this->traitDefineValueForIntegration($value, $integrationField, $integration, $element);
    }

    /**
     * @inheritDoc
     */
    protected function defineValueForSummary($value, ElementInterface $element = null)
    {
        if ($value instanceof MultiOptionsFieldData) {
            return implode(', ', array_map(function($item) {
                return $item->label;
            }, (array)$value));
        }

        return $value->label ?? '';
    }

    /**
     * @inheritDoc
     */
    protected function getPredefinedOptions()
    {
        return Formie::$plugin->getPredefinedOptions()->getPredefinedOptions();
    }
}
