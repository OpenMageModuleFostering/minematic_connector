<?php 

/**
 * Class that saves the correct cron expression from select option in Admin Panel
 */
class Minematic_Connector_Model_System_Config_Backend_Cron extends Mage_Core_Model_Config_Data
{
 
    /**
     * Save the cron expression in core_config table
     * @return [type]
     * @author edudeleon
     * @date   2015-05-12
     */
    protected function _afterSave()
    {
        //Get cron interval (hours)
        $cron_interval_hours = $this->getData('groups/general/fields/cron_interval/value');

        //Prepare cron expression (Run every N hours)
        $cron_expression = "0 */".(int)$cron_interval_hours. " * * *";
 
        try {
            Mage::getModel('core/config_data')
                ->load(Minematic_Connector_Model_Config::CRON_EXPRESSION_PATH, 'path')
                ->setValue($cron_expression)
                ->setPath(Minematic_Connector_Model_Config::CRON_EXPRESSION_PATH)
                ->save();
        }
        catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));
        }
    }
}