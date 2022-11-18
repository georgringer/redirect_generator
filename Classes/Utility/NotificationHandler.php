<?php

namespace GeorgRinger\RedirectGenerator\Utility;

use Symfony\Component\Mime\Address;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;

class NotificationHandler
{
    public const ERROR_MESSAGE = 'The following errors happened:';
    public const IMPORT_SUCCESS_MESSAGE = '%s redirects have been added!';
    public const IMPORT_SKIPPED_MESSAGE = '%s redirects skipped because source is same as target!';
    public const IMPORT_DUPLICATES_MESSAGE = '%s redirects skipped because of duplicates!';

    /** @var ExtensionConfiguration|null */
    protected $extensionConfiguration = null;

    public function __construct(
        ExtensionConfiguration $extensionConfiguration
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
    }

    /**
     * Converts the throwable to a string array and sends it as notification
     *
     * @param Throwable $error the throwable to send
     */
    public function sendThrowableAsEmail(Throwable $error): void
    {
        $lines = $this->throwableToArray($error);
        $this->sendNotificationEmail('Unexpected error in redirect generator', $lines);
    }

    /**
     * Sends the result of the export command as email.
     * The email content is based on the notification_level setting:
     * - Errors are always added
     * - Ok messagea are added if level is info
     *
     * @param array $data the result data of the export
     */
    public function sendExportResultAsEmail(array $data): void
    {
        $level = (int)$this->extensionConfiguration->get('redirect_generator', 'notification_level');
        $lines = [];

        if (!empty($data['ok']) && $level >= 2) {
            $lines[] = $data['ok'];
        }
        if (!empty($data['error'])) {
            $lines[] = static::ERROR_MESSAGE;
            $lines[] = $data['error'];
        }

        if(!empty($lines)) {
            $this->sendNotificationEmail('Redirect generator export notification', $lines);
        }
    }

    /**
     * Sends the result of the import command as email.
     * The email content is based on the notification_level setting:
     * - Errors are always added
     * - Skipped and duplicate information is added if level is at least warning
     * - Ok messagea are added if level is info
     *
     * @param array $data the result data of the import
     */
    public function sendImportResultAsEmail(array $data): void
    {
        $level = (int)$this->extensionConfiguration->get('redirect_generator', 'notification_level');
        $lines = [];

        if (!empty($data['ok']) && $level >= 2) {
            $lines[] = \sprintf('[Ok] ' . self::IMPORT_SUCCESS_MESSAGE, \count($data['ok']));
        }
        if (!empty($data['error'])) {
            $lines[] = '[Error] ' . self::ERROR_MESSAGE;
            foreach ($data['error'] as $errorCode => $messages) {
                $lines[] = 'Error code ' . $errorCode . ':';
                $lines = \array_merge($lines, $messages);
            }
        }
        if (!empty($data['skipped']) && $level >= 1) {
            $lines[] = \sprintf('[Warning] ' .  self::IMPORT_SKIPPED_MESSAGE, \count($data['skipped']));
        }
        if (!empty($data['duplicates']) && $level >= 1) {
            $lines[] = \sprintf('[Warning] ' . self::IMPORT_DUPLICATES_MESSAGE, \count($data['duplicates']));
        }

        if(!empty($lines)) {
            $this->sendNotificationEmail('Redirect Generator import notification', $lines);
        }
    }

    /**
     * Send a notification email with the given string array as body.
     * The email with be send in html and plain text.
     * Sender will be the default system sender.
     * Recipients are configured in the notification_email setting.
     * No E-Mail is sent if notification_email is not set or the lines array is empty.
     *
     * @param string $subject The subject of the e-mail
     * @param array $lines the text to set in the body of the email
     */
    protected function sendNotificationEmail(string $subject, array $lines): void
    {
        $recipients = \explode(',', $this->extensionConfiguration->get('redirect_generator', 'notification_email') ?? '');

        if (empty($recipients)) {
            return;
        }

        $from = MailUtility::getSystemFrom();
        /** @var MailMessage */
        $mail = GeneralUtility::makeInstance(MailMessage::class);

        $mail->setFrom($from);
        foreach ($recipients as $recipient) {
            $mail->addTo(new Address(\trim($recipient)));
        }

        $mail->subject($subject);
        $mail->text(\implode('\r\n', $lines));
        $mail->html('<pre>' . \implode('<br/>', $lines) . '</pre>');
        $mail->send();
    }

    /**
     * Converts the given throwable to an array of strings for logging purposes
     *
     * @param Throwable $error the throwable to convert
     * @return array the message and the stacktrace as a string array (including all prevoous throwables)
     */
    public function throwableToArray(Throwable $error): array
    {
        $result = [];
        $result[] = $error->getMessage();
        $result[] = $error->getTraceAsString();
        if (!empty($error->getPrevious())) {
            $result = \array_merge($result, $this->throwableToArray($error->getPrevious()));
        }

        return $result;
    }
}
