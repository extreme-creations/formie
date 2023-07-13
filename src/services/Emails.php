<?php
namespace verbb\formie\services;

use verbb\formie\Formie;
use verbb\formie\base\NestedFieldInterface;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\events\MailEvent;
use verbb\formie\events\MailRenderEvent;
use verbb\formie\fields\formfields\FileUpload;
use verbb\formie\helpers\Variables;
use verbb\formie\models\Notification;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\mail\Message;
use craft\volumes\Local;

use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;

use DateTime;
use Html2Text\Html2Text;
use Throwable;

class Emails extends Component
{
    // Constants
    // =========================================================================

    const EVENT_MODIFY_RENDER_VARIABLES = 'modifyRenderVariables';
    const EVENT_BEFORE_RENDER_EMAIL = 'beforeRenderEmail';
    const EVENT_AFTER_RENDER_EMAIL = 'afterRenderEmail';
    const EVENT_BEFORE_SEND_MAIL = 'beforeSendEmail';
    const EVENT_AFTER_SEND_MAIL = 'afterSendEmail';


    // Properties
    // =========================================================================

    private $_tempAttachments = [];


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function renderEmail(Notification $notification, Submission $submission)
    {
        // Set Craft to the site template mode
        $view = Craft::$app->getView();
        $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

        $form = $submission->getForm();

        $renderVariables = compact('notification', 'submission', 'form');

        $mailer = Craft::$app->getMailer();

        /** @var Message $newEmail */
        $newEmail = Craft::createObject(['class' => $mailer->messageClass, 'mailer' => $mailer]);

        $event = new MailEvent([
            'email' => $newEmail,
            'notification' => $notification,
            'submission' => $submission,
        ]);
        $this->trigger(self::EVENT_BEFORE_RENDER_EMAIL, $event);

        // Update the email from the event
        $newEmail = $event->email;

        $craftMailSettings = App::mailSettings();

        $fromEmail = Variables::getParsedValue((string)$notification->from, $submission, $form, $notification) ?: $craftMailSettings->fromEmail;
        $fromName = Variables::getParsedValue((string)$notification->fromName, $submission, $form, $notification) ?: $craftMailSettings->fromName;

        $fromEmail = $this->_getFilteredString($fromEmail);
        $fromName = $this->_getFilteredString($fromName);

        if ($fromEmail) {
            $newEmail->setFrom($fromEmail);
        }

        if ($fromName && $fromEmail) {
            $newEmail->setFrom([$fromEmail => $fromName]);
        }

        // To:
        try {
            $to = Variables::getParsedValue((string)$notification->getToEmail($submission), $submission, $form, $notification);
            $to = $this->_getParsedEmails($to);

            if ($to) {
                $newEmail->setTo($to);
            }
        } catch (Throwable $e) {
            $error = Craft::t('formie', 'Notification email parse error for “To: {value}”. Template error: “{message}” {file}:{line}', [
                'value' => $notification->getToEmail($submission),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['error' => $error, 'email' => $newEmail, 'exception' => $e];
        }

        if (!$newEmail->getTo()) {
            $error = Craft::t('formie', 'Notification email error. No recipient email address found.');

            return ['error' => $error, 'email' => $newEmail];
        }

        // BCC:
        if ($notification->bcc) {
            try {
                $bcc = Variables::getParsedValue((string)$notification->bcc, $submission, $form, $notification);
                $bcc = $this->_getParsedEmails($bcc);

                if ($bcc) {
                    $newEmail->setBcc($bcc);
                }
            } catch (Throwable $e) {
                $error = Craft::t('formie', 'Notification email parse error for “BCC: {value}”. Template error: “{message}” {file}:{line}', [
                    'value' => $notification->bcc,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return ['error' => $error, 'email' => $newEmail, 'exception' => $e];
            }
        }

        // CC:
        if ($notification->cc) {
            try {
                $cc = Variables::getParsedValue((string)$notification->cc, $submission, $form, $notification);
                $cc = $this->_getParsedEmails($cc);

                if ($cc) {
                    $newEmail->setCc($cc);
                }
            } catch (Throwable $e) {
                $error = Craft::t('formie', 'Notification email parse error for CC: {value}”. Template error: “{message}” {file}:{line}', [
                    'value' => $notification->cc,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return ['error' => $error, 'email' => $newEmail, 'exception' => $e];
            }
        }

        // Reply To:
        if ($notification->replyTo) {
            try {
                $replyTo = Variables::getParsedValue((string)$notification->replyTo, $submission, $form, $notification);
                $replyTo = $this->_getParsedEmails($replyTo);

                if ($replyTo) {
                    $newEmail->setReplyTo($replyTo);
                }
            } catch (Throwable $e) {
                $error = Craft::t('formie', 'Notification email parse error for ReplyTo: {value}”. Template error: “{message}” {file}:{line}', [
                    'value' => $notification->replyTo,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return ['error' => $error, 'email' => $newEmail, 'exception' => $e];
            }
        }

        // Subject:
        try {
            $subject = Variables::getParsedValue((string)$notification->subject, $submission, $form, $notification);
            $newEmail->setSubject($subject);
        } catch (Throwable $e) {
            $error = Craft::t('formie', 'Notification email parse error for Subject: {value}”. Template error: “{message}” {file}:{line}', [
                'value' => $notification->subject,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['error' => $error, 'email' => $newEmail, 'exception' => $e];
        }

        // Fetch the email template for the notification - if we're using one
        $emailTemplate = Formie::$plugin->getEmailTemplates()->getTemplateById($notification->templateId);

        // We always need a template, so log the error, but use the default built-in one.
        $templatePath = '';

        // Check to see if an email template has been set for the form
        if ($emailTemplate) {
            // Check to see the template is valid
            if (!$view->doesTemplateExist($emailTemplate->template)) {
                // Let's press on if we can't find the template - use the default
                Formie::error(Craft::t('formie', 'Notification email template does not exist at “{templatePath}”.', [
                    'templatePath' => $emailTemplate->template,
                ]));
            } else {
                $templatePath = $emailTemplate->template;
            }
        }

        // Render HTML body
        try {
            // Render the body content for the notification
            $parsedContent = Variables::getParsedValue($notification->getParsedContent(), $submission, $form, $notification);

            // Add it to our render variables
            $renderVariables['contentHtml'] = Template::raw($parsedContent);

            $event = new MailRenderEvent([
                'email' => $newEmail,
                'notification' => $notification,
                'submission' => $submission,
                'renderVariables' => $renderVariables,
            ]);
            $this->trigger(self::EVENT_MODIFY_RENDER_VARIABLES, $event);

            // Update the render variables
            $renderVariables = $event->renderVariables;

            if ($templatePath) {
                // We need to do a little more work here to deal with a template, if picked
                $oldTemplatesPath = $view->getTemplatesPath();
                $view->setTemplatesPath(Craft::$app->getPath()->getSiteTemplatesPath());
                $body = $view->renderTemplate($templatePath, $renderVariables);
                $view->setTemplatesPath($oldTemplatesPath);
            } else {
                $oldTemplateMode = $view->getTemplateMode();
                $view->setTemplateMode($view::TEMPLATE_MODE_CP);

                $templatePath = 'formie/_special/email-template';
                $body = $view->renderTemplate($templatePath, $renderVariables);

                $view->setTemplateMode($oldTemplateMode);
            }

            // Handle an empty string, something likely gone wrong.
            if ($body === '') {
                throw new Exception('Email body render returned empty. Please check your email content for invalid variables.');
            }

            $newEmail->setHtmlBody($body);

            // Auto-generate plain text for ease
            $plainTextBody = $this->_htmlToPlainText($body);

            $newEmail->setTextBody($plainTextBody);
        } catch (Throwable $e) {
            $error = Craft::t('formie', 'Notification email template parse error for “{value}”. Template error: “{message}” {file}:{line}', [
                'value' => $templatePath,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['error' => $error, 'email' => $newEmail, 'exception' => $e];
        }

        $event = new MailEvent([
            'email' => $newEmail,
            'notification' => $notification,
            'submission' => $submission,
        ]);
        $this->trigger(self::EVENT_AFTER_RENDER_EMAIL, $event);

        // Update the email from the event
        $newEmail = $event->email;

        return ['success' => true, 'email' => $newEmail];
    }

    /**
     * @inheritDoc
     */
    public function sendEmail(Notification $notification, Submission $submission, $queueJob = null)
    {
        // Render the email
        $emailRender = $this->renderEmail($notification, $submission);

        $newEmail = $emailRender['email'] ?? '';

        // Check if there were any errors. It's split this was so calling `render()` can return errors for previews
        // But in our case, we want to log the errors and bail.
        if (isset($emailRender['error']) && $emailRender['error']) {
            $error = $emailRender['error'];

            Formie::error($error);

            // Output the full exception if available
            if (isset($emailRender['exception']) && $emailRender['exception']) {
                Formie::error($emailRender['exception']);
            }

            // Save the sent notification, as failed
            Formie::$plugin->getSentNotifications()->saveSentNotification($submission, $notification, $newEmail, false, $error);

            return ['error' => $error];
        }

        // When in the context of a queue job, add some extra info
        if ($queueJob) {
            $queueJob->email = $this->_serializeEmail($newEmail);
        }

        // Attach any file uploads
        if ($notification->attachFiles) {
            // Grab all the file upload fields, including in nested fields
            if ($assets = $this->_getAssetsForSubmission($submission)) {
                $this->_attachAssetsToEmail($assets, $newEmail);
            }
        }

        // Attach any static assets
        if ($assets = $notification->getAssetAttachments()) {
            $this->_attachAssetsToEmail($assets, $newEmail);
        }

        // Attach any PDF templates
        if ($notification->attachPdf) {
            $this->_attachPdfToEmail($notification, $newEmail, $submission);
        }

        try {
            $event = new MailEvent([
                'email' => $newEmail,
                'notification' => $notification,
                'submission' => $submission,
            ]);
            $this->trigger(self::EVENT_BEFORE_SEND_MAIL, $event);

            // Update the email from the event
            $newEmail = $event->email;

            if (!$event->isValid) {
                $error = Craft::t('formie', 'Notification email “{notification}” for submission “{submission}” was cancelled by Formie.', [
                    'notification' => $notification->name,
                    'submission' => $submission->id ?? 'new',
                ]);

                Formie::error($error);
                Formie::error('Email payload: ' . Json::encode($this->_serializeEmail($newEmail)));

                // Save the sent notification, as failed
                Formie::$plugin->getSentNotifications()->saveSentNotification($submission, $notification, $newEmail, false, $error);

                return ['error' => $error];
            }

            if (!Craft::$app->getMailer()->send($newEmail)) {
                $error = Craft::t('formie', 'Notification email “{notification}” could not be sent for submission “{submission}”. The mailer service failed to send the notification: “{e}”.', [
                    'e' => $newEmail->error ?? '',
                    'notification' => $notification->name,
                    'submission' => $submission->id ?? 'new',
                ]);

                Formie::error($error);
                Formie::error('Email payload: ' . Json::encode($this->_serializeEmail($newEmail)));

                // Save the sent notification, as failed
                Formie::$plugin->getSentNotifications()->saveSentNotification($submission, $notification, $newEmail, false, $error);

                return ['error' => $error];
            }

            // Save the sent notification, as successful
            Formie::$plugin->getSentNotifications()->saveSentNotification($submission, $notification, $newEmail);
        } catch (Throwable $e) {
            $error = Craft::t('formie', 'Notification email “{notification}” could not be sent for submission “{submission}”. Error: {error} {file}:{line}', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'notification' => $notification->name,
                'submission' => $submission->id ?? 'new',
            ]);

            Formie::error($error);
            Formie::error('Email payload: ' . Json::encode($this->_serializeEmail($newEmail)));

            // Save the sent notification, as failed
            Formie::$plugin->getSentNotifications()->saveSentNotification($submission, $notification, $newEmail, false, $error);

            return ['error' => $error];
        }

        // Raise an 'afterSendEmail' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SEND_MAIL)) {
            $this->trigger(self::EVENT_AFTER_SEND_MAIL, new MailEvent([
                'email' => $newEmail,
                'notification' => $notification,
                'submission' => $submission,
            ]));
        }

        // Delete any leftover attachments
        foreach ($this->_tempAttachments as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $this->_tempAttachments = [];

        return ['success' => true];
    }

    /**
     * @inheritDoc
     */
    public function sendFailAlertEmail(Notification $notification, Submission $submission, $emailResponse)
    {
        $settings = Formie::$plugin->getSettings();

        // Check our settings are all in order first.
        if (!$settings->sendEmailAlerts) {
            $error = Craft::t('formie', 'Fail alert not configured to send.');

            Formie::log($error);

            return ['error' => $error];
        }

        if (!$settings->validate()) {
            $error = Craft::t('formie', 'Fail alert settings are invalid: “{errors}”.', [
                'errors' => Json::encode($settings->getErrors()),
            ]);

            Formie::error($error);

            return ['error' => $error];
        }

        $form = $submission->getForm();

        $renderVariables = compact('notification', 'submission', 'form', 'emailResponse');

        foreach ($settings->alertEmails as $alertEmail) {
            try {
                $mail = Craft::$app->getMailer()
                    ->composeFromKey('formie_failed_notification', $renderVariables)
                    ->setTo($alertEmail[1]);

                $mail->send();
            } catch (Throwable $e) {
                $error = Craft::t('formie', 'Failure alert email could not be sent for submission “{submission}”. Error: {error} {file}:{line}', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'submission' => $submission->id ?? 'new',
                ]);

                Formie::error($error);

                return ['error' => $error];
            }
        }
    }


    // Private Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    private function _htmlToPlainText($html)
    {
        $html = new Html2Text($html);

        return $html->getText();
    }

    /**
     * @inheritDoc
     */
    private function _getFilteredString($string)
    {
        $string = trim(App::parseEnv(trim($string)));

        // Strip out any emoji's
        $string = trim(StringHelper::replaceMb4($string, ''));

        return $string;
    }

    /**
     * @inheritDoc
     */
    private function _getParsedEmails($emails)
    {
        $emails = str_replace(';', ',', $emails);
        $emails = preg_split('/[\s,]+/', $emails);
        $emailsEnv = [];

        foreach (array_filter($emails) as $email) {
            // Prevent non-utf characters sneaking in.
            $email = StringHelper::convertToUtf8($email);

            // Also check for control characters, which aren't included above
            $email = preg_replace('/[^\PC\s]/u', '', $email);

            $emailsEnv[] = trim(App::parseEnv(trim($email)));
        }

        $emailsEnv = array_filter($emailsEnv);

        return $emailsEnv;
    }

    /**
     * @inheritDoc
     */
    private function _getAssetsForSubmission($element)
    {
        $assets = [];

        foreach ($element->getFieldLayout()->getFields() as $field) {
            if (get_class($field) === FileUpload::class) {
                $value = $element->getFieldValue($field->handle);

                if ($value instanceof AssetQuery) {
                    $assets = array_merge($assets, $value->all());
                }
            }

            // Separate check for nested fields (repeater/group), fetch the element and try again
            if ($field instanceof NestedFieldInterface) {
                $query = $element->getFieldValue($field->handle);

                if ($query) {
                    foreach ($query->all() as $nestedElement) {
                        $assets = array_merge($assets, $this->_getAssetsForSubmission($nestedElement));
                    }
                }
            }
        }

        return $assets;
    }

    /**
     * @inheritDoc
     */
    private function _attachAssetsToEmail($assets, Message $message)
    {
        foreach ($assets as $asset) {
            $path = '';

            // Check for local assets - they're easy
            if (get_class($asset->getVolume()) === Local::class) {
                $path = $this->_getFullAssetFilePath($asset);
            } else {
                // Make a local copy of the file, and store so we can delete
                $this->_tempAttachments[] = $path = $asset->getCopyOfFile();
            }

            // Check for asset size, 0kb files are technically invalid (or at least spammy)
            if (!$asset->size) {
                Formie::log('Not attaching “' . $asset->filename . '” due to invalid file size: ' . $asset->size . '.');

                continue;
            }

            // Check if the asset it over 15mb (a reasonable threshold)
            if (($asset->size / 1000000) > 15) {
                Formie::log('Not attaching “' . $asset->filename . '” due to large file size: ' . $asset->size . '.');

                continue;
            }

            if ($path) {
                $message->attach($path, ['fileName' => $asset->filename]);
            }

            // Fix a bug with SwiftMailer where setting an attachment clears out the body of the email!
            $this->_fixSwiftMailerBody($message);
        }
    }

    /**
     * @inheritDoc
     */
    private function _fixSwiftMailerBody($message)
    {
        // Fix a bug with SwiftMailer where setting an attachment clears out the body of the email!
        $textBody = $message->getSwiftMessage()->getBody();
        $htmlBody = $message->getSwiftMessage()->getBody();
        $children = $message->getSwiftMessage()->getChildren();

        // Getting the content from an email is a little more involved...
        if (!$htmlBody && $children) {
            foreach ($children as $child) {
                if ($child->getContentType() == 'text/html') {
                    $htmlBody = $child->getBody();
                } else if ($child->getContentType() == 'text/plain') {
                    $textBody = $child->getBody();
                }
            }
        }

        $message->setHtmlBody($htmlBody);
        $message->setTextBody($textBody);
    }

    /**
     * @inheritDoc
     */
    private function _getFullAssetFilePath(Asset $asset): string
    {
        $path = $asset->getVolume()->getRootPath() . DIRECTORY_SEPARATOR . $asset->getPath();

        return FileHelper::normalizePath($path);
    }

    /**
     * @inheritDoc
     */
    private function _serializeEmail($email)
    {
        $htmlBody = $email->getSwiftMessage()->getBody();
        $children = $email->getSwiftMessage()->getChildren();

        // Getting the content from an email is a little more involved...
        if (!$htmlBody && $children) {
            foreach ($children as $child) {
                if ($child->getContentType() == 'text/html') {
                    $htmlBody = $child->getBody();
                }
            }
        }

        return [
            'charset' => $email->getCharset(),
            'from' => $email->getFrom(),
            'replyTo' => $email->getReplyTo(),
            'to' => $email->getTo(),
            'cc' => $email->getCc(),
            'bcc' => $email->getBcc(),
            'subject' => $email->getSubject(),
            'body' => $htmlBody,
        ];
    }

    /**
     * @inheritDoc
     */
    private function _attachPdfToEmail(Notification $notification, Message $message, Submission $submission)
    {
        // Render the PDF template
        $template = $notification->getPdfTemplate();

        $pdf = Formie::$plugin->getPdfTemplates()->renderPdf($template, $submission, $notification);

        if (!$pdf) {
            return;
        }

        // Save it in a temp location so we can attach it
        $pdfPath = Assets::tempFilePath('pdf');
        file_put_contents($pdfPath, $pdf);

        if (!$pdfPath) {
            return;
        }

        $variables = [
            'submission' => $submission,
            'notification' => $notification,
        ];

        // Generate the filename correctly.
        $filenameFormat = $template->filenameFormat ?? 'Submission-{submission.id}';
        $fileName = Craft::$app->getView()->renderObjectTemplate($filenameFormat, $variables);

        $message->attach($pdfPath, ['fileName' => $fileName . '.pdf', 'contentType' => 'application/pdf']);

        // Fix a bug with SwiftMailer where setting an attachment clears out the body of the email!
        $this->_fixSwiftMailerBody($message);

        // Store for later
        $this->_tempAttachments[] = $pdfPath;
    }
}
