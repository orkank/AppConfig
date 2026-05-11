<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class HeadlessDelegation extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('idangerous_appconfig_headless_delegation', 'delegation_id');
    }

    public function insertNew(string $codeHash, int $adminUserId, string $expiresAtUtc): void
    {
        $this->getConnection()->insert($this->getMainTable(), [
            'code_hash' => $codeHash,
            'admin_user_id' => $adminUserId,
            'expires_at' => $expiresAtUtc,
        ]);
    }

    /**
     * Remove old rows to keep the table small (best-effort).
     */
    public function pruneStale(): void
    {
        $conn = $this->getConnection();
        $conn->delete(
            $this->getMainTable(),
            '(expires_at < UTC_TIMESTAMP() - INTERVAL 2 DAY) OR (used_at IS NOT NULL AND used_at < UTC_TIMESTAMP() - INTERVAL 2 DAY)'
        );
    }

    /**
     * @return int|null Admin user id when consume succeeds
     */
    public function consumeByCodeHash(string $codeHash): ?int
    {
        $conn = $this->getConnection();
        $conn->beginTransaction();
        try {
            $select = $conn->select()
                ->from($this->getMainTable())
                ->where('code_hash = ?', $codeHash)
                ->where('used_at IS NULL')
                ->where('expires_at > UTC_TIMESTAMP()')
                ->forUpdate(true);
            $row = $conn->fetchRow($select);
            if (!$row) {
                $conn->rollBack();

                return null;
            }
            $conn->update(
                $this->getMainTable(),
                ['used_at' => new \Zend_Db_Expr('UTC_TIMESTAMP()')],
                ['delegation_id = ?' => (int) $row['delegation_id']]
            );
            $conn->commit();

            return (int) $row['admin_user_id'];
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
