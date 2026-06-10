<?php

namespace littlemissrobot\watson\services;

use Craft;
use littlemissrobot\watson\models\ViolationModel;
use littlemissrobot\watson\records\ViolationRecord;
use littlemissrobot\watson\Watson;
use yii\base\Component;

class ViolationService extends Component
{
    private const VALID_STATUSES = ['new', 'resolved', 'ignored'];

    // -------------------------------------------------------------------------
    // Persist
    // -------------------------------------------------------------------------

    public function persistReports(
        string $rawBody,
        string $contentType,
        ?string $userAgent,
        ?string $ip,
        ?string $referrer,
    ): array {
        $decoded = $this->decodeBody($rawBody);
        $reports = $this->normalizeReports($decoded, $contentType);

        $ignoredDirectives = $this->getIgnoredDirectives();

        $written = 0;
        foreach ($reports as $report) {
            // Skip if the effective directive is in the ignore list
            $directive = $report['effectiveDirective'] ?? $report['violatedDirective'] ?? null;
            if ($directive && in_array($directive, $ignoredDirectives, true)) {
                continue;
            }

            $record = new ViolationRecord();
            $record->kind = $report['kind'] ?? 'unparsed';
            $record->disposition = $report['disposition'] ?? null;
            $record->effectiveDirective = $report['effectiveDirective'] ?? $report['violatedDirective'] ?? null;
            $record->blockedUri = $report['blockedUri'] ?? null;
            $record->documentUri = $report['documentUri'] ?? null;
            $record->rawPayload = json_encode($report, JSON_UNESCAPED_SLASHES);
            $record->userAgent = $report['userAgent'] ?? $userAgent;
            $record->referrer = $report['referrer'] ?? $referrer;
            $record->ip = $ip;
            $record->status = 'new';

            if (!$record->save()) {
                Craft::error(
                    'Watson: failed to save violation: ' . json_encode($record->getErrors()),
                    __METHOD__
                );
            } else {
                $written++;
            }
        }

        // If nothing was parseable, store an unparsed record
        if (count($reports) === 0) {
            $record = new ViolationRecord();
            $record->kind = 'unparsed';
            $record->rawPayload = json_encode([
                'rawBody' => $rawBody,
                'contentType' => $contentType,
            ], JSON_UNESCAPED_SLASHES);
            $record->userAgent = $userAgent;
            $record->referrer = $referrer;
            $record->ip = $ip;
            $record->status = 'new';

            if ($record->save()) {
                $written = 1;
            }
        }

        return [
            'received' => count($reports),
            'written' => $written,
        ];
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    public function getViolations(array $filters = [], int $page = 1, int $perPage = 50, string $sort = 'dateCreated', string $dir = 'desc'): array
    {
        $allowedSorts = ['dateCreated', 'status', 'effectiveDirective', 'blockedUri', 'documentUri'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'dateCreated';
        }

        $query = ViolationRecord::find()->orderBy([$sort => $dir === 'asc' ? SORT_ASC : SORT_DESC]);

        if (!empty($filters['effectiveDirective'])) {
            $query->andWhere(['like', 'effectiveDirective', $filters['effectiveDirective']]);
        }

        if (!empty($filters['blockedUri'])) {
            $query->andWhere(['like', 'blockedUri', $filters['blockedUri']]);
        }

        if (!empty($filters['documentUri'])) {
            $query->andWhere(['like', 'documentUri', $filters['documentUri']]);
        }

        if (!empty($filters['status'])) {
            $query->andWhere(['status' => $filters['status']]);
        }

        if (!empty($filters['dateFrom'])) {
            $query->andWhere(['>=', 'dateCreated', $filters['dateFrom'] . ' 00:00:00']);
        }

        if (!empty($filters['dateTo'])) {
            $query->andWhere(['<=', 'dateCreated', $filters['dateTo'] . ' 23:59:59']);
        }

        $total = (int) $query->count();
        $totalPages = (int) ceil($total / $perPage);

        $records = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->asArray()
            ->all();

        $violations = array_map(
            static fn(array $row) => ViolationModel::fromRecord($row),
            $records
        );

        return [
            'violations' => $violations,
            'total' => $total,
            'totalPages' => max($totalPages, 1),
        ];
    }

    public function summarize(int $limit = 20, string $sort = 'count', string $dir = 'desc'): array
    {
        $allowedSorts = ['count', 'effectiveDirective', 'blockedUri', 'documentUri'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'count';
        }

        return ViolationRecord::find()
            ->select([
                'effectiveDirective',
                'blockedUri',
                'documentUri',
                'COUNT(*) AS count',
            ])
            ->andWhere(['!=', 'status', 'ignored'])
            ->groupBy(['effectiveDirective', 'blockedUri', 'documentUri'])
            ->orderBy([$sort => $dir === 'asc' ? SORT_ASC : SORT_DESC])
            ->limit($limit)
            ->asArray()
            ->all();
    }

    // -------------------------------------------------------------------------
    // Status
    // -------------------------------------------------------------------------

    public function updateStatus(int|array $ids, string $status): int
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            Craft::error("Watson: invalid status '{$status}'", __METHOD__);
            return 0;
        }

        $ids = (array) $ids;
        if (empty($ids)) {
            return 0;
        }

        return (int) ViolationRecord::updateAll(
            ['status' => $status],
            ['id' => $ids]
        );
    }

    // -------------------------------------------------------------------------
    // Purge
    // -------------------------------------------------------------------------

    public function deleteByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        return (int) ViolationRecord::deleteAll(['id' => $ids]);
    }

    public function purge(?int $days = null): int
    {
        if ($days === null) {
            $days = Watson::getInstance()->getSettings()->retentionDays;
        }

        $cutoff = (new \DateTime())
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s');

        return (int) ViolationRecord::deleteAll(['<', 'dateCreated', $cutoff]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getIgnoredDirectives(): array
    {
        $raw = Watson::getInstance()->getSettings()->ignoredDirectives;

        // The editable table stores rows as ['col1' => 'value'] arrays
        if (!empty($raw) && is_array($raw[0] ?? null)) {
            return array_filter(array_column($raw, 'col1'));
        }

        // Simple string array fallback
        return array_filter((array) $raw);
    }

    private function decodeBody(string $rawBody): mixed
    {
        if ($rawBody === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Normalises legacy report-uri and modern Reporting API formats into a
     * flat array of report arrays. Ported directly from CspReportService.
     */
    private function normalizeReports(mixed $decoded, string $contentType): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        // Legacy report-uri format: {"csp-report": {...}}
        if (isset($decoded['csp-report']) && is_array($decoded['csp-report'])) {
            return [[
                'kind' => 'csp-report',
                'documentUri' => $decoded['csp-report']['document-uri'] ?? null,
                'referrer' => $decoded['csp-report']['referrer'] ?? null,
                'violatedDirective' => $decoded['csp-report']['violated-directive'] ?? null,
                'effectiveDirective' => $decoded['csp-report']['effective-directive'] ?? null,
                'originalPolicy' => $decoded['csp-report']['original-policy'] ?? null,
                'blockedUri' => $decoded['csp-report']['blocked-uri'] ?? null,
                'statusCode' => $decoded['csp-report']['status-code'] ?? null,
                'scriptSample' => $decoded['csp-report']['script-sample'] ?? null,
                'disposition' => str_contains($contentType, 'report-only') ? 'reporting' : null,
                'body' => $decoded['csp-report'],
            ]];
        }

        // Modern Reporting API format: [{type: "csp-report", body: {...}}, ...]
        if (array_is_list($decoded)) {
            $reports = [];

            foreach ($decoded as $report) {
                if (!is_array($report)) {
                    continue;
                }

                $body = is_array($report['body'] ?? null) ? $report['body'] : [];
                $reports[] = [
                    'kind' => $report['type'] ?? 'report',
                    'age' => $report['age'] ?? null,
                    'type' => $report['type'] ?? null,
                    'url' => $report['url'] ?? null,
                    'userAgent' => $report['user_agent'] ?? null,
                    'group' => $report['group'] ?? null,
                    'documentUri' => $body['documentURL'] ?? null,
                    'blockedUri' => $body['blockedURL'] ?? null,
                    'effectiveDirective' => $body['effectiveDirective'] ?? null,
                    'originalPolicy' => $body['originalPolicy'] ?? null,
                    'referrer' => $body['referrer'] ?? null,
                    'statusCode' => $body['statusCode'] ?? null,
                    'scriptSample' => $body['sample'] ?? null,
                    'disposition' => $body['disposition'] ?? null,
                    'body' => $body,
                ];
            }

            return $reports;
        }

        // Unknown JSON object
        return [[
            'kind' => 'json',
            'body' => $decoded,
        ]];
    }
}
