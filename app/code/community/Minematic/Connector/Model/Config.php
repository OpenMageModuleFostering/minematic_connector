<?php
/**
 * Minematic Connector config model
 *
 * @package    Minematic_Connector
 * @author     edudeleon
 */
class Minematic_Connector_Model_Config extends Mage_Core_Model_Abstract
{

    /*
     * Last sync data for ITEMS
     */
     const ITEMS_LAST_SYNC_DATETIME     = 'items_last_sync_datetime';
     const ITEMS_LAST_SYNC_TYPE         = 'items_last_sync_type'; 
     
     /*
     * Last sync data for USERS
     */
     const USERS_LAST_SYNC_DATETIME     = 'users_last_sync_datetime';
     const USERS_LAST_SYNC_TYPE         = 'users_last_sync_type'; 
     
     /*
     * Last sync data for EVENTS
     */
     const EVENTS_LAST_SYNC_DATETIME    = 'events_last_sync_datetime';
     const EVENTS_LAST_SYNC_TYPE        = 'events_last_sync_type';

    /*
     * Last SYNC type [magento/minematic]
     */

     /*
     * Sync types
     */
    const SYNC_TYPE_MAGENTO_SIDE   = 'magento';      // Magento Customer Side (Cron Job)
    const SYNC_TYPE_MINEMATIC_SIDE = 'minematic';   // Minematic Side (old sync way)


    /*
     *   Elements to sync
     */
    const DATA_TYPE_ALL    = 'ALL';
    const DATA_TYPE_ITEMS  = 'ITEMS';
    const DATA_TYPE_USERS  = 'USERS';
    const DATA_TYPE_EVENTS = 'EVENTS';

    /*
     *   Limit for arrays used to post data 
     */
    const SYNC_LIMIT = 100;

    /*
     *  Events types
     */
    const EVENT_TYPE_ALL    = "ALL";
    const EVENT_TYPE_PAID   = "PAID";
    const EVENT_TYPE_OTHERS = "OTHERS";

    /*
     *  Core config paths for custom fields
     */
    const ADDITIONAL_ITEM_FIELDS  = "minematic_settings/general/product_fields";
    const ADDITIONAL_USER_FIELDS  = "minematic_settings/general/customer_fields";
    const ADDITIONAL_EVENT_FIELDS = "minematic_settings/general/event_fields";

    /**
     *  Cron Expression Path in core_config_data
     */
    const CRON_EXPRESSION_PATH = 'crontab/jobs/minematic_sync_data/schedule/cron_expr';

    /**
     * Store View Config Path
     */
    const CONFIG_PATH_STORE_VIEW = 'minematic_settings/general/store_view';

}
