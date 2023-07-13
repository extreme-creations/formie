<?php
namespace verbb\formie\base;

use verbb\formie\Formie;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\events\ModifyFieldIntegrationValueEvent;
use verbb\formie\fields\formfields\MultiLineText;
use verbb\formie\fields\formfields\SingleLineText;
use verbb\formie\fields\formfields\Table;
use verbb\formie\models\IntegrationField;
use verbb\formie\models\IntegrationFormSettings;

use Craft;
use craft\fields;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use yii\base\Event;
use yii\helpers\Markdown;

use DateTime;
use DateTimeZone;

abstract class Element extends Integration implements IntegrationInterface
{
    // Properties
    // =========================================================================

    public $attributeMapping;
    public $fieldMapping;
    public $updateElement = false;
    public $updateElementMapping;
    public $overwriteValues = false;


    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function typeName(): string
    {
        return Craft::t('formie', 'Elements');
    }

    /**
     * @inheritDoc
     */
    public static function supportsConnection(): bool
    {
        return false;
    }


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        Event::on(self::class, self::EVENT_MODIFY_FIELD_MAPPING_VALUE, function(ModifyFieldIntegrationValueEvent $event) {
            // For rich-text enabled fields, retain the HTML (safely)
            if ($event->field instanceof MultiLineText || $event->field instanceof SingleLineText) {
                $event->value = StringHelper::htmlDecode($event->value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
            }

            // For Date fields as a destination, convert to UTC from system time
            if ($event->integrationField->getType() === IntegrationField::TYPE_DATECLASS) {
                if ($event->value instanceof DateTime) {
                    $timezone = new DateTimeZone(Craft::$app->getTimeZone());

                    $event->value = DateTime::createFromFormat('Y-m-d H:i:s', $event->value->format('Y-m-d H:i:s'), $timezone);
                }
            }

            // Element fields should map 1-for-1
            if ($event->field instanceof fields\BaseRelationField) {
                $event->value = $event->submission->getFieldValue($event->field->handle)->ids();
            }

            // For Table fields with Date/Time destination columns, convert to UTC from system time
            if ($event->field instanceof Table) {
                $timezone = new DateTimeZone(Craft::$app->getTimeZone());

                foreach ($event->value as $rowKey => $row) {
                    foreach ($row as $colKey => $column) {
                        if (is_array($column) && isset($column['date'])) {
                            $event->value[$rowKey][$colKey] = (new DateTime($column['date'], $timezone));
                        }
                    }
                }
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function getIconUrl(): string
    {
        $handle = StringHelper::toKebabCase($this->displayName());

        return Craft::$app->getAssetManager()->getPublishedUrl("@verbb/formie/web/assets/elements/dist/img/{$handle}.svg", true);
    }

    /**
     * @inheritDoc
     */
    public function getSettingsHtml(): string
    {
        $handle = StringHelper::toKebabCase($this->displayName());

        return Craft::$app->getView()->renderTemplate("formie/integrations/elements/{$handle}/_plugin-settings", [
            'integration' => $this,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getFormSettingsHtml($form): string
    {
        $handle = StringHelper::toKebabCase($this->displayName());

        return Craft::$app->getView()->renderTemplate("formie/integrations/elements/{$handle}/_form-settings", [
            'integration' => $this,
            'form' => $form,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('formie/settings/elements/edit/' . $this->id);
    }

    /**
     * @inheritDoc
     */
    public function getFormSettings($useCache = true)
    {
        // Always fetch, no real need for cache
        return $this->fetchFormSettings();
    }


    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function getFieldTypeForField($fieldClass)
    {
        // Provide a map of all native Craft fields to the data we expect
        $fieldTypeMap = [
            fields\Assets::class => IntegrationField::TYPE_ARRAY,
            fields\Categories::class => IntegrationField::TYPE_ARRAY,
            fields\Checkboxes::class => IntegrationField::TYPE_ARRAY,
            fields\Date::class => IntegrationField::TYPE_DATECLASS,
            fields\Entries::class => IntegrationField::TYPE_ARRAY,
            fields\Lightswitch::class => IntegrationField::TYPE_BOOLEAN,
            fields\MultiSelect::class => IntegrationField::TYPE_ARRAY,
            fields\Number::class => IntegrationField::TYPE_FLOAT,
            fields\Table::class => IntegrationField::TYPE_ARRAY,
            fields\Tags::class => IntegrationField::TYPE_ARRAY,
            fields\Users::class => IntegrationField::TYPE_ARRAY,
        ];

        return $fieldTypeMap[$fieldClass] ?? IntegrationField::TYPE_STRING;
    }

    /**
     * @inheritDoc
     */
    protected function fieldCanBeUniqueId($field)
    {
        $type = get_class($field);

        $supportedFields = [
            fields\Checkboxes::class,
            fields\Color::class,
            fields\Date::class,
            fields\Dropdown::class,
            fields\Email::class,
            fields\Lightswitch::class,
            fields\MultiSelect::class,
            fields\Number::class,
            fields\PlainText::class,
            fields\RadioButtons::class,
            fields\Url::class,
        ];

        if (in_array($type, $supportedFields, true)) {
            return true;
        }

        // Include any field types that extend one of the above
        foreach ($supportedFields as $supportedField) {
            if (is_a($type, $supportedField, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function getElementForPayload($elementType, $submission)
    {
        $element = new $elementType();

        // Check if configuring update, and find an existing element, depending on mapping
        $updateElementValues = $this->getFieldMappingValues($submission, $this->updateElementMapping, $this->getUpdateAttributes());
        $updateElementValues = array_filter($updateElementValues);

        if ($updateElementValues) {
            $query = $elementType::find($updateElementValues);

            // Fina elements of any status, like disabled
            $query->status(null);

            Craft::configure($query, $updateElementValues);

            if ($foundElement = $query->one()) {
                $element = $foundElement;
            }
        }

        return $element;
    }

    /**
     * @inheritDoc
     */
    protected function filterNullValues($values)
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->filterNullValues($values[$key]);
            }

            if ($values[$key] === null) {
                unset($values[$key]);
            }
        }

        return $values;
    }
}
