<?php
class Minematic_Connector_Model_Config_Storeviewsdropdown{

    /**
     * Drowpdown options with store views
     * @return [type]
     * @author edudeleon
     * @date   2015-05-14
     */
    public function toOptionArray(){
        foreach (Mage::app()->getWebsites() as $website) {
            $website_name = $website->getName();
            foreach ($website->getGroups() as $group) {
                $group_name = $group->getName();

                $stores = $group->getStores();
                foreach ($stores as $store_id => $store) {
                    $store_views[] = array(
                        'value' => $store_id,
                        'label' => $website_name . " > ". $group_name . " > " . $store->getName(),
                    );
                }
            }
        }

        return $store_views;
    }
}