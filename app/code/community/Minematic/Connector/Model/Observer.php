<?php

/**
 * Class used to handle cron tasks
 * 
 * @author		Eduardo De Leon
 * @email		edudeleon@gmail.com
 */
class Minematic_Connector_Model_Observer
{

	/**
    * Method called by Magento Crontab
    * Call sync_data method in synchronization model  to send all data to Minematic
    * @return [type]
    * @author edudeleon
    * @date   2015-05-13
    */
    public function run_sync_process(){
        //Verify if module is enabled
        if(Mage::helper('connector')->isEnabled()) {

        	//Set sync type
    		$sync_type  = Minematic_Connector_Model_Config::SYNC_TYPE_MAGENTO_SIDE;

            //Log Starting sync process msg
            Mage::helper('connector')->logSyncProcess("Starting synchronization task job...", $sync_type);

            try {

                //Sync all data
                Mage::getModel('connector/synchronization')->sync_data($sync_type, Minematic_Connector_Model_Config::DATA_TYPE_ALL);

            } catch (Exception $e) {

            	// Logging Exceptions
                Mage::helper('connector')->logSyncProcess($e->getMessage(), $sync_type, "ERROR");
                     	
            }

            //Log Finishing sync process msg
            Mage::helper('connector')->logSyncProcess("Finishing synchronization task job.", $sync_type);
        }
    }
}
