<?php

declare(strict_types=1);

namespace ThreadMesh\Dashboard\Presenters;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use RuntimeException;
use ThreadMesh\Dashboard\Model\EmailHtmlSanitizer;
use ThreadMesh\Dashboard\Model\ThreadMeshApi;

final class DashboardPresenter extends Presenter
{
    private const IMPORTANCE = ['low', 'normal', 'high', 'critical'];

    public function __construct(
        private readonly ThreadMeshApi $api,
        private readonly EmailHtmlSanitizer $sanitizer,
    )
    {
        parent::__construct();
    }

    public function actionDefault(
        int $days = 7,
        ?string $importance = null,
        ?string $assessed = null,
        ?string $requiresAction = null,
    ): void {
        $days = in_array($days, [1, 7, 14, 30], true) ? $days : 7;
        $filters = [
            'since' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->sub(new DateInterval('P' . $days . 'D'))
                ->format(DATE_ATOM),
            'limit' => 200,
        ];
        if ($importance !== null && in_array($importance, self::IMPORTANCE, true)) {
            $filters['importance'] = $importance;
        }
        if ($assessed === 'true' || $assessed === 'false') {
            $filters['assessed'] = $assessed;
        }
        if ($requiresAction === 'true' || $requiresAction === 'false') {
            $filters['requiresAction'] = $requiresAction;
        }

        $this->template->days = $days;
        $this->template->importance = $importance;
        $this->template->assessed = $assessed;
        $this->template->requiresAction = $requiresAction;
        $this->template->emails = [];
        $this->template->error = null;
        try {
            $this->template->emails = array_map($this->decorate(...), $this->api->mailbox($filters));
        } catch (RuntimeException $error) {
            $this->template->error = $error->getMessage();
        }
    }

    public function actionDetail(string $id): void
    {
        try {
            $this->template->email = $this->decorate($this->api->email($id));
        } catch (RuntimeException $error) {
            $this->error($error->getMessage(), 502);
        }
    }

    public function actionContent(string $id): void
    {
        try {
            $email = $this->api->email($id);
        } catch (RuntimeException $error) {
            $this->error($error->getMessage(), 502);
        }
        $html = $email['html_body'] ?? null;
        if (!is_string($html) || trim($html) === '') {
            $text = is_string($email['text_body'] ?? null) ? $email['text_body'] : '';
            $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font:16px/1.55 system-ui,sans-serif;padding:1.5rem;color:#212529;white-space:pre-wrap}</style></head><body>'
                . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</body></html>';
        } else {
            $html = $this->sanitizer->sanitize($html);
        }

        $response = $this->getHttpResponse();
        $response->setContentType('text/html', 'UTF-8');
        $response->setHeader('Cache-Control', 'no-store');
        $response->setHeader('Content-Security-Policy', "default-src 'none'; img-src data: cid:; style-src 'unsafe-inline'; font-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
        $response->setHeader('Referrer-Policy', 'no-referrer');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $this->sendResponse(new TextResponse($html));
    }

    /**
     * @param array<string, mixed> $email
     * @return array<string, mixed>
     */
    private function decorate(array $email): array
    {
        $importance = is_string($email['importance'] ?? null) ? $email['importance'] : 'unassessed';
        $email['importance_key'] = $importance;
        $email['importance_label'] = match ($importance) {
            'critical' => 'Kritická',
            'high' => 'Vysoká',
            'normal' => 'Běžná',
            'low' => 'Nízká',
            default => 'Nevyhodnocená',
        };
        $email['importance_class'] = match ($importance) {
            'critical' => 'danger',
            'high' => 'warning',
            'normal' => 'primary',
            'low' => 'secondary',
            default => 'light',
        };

        return $email;
    }
}
