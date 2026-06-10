<?php

namespace littlemissrobot\watson\console\controllers;

use craft\console\Controller;
use littlemissrobot\watson\Watson;
use yii\console\ExitCode;

class ViolationsController extends Controller
{
    public int $limit = 20;
    public int $days = 90;

    public function options($actionID): array
    {
        return match ($actionID) {
            'summary' => array_merge(parent::options($actionID), ['limit']),
            'purge' => array_merge(parent::options($actionID), ['days']),
            default => parent::options($actionID),
        };
    }

    public function actionSummary(): int
    {
        $summary = Watson::getInstance()->violations->summarize($this->limit);

        $total = array_sum(array_column($summary, 'count'));

        $this->stdout("Total violations (excluding ignored): {$total}\n");
        $this->stdout("\nTop violation groups:\n");
        $this->stdout(str_repeat('-', 80) . "\n");

        foreach ($summary as $group) {
            $this->stdout(sprintf(
                "  count=%-5d  directive=%-30s  blocked=%s\n",
                $group['count'],
                $group['effectiveDirective'] ?? 'unknown',
                $group['blockedUri'] ?? 'unknown',
            ));
        }

        return ExitCode::OK;
    }

    public function actionPurge(): int
    {
        $deleted = Watson::getInstance()->violations->purge($this->days);

        $this->stdout("Purged {$deleted} violation(s) older than {$this->days} days.\n");

        return ExitCode::OK;
    }
}
