<?php

namespace ChurchCRM\Emails\notifications;

use ChurchCRM\Emails\BaseEmail;
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

        // Embed configured reminder images as CID attachments so email clients
        // can render them reliably without fetching remote URLs.
        $this->embedConfiguredImages();

        $this->mail->Subject = $this->subject;
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
        $this->mail->AltBody = $this->bodyText;
    }

    protected function getTemplateName(): string
    {
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

    private function embedConfiguredImages(): void
    {
        $tokenToCidPrefix = [
            'churchLogoUrl' => 'event-logo',
            'eventImage1Url' => 'event-image-1',
            'eventImage2Url' => 'event-image-2',
        ];

        foreach ($tokenToCidPrefix as $tokenName => $cidPrefix) {
            $url = trim((string) ($this->tokens[$tokenName] ?? ''));
            if ($url === '' || str_starts_with($url, 'cid:')) {
                continue;
            }

            $localPath = $this->resolveLocalPathFromUrl($url);
            if ($localPath === null || !is_file($localPath)) {
                continue;
            }

            // Keep CID simple/alphanumeric to maximize email client compatibility.
            $cid = sprintf('%s-%s', $cidPrefix, bin2hex(random_bytes(6)));
            $fileName = basename($localPath);
            if ($this->mail->addEmbeddedImage($localPath, $cid, $fileName)) {
                $cidUrl = 'cid:' . $cid;
                $this->replaceImageUrlWithCid($url, $cidUrl, $fileName);
                $this->tokens[$tokenName] = $cidUrl;
            }
        }
    }

    private function replaceImageUrlWithCid(string $url, string $cidUrl, string $fileName): void
    {
        // Replace literal URL.
        $this->bodyHtml = str_replace($url, $cidUrl, $this->bodyHtml);

        // Replace HTML-escaped URL forms frequently produced by sanitizers.
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->bodyHtml = str_replace($escapedUrl, $cidUrl, $this->bodyHtml);

        // Replace any img src containing the same filename (robust against
        // host changes, URL escaping, or template URL transformations).
        $quotedFile = preg_quote($fileName, '/');
        $this->bodyHtml = preg_replace(
            '/(<img\b[^>]*\bsrc\s*=\s*["\'])([^"\']*' . $quotedFile . '[^"\']*)(["\'])/i',
            '$1' . $cidUrl . '$3',
            $this->bodyHtml
        ) ?? $this->bodyHtml;
    }

    private function resolveLocalPathFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        if (isset($parts['host'])) {
            $configuredHost = (string) parse_url((string) SystemURLs::getURL(), PHP_URL_HOST);
            $candidateHost = strtolower($parts['host']);
            $configuredHost = strtolower($configuredHost);

            // Allow apex/www variants for the same configured domain.
            $allowedHosts = array_unique([
                $configuredHost,
                ltrim($configuredHost, 'www.'),
                'www.' . ltrim($configuredHost, 'www.'),
            ]);

            if (!in_array($candidateHost, $allowedHosts, true)) {
                return null;
            }
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path === '') {
            return null;
        }

        $rootPath = (string) SystemURLs::getRootPath();
        if ($rootPath !== '' && str_starts_with($path, $rootPath . '/')) {
            $path = substr($path, strlen($rootPath));
        }

        $documentRoot = rtrim((string) SystemURLs::getDocumentRoot(), '/');
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $documentRoot . $path;
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
