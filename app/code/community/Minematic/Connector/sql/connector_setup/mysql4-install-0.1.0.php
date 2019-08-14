<?php
// Script used to create minematc table

$installer = $this;
$installer->startSetup();

try {

  //Drop 'minematic_config' table in case it exists
  $installer->run("
    DROP TABLE IF   EXISTS {$this->getTable('minematic_config')};
   ");

  // Create table statment
  $configTableName = $this->getTable('minematic_config');
  $sql = "CREATE TABLE IF NOT EXISTS $configTableName (
        `name` varchar(128) NOT NULL,
        `value` varchar(255) NOT NULL,
        PRIMARY KEY (`name`)
      );
      
      INSERT IGNORE INTO $configTableName VALUES('" . Minematic_Connector_Model_Config::ITEMS_LAST_SYNC_DATETIME . "', '');
      INSERT IGNORE INTO $configTableName VALUES('" . Minematic_Connector_Model_Config::ITEMS_LAST_SYNC_TYPE . "', ''); 
      INSERT IGNORE INTO $configTableName VALUES('" . Minematic_Connector_Model_Config::USERS_LAST_SYNC_DATETIME . "', '');
      INSERT IGNORE INTO $configTableName VALUES('" . Minematic_Connector_Model_Config::USERS_LAST_SYNC_TYPE . "', '');
  	  INSERT IGNORE INTO $configTableName VALUES('" . Minematic_Connector_Model_Config::EVENTS_LAST_SYNC_DATETIME . "', '');
      INSERT IGNORE INTO $configTableName VALUES('" . Minematic_Connector_Model_Config::EVENTS_LAST_SYNC_TYPE . "', '');
  ";

  //Create table
  $installer->run($sql);

  //Insert default value for cron expression (run every 2 hours)
  $installer->run("INSERT IGNORE INTO {$this->getTable('core/config_data')} VALUES('" . Minematic_Connector_Model_Config::CRON_EXPRESSION_PATH . "', '0 */2 * * *');");

} catch (Exception $e) {
    Mage::logException($e);
}

$installer->endSetup();