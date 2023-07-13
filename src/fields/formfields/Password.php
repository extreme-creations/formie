<?php
namespace verbb\formie\fields\formfields;

use verbb\formie\Formie;
use verbb\formie\base\FormField;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\helpers\SchemaHelper;
use verbb\formie\models\Notification;

use Craft;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;

class Password extends FormField implements PreviewableFieldInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Password');
    }

    /**
     * @inheritDoc
     */
    public static function getSvgIconPath(): string
    {
        return 'formie/_formfields/password/icon.svg';
    }


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function isValueEmpty($value, ElementInterface $element): bool
    {
        // Evaluate password fields differently. Because we don't populate the value back to the
        // field on reload, for multi-page forms this messes validation up. Because while for _this_
        // request we don't have a value, the submission stored does.
        // So, if the field is considered empty, do a fresh lookup to see if there's already a value.
        // We don't want to tell _what_ the value is, just if it can skip validation.
        $isValueEmpty = parent::isValueEmpty($value, $element);

        if ($isValueEmpty && $element->id) {
            $savedElement = Craft::$app->getElements()->getElementById($element->id, Submission::class);

            if ($savedElement) {
                $isValueEmpty = parent::isValueEmpty($savedElement->getFieldValue($this->handle), $savedElement);
            }
        }

        return $isValueEmpty;
    }

    /**
     * @inheritDoc
     */
    public function getIsTextInput(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        // Only save the password as a hash
        if ($value) {
            $value = Craft::$app->getSecurity()->hashPassword($value);
        } else {
            // Important to reset to null, to prevent hash discovery from an empty string
            $value = null;
        }

        return parent::serializeValue($value, $element);
    }

    /**
     * @inheritDoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        // Mask the value for submissions (but no indication of length)
        if ($value) {
            return '•••••••••••••••••••••';
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function getPreviewInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/password/preview', [
            'field' => $this,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getEmailHtml(Submission $submission, Notification $notification, $value, array $options = null)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function defineGeneralSchema(): array
    {
        return [
            SchemaHelper::labelField(),
            SchemaHelper::textField([
                'label' => Craft::t('formie', 'Placeholder'),
                'help' => Craft::t('formie', 'The text that will be shown if the field doesn’t have a value.'),
                'name' => 'placeholder',
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
            SchemaHelper::matchField([
                'fieldTypes' => [self::class],
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


    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function defineValueForSummary($value, ElementInterface $element = null)
    {
        // Mask the value for submissions (but no indication of length)
        if ($value) {
            return '•••••••••••••••••••••';
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    protected function defineValueForExport($value, ElementInterface $element = null)
    {
        // Hide the hashed password from exports as well
        return $this->getValueForSummary($value, $element);
    }
}
