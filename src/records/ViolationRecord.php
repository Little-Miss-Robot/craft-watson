<?php

namespace littlemissrobot\watson\records;

use craft\db\ActiveRecord;

/**
 * Violation active record.
 *
 * Represents a single CSP violation report stored in the `watson_violations` table.
 *
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
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
class ViolationRecord extends ActiveRecord
{
    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%watson_violations}}';
    }
}
