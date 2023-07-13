<?php
namespace verbb\formie\fields\formfields;

use verbb\formie\Formie;
use verbb\formie\base\FormField;
use verbb\formie\base\Integration;
use verbb\formie\base\SubfieldInterface;
use verbb\formie\base\SubfieldTrait;
use verbb\formie\elements\Form;
use verbb\formie\events\ModifyFrontEndSubfieldsEvent;
use verbb\formie\gql\types\generators\FieldAttributeGenerator;
use verbb\formie\gql\types\input\AddressInputType;
use verbb\formie\helpers\SchemaHelper;
use verbb\formie\models\Address as AddressModel;
use verbb\formie\positions\FieldsetStart;

use Craft;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;

use CommerceGuys\Addressing\Country\CountryRepository;

use GraphQL\Type\Definition\Type;

use yii\base\Event;
use yii\db\Schema;

class Address extends FormField implements SubfieldInterface, PreviewableFieldInterface
{
    // Constants
    // =========================================================================

    const EVENT_MODIFY_FRONT_END_SUBFIELDS = 'modifyFrontEndSubfields';


    // Traits
    // =========================================================================

    use SubfieldTrait;


    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Address');
    }

    /**
     * @inheritDoc
     */
    public static function getSvgIconPath(): string
    {
        return 'formie/_formfields/address/icon.svg';
    }

    /**
     * Returns an array of countries.
     *
     * @return array
     */
    public static function getCountryOptions()
    {
        $locale = Craft::$app->getLocale()->getLanguageID();

        $repo = new CountryRepository($locale);

        $countries = [];
        foreach ($repo->getList() as $value => $label) {
            $countries[] = compact('value', 'label');
        }

        return $countries;
    }


    // Properties
    // =========================================================================

    public $autocompleteIntegration;

    public $autocompleteEnabled;
    public $autocompleteCollapsed;
    public $autocompleteLabel;
    public $autocompletePlaceholder;
    public $autocompleteDefaultValue;
    public $autocompletePrePopulate;
    public $autocompleteRequired;
    public $autocompleteErrorMessage;
    public $autocompleteCurrentLocation;

    public $address1Enabled;
    public $address1Collapsed;
    public $address1Label;
    public $address1Placeholder;
    public $address1DefaultValue;
    public $address1PrePopulate;
    public $address1Required;
    public $address1ErrorMessage;
    public $address1Hidden;

    public $address2Enabled;
    public $address2Collapsed;
    public $address2Label;
    public $address2Placeholder;
    public $address2DefaultValue;
    public $address2PrePopulate;
    public $address2Required;
    public $address2ErrorMessage;
    public $address2Hidden;

    public $address3Enabled;
    public $address3Collapsed;
    public $address3Label;
    public $address3Placeholder;
    public $address3DefaultValue;
    public $address3PrePopulate;
    public $address3Required;
    public $address3ErrorMessage;
    public $address3Hidden;

    public $cityEnabled;
    public $cityCollapsed;
    public $cityLabel;
    public $cityPlaceholder;
    public $cityDefaultValue;
    public $cityPrePopulate;
    public $cityRequired;
    public $cityErrorMessage;
    public $cityHidden;

    public $stateEnabled;
    public $stateCollapsed;
    public $stateLabel;
    public $statePlaceholder;
    public $stateDefaultValue;
    public $statePrePopulate;
    public $stateRequired;
    public $stateErrorMessage;
    public $stateHidden;

    public $zipEnabled;
    public $zipCollapsed;
    public $zipLabel;
    public $zipPlaceholder;
    public $zipDefaultValue;
    public $zipPrePopulate;
    public $zipRequired;
    public $zipErrorMessage;
    public $zipHidden;

    public $countryEnabled;
    public $countryCollapsed;
    public $countryLabel;
    public $countryPlaceholder;
    public $countryDefaultValue;
    public $countryPrePopulate;
    public $countryRequired;
    public $countryErrorMessage;
    public $countryHidden;

    // TODO: Remove at next breakpoint. Will blow up CP unless the migration is done first.
    public $enableAutocomplete;


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [
            ['subfieldLabelPosition'],
            'in',
            'range' => Formie::$plugin->getFields()->getLabelPositions(),
            'skipOnEmpty' => true,
        ];

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * @inheritDoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        $value = parent::normalizeValue($value, $element);
        $value = Json::decodeIfJson($value);

        if ($value instanceof AddressModel) {
            return $value;
        }

        return new AddressModel($value);
    }

    /**
     * @inheritDoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        if ($value instanceof AddressModel) {
            $value = Json::encode($value);
        }

        return parent::serializeValue($value, $element);
    }

    /**
     * @inheritDoc
     */
    public function getExtraBaseFieldConfig(): array
    {
        return [
            'countries' => static::getCountryOptions(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getFieldDefaults(): array
    {
        return [
            'autocompleteEnabled' => false,
            'autocompleteCollapsed' => true,
            'autocompleteLabel' => Craft::t('formie', 'Auto-Complete'),
            'autocompleteDefaultValue' => '',
            'autocompletePrePopulate' => '',
            'autocompleteCurrentLocation' => false,

            'address1Enabled' => true,
            'address1Collapsed' => true,
            'address1Label' => Craft::t('formie', 'Address 1'),
            'address1DefaultValue' => '',
            'address1PrePopulate' => '',

            'address2Enabled' => false,
            'address2Collapsed' => true,
            'address2Label' => Craft::t('formie', 'Address 2'),
            'address2DefaultValue' => '',
            'address2PrePopulate' => '',

            'address3Enabled' => false,
            'address3Collapsed' => true,
            'address3Label' => Craft::t('formie', 'Address 3'),
            'address3DefaultValue' => '',
            'address3PrePopulate' => '',

            'cityEnabled' => true,
            'cityCollapsed' => true,
            'cityLabel' => Craft::t('formie', 'City'),
            'cityDefaultValue' => '',
            'cityPrePopulate' => '',

            'stateEnabled' => true,
            'stateCollapsed' => true,
            'stateLabel' => Craft::t('formie', 'State / Province'),
            'stateDefaultValue' => '',
            'statePrePopulate' => '',

            'zipEnabled' => true,
            'zipCollapsed' => true,
            'zipLabel' => Craft::t('formie', 'ZIP / Postal Code'),
            'zipDefaultValue' => '',
            'zipPrePopulate' => '',

            'countryEnabled' => true,
            'countryCollapsed' => true,
            'countryLabel' => Craft::t('formie', 'Country'),
            'countryDefaultValue' => '',
            'countryPrePopulate' => '',

            'instructionsPosition' => FieldsetStart::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        $rules = parent::getElementValidationRules();
        $rules[] = [$this->handle, 'validateRequiredFields', 'skipOnEmpty' => false];

        return $rules;
    }

    /**
     * Validates the required address sub-fields.
     *
     * @param ElementInterface $element
     */
    public function validateRequiredFields(ElementInterface $element)
    {
        $subFields = [
            'address1',
            'address2',
            'address3',
            'city',
            'zip',
            'state',
            'country',
        ];

        if ($this->autocompleteEnabled) {
            $subFields[] = 'autocomplete';
        }

        /* @var AddressModel $value */
        $value = $element->getFieldValue($this->handle);

        foreach ($subFields as $subField) {
            $labelProp = "{$subField}Label";
            $enabledProp = "{$subField}Enabled";
            $requiredProp = "{$subField}Required";
            $fieldValue = $value->$subField ?? '';

            if ($this->$enabledProp && ($this->required || $this->$requiredProp) && StringHelper::isBlank($fieldValue)) {
                $element->addError($this->handle, Craft::t('formie', '"{label}" cannot be blank.', [
                    'label' => $this->$labelProp,
                ]));
            }

            // Validate the postcode separately
            if ($subField === 'zip' && strlen($fieldValue) > 10) {
                $element->addError($this->handle, Craft::t('formie', '"{label}" should contain at most {max, number} {max, plural, one{character} other{characters}}.', [
                    'label' => $this->$labelProp,
                    'max' => 10,
                ]));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getFrontEndSubfields(): array
    {
        $subFields = [];

        $rows = [];

        if ($this->autocompleteEnabled) {
            $rows[] = ['autocomplete' => 'autocomplete'];
        }

        $rows = array_merge($rows, [
            ['address1' => 'address-line1'],
            ['address2' => 'address-line2'],
            ['address3' => 'address-line3'],
            [
                'city' => 'address-level2',
                'zip' => 'postal-code',
            ],
            [
                'state' => 'address-level1',
                'country' => 'country',
            ],
        ]);

        foreach ($rows as $key => $row) {
            foreach ($row as $handle => $autocomplete) {
                $enabledProp = "{$handle}Enabled";

                if ($this->$enabledProp) {
                    $subFields[$key][$handle] = $autocomplete;
                }
            }
        }

        $event = new ModifyFrontEndSubfieldsEvent([
            'field' => $this,
            'rows' => array_filter($subFields),
        ]);

        Event::trigger(static::class, self::EVENT_MODIFY_FRONT_END_SUBFIELDS, $event);

        return $event->rows;
    }

    /**
     * @inheritDoc
     */
    public function getVisibleFrontEndSubfields($row): array
    {
        $subFields = [];

        foreach ($row as $handle => $autocomplete) {
            $hiddenProp = "{$handle}Hidden";

            if (property_exists($this, $hiddenProp) && !$this->$hiddenProp) {
                $subFields[$handle] = $autocomplete;
            }

            // Special-case for autocomplete, can't be hidden
            if ($handle === 'autocomplete') {
                $subFields['autocomplete'] = 'autocomplete';
            }
        }

        return $subFields;
    }

    /**
     * @inheritDoc
     */
    public function getSubfieldOptions(): array
    {
        $fields = [];
        $addressProviderOptions = $this->_getAddressProviderOptions();

        if ($addressProviderOptions) {
            $fields[] = [
                'label' => Craft::t('formie', 'Auto-Complete'),
                'handle' => 'autocomplete',
            ];
        }

        return array_merge($fields, [
            [
                'label' => Craft::t('formie', 'Address 1'),
                'handle' => 'address1',
            ],
            [
                'label' => Craft::t('formie', 'Address 2'),
                'handle' => 'address2',
            ],
            [
                'label' => Craft::t('formie', 'Address 3'),
                'handle' => 'address3',
            ],
            [
                'label' => Craft::t('formie', 'City'),
                'handle' => 'city',
            ],
            [
                'label' => Craft::t('formie', 'State / Province'),
                'handle' => 'state',
            ],
            [
                'label' => Craft::t('formie', 'ZIP / Postal Code'),
                'handle' => 'zip',
            ],
            [
                'label' => Craft::t('formie', 'Country'),
                'handle' => 'country',
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getIsFieldset(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/address/input', [
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'element' => $element,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getPreviewInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/address/preview', [
            'field' => $this,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getAutocompleteHtml($options): string
    {
        $integration = $this->getAddressProviderIntegration();

        if (!$integration) {
            return '';
        }

        return $integration->getFrontEndHtml($this, $options);
    }

    /**
     * @inheritDoc
     */
    public function getFrontEndJsModules()
    {
        $integration = $this->getAddressProviderIntegration();

        if (!$integration) {
            return null;
        }

        return $integration->getFrontEndJsVariables($this);
    }

    /**
     * @inheritDoc
     */
    public function getAddressProviderIntegration()
    {
        if (!$this->autocompleteEnabled || !$this->autocompleteIntegration) {
            return null;
        }

        return Formie::$plugin->getIntegrations()->getIntegrationByHandle($this->autocompleteIntegration) ?? null;
    }

    /**
     * @inheritDoc
     */
    public function supportsCurrentLocation(): bool
    {
        $integration = $this->getAddressProviderIntegration();

        if ($integration && $integration->supportsCurrentLocation()) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function hasCurrentLocation(): bool
    {
        return $this->supportsCurrentLocation() && $this->autocompleteCurrentLocation;
    }

    /**
     * @inheritDoc
     */
    public function getSettingGqlTypes()
    {
        return array_merge(parent::getSettingGqlTypes(), [
            'countryOptions' => [
                'name' => 'countryOptions',
                'type' => Type::listOf(FieldAttributeGenerator::generateType()),
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function defineGeneralSchema(): array
    {
        $addressProviderOptions = $this->_getAddressProviderOptions();

        $fields = [
            SchemaHelper::labelField(),
        ];

        foreach ($this->getSubfieldOptions() as $key => $nestedField) {
            $subfields = [];

            if ($nestedField['handle'] === 'autocomplete' && $addressProviderOptions) {
                $subfields[] = SchemaHelper::selectField([
                    'label' => Craft::t('formie', 'Auto-Complete Integration'),
                    'help' => Craft::t('formie', 'Select which address provider this field should use.'),
                    'name' => 'autocompleteIntegration',
                    'validation' => 'requiredIf:autocompleteEnabled',
                    'required' => true,
                    'options' => array_merge(
                        [['label' => Craft::t('formie', 'Select an option'), 'value' => '']],
                        $addressProviderOptions
                    ),
                ]);
            }

            $subfields[] = SchemaHelper::textField([
                'label' => Craft::t('formie', 'Label'),
                'help' => Craft::t('formie', 'The label that describes this field.'),
                'name' => $nestedField['handle'] . 'Label',
                'validation' => 'requiredIf:' . $nestedField['handle'] . 'Enabled',
                'required' => true,
            ]);

            $subfields[] = SchemaHelper::textField([
                'label' => Craft::t('formie', 'Placeholder'),
                'help' => Craft::t('formie', 'The text that will be shown if the field doesn’t have a value.'),
                'name' => $nestedField['handle'] . 'Placeholder',
            ]);

            if ($nestedField['handle'] === 'country') {
                $subfields[] = SchemaHelper::selectField([
                    'label' => Craft::t('formie', 'Default Value'),
                    'help' => Craft::t('formie', 'Entering a default value will place the value in the field when it loads.'),
                    'name' => $nestedField['handle'] . 'DefaultValue',
                    'options' => array_merge(
                        [['label' => Craft::t('formie', 'Select an option'), 'value' => '']],
                        static::getCountryOptions()
                    ),
                ]);
            } else {
                $subfields[] = SchemaHelper::textField([
                    'label' => Craft::t('formie', 'Default Value'),
                    'help' => Craft::t('formie', 'Entering a default value will place the value in the field when it loads.'),
                    'name' => $nestedField['handle'] . 'DefaultValue',
                ]);
            }

            $toggleBlocks[] = SchemaHelper::toggleBlock([
                'blockLabel' => $nestedField['label'],
                'blockHandle' => $nestedField['handle'],
            ], $subfields);
        }

        $fields[] = SchemaHelper::toggleBlocks([
            'subfields' => $this->getSubfieldOptions(),
        ], $toggleBlocks);

        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function defineSettingsSchema(): array
    {
        $fields = [];

        foreach ($this->getSubfieldOptions() as $key => $nestedField) {
            $subfields = [
                SchemaHelper::lightswitchField([
                    'label' => Craft::t('formie', 'Required Field'),
                    'help' => Craft::t('formie', 'Whether this field should be required when filling out the form.'),
                    'name' => $nestedField['handle'] . 'Required',
                ]),
                SchemaHelper::toggleContainer('settings.' . $nestedField['handle'] . 'Required', [
                    SchemaHelper::textField([
                        'label' => Craft::t('formie', 'Error Message'),
                        'help' => Craft::t('formie', 'When validating the form, show this message if an error occurs. Leave empty to retain the default message.'),
                        'name' => $nestedField['handle'] . 'ErrorMessage',
                    ]),
                ]),
            ];

            if ($nestedField['handle'] !== 'autocomplete') {
                $subfields[] = SchemaHelper::lightswitchField([
                    'label' => Craft::t('formie', 'Hidden Field'),
                    'help' => Craft::t('formie', 'Whether this field should be hidden when filling out the form.'),
                    'name' => $nestedField['handle'] . 'Hidden',
                ]);
            } else {
                $subfields[] = SchemaHelper::toggleContainer('settings.autocompleteIntegration=googlePlaces', [
                    SchemaHelper::lightswitchField([
                        'label' => Craft::t('formie', 'Show Current Location Button'),
                        'help' => Craft::t('formie', 'Whether this field should show a "Use my location" button.'),
                        'name' => $nestedField['handle'] . 'CurrentLocation',
                    ]),
                ]);
            }

            $subfields[] = SchemaHelper::prePopulate([
                'name' => $nestedField['handle'] . 'PrePopulate',
            ]);

            $fields[] = SchemaHelper::toggleBlock([
                'blockLabel' => $nestedField['label'],
                'blockHandle' => $nestedField['handle'],
                'showToggle' => false,
                'showEnabled' => false,
                'showOnlyIfEnabled' => true,
            ], $subfields);
        }

        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function defineAppearanceSchema(): array
    {
        return [
            SchemaHelper::visibility(),
            SchemaHelper::labelPosition($this),
            SchemaHelper::subfieldLabelPosition(),
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
            SchemaHelper::enableContentEncryptionField(),
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

    /**
     * @inheritDoc
     */
    public function getContentGqlMutationArgumentType()
    {
        return AddressInputType::getType($this);
    }


    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function defineValueForExport($value, ElementInterface $element = null)
    {
        $values = [];

        foreach ($this->getSubfieldOptions() as $subField) {
            if ($this->{$subField['handle'] . 'Enabled'}) {
                $values[$this->handle . '_' . $subField['handle']] = $value[$subField['handle']] ?? '';
            }
        }

        return $values;
    }


    // Private Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    private function _getAddressProviderOptions()
    {
        $addressProviderOptions = [];
        $addressProviders = Formie::$plugin->getIntegrations()->getAllIntegrationsForType(Integration::TYPE_ADDRESS_PROVIDER);

        foreach ($addressProviders as $addressProvider) {
            if ($addressProvider->enabled) {
                $addressProviderOptions[] = ['label' => $addressProvider->getName(), 'value' => $addressProvider->getHandle()];
            }
        }

        return $addressProviderOptions;
    }
}
