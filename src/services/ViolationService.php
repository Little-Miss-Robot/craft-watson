<?php

namespace littlemissrobot\watson\services;

use Carbon\Carbon;
use Craft;
use craft\helpers\Db;
use littlemissrobot\watson\models\ViolationModel;
use littlemissrobot\watson\records\ViolationRecord;
use littlemissrobot\watson\Watson;
use yii\base\Component;

/**
 * Violation service.
 *
 * Handles storing, querying, and managing CSP violation records.
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
class ViolationService extends Component
{
    // =========================================================================
    // Const Properties
    // =========================================================================

    /**
     * @var string[]
     */
    private const VALID_STATUSES = ['new', 'resolved', 'ignored'];

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * Decodes and persists one or more CSP reports from a raw request body.
     *
     * Supports both the legacy `application/csp-report` format and the modern
     * Reporting API `application/reports+json` format. If the body cannot be
     * parsed, an unparsed record is stored so no report is silently lost.
     *
     * @param string      $rawBody
     * @param string      $contentType
     * @param string|null $userAgent
     * @param string|null $ip
     * @param string|null $referrer
     * @return array{received: int, written: int}
     */
    public function persistReports(
        string $rawBody,
        string $contentType,
        ?string $userAgent,
        ?string $ip,
        ?string $referrer,
    ): array {
        $decoded = $this->_decodeBody($rawBody);
        $reports = $this->_normalizeReports($decoded, $contentType);

        $ignoredDirectives = $this->_getIgnoredDirectives();

        $written = 0;
        foreach ($reports as $report) {
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
            $record->rawPayload = json_encode($report, JSON_UNESCAPED_SLASHES) ?: '{}';
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

        // If nothing was parseable, store an unparsed record so no report is silently dropped
        if (count($reports) === 0) {
            $record = new ViolationRecord();
            $record->kind = 'unparsed';
            $record->rawPayload = json_encode([
                'rawBody' => $rawBody,
                'contentType' => $contentType,
            ], JSON_UNESCAPED_SLASHES) ?: '{}';
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

    /**
     * Returns a paginated list of violations, optionally filtered and sorted.
     *
     * @param array<string, string> $filters
     * @param int    $page
     * @param int    $perPage
     * @param string $sort
     * @param string $dir
     * @return array{violations: ViolationModel[], total: int, totalPages: int}
     */
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

        /** @var array<int, array<string, mixed>> $records */
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

    /**
     * Returns a grouped summary of non-ignored violations, sorted and limited.
     *
     * @param int    $limit
     * @param string $sort
     * @param string $dir
     * @return array<int, array<string, mixed>>
     */
    public function summarize(int $limit = 20, string $sort = 'count', string $dir = 'desc'): array
    {
        $allowedSorts = ['count', 'effectiveDirective', 'blockedUri', 'documentUri'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'count';
        }

        /** @var array<int, array<string, mixed>> $result */
        $result = ViolationRecord::find()
            ->addSelect([
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

        return $result;
    }

    /**
     * Updates the status of one or more violations by ID.
     *
     * @param int|int[] $ids
     * @param string    $status
     * @return int Number of rows updated.
     */
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

    /**
     * Permanently deletes violations by ID.
     *
     * @param int[] $ids
     * @return int Number of rows deleted.
     */
    public function deleteByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        return (int) ViolationRecord::deleteAll(['id' => $ids]);
    }

    /**
     * Purges violations older than the given number of days.
     *
     * Defaults to the plugin's configured `retentionDays` setting.
     *
     * @param int|null $days
     * @return int Number of rows deleted.
     */
    public function purge(?int $days = null): int
    {
        if ($days === null) {
            $days = Watson::$plugin->getSettings()->retentionDays;
        }

        $cutoff = Carbon::now()->subDays($days);

        return (int) ViolationRecord::deleteAll(['<', 'dateCreated', Db::prepareDateForDb($cutoff)]);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Returns the list of effective directives that should be silently ignored.
     *
     * @return string[]
     */
    private function _getIgnoredDirectives(): array
    {
        $raw = Watson::$plugin->getSettings()->ignoredDirectives;

        // The editable table stores rows as ['col1' => 'value'] arrays
        if (!empty($raw) && is_array($raw[0] ?? null)) {
            return array_filter(array_column($raw, 'col1'));
        }

        return array_filter((array) $raw);
    }

    /**
     * Decodes a JSON request body, returning null on failure.
     *
     * @param string $rawBody
     * @return mixed
     */
    private function _decodeBody(string $rawBody): mixed
    {
        if ($rawBody === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Normalises a decoded report payload into a flat array of report arrays.
     *
     * Handles both the legacy `report-uri` format (`{"csp-report": {...}}`) and
     * the modern Reporting API format (`[{type: "csp-report", body: {...}}, ...]`).
     *
     * @param mixed  $decoded
     * @param string $contentType
     * @return array<int, array<string, mixed>>
     */
    private function _normalizeReports(mixed $decoded, string $contentType): array
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

        // Unknown JSON object — store as generic record
        return [[
            'kind' => 'json',
            'body' => $decoded,
        ]];
    }
}
