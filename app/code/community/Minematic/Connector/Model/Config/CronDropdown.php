<?php
class Minematic_Connector_Model_Config_CronDropdown
{

    /**
     * Drowpdown options to run module cron tasks
     * @return [type]
     * @author edudeleon
     * @date   2015-05-05
     */
	public function toOptionArray()
    {
        for($i = 1; $i <= 24; $i++){
            $hours_dropdown[] = array(
                'value' => $i,
                'label' => ($i==1) ? ("Every ". $i." Hour") : "Every ". $i." Hours",
            );
        }

        return $hours_dropdown;
    }

}
