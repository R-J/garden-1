<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Db;

use Garden\Db\Db;
use Garden\Db\DbDef;

/**
 * Test various aspects of the {@link DbDef} class and the {@link Db} class as it relates to it.
 */
abstract class DbDefTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var Db The database connection for the tests.
     */
    protected static $db;

    /// Methods ///

    /**
     * Get the database connection for the test.
     *
     * @return Db Returns the db object.
     */
    protected static function createDb() {
        return null;
    }

    /**
     * Get the database def.
     *
     * @return DbDef Returns the db def.
     */
    protected static function createDbDef() {
        return new DbDef(self::$db);
    }

    /**
     * Set up the db link for the test cases.
     */
    public static function setUpBeforeClass() {
        // Drop all of the tables in the database.
        self::$db = static::createDb();

        $tables = self::$db->getAllTables();
        foreach ($tables as $table) {
            self::$db->dropTable($table);
        }
    }

    /**
     * Test a basic call to {@link Db::createTable()}.
     */
    public function testCreateTable() {
        $def = static::createDbDef();
        $db = $def->getDb();

        $def1 = $def->table('user')
            ->primaryKey('userID')
            ->column('name', 'varchar(50)')
            ->index('name', Db::INDEX_IX)
            ->exec(false)
            ->jsonSerialize();

        $def2 = $db->getTableDef('user');

        $this->assertDefEquals($def1, $def2);
    }

    /**
     * Test altering a table's columns.
     */
    public function testAlterTableColumns() {
        $db = self::$db;
        $def = new DbDef($db);
        $tbl = 'tst00';

        $def->table($tbl)
            ->column('col1', 'int')
            ->column('col2', 'int', 0)
            ->index('col1', Db::INDEX_IX)
            ->exec();

        $expected = $def->table($tbl)
            ->column('cola', 'int')
            ->column('colb', 'int')
            ->column('col2', 'int')
            ->index('col1', Db::INDEX_IX)
            ->exec(false)
            ->jsonSerialize();

        $def2 = $db
            ->reset()
            ->getTableDef($tbl);

        $this->assertDefEquals($expected, $def2);
    }

    /**
     * Test altering a table with the {@link Db::OPTION_DROP} option.
     */
    public function testAlterTableWithDrop() {
        $db = self::$db;
        $def = new DbDef($db);
        $tbl = 'tst01';

        $def->table($tbl)
            ->column('col1', 'int')
            ->column('col2', 'int', 0)
            ->index('col1', Db::INDEX_IX)
            ->exec();

        $expected = $def->table($tbl)
            ->column('cola', 'int')
            ->column('colb', 'int')
            ->column('col2', 'int')
            ->index('col2', Db::INDEX_IX)
            ->option(Db::OPTION_DROP, true)
            ->exec(false)
            ->jsonSerialize();

        $actual = $db
            ->reset()
            ->getTableDef($tbl);

        $this->assertDefEquals($expected, $actual, false);
    }

    /**
     * Test altering the primary key.
     */
    public function testAlterPrimaryKey() {
        $db = self::$db;
        $def = new DbDef($db);
        $tbl = 'tst02';

        $def->table($tbl)
            ->column('col1', 'int')
            ->column('col2', 'int', 0)
            ->index('col1', Db::INDEX_PK)
            ->exec();

        $def->table($tbl)
            ->column('col1', 'int')
            ->column('col2', 'int', 0)
            ->index(['col1', 'col2'], Db::INDEX_PK)
            ->exec();
        $expected = $db->getTableDef($tbl);

        $actual = $db
            ->reset()
            ->getTableDef($tbl);

        $this->assertDefEquals($expected, $actual);

        // A more real world example is re-ordering the primary key.
        $def->table($tbl)
            ->column('col1', 'int')
            ->column('col2', 'int', 0)
            ->index(['col2', 'col1'], Db::INDEX_PK)
            ->exec();
        $expected2 = $db->getTableDef($tbl);

        $actual2 = $db
            ->reset()
            ->getTableDef($tbl);

        $this->assertDefEquals($expected2, $actual2);
    }

    /**
     * Assert that two table definitions are equal.
     *
     * @param array $expected The expected table definition.
     * @param array $actual The actual table definition.
     * @param bool $subset Whether or not expected can be a subset of actual.
     */
    public function assertDefEquals($expected, $actual, $subset = true) {
        $colsExpected = $expected['columns'];
        $colsActual = $actual['columns'];

        if ($subset) {
            $colsActual = array_intersect_key($colsActual, $colsExpected);
        }
        $this->assertEquals($colsExpected, $colsActual, "Columns are not the same.");

        $ixExpected = $expected['indexes'];
        $ixActual = $actual['indexes'];

        $isExpected = [];
        foreach ($ixExpected as $ix) {
            $isExpected[] = val('type', $ix, Db::INDEX_IX).'('.implode(', ', $ix['columns']).')';
        }
        asort($isExpected);

        $isActual = [];
        foreach ($ixActual as $ix) {
            $isActual[] = val('type', $ix, Db::INDEX_IX).'('.implode(', ', $ix['columns']).')';
        }
        asort($isExpected);

        if ($subset) {
            $isActual = array_intersect($isActual, $isExpected);
        }
        $this->assertEquals($isExpected, $isActual, "Indexes are not the same.");
    }
}
