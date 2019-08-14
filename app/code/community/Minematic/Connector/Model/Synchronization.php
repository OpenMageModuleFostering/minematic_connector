<?php
/**
 * Class used to sync data between Magento and Minematic
 */
class Minematic_Connector_Model_Synchronization{

    //Private variables
	private $_result_sync_message;
	private $_result_sync_http_code_response;

    /**
     * Class constructor
     * Initializate variables and set server configs for sync process
     * @return [type]
     * @author edudeleon
     * @date   2015-05-11
     */
	public function __construct(){
        // Init variables
		$this->_result_sync_message            = "";
		$this->_result_sync_http_code_response = "";

		$this->total_events	= 0;

        //Set memory limit and max excecution time for data sync process (importatnt for migration)
        ini_set('memory_limit','512M');
        set_time_limit(900); // max execution time (15 mins)

        //Init website_id/store_id variables
        $this->store_id   = "";
        $this->website_id = "";
        $this->_init_store_variables(); //Init variables
	}

    /**
     * Set values for store_id and website_id variables
     * @return [type]
     * @author edudeleon
     * @date   2015-05-14
     */
    public function _init_store_variables(){
        //Check if Store Id is defined in module configuration
        if(Mage::getStoreConfig(Minematic_Connector_Model_Config::CONFIG_PATH_STORE_VIEW)){
            $this->store_id = Mage::getStoreConfig(Minematic_Connector_Model_Config::CONFIG_PATH_STORE_VIEW);
        } else {
            //Get Default Store ID for current Website.
           $this->store_id = Mage::app()
                                ->getWebsite()
                                ->getDefaultGroup()
                                ->getDefaultStoreId();
        }

        //Get Website ID
        $this->website_id = Mage::getModel('core/store')->load($this->store_id)->getWebsiteId();

        //Validate variables
        if(empty($this->store_id) || empty($this->website_id)){
            throw new Exception("Store ID or Webstite variables are not initializated.");
        }
    }

	/**
	 * Check if data type is valid
	 * @param  [type]     $type [ALL, ITEMS, USERES, OTHERS]
	 * @return boolean
	 * @author edudeleon
	 * @date   2015-05-07
	 */
	private function _valid_data_type($type=NULL){
		if(!empty($type)){
			if ($type == Minematic_Connector_Model_Config::DATA_TYPE_ALL ||
				$type == Minematic_Connector_Model_Config::DATA_TYPE_ITEMS || 
				$type == Minematic_Connector_Model_Config::DATA_TYPE_USERS || 
				$type == Minematic_Connector_Model_Config::DATA_TYPE_EVENTS){
				
				return TRUE;
			} else {
				return FALSE;
			}
		}
		
		return TRUE;
	}

	/**
	 * Check if event type is valid
	 * @param  [type]     $event_type [ALL, PAID, OTHERS]
	 * @return boolean
	 * @author edudeleon
	 * @date   2015-05-10
	 */
	private function _valid_event_type($event_type=NULL){
		if(!empty($event_type)){
			if ($event_type == Minematic_Connector_Model_Config::EVENT_TYPE_ALL ||
				$event_type == Minematic_Connector_Model_Config::EVENT_TYPE_PAID || 
				$event_type == Minematic_Connector_Model_Config::EVENT_TYPE_OTHERS){
				return TRUE;
			} else {
				return FALSE;
			}
		}
		
		return TRUE;
	}

    /**
     * Checks if migration needs to be run for data_type specified
     * @param  $data_type [ITEMS, USERES, EVENTS]
     * @return BOOLEAN
     * @author edudeleon
     * @date   2015-05-10
     */
    private function _run_migration($data_type){
        $last_sync_datetime = TRUE;

        // ITEMS
        if($data_type == Minematic_Connector_Model_Config::DATA_TYPE_ITEMS){
            $last_sync_datetime = $this->_get_connector_config_value(Minematic_Connector_Model_Config::ITEMS_LAST_SYNC_DATETIME);
        }

        // USERS
        if($data_type == Minematic_Connector_Model_Config::DATA_TYPE_USERS){
            $last_sync_datetime = $this->_get_connector_config_value(Minematic_Connector_Model_Config::USERS_LAST_SYNC_DATETIME);
        }

        // EVENTS
        if($data_type == Minematic_Connector_Model_Config::DATA_TYPE_EVENTS){
            $last_sync_datetime = $this->_get_connector_config_value(Minematic_Connector_Model_Config::EVENTS_LAST_SYNC_DATETIME);
        }

        // Return boolean 
        return $last_sync_datetime ? FALSE : TRUE;
    }

	/**
	 * Get summary data for items(products), users(customers) and events(orders)
	 * @param  [type]     $type [ALL, ITEMS, USERS, EVENTS]
	 * @return [type]
	 * @author edudeleon
	 * @date   2015-05-07
	 */
	public function get_summary_data($type=null){
		//Verify if type is valid
		if(!$this->_valid_data_type($type)){
			throw new Exception("Invalid type parameter");
		}

        //IF type is not set, get summary for all resources
        if(empty($type)){
            $type = Minematic_Connector_Model_Config::DATA_TYPE_ALL;
        }

        //Get time zone set in Magento
        $magento_timezone = new DateTimeZone(Mage::getStoreConfig('general/locale/timezone'));

        //Get time zone offset for GMT
		$date_time  = new DateTime('now', $magento_timezone);
		$gmt_offest = 'GMT '. $date_time->format('O');

        //Init summary array
        $summary = array(
			'CURDATE'  => time(),
			'TIMEZONE' => $gmt_offest,
        );

        //Get summary data for items (products)
        if ($type == Minematic_Connector_Model_Config::DATA_TYPE_ITEMS || $type == Minematic_Connector_Model_Config::DATA_TYPE_ALL){
            try {
                $cpe_table = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity');

                $coreResource = Mage::getSingleton('core/resource');
             
                $conn = $coreResource->getConnection('core_read');

                //Preparing query
                $sql = 'SELECT count(distinct entity_id) count, Month(`updated_at`) as month, Year(`updated_at`) as year
                        FROM '.$cpe_table .
                        ' GROUP BY Month(`updated_at`), Year(updated_at)
                        ORDER BY updated_at ASC';
                
                //Feth all items                 
                $_products = $conn->fetchAll($sql);

                //Preparing data
                $products = array();
                foreach ($_products as $_product) {
                    //Format month year
					$month_year            = str_pad($_product['month'], 2, "0", STR_PAD_LEFT).'-'.$_product['year'];
					$products[$month_year] = $_product['count'];
                }

                //Adding item to summary array
                $summary['ITEM']  = $products;
            }
            catch(Exception $e)
            {
            	//Log exception in case something goes wrong
                Mage::logException("Exception while querying the database for product data " . $e->getMessage());
                
            }
        }

        //Get summary data for users (customers)
        if ($type == Minematic_Connector_Model_Config::DATA_TYPE_USERS || $type == Minematic_Connector_Model_Config::DATA_TYPE_ALL){
            try {
                $ce_table = Mage::getSingleton('core/resource')->getTableName('customer_entity');

                $coreResource = Mage::getSingleton('core/resource');
             
                $conn = $coreResource->getConnection('core_read');

                //Preparing query
                $sql = 'SELECT count(distinct entity_id) count, Month(`updated_at`) as month, Year(`updated_at`) as year
                        FROM '.$ce_table .
                        ' GROUP BY Month(`updated_at`), Year(updated_at)
                        ORDER BY updated_at ASC';
                
                //Fetch data                    
                $_customers = $conn->fetchAll($sql);

                //Preparing data
                $customers = array();
                foreach ($_customers as $_customer) {
                	//Format month year
					$month_year             = str_pad($_customer['month'], 2, "0", STR_PAD_LEFT).'-'.$_customer['year'];
					$customers[$month_year] = $_customer['count'];
                }

                //Adding item to summary array
                $summary['USER']   = $customers;
            }
            catch(Exception $e)
            {
            	//Log exception in case something goes wrong
                Mage::logException("Exception while querying the database for user data " . $e->getMessage());
            }
        }

        //Get summary data for events
        if ($type == Minematic_Connector_Model_Config::DATA_TYPE_EVENTS || $type == Minematic_Connector_Model_Config::DATA_TYPE_ALL)
        {
            try {
                $sfo_table = Mage::getSingleton('core/resource')->getTableName('sales_flat_order');

                $coreResource = Mage::getSingleton('core/resource');
             
                $conn = $coreResource->getConnection('core_read');

                //Preparing query
                $sql = 'SELECT count(distinct entity_id) count, Month(`created_at`) as month, Year(`created_at`) as year
                        FROM '.$sfo_table .
                        ' GROUP BY Month(`created_at`), Year(created_at)
                        ORDER BY created_at ASC';
                                         
                $_events = $conn->fetchAll($sql);

                //Preparing data
                $events = array();
                foreach ($_events as $_event) {
                    //Format month year
					$month_year          = str_pad($_event['month'], 2, "0", STR_PAD_LEFT).'-'.$_event['year'];
					$events[$month_year] = $_event['count'];
                }

                //Adding item to summary array
                $summary['EVENT'] = $events;
            }
            catch(Exception $e)
            {
            	//Log exception in case something goes wrong
                Mage::logException("Exception while querying the database for events data " . $e->getMessage());
            }
        }

        //Return summay data
        return $summary;
    }

    /**
     * Method used to sync all data between Magento and Minematic
     * Called by Module Observer based on Cron Task configurations
     * @return [type]
     * @author edudeleon
     * @date   2015-05-11
     */
    public function sync_data($sync_type, $data_type=NULL){
        //Verify if module is enabled (just in case). If module is not enabled, Magento won't never come here..
        if(!Mage::helper('connector')->isEnabled()) {
            
            //Throw Exception
            throw new Exception("Minematic Module is disabled but Cron Jobs are still running. Disable Module in System > Configuration > Advanced > Advanced OR edit Minematic_Connect.xml in app/etc/modules/.");
        }

        //Set last_sync_datetime
        $last_sync_datetime = date('Y-m-d H:i:s');

        /* SYNCING ITEMS */
        try {
            //Get information about the last synchronization process
            $items_last_sync_datetime = $this->_get_connector_config_value(Minematic_Connector_Model_Config::ITEMS_LAST_SYNC_DATETIME);
            $items_last_sync_datetime = $items_last_sync_datetime ? strtotime($items_last_sync_datetime) : NULL; //Convert to timestamp

            //Call ITEMS sync method
            $this->sync_items_data($sync_type, $items_last_sync_datetime, NULL, NULL, $last_sync_datetime);
        
        //If something goes wrong, catch the exception, log it and continue the synchronization
        } catch (Exception $e) {
            // Log exception
            Mage::helper('connector')->logSyncProcess($e->getMessage(), $sync_type, "ERROR");
        }

        
        /* SYNCING USERS */
        try {
            //Get information about the last synchronization process
            $users_last_sync_datetime = $this->_get_connector_config_value(Minematic_Connector_Model_Config::USERS_LAST_SYNC_DATETIME);
            $users_last_sync_datetime = $users_last_sync_datetime ? strtotime($users_last_sync_datetime) : NULL; //Convert to timestamp

            //Call USERS sync method
            $this->sync_users_data($sync_type, $users_last_sync_datetime, NULL, $last_sync_datetime);
         
         } catch (Exception $e) {
            // Log exception
            Mage::helper('connector')->logSyncProcess($e->getMessage(), $sync_type, "ERROR");
        }

        /* SYNCING EVENTS */
        try {
            //Get information about the last synchronization process
            $events_last_sync_datetime = $this->_get_connector_config_value(Minematic_Connector_Model_Config::EVENTS_LAST_SYNC_DATETIME);
            $events_last_sync_datetime = $events_last_sync_datetime ? strtotime($events_last_sync_datetime) : 978310861; //(Set date 2001-01-01 01:01:01  for migration)

            //Call EVENTS sync method
            $this->sync_events_data($sync_type, Minematic_Connector_Model_Config::EVENT_TYPE_ALL, $events_last_sync_datetime, NULL, $last_sync_datetime);
         
         } catch (Exception $e) {
            // Log exception
            Mage::helper('connector')->logSyncProcess($e->getMessage(), $sync_type, "ERROR");
        }

    }

    /**
     * Method used to sync Items Data
     * Filter by Store ID
     * @param  [type]     $sync_type 	        Specified the type of synchronization ['magento', 'minematic']. 
     * @param  [type]     $date_from 	        Filter Date From   	(Unix TimeStamp) 
     * @param  [type]     $date_to 		        Filter Date To 		(Unix TimeStamp) 
     * @param  [type]     $limit                Number of elements to send in one request. Default value is set in config file.
     * @param  [type]     $last_sync_datetime   Optional last sync datetime
     * @return [type]
     * @author edudeleon
     * @date   2015-05-08
     */
    public function sync_items_data($sync_type, $date_from=NULL, $date_to=NULL, $limit=NULL, $last_sync_datetime=NULL){
        //Check if migration needs to be run for ITEMS data
        if($this->_run_migration(Minematic_Connector_Model_Config::DATA_TYPE_ITEMS)){

            // Logging migration mode msg
            Mage::helper('connector')->logSyncProcess("[ITEMS DATA] Request receieved in Migration Mode", $sync_type);
            
        } else {

            // Logging normal sync mode
            $log_msg = "[ITEMS DATA] Request receieved in Normal Mode. Since Parameter '$date_from' = '" .date('Y-m-d H:i:s', $date_from). "'";
            Mage::helper('connector')->logSyncProcess($log_msg, $sync_type);

            //Security layer (date_from is mandatory if migration has run before)
            if(empty($date_from)){
                throw new Exception("Migration has run before for ITEMS data. Parameter 'since/data_from' is mandatory.");
            }
        }

    	//Set last sync datetime
    	$last_sync_datetime = $last_sync_datetime ? $last_sync_datetime : date('Y-m-d H:i:s');
		
        //Prepare products collection
        $productsCollection = Mage::getModel('catalog/product')->getCollection()
        						->addAttributeToSelect(array('id'))
                                ->setStoreId($this->store_id); //Adding store ID filter (Language filter)

        //Adding filters by date 
        if(!empty($date_from)){
        	//format date
        	$date_from = date('Y-m-d H:i:s', $date_from); 
        	$productsCollection->addAttributeToFilter('updated_at', array('gteq' => $date_from));	//grether or equal than
        }

        if(!empty($date_to)){
        	//format date
        	$date_to = date('Y-m-d H:i:s', $date_to);
        	$productsCollection->addAttributeToFilter('updated_at', array('lt' => $date_to)); 	//less than
        }

        //If limit is not set, get value from config model
        if(empty($limit)){
            $limit = Minematic_Connector_Model_Config::SYNC_LIMIT;
        }

        //Adding fliter by status
        $productsCollection->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));

        //Prepare Pagination
        $productsCollection->setPageSize($limit);
 
 		//Set current and total pages variables
		$currentPage = 1;
		$pages       = $productsCollection->getLastPageNumber();

		//Define base url for images
		$imageBaseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product';

		//Load products
		$total_items = 0;
		do {
			//Get collection by current page
	        $productsCollection->setCurPage($currentPage);
	        $productsCollection->load();
 
        	$items_data = array();
	        foreach ($productsCollection as $product) {
	        	//Load product data
	        	$_product = Mage::getModel('catalog/product')->load($product->getId());

	        	//Prepare Image URL
				$iurl   = '';
				$tmpUrl = $imageBaseUrl . $_product->getImage();

                //Validate Image
                if($this->_validateProductImage($_product->getImage())) {
                    $iurl = $tmpUrl;
                } else if($this->_validateProductImage($_product->getSmallImage())) {
                    $iurl = $_product->getSmallImage();
                }

                //Get product attributes
				$sku    = $_product->getId();
				$pname  = $_product->getName();
				$purl   = $_product->getProductUrl();

				//Get product Stock Flag
                $stock_item = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);
                $inStock   = $stock_item->getIsInStock();
                $stock_qty = $stock_item->getQty();

                //Verify if add product to result
                if (!empty($sku) && !empty($pname) && !empty($purl) && !empty($iurl)){
                	//Getting Product tags
					$tags_array = array();
                    $tags = htmlspecialchars($_product->getMetaKeyword());
                    if (!empty($tags)){
                        $tags_array = split(',', $tags);
                    }

                    //Getting product categories
					$category_ids  = $_product->getCategoryIds();
                    if (is_array($category_ids)) {
                        foreach ($category_ids as $category_id) {
                            $category = Mage::getModel('catalog/category')->load($category_id);
                            array_push($tags_array, $category->getName());
                        }
                    }

                    //Adding data
                    $item_data = array (
                        'id'              => $_product->getId(),
                        'name'            => str_replace(",", " ", $_product->getName()),
                        'image_url'       => $iurl,
                        'page_url'        => $_product->getProductUrl(),
                        'active'          => TRUE,    //Status = enabled
                        'available'       => $inStock ? 'Y' : 'N',
                        'tags'            => $tags_array,
                        'amount'          => $_product->getPrice(),
                        'discount_amount' => $_product->getFinalPrice(),
                        'quantity'        => (int)$stock_qty,
					);

                    //Get additional fields from Admin Panel
                    $additional_fields = $this->get_additional_fields(Minematic_Connector_Model_Config::DATA_TYPE_ITEMS);
                    foreach ($additional_fields as $field_name => $get_method) {
                  		$item_data[$field_name] = $_product->$get_method();
                    }

                    //Adding elements
                    $items_data[] = $item_data;

                    //items count
                	$total_items++;
                }
	        }


            //Push data to Minematic. Array size is equal to Page_Size/Limit
            if(!empty($items_data)){
	           
                Mage::helper('connector')->pushData('items', $items_data);
                
                //$this->emulate_pushData("ITEMS", $items_data, $sync_type); //Remove this line for production
                
	        }

	 		//Set pagination for next page
	        $currentPage++;

	        //clear collection and free memory
	        $productsCollection->clear();

	    } while ($currentPage <= $pages);

	    //Set sync result messages (Variables are retrieved when method is called from Frontend Controller)
	   	if($total_items > 0){
			$this->_result_sync_message            = 'Successfully loaded ' . $total_items . ' products';
			$this->_result_sync_http_code_response = 200;
	   	} else {
			$this->_result_sync_message            = 'No products Found';
			$this->_result_sync_http_code_response = 201;
	   	}

        // Logging sync summary for ITEMS 
        Mage::helper('connector')->logSyncProcess("[ITEMS DATA] Total Items synchronized ($total_items).", $sync_type);

        //Update data about last synchronzation process
        $this->_update_last_sync_data(Minematic_Connector_Model_Config::DATA_TYPE_ITEMS, $sync_type, $last_sync_datetime);
    }

    /**
     * Method used to sync users data
     * @param  [type]     $sync_type 	        Specified the type of synchronization ['magento', 'minematic']. 
     * @param  [type]     $date_from 	        Filter Date From   	(Unix TimeStamp)
     * @param  [type]     $limit 		        Number of elements to send in one request. Default value is set in config file.
     * @param  [type]     $last_sync_datetime   Optional last sync datetime
     * @return [type]
     * @author edudeleon
     * @date   2015-05-09
     */
    public function sync_users_data($sync_type, $date_from=NULL, $limit=NULL, $last_sync_datetime=NULL){
        //Check if migration needs to be run for USERS data
        if($this->_run_migration(Minematic_Connector_Model_Config::DATA_TYPE_USERS)){

            // Logging migration mode msg
            Mage::helper('connector')->logSyncProcess("[USERES DATA] Request receieved in Migration Mode", $sync_type);
            
        } else {

            // Logging normal sync mode
            $log_msg = "[USERS DATA] Request receieved in Normal Mode. Since Parameter '$date_from' = '" .date('Y-m-d H:i:s', $date_from). "'";
            Mage::helper('connector')->logSyncProcess($log_msg, $sync_type);

            //Security layer (date_from is mandatory if migration has run before)
            if(empty($date_from)){
                throw new Exception("Migration has run before for USERS data. Parameter 'since/data_from' is mandatory.");
            }
        }

    	//Set sync datetime
    	$last_sync_datetime = $last_sync_datetime ? $last_sync_datetime : date('Y-m-d H:i:s');

    	//Prepare customers collection
    	$customersCollection = Mage::getModel('customer/customer')->getCollection()
            					->addAttributeToSelect('*')
                                ->addAttributeToFilter('store_id', array('eq' => $this->store_id)); //Filter by store Id

        //Adding filters by date_from
        if(!empty($date_from)){
        	
        	//format date
        	$date_from = date('Y-m-d H:i:s', $date_from); 
        	$customersCollection->addAttributeToFilter('updated_at', array('gteq' => $date_from));	//grether or equal than

        }

    	//If limit is not set, get value from config model
        if(empty($limit)){
            $limit = Minematic_Connector_Model_Config::SYNC_LIMIT;
        }

        //Prepare Pagination
        $customersCollection->setPageSize($limit);
 
 		//Set current and total pages variables
		$currentPage = 1;
		$pages       = $customersCollection->getLastPageNumber();

		//Load customers
		$total_users = 0;
		do {
	        $customersCollection->setCurPage($currentPage);
	        $customersCollection->load();
 
        	$customers_data = array();
	        foreach ($customersCollection as $customer) {
				$id    = $customer->getId();
                $email = $customer->getEmail();

                if (!empty($id) && !empty($email)){
                	$customer_data = array(
                        'id'            => $id,
                        'email'         => $email,
                        'first_name'    => $customer->getFirstname(),
                        'last_name'     => $customer->getLastname(),
                        //'reward_points' => '', not avaialable fro Magento CE
                        //'credit_balnce' => '', not available
                        'active'        => TRUE, //Default active 
                	);

                	//Get additional fields from Admin Panel
                    $additional_fields = $this->get_additional_fields(Minematic_Connector_Model_Config::DATA_TYPE_USERS);
                    foreach ($additional_fields as $field_name => $get_method) {
                  		$customer_data[$field_name] = $customer->$get_method();
                    }

                    //Adding elements
                    $customers_data[] = $customer_data;

                    //total count
                	$total_users++;
            	}
            }

      		//Push data to Minematic. Array size is equal to Page_Size/Limit
      		if(!empty($customers_data)){
      			 
                 Mage::helper('connector')->pushData('users', $customers_data);

                 //$this->emulate_pushData("USERS", $customers_data, $sync_type); //Remove this line for production
            }

	 		//Set pagination for next page
	        $currentPage++;

	        //clear collection and free memory
	        $customersCollection->clear();
	    } while ($currentPage <= $pages);

	    //Set sync result messages (Variables are retrieved when method is called from Frontend Controller)
	   	if($total_users > 0){
			$this->_result_sync_message            = 'Successfully loaded ' . $total_users . ' users';
			$this->_result_sync_http_code_response = 200;
	   	} else {
			$this->_result_sync_message            = 'No users Found';
			$this->_result_sync_http_code_response = 201;
	   	}

        // Logging sync summary for ITEMS 
        Mage::helper('connector')->logSyncProcess("[USERS DATA] Total Users synchronized ($total_users).", $sync_type);

        //Update data about last synchronzation process
        $this->_update_last_sync_data(Minematic_Connector_Model_Config::DATA_TYPE_USERS, $sync_type, $last_sync_datetime);

    }

    /**
     * Method used to sync events data
     * @param  [type]     $sync_type	       Specified the type of synchronization ['magento', 'minematic']. 
     * @param  [type]     $event_type          Event type [ALL, PAID, OTHERS]
     * @param  [type]     $date_from 	       Filter Date From   	(Unix TimeStamp)
     * @param  [type]     $last_sync_datetime  Optional last sync datetime
     * @return [type]
     * @author edudeleon
     * @date   2015-05-10
     */
   	public function sync_events_data($sync_type, $event_type=NULL, $date_from=NULL, $limit=NULL, $last_sync_datetime=NULL){
        //TESTING
        //$date_from=time()-60;  //978310861

        //Check if migration needs to be run for EVENTS data
        if($this->_run_migration(Minematic_Connector_Model_Config::DATA_TYPE_EVENTS)){

            // Log migration mode
            Mage::helper('connector')->logSyncProcess("[EVENTS DATA] Request receieved in Migration Mode", $sync_type);
            
        } else {

            // Logging normal sync mode
            $log_msg = "[EVENTS DATA] Request receieved in Normal Mode. Since Parameter '$date_from' = '" .date('Y-m-d H:i:s', $date_from). "'";
            Mage::helper('connector')->logSyncProcess($log_msg, $sync_type);

        }
        
        //Security layer (date_from is mandatory for migration and normal mode)
        if(empty($date_from)){
            throw new Exception("Parameter 'since/data_from' is mandatory for Migration Mode and Normal Mode.");
        }

        //Set sync datetime
        $last_sync_datetime = $last_sync_datetime ? $last_sync_datetime : date('Y-m-d H:i:s');

        //Check if event type is set
        if(!empty($event_type)){
        	//Validate event type
        	if(!$this->_valid_event_type($event_type)){
        		throw new Exception("Event Type Not Valid");
        	}

        } else {
        	//Set event type to ALL
            $event_type = Minematic_Connector_Model_Config::EVENT_TYPE_ALL;
        }

   		//Check If limit is set, if not, get value from config model
        if(empty($limit)){
            $limit = Minematic_Connector_Model_Config::SYNC_LIMIT;
        }

        $this->total_events = 0;

        //Sync "PAID" events
        if($event_type == Minematic_Connector_Model_Config::EVENT_TYPE_PAID || $event_type == Minematic_Connector_Model_Config::EVENT_TYPE_ALL){
        	//Syncing ORDERS and PAID ORDERS events
        	$this->_sync_paid_events($date_from, $limit, $sync_type);
        }

        //Sync "OTHERS" events
        if($event_type == Minematic_Connector_Model_Config::EVENT_TYPE_OTHERS || $event_type == Minematic_Connector_Model_Config::EVENT_TYPE_ALL){
        	//Syncing OTHER Events
        	$this->_sync_other_events($date_from, $limit, $sync_type);
        }

	    //Set sync result messages (Variables are retrieved when method is called from Frontend Controller)
	   	if($this->total_events > 0){
			$this->_result_sync_message            = 'Successfully loaded ' . $this->total_events . ' events';
			$this->_result_sync_http_code_response = 200;
	   	} else {
			$this->_result_sync_message            = 'No events Found';
			$this->_result_sync_http_code_response = 201;
	   	}

        // Logging sync summary for EVENTS 
        Mage::helper('connector')->logSyncProcess("[EVENTS DATA] Total Events synchronized (". $this->total_events. ").", $sync_type);

        //Update data about last synchronzation process
        $this->_update_last_sync_data(Minematic_Connector_Model_Config::DATA_TYPE_EVENTS, $sync_type, $last_sync_datetime);

   	}

    /**
     * Sync orders and paid orders events [ORDER, ORDER]
     * @param  [type]     $date_from
     * @param  [type]     $limit
     * @param  [type]     $sync_type
     * @return [type]
     * @author edudeleon
     * @date   2015-05-14
     */
   	private function _sync_paid_events($date_from, $limit, $sync_type=NULL){
            //Set tables prefix
            $tbl_prefix = Mage::getConfig()->getTablePrefix();

            //Format date
            if(!empty($date_from)){
                $date_from = date('Y-m-d H:i:s', $date_from); 
            }

            /**
             * ORDERS LIST
             */

            //Get all ORDERS where status is different from "canceled" and "customer_id" field is set
            $ordersCollection = Mage::getModel('sales/order')->getCollection()
                ->addFieldToSelect('created_at')
                ->addAttributeToFilter('status', array('nin' => array('canceled','complete')))
                ->addFieldToFilter('product_id', array('gt' => 0))
                ->addFieldToFilter('customer_id', array('gt' => 0))
                ->addFieldToFilter('main_table.store_id', array('eq' => $this->store_id)); //Filter by store Id
            

            //Join "sales_flat_order" table with "sales_flat_order_item" table
            $ordersCollection->getSelect()->join( 
                array('sales_flat_order_item_alias'=> $tbl_prefix.'sales_flat_order_item'), 'sales_flat_order_item_alias.order_id = main_table.entity_id', array("product_id" => "sales_flat_order_item_alias.product_id")
            );

            //Join "sales_flat_order" table with "customer_entity" table
            $ordersCollection->getSelect()->join( 
                array('customer_alias'=> $tbl_prefix.'customer_entity'), 'customer_alias.entity_id = main_table.customer_id', array("customer_id" => "main_table.customer_id")
            );

            //Adding filters by date_from
            if(!empty($date_from)){
                $ordersCollection->addFieldToFilter('main_table.updated_at', array('gteq' => $date_from));   //grether or equal than
            }

            //Prepare Pagination
            $ordersCollection->setPageSize($limit);
            
            //Set current and total pages variables
            $currentPageOrders = 1;
            $orderTotalPages   = $ordersCollection->getLastPageNumber();

            //Load ORDERS
            do {
                $ordersCollection->setCurPage($currentPageOrders);
                $ordersCollection->load();
                $orders_data = array();
                foreach ($ordersCollection as $order) {
                    
                    $order_data = array(
                        'type'     => "ORDER",
                        'user_id'  => $order->getCustomerId(),
                        'item_id'  => $order->getProductId(),
                        'datetime' => strtotime($order->getCreatedAt()),
                    );

                    //Adding orders
                    $orders_data[] = $order_data;

                    $this->total_events++;
                }

                //Push data to Minematic by chunks
                if(!empty($orders_data)){
                    Mage::helper('connector')->pushData('events', $orders_data);
                    
                    //$this->emulate_pushData("EVENTS", $orders_data, $sync_type); //Remove this line for production
                }

                //Set pagination for next page
                $currentPageOrders++;

                //clear collection and free memory
                $ordersCollection->clear();
            } while ($currentPageOrders <= $orderTotalPages);


            /**
             * PAID ORDERS
             */

            //Get all PAID orders where status is "complete" and "customer_id" field is set
            $paidOrdersCollection = Mage::getModel('sales/order')->getCollection()
                ->addFieldToSelect('created_at')
                ->addFieldToFilter('status', array('eq' => 'complete'))
                ->addFieldToFilter('product_id', array('gt' => 0))
                ->addFieldToFilter('customer_id', array('gt' => 0))
                ->addFieldToFilter('main_table.store_id', array('eq' => $this->store_id)); //Filter by store Id
            
            //Join "sales_flat_order" table with "sales_flat_order_item" table
            $paidOrdersCollection->getSelect()->join( 
                array('sales_flat_order_item_alias'=> $tbl_prefix.'sales_flat_order_item'), 'sales_flat_order_item_alias.order_id = main_table.entity_id', array("product_id" => "sales_flat_order_item_alias.product_id")
            );

            //Join "sales_flat_order" table with "customer_entity" table
            $paidOrdersCollection->getSelect()->join( 
                array('customer_alias'=> $tbl_prefix.'customer_entity'), 'customer_alias.entity_id = main_table.customer_id', array("customer_id" => "main_table.customer_id")
            );

            //Adding filters by date_from
            if(!empty($date_from)){
                $paidOrdersCollection->addFieldToFilter('main_table.updated_at', array('gteq' => $date_from));   //grether or equal than
            }

            //Prepare Pagination
            $paidOrdersCollection->setPageSize($limit);
            
            //Set current and total pages variables
            $currentPagePaidOrders = 1;
            $paidOrdersTotalPages  = $paidOrdersCollection->getLastPageNumber();

            //Load PAID orders
            do {
                $paidOrdersCollection->setCurPage($currentPagePaidOrders);
                $paidOrdersCollection->load();
                $paid_orders_data = array();
                foreach ($paidOrdersCollection as $paid_order) {
                    
                    $paid_order_data = array(
                        'type'     => "PAID",
                        'user_id'  => $paid_order->getCustomerId(),
                        'item_id'  => $paid_order->getProductId(),
                        'datetime' => strtotime($paid_order->getCreatedAt()),
                    );

                    //Adding paid orders
                    $paid_orders_data[] = $paid_order_data;

                    $this->total_events++;
                }

                //Push data to Minematic by chunks
                if(!empty($paid_orders_data)){
                    Mage::helper('connector')->pushData('events', $paid_orders_data);
                   
                    //$this->emulate_pushData("EVENTS", $paid_orders_data, $sync_type); //Remove this line for production
                }

                //Set pagination for next page
                $currentPagePaidOrders++;

                //clear collection and free memory
                $paidOrdersCollection->clear();
            } while ($currentPagePaidOrders <= $paidOrdersTotalPages);
   	}

   	/**
   	 * Sync other events [CART, LIKE, SHARE]
   	 * @param  [type]     $date_from
     * @param  [type]     $limit
   	 * @param  [type]     $sync_type
   	 * @return [type]
   	 * @author edudeleon
   	 * @date   2015-05-10
   	 */
   	private function _sync_other_events($date_from, $limit, $sync_type=NULL){
   		//Prepare report events collection
        $eventsCollection = Mage::getModel('reports/event')->getCollection();

        //Filter by store Id
        $eventsCollection->addFieldToFilter('main_table.store_id', array('eq' => $this->store_id)); 

        //Set tables prefix
        $tbl_prefix = Mage::getConfig()->getTablePrefix();
        
        //Join "report_event" table with "report_event_types" table
        $eventsCollection->getSelect()->join( 
            array('report_types_alias'=> $tbl_prefix.'report_event_types'), 'report_types_alias.event_type_id = main_table.event_type_id'
        );   

        //Join "report_event" table with "customer_entity" table
        $eventsCollection->getSelect()->join( 
            array('customer_alias'=> $tbl_prefix.'customer_entity'), 'customer_alias.entity_id = main_table.subject_id'
        );

        //Adding Filters (OR)
        $eventsCollection->addFieldToFilter(array('main_table.event_type_id', 'main_table.event_type_id', 'main_table.event_type_id'), 
                                            array(  Mage_Reports_Model_Event::EVENT_PRODUCT_TO_CART,
                                                    Mage_Reports_Model_Event::EVENT_PRODUCT_TO_WISHLIST,
                                                    Mage_Reports_Model_Event::EVENT_WISHLIST_SHARE,
                                                )  
                                            );

        //Adding filters by date_from
        if(!empty($date_from)){
            //format date
            $date_from = date('Y-m-d H:i:s', $date_from); 
            $eventsCollection->addFieldToFilter('logged_at', array('gteq' => $date_from));   //grether or equal than
        }

        //Prepare Pagination
        $eventsCollection->setPageSize($limit);
 		
 		//Set current and total pages variables
        $currentPage = 1;
        $pages       = $eventsCollection->getLastPageNumber();

        //Load events
        do {
            $eventsCollection->setCurPage($currentPage);
            $eventsCollection->load();
            $events_data = array();
            foreach ($eventsCollection as $event) {
                
                $event_data = array(
                    'type'     => $this->_get_event_type_name($event->getEventTypeId()),
                    'user_id'  => $event->getSubjectId(),
                    'item_id'  => $event->getObjectId(),
                    'datetime' => strtotime($event->getLoggedAt()),
                );

                //Get additional fields from Admin Panel
                $additional_fields = $this->get_additional_fields(Minematic_Connector_Model_Config::DATA_TYPE_EVENTS);
                foreach ($additional_fields as $field_name => $get_method) {
                    $event_data[$field_name] = $event->$get_method();
                }

                //Adding events
                $events_data[] = $event_data;

                //Increment count
                $this->total_events++;
            }

            //Push data to Minematic by chunks
            if(!empty($events_data)){
                
                Mage::helper('connector')->pushData('events', $events_data);

                //$this->emulate_pushData("EVENTS", $events_data, $sync_type); //Remove this line for production
                
            }

            //Set pagination for next page
            $currentPage++;

            //clear collection and free memory
            $eventsCollection->clear();
        } while ($currentPage <= $pages); 

   	}

   	/**
     * Get event type name by event type ID
     * @param  [type]     $event_type_id
     * @return [type]
     * @author edudeleon
     * @date   2015-05-10
     */
    private function _get_event_type_name($event_type_id){
        switch ($event_type_id) {
            case Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW:
                $event_name = "VIEW";
                break;

                case Mage_Reports_Model_Event::EVENT_PRODUCT_SEND:
                $event_name = "SEND";
                break;

                case Mage_Reports_Model_Event::EVENT_PRODUCT_COMPARE:
                $event_name = "COMPARE";
                break;

                case Mage_Reports_Model_Event::EVENT_PRODUCT_TO_CART:
                $event_name = "CART";
                break;

                case Mage_Reports_Model_Event::EVENT_PRODUCT_TO_WISHLIST:
                $event_name = "LIKE";
                break;

                case Mage_Reports_Model_Event::EVENT_WISHLIST_SHARE:
                $event_name = "SHARE";
                break;
            
                default:
                $event_name = "NOT_DEFINED";
                break;
        }
        return $event_name;
    }

    /**
     * Method used to update last synchronzation data by data type
     * @param  [type]     $data_type [ITEMS, USERS, EVENTS]
     * @param  [type]     $sync_type [minematic, magento]
     * @param  [type]     $last_sync_datetime
     * @return [type]
     * @author edudeleon
     * @date   2015-05-11
     */
    private function _update_last_sync_data($data_type, $sync_type, $last_sync_datetime){
        //Update last sync type and datetime for ITEMS
        if($data_type == Minematic_Connector_Model_Config::DATA_TYPE_ITEMS){
            $this->_update_connector_config_value(Minematic_Connector_Model_Config::ITEMS_LAST_SYNC_TYPE, $sync_type);
            $this->_update_connector_config_value(Minematic_Connector_Model_Config::ITEMS_LAST_SYNC_DATETIME, $last_sync_datetime);
        }

        //Update last sync type and datetime for USERS
        if($data_type == Minematic_Connector_Model_Config::DATA_TYPE_USERS){
            $this->_update_connector_config_value(Minematic_Connector_Model_Config::USERS_LAST_SYNC_TYPE, $sync_type);
            $this->_update_connector_config_value(Minematic_Connector_Model_Config::USERS_LAST_SYNC_DATETIME, $last_sync_datetime);
        }

        //Update last sync type and datetime for EVENTS
        if($data_type == Minematic_Connector_Model_Config::DATA_TYPE_EVENTS){
            $this->_update_connector_config_value(Minematic_Connector_Model_Config::EVENTS_LAST_SYNC_TYPE, $sync_type);
            $this->_update_connector_config_value(Minematic_Connector_Model_Config::EVENTS_LAST_SYNC_DATETIME, $last_sync_datetime);
        }
    }

    /**
     * Update config value
     * @param  [type]     $config_name
     * @param  [type]     $config_value
     * @return [type]
     * @author edudeleon
     * @date   2015-05-10
     */
    private function _update_connector_config_value($config_name, $config_value){
        try {
            /**
             * Get the resource model
             */
            $resource = Mage::getSingleton('core/resource');
             
            /**
             * Retrieve the write connection
             */
            $writeConnection = $resource->getConnection('core_write');
         
            /**
             * Retrieve our table name
             */
            $table = $resource->getTableName('minematic_config');
             
            //Update register
            $query = "UPDATE {$table} SET value = '{$config_value}' WHERE name = '"
                     . $config_name ."'";
            
            //Execute the query
            $writeConnection->query($query);

            return;
            
        } catch (Exception $e) {
            //Log Exception Message
            Mage::helper('connector')->logSyncProcess($e->getMessage(), "DATABASE", "ERROR");
        }
    }

    /**
     * Retrieve config value by config name
     * @param  [type]     $config_name
     * @return [type]
     * @author edudeleon
     * @date   2015-05-11
     */
    private function _get_connector_config_value($config_name){
        try {
            /**
             * Get the resource model
             */
            $resource = Mage::getSingleton('core/resource');
             
            /**
             * Retrieve the write connection
             */
            $readConnection = $resource->getConnection('core_read');
         
            /**
             * Retrieve our table name
             */
            $table = $resource->getTableName('minematic_config');
             
            //Update register
            $query = "SELECT value FROM " . $table . " WHERE name = '"
                     . $config_name ."'";
            
            //Execute the query
            $config_value = $readConnection->fetchOne($query);

            return $config_value ? $config_value : FALSE;
            
        } catch (Exception $e) {
            //Log Exception Message
            Mage::helper('connector')->logSyncProcess($e->getMessage(), "DATABASE", "ERROR");
        }

        return FALSE;
    }

    /**
     * Get additional fields and respective getMethods() from Admin Panel by Data Type
     * @param  [type]     $type
     * @param  [type]     $store_id
     * @return [type]
     * @author edudeleon
     * @date   2015-05-11
     */
    private function get_additional_fields($type, $store_id=NULL){
        //Get additional fields
        switch ($type) {
            case Minematic_Connector_Model_Config::DATA_TYPE_ITEMS:
                $custom_fields =  Mage::getStoreConfig(Minematic_Connector_Model_Config::ADDITIONAL_ITEM_FIELDS, $store_id);
                break;

            case Minematic_Connector_Model_Config::DATA_TYPE_USERS:
                $custom_fields =  Mage::getStoreConfig(Minematic_Connector_Model_Config::ADDITIONAL_USER_FIELDS, $store_id);
                break;

            case Minematic_Connector_Model_Config::DATA_TYPE_EVENTS:
                $custom_fields =  Mage::getStoreConfig(Minematic_Connector_Model_Config::ADDITIONAL_EVENT_FIELDS, $store_id);
                break;
            
            default:
                $custom_fields = "";
                break;
        }
        
        $additional_fields = array();
        
        //Prepare field names and get methods
        if($custom_fields){
            $custom_field_names = explode(',', $custom_fields);

            //Get field names
            foreach($custom_field_names as $field_name){
                //Prepare field_name
                $field_name = trim($field_name);

                //Prepare Get method name
                $name_parts = explode('_', $field_name);
                $get_method = "get";
                foreach ($name_parts as $name_part) {
                    $get_method .= ucfirst(strtolower($name_part));
                }

                $additional_fields[$field_name] = $get_method;
            }
        }
        
        //Return array of fields
        return $additional_fields;
    }

    /**
     * Validate product image
     * @param  [type]     $image
     * @return [type]
     * @author minematic
     * @date   2015-05-08
     */
    private static function _validateProductImage($image) {
        if(empty($image)) {
            return false;
        }

        if('no_selection' == $image) {
            return false;
        }

        return true;
    }

    /**
     * Return variable $_result_sync_message value
     * @return [type]
     * @author edudeleon
     * @date   2015-05-11
     */
    public function get_result_sync_message(){
        return $this->_result_sync_message;
    }

    /**
     * Return variable _result_sync_http_code_response value
     * @return [type]
     * @author edudeleon
     * @date   2015-05-11
     */
    public function get_result_sync_http_code_response(){
        return $this->_result_sync_http_code_response;
    }

    /**
     * Method that helps the debuggin of pushing data to Minematic
     * Data is logged in files
     * @param  [type]     $data_type [ITEMS, USERS, EVENTS]
     * @param  [type]     $data
     * @param  [type]     $sync_type
     * @return [type]
     * @author edudeleon
     * @date   2015-05-14
     */
    private function emulate_pushData($data_type, $data, $sync_type=NULL){
        if(empty($sync_type)){
            $sync_type="not_specified";
        }
        // Log name
        $log_filename = $sync_type."_mode_data_pushed_".date('Y-m-d').".log";

        //Format data
        foreach($data as $data_element){
            //Get data_element
            
            $log_line = "{";
            $i = 1;
            foreach ($data_element as $k => $value) {
                if($i != count($data_element)){
                    $log_line .= $k.": ".$value.", ";
                } else {
                    $log_line .= $k.": ".$value;
                }
                $i++;
            }

            $log_line .= "}";

            Mage::log(" [ $data_type ] :: " . $log_line,  null, $log_filename);
        }
    }


}
