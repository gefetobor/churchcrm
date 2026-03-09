<?php

namespace ChurchCRM\Emails\notifications;

use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class EventReminderEmail extends BaseEmail
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
        return 'UnifiedNotificationEmail.html.twig';
    }

    /**
     * @return array<string, string>
     */
    public function getTokens(): array
    {
        $brandLogoUrl = trim((string) ($this->tokens['brandLogoUrl'] ?? ''));
        if ($brandLogoUrl === '') {
            $brandLogoUrl = trim((string) ($this->tokens['churchLogoUrl'] ?? ''));
        }
        if ($brandLogoUrl === '') {
            $brandLogoUrl = trim((string) SystemConfig::getValue('sEventReminderLogoUrl'));
        }
        $this->tokens['brandLogoUrl'] = $this->resolveBrandLogoUrl($brandLogoUrl);

        return array_merge($this->getCommonTokens(), $this->tokens, [
            'bodyHtml' => $this->bodyHtml,
            'bodyText' => $this->bodyText,
        ]);
    }

    private function resolveBrandLogoUrl(string $configuredUrl): string
    {
        $baseUrl = rtrim((string) SystemURLs::getURL(), '/');
        $fallbackRelative = '/Images/logo-churchcrm-350.jpg';

        if ($configuredUrl === '') {
            $fallbackPath = SystemURLs::getDocumentRoot() . $fallbackRelative;
            if (is_file($fallbackPath)) {
                $this->mail->addEmbeddedImage($fallbackPath, 'event-brand-logo-fallback');

                return 'cid:event-brand-logo-fallback';
            }

            return $baseUrl . $fallbackRelative;
        }

        if (!preg_match('/^(https?:|cid:)/i', $configuredUrl)) {
            $configuredUrl = $baseUrl . '/' . ltrim($configuredUrl, '/');
        }

        if (str_starts_with($configuredUrl, 'cid:')) {
            return $configuredUrl;
        }

        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $parts = parse_url($configuredUrl);
        if ($parts !== false && isset($parts['path'])) {
            $path = $parts['path'];
            $isLocalHost = isset($parts['host']) && in_array($parts['host'], ['localhost', '127.0.0.1'], true);
            $isSameHost = isset($parts['host']) && !empty($baseHost) && $parts['host'] === $baseHost;
            if (!str_starts_with($path, '/Images/') && str_contains($path, '/Images/')) {
                $path = substr($path, strpos($path, '/Images/'));
            }
            if (str_starts_with($path, '/Images/') && ($isLocalHost || $isSameHost || !isset($parts['host']))) {
                $imagePath = SystemURLs::getDocumentRoot() . $path;
                if (is_file($imagePath)) {
                    $this->mail->addEmbeddedImage($imagePath, 'event-brand-logo');

                    return 'cid:event-brand-logo';
                }
            }
        }

        return $configuredUrl;
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
