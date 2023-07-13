<?php
namespace verbb\formie\integrations\emailmarketing;

use verbb\formie\base\Integration;
use verbb\formie\base\EmailMarketing;
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
use craft\web\View;

class ActiveCampaign extends EmailMarketing
{
    // Properties
    // =========================================================================

    public $apiKey;
    public $apiUrl;


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'ActiveCampaign');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return Craft::t('formie', 'Sign up users to your ActiveCampaign lists to grow your audience for campaigns.');
    }

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['apiKey', 'apiUrl'], 'required'];

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function fetchFormSettings()
    {
        $settings = [];

        try {
            $lists = $this->_getPaginated('lists');

            // While we're at it, fetch the fields for the list
            $fields = $this->_getPaginated('fields');

            foreach ($lists as $list) {
                $listFields = array_merge([
                    new IntegrationField([
                        'handle' => 'email',
                        'name' => Craft::t('formie', 'Email'),
                        'required' => true,
                    ]),
                    new IntegrationField([
                        'handle' => 'firstName',
                        'name' => Craft::t('formie', 'First Name'),
                    ]),
                    new IntegrationField([
                        'handle' => 'lastName',
                        'name' => Craft::t('formie', 'Last Name'),
                    ]),
                    new IntegrationField([
                        'handle' => 'phone',
                        'name' => Craft::t('formie', 'Phone'),
                    ]),
                ], $this->_getCustomFields($fields));

                $listFields[] = new IntegrationField([
                    'handle' => 'tags',
                    'name' => Craft::t('formie', 'Tags'),
                ]);

                $settings['lists'][] = new IntegrationCollection([
                    'id' => $list['id'],
                    'name' => $list['name'],
                    'fields' => $listFields,
                ]);
            }
        } catch (\Throwable $e) {
            Integration::apiError($this, $e);
        }

        return new IntegrationFormSettings($settings);
    }

    /**
     * @inheritDoc
     */
    public function sendPayload(Submission $submission): bool
    {
        try {
            $fieldValues = $this->getFieldMappingValues($submission, $this->fieldMapping);

            // Pull out email, as it needs to be top level
            $email = ArrayHelper::remove($fieldValues, 'email');
            $firstName = ArrayHelper::remove($fieldValues, 'firstName');
            $lastName = ArrayHelper::remove($fieldValues, 'lastName');
            $phone = ArrayHelper::remove($fieldValues, 'phone');

            $tags = ArrayHelper::remove($fieldValues, 'tags');

            $payload = [
                'contact' => [
                    'email' => $email,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'phone' => $phone,
                    'fieldValues' => $this->_prepCustomFields($fieldValues),
                ],
            ];

            $response = $this->deliverPayload($submission, 'contact/sync', $payload);

            if ($response === false) {
                return true;
            }

            $contactId = $response['contact']['id'] ?? '';

            if (!$contactId) {
                Integration::error($this, Craft::t('formie', 'Missing return “contactId” {response}. Sent payload {payload}', [
                    'response' => Json::encode($response),
                    'payload' => Json::encode($payload),
                ]), true);

                return false;
            }

            $payload = [
                'contactList' => [
                    'list' => $this->listId,
                    'contact' => $contactId,
                    'status' => 1,
                ],
            ];

            $response = $this->deliverPayload($submission, 'contactLists', $payload);

            if ($response === false) {
                return true;
            }

            // Process any tags, we need to find or create each one.
            if ($tags) {
                // Cleanup and handle multiple tags
                $tags = array_filter(array_map('trim', explode(',', $tags)));

                if ($tags) {
                    // Find all the tags first
                    $existingTags = $this->_getPaginated('tags');
                    $tagIds = [];

                    // Process each tag
                    foreach ($tags as $tag) {
                        // Find if it's already been created, don't create again
                        foreach ($existingTags as $existingTag) {
                            if ($existingTag['tag'] === $tag) {
                                $tagIds[] = $existingTag['id'];

                                continue 2;
                            }
                        }

                        // Create the tag
                        $payload = [
                            'tag' => [
                                'tag' => $tag,
                                'tagType' => 'contact',
                                'description' => '',
                            ],
                        ];

                        $response = $this->deliverPayload($submission, 'tags', $payload);

                        $tagIds[] = $response['tag']['id'] ?? null;
                    }

                    // Assign all tags to the contact
                    foreach ($tagIds as $tagId) {
                        $payload = [
                            'contactTag' => [
                                'contact' => $contactId,
                                'tag' => $tagId,
                            ],
                        ];

                        $response = $this->deliverPayload($submission, 'contactTags', $payload);
                    }
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
            $response = $this->request('GET', 'contacts');
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

        return $this->_client = Craft::createGuzzleClient([
            'base_uri' => trim(App::parseEnv($this->apiUrl), '/') . '/api/3/',
            'headers' => ['Api-Token' => App::parseEnv($this->apiKey)],
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
            'multiselect' => IntegrationField::TYPE_ARRAY,
            'checkbox' => IntegrationField::TYPE_ARRAY,
            'date' => IntegrationField::TYPE_DATETIME,
        ];

        return $fieldTypes[$fieldType] ?? IntegrationField::TYPE_STRING;
    }

    /**
     * @inheritDoc
     */
    private function _getCustomFields($fields, $excludeNames = [])
    {
        $customFields = [];

        // Don't use all fields, at least for the moment...
        $supportedFields = [
            'text',
            'textarea',
            'hidden',
            'dropdown',
            'radio',
            'date',
            // 'checkbox',
            // 'listbox',
        ];

        foreach ($fields as $key => $field) {
            // Some endpoints return different things!
            $fieldName = $field['fieldLabel'] ?? $field['title'] ?? '';
            $fieldType = $field['fieldType'] ?? $field['type'] ?? '';

            // // Only allow supported types
            if (!in_array($fieldType, $supportedFields)) {
                continue;
            }

            // Exclude any names
            if (in_array($fieldName, $excludeNames)) {
                continue;
            }

            $customFields[] = new IntegrationField([
                'handle' => (string)$field['id'],
                'name' => $fieldName,
                'type' => $this->_convertFieldType($fieldType),
            ]);
        }

        return $customFields;
    }

    /**
     * @inheritDoc
     */
    private function _prepCustomFields($fields)
    {
        $customFields = [];

        foreach ($fields as $key => $value) {
            $customFields[] = [
                'field' => $key,
                'value' => $value,
            ];
        }

        return $customFields;
    }

    /**
     * @inheritDoc
     */
    private function _getPaginated($endpoint, $limit = 100, $offset = 0, $items = [])
    {
        $response = $this->request('GET', $endpoint, [
            'query' => [
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);

        $newItems = $response[$endpoint] ?? [];
        $total = $response['meta']['total'] ?? 0;

        $items = array_merge($items, $newItems);

        if (count($items) < $total) {
            $items = array_merge($items, $this->_getPaginated($endpoint, $limit, $offset + $limit, $items));
        }

        return $items;
    }
}