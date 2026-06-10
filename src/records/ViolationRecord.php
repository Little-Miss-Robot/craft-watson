<?php

namespace littlemissrobot\watson\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property string      $kind
 * @property string|null $disposition
 * @property string|null $effectiveDirective
 * @property string|null $blockedUri
 * @property string|null $documentUri
 * @property string      $rawPayload
 * @property string|null $userAgent
 * @property string|null $referrer
 * @property string|null $ip
 * @property string      $status
 * @property string      $dateCreated
 * @property string      $dateUpdated
 * @property string      $uid
 */
class ViolationRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%watson_violations}}';
    }
}
