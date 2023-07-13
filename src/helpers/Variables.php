<?php
namespace verbb\formie\helpers;

use verbb\formie\Formie;
use verbb\formie\base\SubFieldInterface;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\formfields\Date;
use verbb\formie\fields\formfields\Group;
use verbb\formie\fields\formfields\MultiLineText;
use verbb\formie\fields\formfields\Repeater;
use verbb\formie\fields\formfields\Recipients;
use verbb\formie\models\Notification;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\fields\BaseRelationField;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\SingleOptionsFieldData;
use craft\helpers\App;
use craft\web\twig\variables\CraftVariable;

use DateTime;
use DateTimeZone;
use Throwable;

class Variables
{
    // Public Static Methods
    // =========================================================================

    /**
     * Returns a list of form variables.
     *
     * @return array
     */
    public static function getFormVariables(): array
    {
        return [
            ['label' => Craft::t('formie', 'Form'), 'heading' => true],
            ['label' => Craft::t('formie', 'All Form Fields'), 'value' => '{allFields}'],
            ['label' => Craft::t('formie', 'All Non Empty Fields'), 'value' => '{allContentFields}'],
            ['label' => Craft::t('formie', 'All Visible Fields'), 'value' => '{allVisibleFields}'],
            ['label' => Craft::t('formie', 'Form Name'), 'value' => '{formName}'],
            ['label' => Craft::t('formie', 'Submission CP URL'), 'value' => '{submissionUrl}'],
            ['label' => Craft::t('formie', 'Submission ID'), 'value' => '{submissionId}'],
        ];
    }

    /**
     * Returns a list of email variables.
     *
     * @return array
     */
    public static function getEmailVariables(): array
    {
        return [
            ['label' => Craft::t('formie', 'Email'), 'heading' => true],
            ['label' => Craft::t('formie', 'System Email'), 'value' => '{systemEmail}'],
            ['label' => Craft::t('formie', 'System Reply-To'), 'value' => '{systemReplyTo}'],
        ];
    }

    /**
     * Returns a list of general variables.
     *
     * @return array
     */
    public static function getGeneralVariables(): array
    {
        return [
            ['label' => Craft::t('formie', 'General'), 'heading' => true],
            ['label' => Craft::t('formie', 'System Name'), 'value' => '{systemName}'],
            ['label' => Craft::t('formie', 'Site Name'), 'value' => '{siteName}'],
            ['label' => Craft::t('formie', 'Timestamp'), 'value' => '{timestamp}'],
            ['label' => Craft::t('formie', 'Date (mm/dd/yyyy)'), 'value' => '{dateUs}'],
            ['label' => Craft::t('formie', 'Date (dd/mm/yyyy)'), 'value' => '{dateInt}'],
            ['label' => Craft::t('formie', 'Time (12h)'), 'value' => '{time12}'],
            ['label' => Craft::t('formie', 'Time (24h)'), 'value' => '{time24}'],
        ];
    }

    /**
     * Returns a list of users variables.
     *
     * @return array
     */
    public static function getUsersVariables(): array
    {
        return [
            ['label' => Craft::t('formie', 'Users'), 'heading' => true],
            ['label' => Craft::t('formie', 'User IP Address'), 'value' => '{userIp}'],
            ['label' => Craft::t('formie', 'User ID'), 'value' => '{userId}'],
            ['label' => Craft::t('formie', 'User Email'), 'value' => '{userEmail}'],
            ['label' => Craft::t('formie', 'Username'), 'value' => '{username}'],
            ['label' => Craft::t('formie', 'User Full Name'), 'value' => '{userFullName}'],
            ['label' => Craft::t('formie', 'User First Name'), 'value' => '{userFirstName}'],
            ['label' => Craft::t('formie', 'User Last Name'), 'value' => '{userLastName}'],
        ];
    }

    /**
     * Returns a list of all available variables.
     *
     * @return array
     */
    public static function getVariables(): array
    {
        return array_merge(
            static::getFormVariables(),
            static::getGeneralVariables(),
            static::getEmailVariables(),
            static::getUsersVariables()
        );
    }

    /**
     * Returns a list of all available variables.
     *
     * @return array
     */
    public static function getVariablesArray(): array
    {
        return [
            'form' => static::getFormVariables(),
            'general' => static::getGeneralVariables(),
            'email' => static::getEmailVariables(),
            'users' => static::getUsersVariables(),
        ];
    }

    /**
     * Renders and returns an object template, or null if it fails.
     *
     * @param string $value
     * @param Submission $submission
     * @param Form $form
     * @return string|null
     */
    public static function getParsedValue($value, Submission $submission = null, Form $form = null, Notification $notification = null)
    {
        $originalValue = $value;

        if (!$value) {
            return '';
        }

        if (is_array($value)) {
            return '';
        }

        // Try and get the form from the submission if not set
        if ($submission && !$form) {
            $form = $submission->form;
        }

        // Parse aliases and env variables
        $value = App::parseEnv($value);

        // Use a cache key based on the submission, or few unsaved submissions, the formId
        // This helps to only cache it per-submission, when being run in queues.
        $cacheKey = $submission->id ?? $form->id ?? rand();

        // Check to see if we have these already calculated for the request and submission
        // Just saves a good bunch of calculating values like looping through fields
        if (!Formie::$plugin->getRenderCache()->getGlobalVariables($cacheKey)) {
            // Get the user making the submission. Some checks to do to get it right.
            $currentUser = self::_getCurrentUser($submission);

            // User Info
            $userId = $currentUser->id ?? '';
            $userEmail = $currentUser->email ?? '';
            $username = $currentUser->username ?? '';
            $userFullName = $currentUser->fullName ?? '';
            $userFirstName = $currentUser->firstName ?? '';
            $userLastName = $currentUser->lastName ?? '';
            $userIp = $submission->ipAddress ?? '';

            // Site Info
            $site = self::_getSite($submission);
            $siteName = $site->name ?? '';

            // Force-set the current site. This will either be the current site the user is on for front-end requests,
            // or the site saved against the submission. When being run from a queue there's no concept of the 'site'
            // we're currently on, so using the `siteId` against the submission is the only way to determine that.
            if ($site) {
                Craft::$app->getSites()->setCurrentSite($site);
            }

            $craftMailSettings = App::mailSettings();
            $systemEmail = $craftMailSettings->fromEmail;
            $systemReplyTo = $craftMailSettings->replyToEmail;
            $systemName = $craftMailSettings->fromName;

            // Date Info
            $timeZone = Craft::$app->getTimeZone();
            $now = new DateTime('now', new DateTimeZone($timeZone));

            // Form Info
            $formName = $form->title ?? '';

            Formie::$plugin->getRenderCache()->setGlobalVariables($cacheKey, [
                'formName' => $formName,
                'submissionUrl' => $submission->cpEditUrl ?? '',
                'submissionId' => $submission->id ?? '',

                'siteName' => $siteName,
                'systemEmail' => $systemEmail,
                'systemReplyTo' => $systemReplyTo,
                'systemName' => $systemName,
                'craft' => new CraftVariable(),
                'currentSite' => $site,
                'currentUser' => $currentUser,
                'siteUrl' => $site->getBaseUrl(),

                'timestamp' => $now->format('Y-m-d H:i:s'),
                'dateUs' => $now->format('m/d/Y'),
                'dateInt' => $now->format('d/m/Y'),
                'time12' => $now->format('h:i a'),
                'time24' => $now->format('H:i'),

                'userIp' => $userIp,
                'userId' => $userId,
                'userEmail' => $userEmail,
                'username' => $username,
                'userFullName' => $userFullName,
                'userFirstName' => $userFirstName,
                'userLastName' => $userLastName,
            ]);

            // Add support for all global sets
            foreach (Craft::$app->getGlobals()->getAllSets() as $globalSet) {
                Formie::$plugin->getRenderCache()->setGlobalVariables($cacheKey, [
                    $globalSet->handle => $globalSet,
                ]);
            }
        }

        $fieldHtml = self::getFormFieldsHtml($form, $notification, $submission);
        $fieldContentHtml = self::getFormFieldsHtml($form, $notification, $submission, true);
        $fieldVisibleHtml = self::getFormFieldsHtml($form, $notification, $submission, false, true);

        $fieldVariables = [
            'allFields' => $fieldHtml,
            'allContentFields' => $fieldContentHtml,
            'allVisibleFields' => $fieldVisibleHtml,
        ];

        // Old and deprecated methods. Ensure all fields are prefixed with 'field:', but too tricky to migrate...
        // TODO: Remove at next breakpoint
        $fieldVariables = array_merge($fieldVariables, self::_getParsedFieldValuesLegacy($form, $notification, $submission));

        // Properly parse field values
        $fieldVariables = array_merge($fieldVariables, self::_getParsedFieldValues($form, $submission, $notification));

        // Don't save anything unless we have values
        $fieldVariables = array_filter($fieldVariables);

        Formie::$plugin->getRenderCache()->setFieldVariables($cacheKey, $fieldVariables);

        $variables = Formie::$plugin->getRenderCache()->getVariables($cacheKey);

        // Try to parse submission + extra variables
        $view = Craft::$app->getView();

        try {
            return $view->renderObjectTemplate($value, $submission, $variables);
        } catch (Throwable $e) {
            Formie::error(Craft::t('formie', 'Failed to render dynamic string “{value}”. Template error: “{message}” {file}:{line}', [
                'value' => $originalValue,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));

            return '';
        }
    }

    /**
     * @inheritdoc
     */
    public static function getFormFieldsHtml($form, $notification, $submission, $excludeEmpty = false, $excludeHidden = false, $asArray = false)
    {
        $fieldItems = $asArray ? [] : '';

        if (!$form || !$submission || !$notification) {
            return $fieldItems;
        }

        // Need to switch back to the CP to render our fields email HTML
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        foreach ($form->getFields() as $field) {
            if (!$field->includeInEmail) {
                continue;
            }

            if ($field->isConditionallyHidden($submission)) {
                continue;
            }

            if ($excludeHidden && $field->getIsHidden()) {
                continue;
            }

            $value = $submission->getFieldValue($field->handle);

            if (empty($field->getValueAsString($value, $submission)) && $excludeEmpty) {
                continue;
            }

            $html = $field->getEmailHtml($submission, $notification, $value);

            if ($html === false) {
                continue;
            }

            if ($asArray) {
                $fieldItems[$field->handle] = (string)$html;
            } else {
                $fieldItems .= (string)$html;
            }
        }

        $view->setTemplateMode($oldTemplateMode);

        return $fieldItems;
    }


    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    private static function _getParsedFieldValuesLegacy($form, $notification, $submission)
    {
        $values = [];

        if (!$form || !$submission || !$notification) {
            return $values;
        }

        $parsedFieldContent = self::getFormFieldsHtml($form, $notification, $submission, true, true, true);

        // For now, only handle element fields, which need HTML generated
        if ($submission && $submission->getFieldLayout()) {
            foreach ($submission->getFieldLayout()->getFields() as $field) {
                // Element fields
                if ($field instanceof BaseRelationField) {
                    $parsedContent = $parsedFieldContent[$field->handle] ?? '';

                    if ($parsedContent) {
                        $values[$field->handle . '_html'] = $parsedContent;
                    }
                }

                // Date fields
                if ($field instanceof Date) {
                    $parsedContent = $submission[$field->handle] ?? '';

                    if ($parsedContent && $parsedContent instanceof DateTime) {
                        $values[$field->handle] = $parsedContent->format('Y-m-d H:i:s');
                    }
                }
            }
        }

        return $values;
    }

    /**
     * @inheritdoc
     */
    private static function _getParsedFieldValues($form, $submission, $notification)
    {
        $values = [];

        if (!$form || !$submission) {
            return $values;
        }

        if ($submission && $submission->getFieldLayout()) {
            foreach ($submission->getFieldLayout()->getFields() as $field) {
                $submissionValue = $submission->getFieldValue($field->handle);

                $values = array_merge($values, self::_getParsedFieldValue($field, $submissionValue, $submission, $notification));
            }
        }

        return self::_expandArray($values);
    }

    /**
     * @inheritdoc
     */
    private static function _getParsedFieldValue($field, $submissionValue, $submission, $notification)
    {
        $values = [];

        if (!$submission) {
            return $values;
        }

        $parsedContent = '';

        if ($notification) {
            $parsedContent = (string)$field->getEmailHtml($submission, $notification, $submissionValue, ['hideName' => true]);
        }

        $prefix = 'field.';

        if ($field instanceof Date) {
            if ($submissionValue && $submissionValue instanceof DateTime) {
                $values["{$prefix}{$field->handle}"] = $field->getValueAsString($submissionValue, $submission);

                // Generate additional values
                if ($field->displayType !== 'calendar') {
                    $props = [
                        'year' => 'Y',
                        'month' => 'm',
                        'day' => 'd',
                        'hour' => 'H',
                        'minute' => 'i',
                        'second' => 's',
                        'ampm' => 'a',
                    ];

                    foreach ($props as $k => $format) {
                        $values["{$prefix}{$field->handle}.{$k}"] = $submissionValue->format($format);
                    }
                }
            }
        } else if ($field instanceof SubFieldInterface && $field->hasSubfields()) {
            foreach ($field->getSubFieldOptions() as $subfield) {
                $handle = "{$prefix}{$field->handle}.{$subfield['handle']}";

                $values[$handle] = $submissionValue[$subfield['handle']] ?? '';
            }
        } else if ($field instanceof Group) {
            if ($submissionValue && $row = $submissionValue->one()) {
                if ($fieldLayout = $row->getFieldLayout()) {
                    foreach ($row->getFieldLayout()->getFields() as $nestedField) {
                        $submissionValue = $row->getFieldValue($nestedField->handle);
                        $fieldValues = self::_getParsedFieldValue($nestedField, $submissionValue, $submission, $notification);

                        foreach ($fieldValues as $key => $fieldValue) {
                            $handle = "{$prefix}{$field->handle}." . str_replace($prefix, '', $key);

                            $values[$handle] = $fieldValue;
                        }
                    }
                }
            }
        } else if ($field instanceof BaseRelationField) {
            $values["{$prefix}{$field->handle}"] = $parsedContent;
        } else if ($field instanceof MultiLineText) {
            if ($field->useRichText) {
                $values["{$prefix}{$field->handle}"] = $parsedContent;
            } else {
                $values["{$prefix}{$field->handle}"] = nl2br($field->getValueAsString($submissionValue, $submission));
            }
        } else if ($field instanceof Repeater) {
            $values["{$prefix}{$field->handle}"] = $parsedContent;
        } else if ($field instanceof Recipients && $field->displayType === 'hidden') {
            // Chcek for hidden recipients fields, which might have array content
            // It's arguable that `getValueAsString()` should be the default behaviour but requires more testing
            $values["{$prefix}{$field->handle}"] = $field->getValueAsString($submissionValue, $submission);
        } else if ($submissionValue instanceof MultiOptionsFieldData) {
            // TODO: this should become the single thing we need to use here at the next breakpoint. Requires full testing.
            $values["{$prefix}{$field->handle}"] = $field->getValueForEmail($submissionValue, $notification, $submission);
        } else if ($submissionValue instanceof SingleOptionsFieldData) {
            // TODO: this should become the single thing we need to use here at the next breakpoint. Requires full testing.
            $values["{$prefix}{$field->handle}"] = $field->getValueForEmail($submissionValue, $notification, $submission);
        } else {
            // Try to convert as a simple string value, if not, fall back on email template
            try {
                $values["{$prefix}{$field->handle}"] = $field->getValueAsString($submissionValue, $submission);
            } catch (\Throwable $e) {
                if ($parsedContent) {
                    $values["{$prefix}{$field->handle}"] = $parsedContent;
                }
            }
        }

        return $values;
    }

    /**
     * @inheritdoc
     */
    private static function _expandArray($array)
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = self::_expandArray($value);
            }

            foreach (array_reverse(explode(".", $key)) as $key) {
                $value = [$key => $value];
            }

            $result = array_merge_recursive($result, $value);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    private static function _getCurrentUser($submission = null)
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        // If this is a front-end request, check the current user. This only really applies
        // if `useQueueForNotifications = false`.
        if ($currentUser && Craft::$app->getRequest()->getIsSiteRequest()) {
            return $currentUser;
        }

        // Is the "Collect User" enabled on the form?
        if ($submission && $submission->getUser()) {
            return $submission->getUser();
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public static function _getSite($submission)
    {
        // Get the current site, based on front-end requests first. This will fail for a queue job.
        // For front-end requests where we want to parse content, we must respect the current site.
        $currentSite = Craft::$app->getSites()->getCurrentSite();

        if ($currentSite) {
            return $currentSite;
        }

        // Otherwise, use the siteId for the submission
        $siteId = $submission->siteId ?? null;

        if ($siteId) {
            return Craft::$app->getSites()->getSiteById($siteId);
        }

        // If all else fails, the primary site.
        return Craft::$app->getSites()->getPrimarySite()->id;
    }
}
