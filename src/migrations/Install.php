<?php

namespace littlemissrobot\watson\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\StringHelper;
use littlemissrobot\watson\records\ViolationRecord;

/**
 * Install migration.
 *
 * Creates the `watson_violations` table and optionally imports a legacy JSONL
 * violation log if one exists at `storage/logs/csp-violations.jsonl`.
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
class Install extends Migration
{
    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->_createTable();
        $this->_importJsonl();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(ViolationRecord::tableName());

        return true;
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Creates the violations table and its indexes.
     *
     * @return void
     */
    private function _createTable(): void
    {
        $table = ViolationRecord::tableName();

        if ($this->db->tableExists($table)) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'kind' => $this->string()->notNull()->defaultValue('unparsed'),
            'disposition' => $this->string()->null(),
            'effectiveDirective' => $this->string()->null(),
            'blockedUri' => $this->text()->null(),
            'documentUri' => $this->text()->null(),
            'rawPayload' => $this->text()->notNull(),
            'userAgent' => $this->text()->null(),
            'referrer' => $this->text()->null(),
            'ip' => $this->string(45)->null(),
            'status' => $this->string(16)->notNull()->defaultValue('new'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, $table, ['effectiveDirective']);
        $this->createIndex(null, $table, ['status']);
        $this->createIndex(null, $table, ['dateCreated']);
    }

    /**
     * Imports a legacy JSONL violation log if present.
     *
     * This is a best-effort migration that runs silently when no file is found.
     * Malformed lines are skipped individually so a single bad entry does not
     * abort the import.
     *
     * @return void
     */
    private function _importJsonl(): void
    {
        $path = Craft::$app->getPath()->getStoragePath()
            . DIRECTORY_SEPARATOR . 'logs'
            . DIRECTORY_SEPARATOR . 'csp-violations.jsonl';

        if (!is_file($path)) {
            return;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            Craft::warning('Watson: could not open JSONL file for import: ' . $path, __METHOD__);
            return;
        }

        $table = ViolationRecord::tableName();
        $imported = 0;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }

            try {
                $now = gmdate('Y-m-d H:i:s');
                $receivedAt = !empty($row['receivedAt'])
                    ? date('Y-m-d H:i:s', strtotime($row['receivedAt']))
                    : $now;

                $this->insert($table, [
                    'kind' => $row['kind'] ?? 'unparsed',
                    'disposition' => $row['disposition'] ?? null,
                    'effectiveDirective' => $row['effectiveDirective'] ?? $row['violatedDirective'] ?? null,
                    'blockedUri' => $row['blockedUri'] ?? null,
                    'documentUri' => $row['documentUri'] ?? null,
                    'rawPayload' => json_encode($row, JSON_UNESCAPED_SLASHES),
                    'userAgent' => $row['userAgent'] ?? null,
                    'referrer' => $row['referer'] ?? $row['referrer'] ?? null,
                    'ip' => $row['ipAddress'] ?? null,
                    'status' => 'new',
                    'dateCreated' => $receivedAt,
                    'dateUpdated' => $receivedAt,
                    'uid' => StringHelper::UUID(),
                ]);

                $imported++;
            } catch (\Throwable) {
                // Skip malformed lines — best-effort import
                continue;
            }
        }

        fclose($handle);

        Craft::info("Watson: imported {$imported} violation(s) from JSONL.", __METHOD__);
    }
}
