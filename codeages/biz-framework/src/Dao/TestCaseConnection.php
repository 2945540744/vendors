<?php

namespace Codeages\Biz\Framework\Dao;

class TestCaseConnection extends Connection
{
    private $_insertedTables = [];

    public function insert($tableName, array $data, array $types = [])
    {
        $this->_insertedTables[] = $tableName;

        return parent::insert($tableName, $data, $types);
    }

    public function getInsertedTables()
    {
        return $this->_insertedTables;
    }

    public function resetInsertedTables()
    {
        $this->_insertedTables = [];
    }
}
