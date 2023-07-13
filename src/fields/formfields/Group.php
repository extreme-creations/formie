<?php
namespace verbb\formie\fields\formfields;

use verbb\formie\base\FormField;
use verbb\formie\base\NestedFieldInterface;
use verbb\formie\base\NestedFieldTrait;
use verbb\formie\elements\Form;
use verbb\formie\elements\db\NestedFieldRowQuery;
use verbb\formie\gql\types\input\GroupInputType;
use verbb\formie\helpers\SchemaHelper;

use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Gql;

use GraphQL\Type\Definition\ObjectType;

class Group extends FormField implements NestedFieldInterface, EagerLoadingFieldInterface
{
    // Traits
    // =========================================================================

    use NestedFieldTrait;


    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Group');
    }

    /**
     * @inheritDoc
     */
    public static function getSvgIconPath(): string
    {
        return 'formie/_formfields/group/icon.svg';
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        $rules = parent::getElementValidationRules();
        $rules[] = ['validateRows'];

        return $rules;
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
        return Craft::$app->getView()->renderTemplate('formie/_formfields/group/input', [
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getPreviewInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/group/preview', [
            'field' => $this,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function populateValue($value)
    {
        if (!is_array($value)) {
            return;
        }

        if ($fields = $this->getFields()) {
            foreach ($fields as $field) {
                $fieldValue = $value[$field->handle] ?? null;

                if ($fieldValue) {
                    $field->populateValue($fieldValue);
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function parsePopulatedFieldValues($value, $element)
    {
        // For when parsing populated content from the cache, when the field is visibly disabled
        // It's supplied in a format that makes sense for `populateValue()` but not for `$element->setFieldValue()`.
        return [
            'new1' => [
                'fields' => $value,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getConfigJson()
    {
        // Group fields themselves should not contain the inner field's JS
        return null;
    }

    /**
     * @inheritDoc
     */
    public function defineGeneralSchema(): array
    {
        return [
            SchemaHelper::labelField(),
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
        return GroupInputType::getType($this);
    }

    /**
     * @inheritDoc
     */
    public function getContentGqlType()
    {
        $typeName = ($this->getForm()->handle ?? '') . '_' . $this->handle . '_FormieGroupField';

        if ($inputType = GqlEntityRegistry::getEntity($typeName)) {
            return $inputType;
        }

        $groupFields = [];

        foreach ($this->getFields() as $field) {
            $groupFields[$field->handle] = $field->getContentGqlType();
        }

        return GqlEntityRegistry::createEntity($typeName, new ObjectType([
            'name' => $typeName,
            'fields' => function() use ($groupFields) {
                return $groupFields;
            },
            'resolveField' => function($source, $args, $context, $info) {
                $fieldName = Gql::getFieldNameWithAlias($info, $source, $context);
                return $source[0][$fieldName] ?? null;
            },
        ]));
    }
}
