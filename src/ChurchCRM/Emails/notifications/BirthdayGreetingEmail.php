<?php

namespace ChurchCRM\Emails\notifications;

use ChurchCRM\Emails\BaseEmail;

class BirthdayGreetingEmail extends BaseEmail
{
    private string $subject;
    private string $bodyHtml;
    private string $bodyText;
    /**
     * @var array<string, string>
     */
    private array $tokens;

    /**
     * @param string[] $toAddresses
     * @param array<string, string> $tokens
     */
    public function __construct(array $toAddresses, string $subject, string $bodyHtml, string $bodyText, array $tokens)
    {
        $this->subject = $subject;
        $this->bodyHtml = $bodyHtml;
        $this->bodyText = $bodyText;
        $this->tokens = $tokens;

        parent::__construct($toAddresses);

        $this->mail->Subject = $this->subject;
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
        $this->mail->AltBody = $this->bodyText;
    }

    protected function getTemplateName(): string
    {
        // Reuse the same wrapper/template as event reminders to keep
        // message structure consistent for deliverability behavior.
        return 'EventReminderEmail.html.twig';
    }

    /**
     * @return array<string, string>
     */
    public function getTokens(): array
    {
        return array_merge($this->getCommonTokens(), $this->tokens, [
            'bodyHtml' => $this->bodyHtml,
            'bodyText' => $this->bodyText,
        ]);
    }

    protected function getFullURL(): string
    {
        return '';
    }

    protected function getButtonText(): string
    {
        return '';
    }
}
