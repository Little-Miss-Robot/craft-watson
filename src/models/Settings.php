<?php

namespace littlemissrobot\watson\models;

use craft\base\Model;

class Settings extends Model
{
    public int $retentionDays = 90;
    public array $ignoredDirectives = [];

    protected function defineRules(): array
    {
        return [
            [['retentionDays'], 'integer', 'min' => 1],
            [['ignoredDirectives'], 'safe'],
        ];
    }
}
