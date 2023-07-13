<?php
namespace verbb\formie\fields\formfields;

use verbb\formie\base\FormField;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\helpers\SchemaHelper;

use Craft;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template;

use yii\db\Schema;
use LitEmoji\LitEmoji;
use Twig\Markup;

class MultiLineText extends FormField implements PreviewableFieldInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Multi-line Text');
    }

    /**
     * @inheritDoc
     */
    public static function getSvgIconPath(): string
    {
        return 'formie/_formfields/multi-line-text/icon.svg';
    }


    // Properties
    // =========================================================================

    public $useRichText;
    public $richTextButtons;


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        if ($value !== null) {
            $value = LitEmoji::entitiesToUnicode($value);
        }

        $value = $value !== '' ? $value : null;

        return parent::normalizeValue($value, $element);
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        if ($value !== null) {
            // Save as HTML entities (e.g. `&#x1F525;`) so we can use that in JS to determine length.
            // Saving as a shortcode is too tricky to detemine the same length in JS.
            $value = LitEmoji::encodeHtml($value);
        }

        return parent::serializeValue($value, $element);
    }

    /**
     * @inheritDoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        $form = null;

        if ($element instanceof Submission) {
            $form = $element->getForm();
        }

        return Craft::$app->getView()->renderTemplate('formie/_formfields/multi-line-text/input', [
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'form' => $form,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getPreviewInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/multi-line-text/preview', [
            'field' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getFrontEndJsModules()
    {
        $modules = [];

        if ($this->limit) {
            $modules[] = [
                'src' => Craft::$app->getAssetManager()->getPublishedUrl('@verbb/formie/web/assets/frontend/dist/js/fields/text-limit.js', true),
                'module' => 'FormieTextLimit',
            ];
        }

        if ($this->useRichText) {
            $modules[] = [
                'src' => Craft::$app->getAssetManager()->getPublishedUrl('@verbb/formie/web/assets/frontend/dist/js/fields/rich-text.js', true),
                'module' => 'FormieRichText',
                'settings' => [
                    'buttons' => $this->richTextButtons,
                ],
            ];
        }

        return $modules;
    }

    /**
     * @inheritDoc
     */
    public function getFieldDefaults(): array
    {
        return [
            'richTextButtons' => ['bold', 'italic'],
        ];
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
            SchemaHelper::textareaField([
                'label' => Craft::t('formie', 'Default Value'),
                'help' => Craft::t('formie', 'Entering a default value will place the value in the field when it loads.'),
                'name' => 'defaultValue',
                'rows' => '3',
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
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Limit Field Content'),
                'help' => Craft::t('formie', 'Whether to limit the content of this field.'),
                'name' => 'limit',
            ]),
            SchemaHelper::toggleContainer('settings.limit', [
                [
                    'label' => Craft::t('formie', 'Limit'),
                    'help' => Craft::t('formie', 'Enter the number of characters or words to limit this field by.'),
                    'type' => 'fieldWrap',
                    'children' => [
                        [
                            'component' => 'div',
                            'class' => 'flex',
                            'children' => [
                                SchemaHelper::textField([
                                    'name' => 'limitAmount',
                                    'class' => 'text flex-grow',
                                    'size' => '3',
                                    'validation' => 'optional|number|min:0',
                                ]),
                                SchemaHelper::selectField([
                                    'name' => 'limitType',
                                    'options' => [
                                        ['label' => Craft::t('formie', 'Characters'), 'value' => 'characters'],
                                        ['label' => Craft::t('formie', 'Words'), 'value' => 'words'],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
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
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Use Rich Text Field'),
                'help' => Craft::t('formie', 'Whether to display this field with a rich text editor for users to enter their content with.'),
                'name' => 'useRichText',
            ]),
            SchemaHelper::toggleContainer('settings.useRichText', [
                SchemaHelper::checkboxSelectField([
                    'label' => Craft::t('formie', 'Rich Text Buttons'),
                    'help' => Craft::t('formie', 'Select which formatting buttons available for users to use.'),
                    'name' => 'richTextButtons',
                    'showAllOption' => false,
                    'options' => [
                        ['label' => Craft::t('formie', 'Bold'), 'value' => 'bold'],
                        ['label' => Craft::t('formie', 'Italic'), 'value' => 'italic'],
                        ['label' => Craft::t('formie', 'Underline'), 'value' => 'underline'],
                        ['label' => Craft::t('formie', 'Strike-through'), 'value' => 'strikethrough'],
                        ['label' => Craft::t('formie', 'Heading 1'), 'value' => 'heading1'],
                        ['label' => Craft::t('formie', 'Heading 2'), 'value' => 'heading2'],
                        ['label' => Craft::t('formie', 'Paragraph'), 'value' => 'paragraph'],
                        ['label' => Craft::t('formie', 'Quote'), 'value' => 'quote'],
                        ['label' => Craft::t('formie', 'Ordered List'), 'value' => 'olist'],
                        ['label' => Craft::t('formie', 'Unordered List'), 'value' => 'ulist'],
                        ['label' => Craft::t('formie', 'Code'), 'value' => 'code'],
                        ['label' => Craft::t('formie', 'Horizontal Rule'), 'value' => 'line'],
                        ['label' => Craft::t('formie', 'Link'), 'value' => 'link'],
                        ['label' => Craft::t('formie', 'Image'), 'value' => 'image'],
                        ['label' => Craft::t('formie', 'Align Left'), 'value' => 'alignleft'],
                        ['label' => Craft::t('formie', 'Align Center'), 'value' => 'aligncenter'],
                        ['label' => Craft::t('formie', 'Align Right'), 'value' => 'alignright'],
                        ['label' => Craft::t('formie', 'Clear Formatting'), 'value' => 'clear'],
                    ],
                ]),
            ]),
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
}
