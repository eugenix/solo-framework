<?php
require_once '../Helpers/MigrationManager.php';
require_once '../Helpers/DirectoryHandler.php';

/**
 * Test class for MigrationManager.
 * Generated by PHPUnit on 2011-05-20 at 16:35:08.
 */
class MigrationManagerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var MigrationManager
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new MigrationManager('localhost', 'root', 'toor', 'testmigration');

        $this->object->dbHelper->makeDBEmpty();
        DirectoryHandler::clean('data/storage');
        MigrationManagerHelper::cleanMigrDir('data/my_migration');

         // создаем копии deltas для работы
        DirectoryHandler::delete('data/deltas_work');
        DirectoryHandler::copy('data/deltas', 'data/deltas_work');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        DirectoryHandler::delete('data/deltas_work');

        MigrationManagerHelper::cleanMigrDir('data/my_migration');
        DirectoryHandler::clean('data/storage');
        $this->object->dbHelper->makeDBEmpty();

        if ($this->object != null)
            $this->object->__destruct();
    }

    /**
     * @todo Implement testGetAllMigrations().
     */
    public function testGetAllMigrations()
    {
        $this->object->dbHelper->createTable();

        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (0, unix_timestamp(NOW()), '')");
        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (1, unix_timestamp(NOW()), '')");
        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (2, unix_timestamp(NOW()), '')");

        $this->assertEquals(3, count($this->object->getAllMigrations()), "3 migrations");
    }

    /**
     * @todo Implement testGetLastMigration().
     */
    public function testGetLastMigration()
    {
        $this->object->dbHelper->createTable();

        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (0, 1111111111, '')");
        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (1, 1222222222, '')");
        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (2, 1333333333, '')");

        $this->assertEquals(1333333333, $this->object->getLastMigration()->createTime, "last timestamp");
        $this->assertEquals(2, $this->object->getLastMigration()->number, "second number");
        $this->assertEquals(3, $this->object->getLastMigration()->id, "third id");
    }

    /**
     * @todo Implement testCreateMigration().
     */
    public function testCreateMigration()
    {
        $this->object->createMigration('data/my_migration');
        $this->assertTrue(is_writable('data/my_migration/delta.sql'));
        $this->assertContains('/*YOU CODE HERE*/', file_get_contents('data/my_migration/delta.sql'));
    }

    public function testInit()
    {
        $this->object->dbHelper->importFiles('data/deltas_work/0',
            array('scheme.sql', 'data.sql', 'procedures.sql', 'triggers.sql'));

        $this->object->init('data/storage');

        $this->check0Changes();

        $this->assertTrue(is_writable('data/storage/0/data.sql'));
        $this->assertTrue(is_writable('data/storage/0/procedures.sql'));
        $this->assertTrue(is_writable('data/storage/0/scheme.sql'));
        $this->assertTrue(is_writable('data/storage/0/triggers.sql'));

        $this->assertEquals(0, $this->object->getLastMigration()->number, "number 0");
    }

    /**
     * @todo Implement testAddMigration().
     */
    public function testCommitMigration()
    {
        $this->object->init('data/storage');

        // создаем миграцию
        $this->object->createMigration('data/my_migration');
        
        // запишем дельту
        $str  = str_replace('/*YOU CODE HERE*/', 'CREATE TABLE ...', file_get_contents('data/my_migration/delta.sql'));
        file_put_contents('data/my_migration/delta.sql', $str);

        $this->object->commitMigration('data/my_migration', 'data/storage');

        $this->assertTrue(is_writable('data/storage/1/data.sql'));
        $this->assertTrue(is_writable('data/storage/1/delta.sql'));
        $this->assertTrue(is_writable('data/storage/1/procedures.sql'));
        $this->assertTrue(is_writable('data/storage/1/scheme.sql'));
        $this->assertTrue(is_writable('data/storage/1/triggers.sql'));

        $this->assertEquals(1, $this->object->getLastMigration()->number, 'Number migration is 1');
        $this->assertFalse(file_exists('data/my_migration'));

    }

    /**
     * @todo Implement testAddMigration().
     */
    public function testValidAutoDelta()
    {
        $this->object->dbHelper->importFiles('data/deltas_work/0',
            array('scheme.sql', 'data.sql', 'procedures.sql', 'triggers.sql'));

        // инициализация репозитория миграций
        $this->object->init('data/storage');

        // делаем изменения в базе
        $this->object->dbHelper->importFiles('data/deltas_work/1', array('delta.sql'));
        $this->object->dbHelper->importFiles('data/deltas_work/2', array('delta.sql'));
        $this->object->dbHelper->importFiles('data/deltas_work/3', array('delta.sql'));

        $delta = $this->object->getDeltaByBinLog("/var/log/mysql", 'data/storage', false);
        $this->assertNotEquals("", $delta);

        /*
        file_put_contents("auto_delta.sql", $delta);
        $this->object->dbHelper->executeSQLFromFile("auto_delta.sql");
        unlink("auto_delta.sql");
        */
    }

    private function initState()
    {        
        $this->object->dbHelper->importFiles('data/deltas_work/0',
            array('scheme.sql', 'data.sql', 'procedures.sql', 'triggers.sql'));

        // инициализация репозитория миграций
        $this->object->init('data/storage');

        // делаем изменения в базе
        $this->object->dbHelper->importFiles('data/deltas_work/1', array('delta.sql'));
        // коммитим  миграцию
        $this->object->commitMigration('data/deltas_work/1', 'data/storage');

        $this->object->dbHelper->importFiles('data/deltas_work/2', array('delta.sql'));
        $this->object->commitMigration('data/deltas_work/2', 'data/storage');

        $this->object->dbHelper->importFiles('data/deltas_work/3', array('delta.sql'));
        $this->object->commitMigration('data/deltas_work/3', 'data/storage');
    }

    /**
     * @todo Implement testGotoMigration().
     */
    public function testGoto0SoftMigration()
    {
        $this->initState();
        $this->object->gotoMigration('data/storage', 0);

        $this->check0Changes();
    }

    private function check0Changes()
    {
        $res = $this->object->dbHelper->executeQuery("
            SELECT COUNT(*) AS count
            FROM information_schema.tables
            WHERE table_schema = 'testmigration'
        ");
        $this->assertEquals(24, mysql_result($res, 0), "24 tables");

        $res = $this->object->dbHelper->executeQuery("
            SELECT COUNT(*) AS COUNT
            FROM information_schema.triggers
            WHERE trigger_schema = 'testmigration'
        ");
        $this->assertEquals(6, mysql_result($res, 0), "6 triggers");

        $res = $this->object->dbHelper->executeQuery("
            SELECT COUNT(*) AS COUNT
            FROM information_schema.routines
            WHERE routine_schema = 'testmigration'
        ");
        $this->assertEquals(6, mysql_result($res, 0), "6 procedures");
    }


    public function testGoto1SoftMigration()
    {
        $this->initState();
        $this->object->gotoMigration('data/storage', 1);

        $this->check0Changes();
        $this->check1Changes();
    }

    private function check1Changes()
    {
        $res = $this->object->dbHelper->executeQuery("SHOW COLUMNS FROM actor LIKE 'first_name'");
        $this->assertEquals('varchar(128)', mysql_result($res, 0, 'Type'), "Field changed");

        $res = $this->object->dbHelper->executeQuery("SHOW COLUMNS FROM address LIKE 'new_fiels'");
        $this->assertEquals('new_fiels', mysql_result($res, 0, 'Field'), "New field added");
    }

    public function testGoto2SoftMigration()
    {
        $this->initState();
        $this->object->gotoMigration('data/storage', 2);

        $this->check1Changes();
        $this->check2Changes();
    }

    private function check2Changes()
    {
        $res = $this->object->dbHelper->executeQuery("SHOW TABLES IN testmigration LIKE 'man'");
        $this->assertEquals('man', mysql_result($res, 0), "New table added");

        $res = $this->object->dbHelper->executeQuery("SELECT COUNT(*) FROM man");
        $this->assertEquals(5, mysql_result($res, 0), "New table added");
    }

    public function testGoto3SoftMigration()
    {
        $this->initState();
        $this->object->gotoMigration('data/storage', 3);

        $this->check1Changes();
        $this->check2Changes();
        $this->check3Changes();
    }

    private function check3Changes()
    {
         $res = $this->object->dbHelper->executeQuery("
            SELECT COUNT(*) AS COUNT
            FROM information_schema.routines
            WHERE routine_schema = 'testmigration'
        ");
        $this->assertEquals(7, mysql_result($res, 0), "New procedure addded");
    }

    /**
     * @todo Implement testGotoMigration().
     */
    public function testGoto2ForceMigration()
    {
        $this->initState();
        $this->object->gotoMigration('data/storage', 2, true);

        $this->check1Changes();
        $this->check2Changes();
    }


    /**
     * @todo Implement testGotoLastMigration().
     */
    public function testGotoLastMigration()
    {
        $this->initState();
        $this->object->gotoLastMigration('data/storage');

        $this->check1Changes();
        $this->check2Changes();
        $this->check3Changes();
    }

    /**
     * @todo Implement testCheckMigrations().
     */
    public function testCheckMigrations()
    {
        $this->object->dbHelper->createTable();

        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (0, unix_timestamp(NOW()), '')");
        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (1, unix_timestamp(NOW()), '')");
        $this->object->dbHelper->executeQuery("INSERT INTO __migration (number, createTime, comment) VALUES (2, unix_timestamp(NOW()), '')");

        try
        {
            $this->object->checkMigrations($this->object->getAllMigrations());
            $this->assertTrue(true);
        }
        catch (Exception $e)
        {
            $this->fail('Thats ok');
        }
    }

    public function testSetCurrentVersion()
    {
        $this->object->setCurrentVersion('data/storage');
        $this->assertEquals(0, $this->object->getCurrentVersion('data/storage'));

        $this->object->setCurrentVersion('data/storage', 12);
        $this->assertEquals(12, $this->object->getCurrentVersion('data/storage'));
    }
}
?>
