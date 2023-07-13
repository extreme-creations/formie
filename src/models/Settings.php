<?php
namespace verbb\formie\models;

use verbb\formie\Formie;
use verbb\formie\positions\AboveInput;
use verbb\formie\positions\BelowInput;
use verbb\formie\prosemirror\toprosemirror\Renderer as ProseMirrorRenderer;

use Craft;
use craft\base\Model;
use craft\helpers\DateTimeHelper;

use yii\validators\EmailValidator;

class Settings extends Model
{
    // Constants
    // =========================================================================

    const SPAM_BEHAVIOUR_SUCCESS = 'showSuccess';
    const SPAM_BEHAVIOUR_MESSAGE = 'showMessage';

    // Public Properties
    // =========================================================================

    /**
     * The plugin display name.
     *
     * @var string
     */
    public $pluginName = 'Formie';

    /**
     * The slug to the default page when opening the plugin.
     *
     * @var string
     */
    public $defaultPage = 'forms';

    // Forms
    public $defaultFormTemplate = '';
    public $defaultEmailTemplate = '';
    public $enableUnloadWarning = true;
    public $ajaxTimeout = 10;

    // Fields
    public $disabledFields = [];

    // General Fields
    public $defaultLabelPosition = AboveInput::class;
    public $defaultInstructionsPosition = AboveInput::class;

    // Fields
    public $defaultFileUploadVolume = '';
    public $defaultDateDisplayType = '';
    public $defaultDateValueOption = '';
    public $defaultDateTime = null;
    public $enableLargeFieldStorage = false;

    /**
     * The maximum age of an incomplete submission in days
     * before it is deleted in garbage collection.
     *
     * Set to 0 to disable automatic deletion.
     *
     * @var int days
     */
    public $maxIncompleteSubmissionAge = 30;

    /**
     * Whether to enable Gatsby support for the plugin.
     * Enabling this will rename the `fields` field on the GraphQL Form type to `formFields`.
     *
     * @var bool
     */
    public $enableGatsbyCompatibility = false;

    /**
     * The maximum age of an incomplete submission in days
     * before it is deleted in garbage collection.
     *
     * Set to 0 to disable automatic deletion.
     *
     * @var int days
     */
    public $maxSentNotificationsAge = 30;

    // Submissions
    public $enableCsrfValidationForGuests = true;
    public $useQueueForNotifications = true;
    public $useQueueForIntegrations = true;

    // Spam
    public $saveSpam = true;
    public $spamLimit = 500;
    public $spamEmailNotifications = false;
    public $spamBehaviour = self::SPAM_BEHAVIOUR_SUCCESS;
    public $spamKeywords = '';
    public $spamBehaviourMessage = '';

    // Notifications
    public $sendEmailAlerts = false;
    public $sentNotifications = true;
    public $alertEmails;

    // PDFs
    public $pdfPaperSize = 'letter';
    public $pdfPaperOrientation = 'portrait';

    public $captchas = [];


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['pluginName', 'defaultPage', 'maxIncompleteSubmissionAge', 'maxSentNotificationsAge'], 'required'];
        $rules[] = [['pluginName'], 'string', 'max' => 52];
        $rules[] = [['maxIncompleteSubmissionAge', 'maxSentNotificationsAge'], 'number', 'integerOnly' => true];
        $rules[] = [['alertEmails'], 'validateAlertEmails'];

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function validateAlertEmails($attribute)
    {
        if ($this->sendEmailAlerts) {
            if (empty($this->alertEmails)) {
                $this->addError($attribute, Craft::t('formie', 'You must enter at least one name and email.'));
                return;
            }

            foreach ($this->alertEmails as $fromNameEmail) {
                if ($fromNameEmail[0] === '' || $fromNameEmail[1] === '') {
                    $this->addError($attribute, Craft::t('formie', 'The name and email cannot be blank.'));
                    return;
                }

                $emailValidator = new EmailValidator();

                if (!$emailValidator->validate($fromNameEmail[1])) {
                    $this->addError($attribute, Craft::t('formie', 'An invalid email was entered.'));
                    return;
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getDefaultFormTemplateId()
    {
        if ($template = Formie::$plugin->getFormTemplates()->getTemplateByHandle($this->defaultFormTemplate)) {
            return $template->id;
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDefaultEmailTemplateId()
    {
        if ($template = Formie::$plugin->getEmailTemplates()->getTemplateByHandle($this->defaultEmailTemplate)) {
            return $template->id;
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDefaultDateTimeValue()
    {
        if ($this->defaultDateTime = DateTimeHelper::toDateTime($this->defaultDateTime)) {
            return $this->defaultDateTime;
        }

        return null;
    }
}
