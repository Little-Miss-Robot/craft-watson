<?php

namespace littlemissrobot\watson\models;

use craft\base\Model;
use craft\helpers\DateTimeHelper;
use DateTime;

/**
 * Violation model.
 *
 * Represents a single CSP violation as a read-only view object hydrated from a
 * database row.
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
final class ViolationModel extends Model
{
    // =========================================================================
    // Public Properties
    // =========================================================================

    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * Report format kind: `csp-report`, `report`, `json`, or `unparsed`.
     *
     * @var string
     */
    public string $kind = 'unparsed';

    /**
     * @var string|null
     */
    public ?string $disposition = null;

    /**
     * @var string|null
     */
    public ?string $effectiveDirective = null;

    /**
     * @var string|null
     */
    public ?string $blockedUri = null;

    /**
     * @var string|null
     */
    public ?string $documentUri = null;

    /**
     * @var string
     */
    public string $rawPayload = '{}';

    /**
     * @var string|null
     */
    public ?string $userAgent = null;

    /**
     * @var string|null
     */
    public ?string $referrer = null;

    /**
     * @var string|null
     */
    public ?string $ip = null;

    /**
     * @var string
     */
    public string $status = 'new';

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateCreated = null;

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * Hydrates a model instance from a raw database row array.
     *
     * @param array<string, mixed> $row
     * @return static
     */
    public static function fromRecord(array $row): self
    {
        $model = new self();
        $model->id = (int) $row['id'];
        $model->kind = $row['kind'] ?? 'unparsed';
        $model->disposition = $row['disposition'] ?? null;
        $model->effectiveDirective = $row['effectiveDirective'] ?? null;
        $model->blockedUri = $row['blockedUri'] ?? null;
        $model->documentUri = $row['documentUri'] ?? null;
        $model->rawPayload = $row['rawPayload'] ?? '{}';
        $model->userAgent = $row['userAgent'] ?? null;
        $model->referrer = $row['referrer'] ?? null;
        $model->ip = $row['ip'] ?? null;
        $model->status = $row['status'] ?? 'new';

        if (!empty($row['dateCreated'])) {
            $dt = DateTimeHelper::toDateTime($row['dateCreated']);
            $model->dateCreated = $dt !== false ? $dt : null;
        }

        return $model;
    }
}
