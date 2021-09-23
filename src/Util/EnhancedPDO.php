<?php

namespace Makiavelo\Flex\Util;

/**
 * Extend PDO to add an emulation of nested transactions
 */
class EnhancedPDO extends \PDO
{
    protected $transactionCount = 0;

    /**
     * Create a new transaction.
     * If a transaction was already created, only increment
     * the transactions counter.
     * 
     * Also creates a savepoint (InnoDB feature)
     * 
     * @return boolean
     */
    public function beginTransaction()
    {
        if (!$this->transactionCounter++) {
            return parent::beginTransaction();
        }
        $this->exec('SAVEPOINT trans'.$this->transactionCounter);
        return $this->transactionCounter >= 0;
    }

    /**
     * Commit the current transaction.
     * 
     * If there are more virtual transactions then it
     * just decrements the transactions counter.
     * 
     * @return boolean
     */
    public function commit()
    {
        if (!--$this->transactionCounter) {
            return parent::commit();
        }
        return $this->transactionCounter >= 0;
    }

    /**
     * Rollbacks a transaction.
     * 
     * If there are more virtual transactions then it just rolls
     * back to the previous savepoint.
     * 
     * @return boolean
     */
    public function rollback()
    {
        if (--$this->transactionCounter) {
            $this->exec('ROLLBACK TO trans'.($this->transactionCounter + 1));
            return true;
        }
        return parent::rollback();
    }
}