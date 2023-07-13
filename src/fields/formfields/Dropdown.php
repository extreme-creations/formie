<?php
namespace verbb\formie\fields\formfields;

use verbb\formie\base\FormFieldInterface;
use verbb\formie\helpers\SchemaHelper;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\ArrayHelper;

class Dropdown extends BaseOptionsField implements FormFieldInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Dropdown');
    }

    /**
     * @inheritDoc
     */
    public static function getSvgIconPath(): string
    {
        return 'formie/_formfields/dropdown/icon.svg';
    }


    // Properties
    // =========================================================================

    public $multiple;
    public $multi = false;
    public $optgroups = true;


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init()
    {
        // Mirror to native `multi` attribute
        $this->setMultiple($this->multiple);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function getFieldDefaults(): array
    {
        return [
            'options' => [
                [
                    'label' => Craft::t('formie', 'Select an option'),
                    'value' => '',
                    'isOptgroup' => false,
                    'isDefault' => true,
                ],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getFieldOptions()
    {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/dropdown/input', [
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'options' => $this->translatedOptions(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getPreviewInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/dropdown/preview', [
            'field' => $this,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getSavedSettings(): array
    {
        $settings = parent::getSavedSettings();

        foreach ($settings['options'] as &$option) {
            if (isset($option['optgroup']) && $option['optgroup']) {
                $option['isOptgroup'] = true;
                $option['label'] = ArrayHelper::remove($option, 'optgroup');
            } else {
                $option['isOptgroup'] = false;
            }
        }

        return $settings;
    }

    /**
     * @inheritDoc
     */
    public function getIsSelect(): bool
    {
        return !$this->getMultiple();
    }

    /**
     * Sets the multi property.
     *
     * @param $value
     */
    public function setMultiple($value)
    {
        $this->multi = $value;
    }

    /**
     * Returns the multi property.
     *
     * @return bool
     */
    public function getMultiple()
    {
        return $this->multi;
    }

    /**
     * @inheritdoc
     */
    protected function optionsSettingLabel(): string
    {
        return Craft::t('app', 'Dropdown Options');
    }

    /**
     * @inheritDoc
     */
    public function defineGeneralSchema(): array
    {
        return [
            SchemaHelper::labelField(),
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Allow Multiple'),
                'help' => Craft::t('formie', 'Whether this field should allow multiple options to be selected.'),
                'name' => 'multiple',
            ]),
            SchemaHelper::tableField([
                'label' => Craft::t('formie', 'Options'),
                'help' => Craft::t('formie', 'Define the available options for users to select from.'),
                'name' => 'options',
                'allowMultipleDefault' => 'settings.multiple',
                'enableBulkOptions' => true,
                'predefinedOptions' => $this->getPredefinedOptions(),
                'newRowDefaults' => [
                    'label' => '',
                    'value' => '',
                    'isOptgroup' => false,
                    'isDefault' => false,
                ],
                'columns' => [
                    [
                        'type' => 'optgroup',
                        'label' => Craft::t('formie', 'Optgroup?'),
                        'class' => 'thin checkbox-cell',
                    ],
                    [
                        'type' => 'label',
                        'label' => Craft::t('formie', 'Option Label'),
                        'class' => 'singleline-cell textual',
                    ],
                    [
                        'type' => 'value',
                        'label' => Craft::t('formie', 'Value'),
                        'class' => 'code singleline-cell textual',
                    ],
                    [
                        'type' => 'default',
                        'label' => Craft::t('formie', 'Default?'),
                        'class' => 'thin checkbox-cell',
                    ],
                ],
            ]),
        ];
    }

    /**
     * @inheritDoc
     */
    public function defineSettingsSchema(): array
    {
        return [
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Required Field'),
                'help' => Craft::t('formie', 'Whether this field should be required when filling out the form.'),
                'name' => 'required',
            ]),
            SchemaHelper::toggleContainer('settings.required', [
                SchemaHelper::textField([
                    'label' => Craft::t('formie', 'Error Message'),
                    'help' => Craft::t('formie', 'When validating the form, show this message if an error occurs. Leave empty to retain the default message.'),
                    'name' => 'errorMessage',
                ]),
            ]),
            SchemaHelper::prePopulate(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function defineAppearanceSchema(): array
    {
        return [
            SchemaHelper::visibility(),
            SchemaHelper::labelPosition($this),
            SchemaHelper::instructions(),
            SchemaHelper::instructionsPosition($this),
        ];
    }

    /**
     * @inheritDoc
     */
    public function defineAdvancedSchema(): array
    {
        return [
            SchemaHelper::handleField(),
            SchemaHelper::cssClasses(),
            SchemaHelper::containerAttributesField(),
            SchemaHelper::inputAttributesField(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function defineConditionsSchema(): array
    {
        return [
            SchemaHelper::enableConditionsField(),
            SchemaHelper::conditionsField(),
        ];
    }
}
