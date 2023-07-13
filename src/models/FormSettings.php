<?php
namespace verbb\formie\models;

use verbb\formie\Formie;
use verbb\formie\helpers\RichTextHelper;
use verbb\formie\positions\AboveInput;
use verbb\formie\positions\BelowInput;
use verbb\formie\prosemirror\toprosemirror\Renderer as ProseMirrorRenderer;

use Craft;
use craft\base\Model;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;

use yii\behaviors\AttributeTypecastBehavior;

class FormSettings extends Model
{
    // Public Properties
    // =========================================================================

    // Appearance
    public $displayFormTitle = false;
    public $displayCurrentPageTitle = false;
    public $displayPageTabs = false;
    public $displayPageProgress = false;
    public $scrollToTop = true;
    public $progressPosition = 'end';
    public $defaultLabelPosition;
    public $defaultInstructionsPosition;

    // Behaviour
    public $submitMethod;
    public $submitAction;
    public $submitActionTab;
    public $submitActionUrl;
    public $submitActionFormHide;
    public $submitActionMessage;
    public $submitActionMessageTimeout;
    public $submitActionMessagePosition = 'top-form';
    public $loadingIndicator;
    public $loadingIndicatorText;

    // Behaviour - Validation
    public $validationOnSubmit;
    public $validationOnFocus;
    public $errorMessage;
    public $errorMessagePosition = 'top-form';

    // Behaviour - Availability
    public $availabilityMessage;
    public $availabilityMessageDate;
    public $availabilityMessageSubmissions;

    // Integrations
    public $integrations = [];

    // Settings
    public $submissionTitleFormat = '{timestamp}';

    // Settings - Privacy
    public $collectIp;
    public $collectUser;
    public $dataRetention;
    public $dataRetentionValue;
    public $fileUploadsAction;

    // Other
    public $redirectUrl;
    public $defaultEmailTemplateId = '';

    // Private (template-only)
    public $disableCaptchas = false;

    // TODO: to remove
    public $storeData;
    public $userDeletedAction;


    // Private Properties
    // =========================================================================

    private $_form;


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $settings = Formie::$plugin->getSettings();

        if (!$this->errorMessage) {
            $errorMessage = (new ProseMirrorRenderer)->render('<p>' . Craft::t('formie', 'Couldn’t save submission due to errors.') . '</p>');

            $this->errorMessage = $errorMessage['content'];
        }

        if (!$this->submitActionMessage) {
            $submitActionMessage = (new ProseMirrorRenderer)->render('<p>' . Craft::t('formie', 'Submission saved.') . '</p>');

            $this->submitActionMessage = $submitActionMessage['content'];
        }

        if (!$this->defaultLabelPosition) {
            $this->defaultLabelPosition = $settings->defaultLabelPosition;
        }

        if (!$this->defaultInstructionsPosition) {
            $this->defaultInstructionsPosition = $settings->defaultInstructionsPosition;
        }

        $this->defaultEmailTemplateId = $settings->getDefaultEmailTemplateId();
    }

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        return [
            'typecast' => [
                'class' => AttributeTypecastBehavior::class,
                'attributeTypes' => [
                    'displayFormTitle' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'displayPageTabs' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'displayCurrentPageTitle' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'displayPageProgress' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'scrollToTop' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'submitMethod' => AttributeTypecastBehavior::TYPE_STRING,
                    'submitAction' => AttributeTypecastBehavior::TYPE_STRING,
                    'submitActionTab' => AttributeTypecastBehavior::TYPE_STRING,
                    'submitActionFormHide' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'submitActionMessage' => AttributeTypecastBehavior::TYPE_STRING,
                    'submitActionMessageTimeout' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'submitActionUrl' => AttributeTypecastBehavior::TYPE_STRING,
                    'errorMessage' => AttributeTypecastBehavior::TYPE_STRING,
                    'loadingIndicator' => AttributeTypecastBehavior::TYPE_STRING,
                    'loadingIndicatorText' => AttributeTypecastBehavior::TYPE_STRING,
                    'validationOnSubmit' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'validationOnFocus' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'submissionTitleFormat' => AttributeTypecastBehavior::TYPE_STRING,
                    'collectIp' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'collectUser' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'storeData' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'availabilityMessage' => AttributeTypecastBehavior::TYPE_STRING,
                    'defaultLabelPosition' => AttributeTypecastBehavior::TYPE_STRING,
                    'defaultInstructionsPosition' => AttributeTypecastBehavior::TYPE_STRING,
                    'progressPosition' => AttributeTypecastBehavior::TYPE_STRING,
                ],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getForm()
    {
        return $this->_form;
    }

    /**
     * @inheritDoc
     */
    public function setForm($value)
    {
        $this->_form = $value;
    }

    /**
     * @inheritDoc
     */
    public function getSubmitActionMessage($submission = null)
    {
        return Craft::t('site', $this->_getHtmlContent($this->submitActionMessage, $submission));
    }

    /**
     * @inheritDoc
     */
    public function getSubmitActionMessageHtml()
    {
        return $this->_getHtmlContent($this->submitActionMessage);
    }

    /**
     * @inheritDoc
     */
    public function getErrorMessage()
    {
        return Craft::t('site', $this->_getHtmlContent($this->errorMessage));
    }

    /**
     * @inheritDoc
     */
    public function getErrorMessageHtml()
    {
        return $this->_getHtmlContent($this->errorMessage);
    }

    /**
     * @inheritDoc
     */
    public function getEnabledIntegrations()
    {
        $enabledIntegrations = [];

        // Use all integrations + captchas
        $integrations = array_merge(Formie::$plugin->getIntegrations()->getAllIntegrations(), Formie::$plugin->getIntegrations()->getAllCaptchas());

        // Find all the form-enabled integrations
        $formIntegrationSettings = $this->integrations ?? [];
        $enabledFormSettings = ArrayHelper::where($formIntegrationSettings, 'enabled', true);

        foreach ($enabledFormSettings as $handle => $formSettings) {
            $integration = ArrayHelper::firstWhere($integrations, 'handle', $handle);

            // If this disabled globally? Then don't include it, otherwise populate the settings
            if ($integration && $integration->enabled) {
                $integration->setAttributes($formSettings, false);

                $enabledIntegrations[] = $integration;
            }
        }

        return $enabledIntegrations;
    }

    /**
     * Gets the form's redirect URL.
     *
     * @return String
     */
    public function getFormRedirectUrl($checkLastPage = true)
    {
        return $this->getForm()->getRedirectUrl($checkLastPage);
    }

    /**
     * Gets the form's redirect entry, or null if not set.
     *
     * @return Entry|null
     */
    public function getRedirectEntry()
    {
        return $this->getForm()->getRedirectEntry();
    }


    // Private Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    private function _getHtmlContent($content, $submission = null)
    {
        return RichTextHelper::getHtmlContent($content, $submission);
    }
}
