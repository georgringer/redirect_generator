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
        $this->sendNotificationEmail($lines);
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

        if ($data['ok'] > 0 && $level >= 2) {
            $lines[] = \sprintf('%s redirects have been added!', $data['ok']);
        }
        if (!empty($data['error'])) {
            $lines[] = 'The following errors happened: ';
            foreach ($data['error'] as $errorCode => $messages) {
                $lines[] = 'Error code ' . $errorCode . ':';
                $lines[] = \array_merge($lines, $messages);
            }
        }
        if ($data['skipped'] > 0 && $level >= 1) {
            $lines[] = \sprintf('[Warning] %s redirects skipped because source is same as target', $data['skipped']);
        }
        if ($data['duplicates'] && $level >= 1) {
            $lines[] = \sprintf('[Warning] %s redirects skipped because of duplicates', $data['duplicates']);
        }

        if(!empty($lines)) {
            $this->sendNotificationEmail($lines);
        }
    }

    /**
     * Send a notification email with the given string array as body.
     * The email with be send in html and plain text.
     * Sender will be the default system sender.
     * Recipients are configured in the notification_email setting.
     * No E-Mail is sent if notification_email is not set or the lines array is empty.
     *
     * @param array $lines the text to set in the body of the email
     */
    protected function sendNotificationEmail(array $lines): void
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

        $mail->subject('Redirect Generator Import Notification');
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
    protected function throwableToArray(Throwable $error): array
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
