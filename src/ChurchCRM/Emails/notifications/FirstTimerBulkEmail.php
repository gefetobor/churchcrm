<?php

namespace ChurchCRM\Emails\notifications;

use ChurchCRM\Emails\BaseEmail;

class FirstTimerBulkEmail extends BaseEmail
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
    public function __construct(array $toAddresses, string $subject, string $bodyHtml, string $bodyText, array $tokens = [])
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
        return 'FirstTimerBulkEmail.html.twig';
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
