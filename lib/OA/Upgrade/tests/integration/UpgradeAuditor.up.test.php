<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

require_once MAX_PATH . '/lib/OA/Upgrade/DB_UpgradeAuditor.php';
require_once MAX_PATH . '/lib/OA/Upgrade/UpgradeLogger.php';
require_once MAX_PATH . '/lib/OA/Upgrade/UpgradeAuditor.php';
require_once MAX_PATH . '/lib/OA/Upgrade/tests/integration/BaseUpgradeAuditor.up.test.php';


Mock::generate('OA_DB_UpgradeAuditor');
Mock::generate('OA_UpgradeAuditor');
/**
 * A class for testing the Openads_DB_Upgrade class.
 *
 * @package    OpenX Upgrade
 * @subpackage TestSuite
 */
class Test_OA_UpgradeAuditor extends Test_OA_BaseUpgradeAuditor
{
    public $path;

    public $oDBAuditor;

    /**
     * The constructor method.
     */
    public function __construct()
    {
        parent::__construct();
        $this->path = MAX_PATH . '/lib/OA/Upgrade/tests/data/';

        Mock::generatePartial(
            'OA_DB_UpgradeAuditor',
            $mockDBAud = 'OA_DB_UpgradeAuditor' . rand(),
            []
        );
        $this->oDBAuditor = new $mockDBAud($this);


        $this->aAuditParams[0] = ['description' => 'COMPLETE',
                        'action' => UPGRADE_ACTION_UPGRADE_SUCCEEDED

                       ];
        $this->aAuditParams[1] = ['description' => 'COMPLETE',
                        'action' => UPGRADE_ACTION_UPGRADE_SUCCEEDED,
                        'confbackup' => '/etc/test_backupconffile.ini' // do not change, used in test_cleanAuditArtifacts
                       ];

        $this->aDBAuditParams[0][0] = ['info1' => 'UPGRADE STARTED',
                              'action' => DB_UPGRADE_ACTION_UPGRADE_STARTED,
                             ];
        $this->aDBAuditParams[0][1] = ['info1' => 'BACKUP STARTED',
                              'action' => DB_UPGRADE_ACTION_BACKUP_STARTED,
                             ];

        // do not edit the index ; they are used in the test_cleanAuditArtifacts()
        $this->tableBackupName = 'z_test1';

        $this->aDBAuditParams[0][2] = ['info1' => 'copied table',
                              'tablename' => 'test_table1',
                              'tablename_backup' => $this->tableBackupName,
                              'table_backup_schema' => serialize($this->_getFieldDefinitionArray(1)),
                              'action' => DB_UPGRADE_ACTION_BACKUP_TABLE_COPIED,
                             ];
        $this->aDBAuditParams[0][3] = ['info1' => 'BACKUP COMPLETE',
                              'action' => DB_UPGRADE_ACTION_BACKUP_SUCCEEDED,
                             ];
        $this->aDBAuditParams[0][4] = ['info1' => 'UPGRADE SUCCEEDED',
                              'action' => DB_UPGRADE_ACTION_UPGRADE_SUCCEEDED,
                             ];
    }

    public function test_constructor()
    {
        $oAuditor = new OA_UpgradeAuditor();
        $this->assertNotNull($oAuditor->logTable);
    }

    public function test_init()
    {
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $this->prefix = $oAuditor->prefix;

        $this->_dropAuditTable($oAuditor->prefix . $oAuditor->logTable);
        $this->assertTrue($oAuditor->init($oAuditor->oDbh, '', $this->oDBAuditor), 'failed to initialise upgrade auditor');
        $aDBTables = OA_DB_Table::listOATablesCaseSensitive();
        $this->assertTrue(in_array($oAuditor->prefix . $oAuditor->logTable, $aDBTables));
    }

    /**
     * test that param array is set
     * test that param values are quoted
     *
     */
    public function test_setKeyParams()
    {
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $oAuditor->setKeyParams(
            [
                                        'string' => 'test_tables',
                                        'integer' => 10,
                                        'escape' => "openad's value",
                                     ]
        );
        $this->assertEqual($oAuditor->aParams['string'], '\'test_tables\'', 'wrong param value: string');
        $this->assertEqual($oAuditor->aParams['integer'], 10, 'wrong param value: integer');
        if ($oAuditor->oDbh->dbsyntax == 'pgsql') {
            $this->assertEqual($oAuditor->aParams['escape'], "'openad''s value'", 'wrong param value: escape');
        } else {
            $this->assertEqual($oAuditor->aParams['escape'], "'openad\'s value'", 'wrong param value: escape');
        }
    }

    /**
     * Test 1: with lowercase prefix
     * Test 2: with uppercase prefix
     */
    public function test_createAuditTable()
    {
        // Test 1
        $GLOBALS['_MAX']['CONF']['table']['prefix'] = 'oa_';
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $this->_dropAuditTable($oAuditor->prefix . $oAuditor->logTable);
        $this->assertTrue($oAuditor->_createAuditTable(), 'failed to createAuditTable');
        $aDBTables = OA_DB_Table::listOATablesCaseSensitive();
        $this->assertTrue(in_array('oa_' . $oAuditor->logTable, $aDBTables));
        TestEnv::restoreConfig();
        TestEnv::restoreEnv();

        // Test 2
        $GLOBALS['_MAX']['CONF']['table']['prefix'] = 'OA_';
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $this->_dropAuditTable($oAuditor->prefix . $oAuditor->logTable);
        $this->assertTrue($oAuditor->_createAuditTable(), 'failed to createAuditTable');
        $aDBTables = OA_DB_Table::listOATablesCaseSensitive();
        $this->assertTrue(in_array('OA_' . $oAuditor->logTable, $aDBTables));
        TestEnv::restoreConfig();
        TestEnv::restoreEnv();
    }

    public function test_checkCreateAuditTable()
    {
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $this->_dropAuditTable($oAuditor->prefix . $oAuditor->logTable);
        // test1 : table does not exist, should create table
        $this->assertTrue($oAuditor->_checkCreateAuditTable(), 'failed to createAuditTable');
        $aDBTables = OA_DB_Table::listOATablesCaseSensitive();
        $this->assertTrue(in_array($oAuditor->prefix . $oAuditor->logTable, $aDBTables));
        // test2 : table does exist, should return true
        $this->assertTrue($oAuditor->_checkCreateAuditTable(), '');
    }


    public function test_logAuditAction()
    {
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $oAuditor->setKeyParams($this->_getUpgradeAuditKeyParamsArray());

        $this->assertTrue($oAuditor->logAuditAction($this->aAuditParams[0]), '');
        $this->assertTrue($oAuditor->logAuditAction($this->aAuditParams[1]), '');
    }


    /**
     * main audit query method
     * return all columns from the upgrade_action table
     * along with a count of the backup table artifacts for each upgrade_action_id
     *
     * @return array
     */
    public function test_queryAuditAllDescending()
    {
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $oAuditor->init($oAuditor->oDbh, '');

        $aResult = $oAuditor->queryAuditAllDescending();

        $this->assertIsA($aResult, 'array', 'not an array');
        $this->assertIsA($aResult[0], 'array', 'subelement is not an array');
        $this->assertTrue($aResult[0]['backups'] >= 0, 'count <= 0');

        $this->assertTrue(
            $aResult[0]['upgrade_action_id'] > $aResult[1]['upgrade_action_id'],
            'the result set is not in descending order'
        );

        // check that the resulting aResultAfter is identical to what we've recorded
        $aAuditKeyParams = $this->_getUpgradeAuditKeyParamsArray();
        $aAuditParams = array_reverse($this->aAuditParams);

        foreach ($aResult as $k => $v) {
            $this->assertEqual($v['upgrade_name'], $aAuditKeyParams['upgrade_name'], 'wrong upgrade_name for audit query result ' . $k);
            $this->assertEqual($v['version_from'], $aAuditKeyParams['version_from'], 'wrong version_from for audit query result ' . $k);
            $this->assertEqual($v['version_to'], $aAuditKeyParams['version_to'], 'wrong version_to for audit query result ' . $k);
            $this->assertEqual($v['logfile'], $aAuditKeyParams['logfile'], 'wrong logfile for audit query result ' . $k);
            $this->assertEqual($v['description'], $aAuditParams[$k]['description'], 'wrong description for audit query result ' . $k);

            if (array_key_exists('confbackup', $aAuditParams[$k])) {
                $this->assertEqual($v['confbackup'], $aAuditParams[$k]['confbackup'], 'wrong confbackup for audit query result ' . $k);
            } else {
                $this->assertNull($v['confbackup'], 'confbackup should be null for audit query result ' . $k);
            }
        }
    }


    /**
     * return all columns from upgrade_action table for given upgrade_action_id
     *
     * @param integer $id
     * @return array
     */
    public function test_queryAuditByUpgradeId()
    {
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');

        $aResult = $oAuditor->queryAuditByUpgradeId(1);
        $this->assertIsa($aResult, 'array', 'audit table query result is not an array');
        $this->assertEqual(count($aResult), 1, 'incorrect number of elements');
    }

    /**
     * return all columns from database_action table for given upgrade_action_id
     *
     * @param integer $id
     * @return array
     */
    public function test_queryAuditArtifactsByUpgradeId()
    {
        $mockDBAuditor = new MockOA_DB_UpgradeAuditor();

        $toTest = ['1', '2', '3'];
        $mockDBAuditor->setReturnValue('queryAuditByUpgradeId', $toTest, [1]);


        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $oAuditor->oDBAuditor = $mockDBAuditor;

        $this->assertEqual(
            $toTest,
            $oAuditor->queryAuditArtifactsByUpgradeId(1),
            'input array is different from output array'
        );
    }

    /**
     * main backup tables query method
     *
     * @param integer $id
     * @return array
     */
    public function test_queryAuditBackupTablesByUpgradeId()
    {
        $aResult = ['1', '2'];

        $mockDBAuditor = new MockOA_DB_UpgradeAuditor();
        $toTest = [0 => ['tablename_backup' => 'upgrade_action']];
        $mockDBAuditor->setReturnValue('queryAuditBackupTablesByUpgradeId', $toTest, [1]);

        Mock::generatePartial(
            'OA_UpgradeAuditor',
            'OA_UpgradeAuditorTest',
            ['getBackupTableStatus']
        );

        $oMockAuditor = new OA_UpgradeAuditorTest($this);
        $oMockAuditor->setReturnValue('getBackupTableStatus', $aResult, [$toTest]);
        $oMockAuditor->oDBAuditor = $mockDBAuditor;

        $aResultFinal = $oMockAuditor->queryAuditBackupTablesByUpgradeId(1);


        $this->assertEqual(
            $aResultFinal,
            $aResult,
            'result is different for the main backup tables'
        );
    }

    /**
     * used by queryAuditBackupTablesByUpgradeId(0
     *
     * @param array $aTables
     * @return array
     */
    public function test_getBackupTableStatus()
    {
        $aConf = $GLOBALS['_MAX']['CONF'];
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $oAuditor->init($oAuditor->oDbh, '');

        $aResult = $oAuditor->getBackupTableStatus([0 => ['tablename_backup' => 'upgrade_action']]);
        $this->assertIsa($aResult, 'array', 'not an array');
        $this->assertEqual(count($aResult[0]), 3, 'incorrect number of elements');
        $this->assertTrue($aResult[0]['backup_size'] > 0, 'data length <= 0');
        $this->assertTrue($aResult[0]['backup_rows'] > 0, 'number of rows <= 0');
    }

    public function _setupLogFile($logFile)
    {
        if (is_dir(dirname($logFile))) {
            chmod(dirname($logFile), 0777);
        }
        if (is_file($logFile)) {
            chmod($logFile, 0777);
            unlink($logFile);
        }
        if (is_dir(dirname($logFile))) {
            rmdir(dirname($logFile));
        }
    }

    public function _tableExists($tablename, $aExistingTables)
    {
        return in_array($this->prefix . $tablename, $aExistingTables);
    }

    /**
     * internal function to set up some test tables
     *
     * @param mdb2 connection $oDbh
     */
    public function _createTestTables($oDbh)
    {
        $aExistingTables = OA_DB_Table::listOATablesCaseSensitive();
        if (!$this->_tableExists('z_test1', $aExistingTables)) {
            $conf = &$GLOBALS['_MAX']['CONF'];
            //$conf['table']['prefix'] = '';
            $oTable = new OA_DB_Table();
            $oTable->init($this->path . 'schema_test_backups.xml');
            $this->assertTrue($oTable->createTable('z_test1'), 'error creating test table1');
            $aExistingTables = OA_DB_Table::listOATablesCaseSensitive();
            $this->assertTrue($this->_tableExists('z_test1', $aExistingTables), '_createTestTables');
            return $oTable->aDefinition;
        }
        return false;
    }


    /**
     * drops all backup artifacts for given upgrade_id
     * logs a reason
     *
     * @param integer $upgrade_id
     * @param string $reason
     * @return boolean
     */
    public function test_cleanAuditArtifacts()
    {
        // put some data with 2 different upgrade_id
        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');

        Mock::generatePartial(
            'OA_UpgradeLogger',
            $mockDBAud = 'OA_UpgradeLogger' . rand(),
            ['isPearError', 'logError']
        );
        $oLoggerMock = new $mockDBAud($this);

        $oAuditor->init($oAuditor->oDbh, $oLoggerMock);
        $this->_test_logDBAuditAction($oAuditor->oDBAuditor, $this->aDBAuditParams, $this->_getDBAuditKeyParamsArray());

        $this->_createTestTables($oAuditor->oDbh);

        // init data to be able to compare later
        $initData1 = $oAuditor->queryAuditByUpgradeId(1);
        $initDataBackup1 = $oAuditor->queryAuditBackupTablesByUpgradeId(1);
        $initDataAll = $oAuditor->queryAuditAllDescending();

        // delete one upgrade_id that doesn't exist, it shouldn't delete anything
        $aResult = $oAuditor->cleanAuditArtifacts(11);
        $this->assertEqual($initDataAll, $oAuditor->queryAuditAllDescending());

        // the log file of the upgrade_id = 0 is "openads_upgrade_2.0.0.log"
        // the confbackup file of the upgrade_id = 0 is "/etc/test_backupconffile.ini"
        $prefixLogFile = MAX_PATH . '/var/';
        $suffixLogFile = "test/openads_upgrade_2.0.0.log";
        $logFile = $prefixLogFile . $suffixLogFile;
        $backupFile = '/etc/test_backupconffile.ini';

        // setup
        $this->_setupLogFile($logFile);

        // delete one with existing logfile but no write permission => false
        mkdir(dirname($logFile));
        touch($logFile);
        chmod($logFile, 0444);
        chmod(dirname($logFile), 0555); //You need execution permission to traverse a directory.

        if (function_exists('posix_getuid') && posix_getuid()) {
            // The following assertions fail when running the test as root
            $this->assertEqual($oAuditor->cleanAuditArtifacts(1), false);
            $logResult = $oAuditor->queryAuditByUpgradeId(1);
            $logResult = $logResult[0]['logfile'];
            $this->assertEqual($logResult, $suffixLogFile);
        }

        // setup
        $this->_setupLogFile($logFile);
        $this->_createTestTables($oAuditor->oDbh);

        // delete one with existing logfile and rights => true
        mkdir(dirname($logFile));
        touch($logFile);
        chmod($logFile, 0777);
        $this->assertEqual($oAuditor->cleanAuditArtifacts(1), true);

        // setup
        $this->_setupLogFile($logFile);
        $this->_createTestTables($oAuditor->oDbh);

        // delete one with non existing logfile => true
        $oAuditor->setKeyParams($this->_getUpgradeAuditKeyParamsArray());
        $this->assertTrue($oAuditor->logAuditAction($this->aAuditParams[0]), '');
        // we use the previous logfile = "cleaned record" because the logfile field was set to "cleaned by user"
        $this->assertEqual($oAuditor->cleanAuditArtifacts(1), true);
    }

    /**
     * replace the backup conf column with reason for deletion
     *
     * @param integer $upgrade_action_id
     * @param string $reason
     * @return boolean
     */
    public function test_updateAuditBackupConfDroppedById()
    {
        $upgrade_id = 1;
        $reason = "hey that was a super log msg";

        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $oAuditor->init($oAuditor->oDbh, '');

        $this->assertTrue($oAuditor->updateAuditBackupConfDroppedById($upgrade_id, $reason));

        $table = $oAuditor->getLogTablename();
        $result = $oAuditor->oDbh->queryRow("SELECT confbackup
							FROM {$table}
							WHERE upgrade_action_id='{$upgrade_id}'");

        $this->assertEqual($result['confbackup'], $reason, "the message '$reason' has not been correctly set '{$result['confbackup']}'");
    }

    /**
     * replace the logfile column with reason for deletion
     *
     * @param integer $upgrade_action_id
     * @param string $reason
     * @return boolean
     */
    public function test_updateAuditBackupLogDroppedById()
    {
        $upgrade_id = 1;
        $reason = "hey that was a super log msg22243235";

        $oAuditor = $this->_getAuditObject('OA_UpgradeAuditor');
        $oAuditor->init($oAuditor->oDbh, '');

        $this->assertTrue($oAuditor->updateAuditBackupLogDroppedById($upgrade_id, $reason));
        $table = $oAuditor->getLogTablename();
        $result = $oAuditor->oDbh->queryRow("SELECT logfile
							FROM {$table}
							WHERE upgrade_action_id='{$upgrade_id}'");

        $this->assertEqual($result['logfile'], $reason, "the message '$reason' has not been correctly set : '{$result['logfile']}'");
    }

    public function _createAuditData($classToTest)
    {
        $oAuditor = $this->_getAuditObject($classToTest);


        $oAuditor->setKeyParams($this->_getUpgradeAuditKeyParamsArray());

        $this->assertTrue($oAuditor->logAuditAction($this->aAuditParams[0][0]), '');

        $oAuditor->setKeyParams($this->_getUpgradeAuditKeyParamsArray());
        $this->assertTrue($oAuditor->logAuditAction($this->aAuditParams[1][0]), '');
    }
}
