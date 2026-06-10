<?php

namespace littlemissrobot\watson\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\StringHelper;
use littlemissrobot\watson\records\ViolationRecord;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->_createTable();
        $this->_importJsonl();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(ViolationRecord::tableName());

        return true;
    }

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
