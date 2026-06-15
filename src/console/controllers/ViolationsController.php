<?php

namespace littlemissrobot\watson\console\controllers;

use craft\console\Controller;
use littlemissrobot\watson\Watson;
use yii\console\ExitCode;

/**
 * Manages CSP violation records from the command line.
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
class ViolationsController extends Controller
{
    // =========================================================================
    // Public Properties
    // =========================================================================

    /**
     * Maximum number of groups to display in the summary.
     *
     * @var int
     */
    public int $limit = 20;

    /**
     * Number of days used as the retention threshold for the purge action.
     *
     * @var int
     */
    public int $days = 90;

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        return match ($actionID) {
            'summary' => array_merge(parent::options($actionID), ['limit']),
            'purge' => array_merge(parent::options($actionID), ['days']),
            default => parent::options($actionID),
        };
    }

    /**
     * Prints a summary of the top violation groups.
     *
     * @return int
     */
    public function actionSummary(): int
    {
        $summary = Watson::$plugin->getViolations()->summarize($this->limit);

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

    /**
     * Purges violations older than `--days` (default: 90).
     *
     * @return int
     */
    public function actionPurge(): int
    {
        $deleted = Watson::$plugin->getViolations()->purge($this->days);

        $this->stdout("Purged {$deleted} violation(s) older than {$this->days} days.\n");

        return ExitCode::OK;
    }
}
