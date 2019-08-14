<?php 
class Minematic_Connector_Helper_Data extends Mage_Core_Helper_Abstract
{
    const M_PULL_LOG_FILENAME    = 'minematic-request.log';
    const M_PUSH_LOG_FILENAME    = 'minematic-push-api.log';
    
    const M_API_PATH             = 'minematic_settings/general/apikey';
    const M_CLIENT_ID_PATH       = 'minematic_settings/general/clientid';
    const M_JAVASCRIPT_CODE_PATH = 'minematic_settings/general/javascript_code';
    const M_ENABLED_PATH         = 'minematic_settings/general/enabled';
    const M_KEY_HEADER           = 'X-MINEMATIC-APIKEY';
    const M_ID_HEADER            = 'X-MINEMATIC-CLIENTID';
    //const M_API_BASE_URI       = 'http://128.199.242.4:8080/mdata/v1/';
    const M_API_BASE_URI         = 'https://www.minematic.com/mdata/v1/';

    public function pushData($type, $data, $limit = 400) {
        //Include Minematic CLient Class		
        include_once("push_api/MinematicClient.php");

        //Intiantate class
        $minematic = new MinematicClient(self::getAPIKey(), self::getMinematicURI());
        
        //Data is pushed depending the page size (limit) specified in the Model
        $result = $minematic->pushData($type, $data);

        return true;
    }

    /**
     * Log Module Error
     * @param  [type]     $msg
     * @param  [type]     $sync_type
     * @return [type]
     * @author edudeleon
     * @date   2015-05-14
     */
    public function logModuleError($msg=null, $sync_type=null){
        if (!empty($msg)){
            Mage::log(" [ $sync_type ] ( $type ) :: " . $msg,  null, "minematic_errors.log");
        }
    }

    /**
     * Log Moduele synchronization process
     * @param  [type]     $msg
     * @param  [type]     $sync_type
     * @param  string     $type
     * @return [type]
     * @author edudeleon
     * @date   2015-05-14
     */
    public function logSyncProcess($msg=null, $sync_type=null, $type="REQUEST"){
        if (!empty($msg)){
            Mage::log(" [ $sync_type ] ( $type ) :: " . $msg, null, "minematic_sync_process.log");
        }
    }
	
	public function getMinematicURI()
	{
		return self::M_API_BASE_URI;
	}

	public function isEnabled($store = null)
    {
        return Mage::getStoreConfig(self::M_ENABLED_PATH, $store);
    }

	public function getAPIKey($store = null)
    {
        return Mage::getStoreConfig(self::M_API_PATH, $store);
    }

	public function getClientID($store = null)
    {
        return Mage::getStoreConfig(self::M_CLIENT_ID_PATH, $store);
    }

    public function getJavascriptCode($store = null){
        return Mage::getStoreConfig(self::M_JAVASCRIPT_CODE_PATH, $store);
    }

    public function getAPIKeyHeaderField() {
    	return self::M_KEY_HEADER;
    }
    
    public function getAPIClientIDHeaderField() {
    	return self::M_ID_HEADER;
    }

    /** deprecated */
    public function logRequests($msg) {
    	if (!empty($msg))
    	{
    		Mage::log("PULL: " . $msg, null, self::M_PULL_LOG_FILENAME);
    	}
    }

    /** deprecated */
    public function logPushAPI($msg = null) {
    	if (!empty($msg))
    	{
    		Mage::log("PUSH: " . $msg, null, self::M_PUSH_LOG_FILENAME);
    	}
    }

    /**
     * Prepare return message in Json format
     * @param  [type]     $key
     * @param  [type]     $msg
     */
    public function setReturnMessage($key, $msg) {
    	if (!empty($msg) && !empty($key)) {
			
			$res = array();
			$res[$key] = $msg;

			return json_encode($res);
    	}
    }

    /**
     * Verify api_key and client Id
     * @param  [type]     $api_key
     * @param  [type]     $client_id
     * @return [type]
     * @author edudeleon
     * @date   2015-05-14
     */
    public function valid_credentials($api_key, $client_id){
        if ($this->getAPIKey() === $api_key && $this->getClientID() === $client_id){
            return TRUE;
        }
        return FALSE;
    }

}
