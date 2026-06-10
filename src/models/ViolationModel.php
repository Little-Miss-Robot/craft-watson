<?php

namespace littlemissrobot\watson\models;

use craft\base\Model;
use DateTime;

class ViolationModel extends Model
{
    public ?int $id = null;
    public string $kind = 'unparsed';
    public ?string $disposition = null;
    public ?string $effectiveDirective = null;
    public ?string $blockedUri = null;
    public ?string $documentUri = null;
    public string $rawPayload = '{}';
    public ?string $userAgent = null;
    public ?string $referrer = null;
    public ?string $ip = null;
    public string $status = 'new';
    public ?DateTime $dateCreated = null;

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
            $model->dateCreated = new DateTime($row['dateCreated']);
        }

        return $model;
    }
}
