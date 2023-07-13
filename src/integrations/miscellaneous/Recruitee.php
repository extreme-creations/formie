<?php
namespace verbb\formie\integrations\miscellaneous;

use verbb\formie\base\Integration;
use verbb\formie\base\Miscellaneous;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\errors\IntegrationException;
use verbb\formie\events\SendIntegrationPayloadEvent;
use verbb\formie\models\IntegrationCollection;
use verbb\formie\models\IntegrationField;
use verbb\formie\models\IntegrationFormSettings;

use Craft;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\web\View;

class Recruitee extends Miscellaneous
{
    // Properties
    // =========================================================================

    public $apiKey;
    public $subdomain;
    public $mapToCandidate = false;
    public $candidateFieldMapping;


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Recruitee');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return Craft::t('formie', 'Apply for Recruitee job offers.');
    }

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['apiKey', 'subdomain'], 'required'];

        $candidate = $this->getFormSettingValue('candidate');

        // Validate the following when saving form settings
        $rules[] = [['candidateFieldMapping'], 'validateFieldMapping', 'params' => $candidate, 'when' => function($model) {
            return $model->enabled && $model->mapToCandidate;
        }, 'on' => [Integration::SCENARIO_FORM]];

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function fetchFormSettings()
    {
        $settings = [];

        try {
            $response = $this->request('GET', 'offers');
            $offers = $response['offers'] ?? [];

            $candidateFields = array_merge([
                new IntegrationField([
                    'handle' => 'offer_slug',
                    'name' => Craft::t('formie', 'Offer Slug'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'email',
                    'name' => Craft::t('formie', 'Email'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'name',
                    'name' => Craft::t('formie', 'Name'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'phone',
                    'name' => Craft::t('formie', 'Phone'),
                ]),
                new IntegrationField([
                    'handle' => 'remote_cv_url',
                    'name' => Craft::t('formie', 'CV'),
                ]),
                new IntegrationField([
                    'handle' => 'referrer',
                    'name' => Craft::t('formie', 'Referrer'),
                ]),
                new IntegrationField([
                    'handle' => 'photo',
                    'name' => Craft::t('formie', 'Photo'),
                ]),
                new IntegrationField([
                    'handle' => 'cover_letter',
                    'name' => Craft::t('formie', 'Cover Letter'),
                ]),
            ], $this->_getCustomFields($offers));

            $settings = [
                'candidate' => $candidateFields,
            ];
        } catch (\Throwable $e) {
            Integration::apiError($this, $e);
        }

        return new IntegrationFormSettings($settings);
    }

    /**
     * @inheritDoc
     */
    public function getFieldMappingValues(Submission $submission, $fieldMapping, $fieldSettings = [])
    {
        // A quick shortcut to keep CRM's simple, just pass in a string to the namespace
        $fields = $this->getFormSettingValue($fieldSettings);

        return parent::getFieldMappingValues($submission, $fieldMapping, $fields);
    }

    /**
     * @inheritDoc
     */
    public function sendPayload(Submission $submission): bool
    {
        try {
            $candidateValues = $this->getFieldMappingValues($submission, $this->candidateFieldMapping, 'candidate');

            // Extract the offer slug
            $offerSlug = ArrayHelper::remove($candidateValues, 'offer_slug');

            // Handle open questions
            $candidateValues['open_question_answers_attributes'] = $this->_prepCustomFields($candidateValues);

            if ($this->mapToCandidate) {
                $candidatePayload = [
                    'candidate' => $candidateValues,
                ];

                $response = $this->deliverPayload($submission, "offers/{$offerSlug}/candidates", $candidatePayload);

                if ($response === false) {
                    return true;
                }

                $candidateId = $response['id'] ?? '';

                if (!$candidateId) {
                    Integration::error($this, Craft::t('formie', 'Missing return “candidateId” {response}. Sent payload {payload}', [
                        'response' => Json::encode($response),
                        'payload' => Json::encode($candidatePayload),
                    ]), true);

                    return false;
                }
            }
        } catch (\Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function fetchConnection(): bool
    {
        try {
            $response = $this->request('GET', 'offers');
        } catch (\Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        if ($this->_client) {
            return $this->_client;
        }

        $subdomain = App::parseEnv($this->subdomain);

        return $this->_client = Craft::createGuzzleClient([
            'base_uri' => "https://{$subdomain}.recruitee.com/api/",
        ]);
    }


    // Private Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    private function _convertFieldType($fieldType)
    {
        $fieldTypes = [
            'boolean' => IntegrationField::TYPE_BOOLEAN,
            'legal' => IntegrationField::TYPE_BOOLEAN,
            'multi_choice' => IntegrationField::TYPE_ARRAY,
        ];

        return $fieldTypes[$fieldType] ?? IntegrationField::TYPE_STRING;
    }

    /**
     * @inheritDoc
     */
    private function _getCustomFields($offers)
    {
        $customFields = [];

        $types = [
            'boolean' => 'flag',
            'legal' => 'flag',
            'multi_choice' => 'multi_content',
            'file' => 'file',
        ];

        foreach ($offers as $offer) {
            $openQuestions = $offer['open_questions'] ?? [];

            foreach ($openQuestions as $openQuestion) {
                $options = [];

                $fieldOptions = $openQuestion['open_question_options'] ?? [];

                foreach ($fieldOptions as $key => $fieldOption) {
                    $options[] = [
                        'label' => $fieldOption['body'],
                        'value' => $fieldOption['id'],
                    ];
                }

                if ($options) {
                    $options = [
                        'label' => $openQuestion['body'],
                        'options' => $options,
                    ];
                }

                // Get the right type we need to pass in later for the value param
                $typeKey = $types[$openQuestion['kind']] ?? 'content';

                $customFields[] = new IntegrationField([
                    'handle' => 'open_questions:' . $typeKey . ':' . $openQuestion['id'],
                    'name' => $openQuestion['body'],
                    'type' => $this->_convertFieldType($openQuestion['kind']),
                    'options' => $options,
                ]);
            }
        }

        return $customFields;
    }

    /**
     * @inheritDoc
     */
    private function _prepCustomFields(&$fields, $extras = [])
    {
        $customFields = [];

        foreach ($fields as $key => $value) {
            if (StringHelper::startsWith($key, 'open_questions:')) {
                $field = ArrayHelper::remove($fields, $key);

                $typeKey = str_replace('open_questions:', '', $key);
                $typeKey = explode(':', $typeKey);

                $customFields[] = [
                    'open_question_id' => $typeKey[1],
                    $typeKey[0] => $value,
                ];
            }
        }

        return $customFields;
    }
}