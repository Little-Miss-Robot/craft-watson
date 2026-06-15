<?php

namespace littlemissrobot\watson\models;

use craft\base\Model;

/**
 * Watson settings model.
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
class Settings extends Model
{
    // =========================================================================
    // Public Properties
    // =========================================================================

    /**
     * Number of days to retain violations before they are pruned.
     *
     * @var int
     */
    public int $retentionDays = 90;

    /**
     * Effective directives to silently ignore on incoming reports.
     *
     * Accepts a flat string array or the editable table row format
     * (`[['col1' => 'directive'], ...]`).
     *
     * @var array
     */
    /** @var array<mixed> */
    public array $ignoredDirectives = [];

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    /** @return array<mixed> */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['retentionDays'], 'integer', 'min' => 1],
            [['ignoredDirectives'], 'safe'],
        ]);
    }
}
