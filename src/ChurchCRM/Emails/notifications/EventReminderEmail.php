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
     * @var array<string, string>
     */
    private array $inlineImageCache = [];

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

        $this->prepareInlineImages();
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
        return array_merge($this->getCommonTokens(), $this->tokens, [
            'bodyHtml' => $this->bodyHtml,
            'bodyText' => $this->bodyText,
        ]);
    }

    private function prepareInlineImages(): void
    {
        $churchLogoOriginal = trim((string) ($this->tokens['churchLogoUrl'] ?? ''));
        if ($churchLogoOriginal === '') {
            $churchLogoOriginal = trim((string) SystemConfig::getValue('sEventReminderLogoUrl'));
        }
        $resolvedChurchLogo = $this->resolveInlineImageUrl($churchLogoOriginal, 'event-church-logo');
        if ($resolvedChurchLogo !== '') {
            $this->tokens['churchLogoUrl'] = $resolvedChurchLogo;
        }
        if ($churchLogoOriginal !== '' && $resolvedChurchLogo !== '' && $resolvedChurchLogo !== $churchLogoOriginal) {
            $this->bodyHtml = str_replace($churchLogoOriginal, $resolvedChurchLogo, $this->bodyHtml);
        }

        $brandLogoOriginal = trim((string) ($this->tokens['brandLogoUrl'] ?? ''));
        if ($brandLogoOriginal === '') {
            $brandLogoOriginal = $resolvedChurchLogo !== '' ? $resolvedChurchLogo : $churchLogoOriginal;
        }
        if ($brandLogoOriginal === '') {
            $brandLogoOriginal = '/Images/logo-churchcrm-350.jpg';
        }
        $resolvedBrandLogo = $this->resolveInlineImageUrl($brandLogoOriginal, 'event-brand-logo');
        if ($resolvedBrandLogo !== '') {
            $this->tokens['brandLogoUrl'] = $resolvedBrandLogo;
        }
        if ($brandLogoOriginal !== '' && $resolvedBrandLogo !== '' && $resolvedBrandLogo !== $brandLogoOriginal) {
            $this->bodyHtml = str_replace($brandLogoOriginal, $resolvedBrandLogo, $this->bodyHtml);
        }

        $eventImageOriginal = trim((string) ($this->tokens['eventImageUrl'] ?? ''));
        if ($eventImageOriginal !== '') {
            $resolvedEventImage = $this->resolveInlineImageUrl($eventImageOriginal, 'event-main-image');
            if ($resolvedEventImage !== '') {
                $this->tokens['eventImageUrl'] = $resolvedEventImage;
            }
            if ($resolvedEventImage !== '' && $resolvedEventImage !== $eventImageOriginal) {
                $this->bodyHtml = str_replace($eventImageOriginal, $resolvedEventImage, $this->bodyHtml);
            }
        }
    }

    private function resolveInlineImageUrl(string $configuredUrl, string $cidPrefix): string
    {
        $configuredUrl = trim($configuredUrl);
        if ($configuredUrl === '') {
            return '';
        }
        if (str_starts_with($configuredUrl, 'cid:')) {
            return $configuredUrl;
        }
        if (isset($this->inlineImageCache[$configuredUrl])) {
            return $this->inlineImageCache[$configuredUrl];
        }

        $baseUrl = rtrim((string) SystemURLs::getURL(), '/');
        $resolvedUrl = $configuredUrl;
        if (!preg_match('/^(https?:|cid:)/i', $resolvedUrl)) {
            $resolvedUrl = $baseUrl . '/' . ltrim($resolvedUrl, '/');
        }

        if (isset($this->inlineImageCache[$resolvedUrl])) {
            return $this->inlineImageCache[$resolvedUrl];
        }

        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $parts = parse_url($resolvedUrl);
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
                    $cid = $cidPrefix . '-' . substr(sha1($imagePath), 0, 10);
                    $this->mail->addEmbeddedImage($imagePath, $cid);
                    $cidUrl = 'cid:' . $cid;
                    $this->inlineImageCache[$configuredUrl] = $cidUrl;
                    $this->inlineImageCache[$resolvedUrl] = $cidUrl;

                    return $cidUrl;
                }
            }
        }

        $this->inlineImageCache[$configuredUrl] = $resolvedUrl;
        $this->inlineImageCache[$resolvedUrl] = $resolvedUrl;

        return $resolvedUrl;
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
