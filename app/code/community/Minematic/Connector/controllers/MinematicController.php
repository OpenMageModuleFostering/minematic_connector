<?php
class Minematic_Connector_MinematicController extends Mage_Core_Controller_Front_Action
{
    /**
     * Method used to verify the module status and security parameters
     * @param  boolean    $ping_action
     * @return [type]
     * @author edudeleon
     * @date   2015-05-10
     */
    private function _init($ping_action = false){
        //Check if module is enabled
        if(!Mage::helper('connector')->isEnabled()) {
            $this->_set_header_response('error', 'Minematic module is not enabled', 400);
            return false;
        }

        //Get credentials
        $api_key     = $this->getRequest()->getHeader(Mage::helper('connector')->getAPIKeyHeaderField());
        $client_id   = $this->getRequest()->getHeader(Mage::helper('connector')->getAPIClientIDHeaderField());

        //Validate credentials
        if (Mage::helper('connector')->valid_credentials($api_key, $client_id)){
            //If ping action, set header response
            if ($ping_action) {
                //Set header respoonse
                $this->_set_header_response('message', 'Ping Successfull', 200); 
            }

            return true;
        
        } else {

            //Set header response for invalid credentials
            $this->_set_header_response('error', 'Invalid API Key or Client Id.', 400);
            return false;
        }

        return true;
    }

    /**
     * Method used to set header responses
     * @param  [type]     $response_type ['message', 'error']
     * @param  [type]     $message
     * @param  [type]     $http_code
     * @author edudeleon
     * @date   2015-05-10
     */
    private function _set_header_response($response_type, $message, $http_code){
        //Prepare Json Message
        $jsonData = Mage::helper('connector')->setReturnMessage($response_type, $message);
            
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setHttpResponseCode($http_code);
        $this->getResponse()->setBody($jsonData);
    }

    /**
     * Method used to check store status (ping)
     * @return [type]
     * @author edudeleon
     * @date   2015-05-10
     */
    public function pingAction(){
        //Call init method to verify module status and security parameters
        $this->_init(true);
        
        return;
    }

    /**
     * Method used to get store summary data 
     * @return [type]
     * @author edudeleon
     * @date   2015-05-10
     */
    public function summaryAction(){
        //Call init method to verify module status and security parameters
        if(!$this->_init()){
            return;
        }

        //Get Post Parameters
        $type  = $this->getRequest()->getPost('type');

        //Get summary data from model
        try {
            $summary_data = Mage::getModel('connector/synchronization')->get_summary_data($type);
        } catch (Exception $e) {
            //Set error response
            $this->_set_header_response('error', $e->getMessage(), 400);
            return;
        }

        //Set response
        $this->_set_header_response('summary', $summary_data, 200);
        return;
    }

    /**
     * Method used to items pull data manually through URL (i.e. http://www.storename.com/connector/minematic/items)
     * @return [type]
     * @author edudeleon
     * @date   2015-05-05
     */
    public function itemsAction(){
        //Call init method to verify module status and security parameters
        if(!$this->_init()){
            return;
        }

        //Get post parameters
        $since   = $this->getRequest()->getPost('since');
        $till    = $this->getRequest()->getPost('till');
        $limit   = $this->getRequest()->getPost('limit');

        try {
            //Intiantate sync model
            $sync_model = Mage::getModel('connector/synchronization');

            //Sync Items
            $sync_model->sync_items_data(Minematic_Connector_Model_Config::SYNC_TYPE_MINEMATIC_SIDE, $since, $till, $limit);

            //Get result sync messagae
            $result_sync_message            = $sync_model->get_result_sync_message();
            $result_sync_http_code_response = $sync_model->get_result_sync_http_code_response();
            
            //Set header response
            $this->_set_header_response('success', $result_sync_message, $result_sync_http_code_response);
            return;
            
        } catch (Exception $e) {
            //Log Error
            Mage::helper('connector')->logSyncProcess($e->getMessage(), Minematic_Connector_Model_Config::SYNC_TYPE_MINEMATIC_SIDE, "ERROR");

            //Set error response
            $this->_set_header_response('error', $e->getMessage(), 400);
        }

        return;
    }

    /**
     * Method used to pull users data manually through URL (i.e. http://www.storename.kcom/connector/minematic/users)
     * @return [type]
     * @author edudeleon
     * @date   2015-05-10
     */
    public function usersAction(){
        //Call init method to verify module status and security parameters
        if(!$this->_init()){
            return;
        }

         //Get post parameters
        $since   = $this->getRequest()->getPost('since');
        $limit   = $this->getRequest()->getPost('limit');

        try {
            //Intiantate sync model
            $sync_model = Mage::getModel('connector/synchronization');

            //Sync users
            $sync_model->sync_users_data(Minematic_Connector_Model_Config::SYNC_TYPE_MINEMATIC_SIDE, $since, $limit);

            //Get result sync messagae
            $result_sync_message            = $sync_model->get_result_sync_message();
            $result_sync_http_code_response = $sync_model->get_result_sync_http_code_response();
            
            //Set header response
            $this->_set_header_response('success', $result_sync_message, $result_sync_http_code_response);
            return;
            
        } catch (Exception $e) {
            //Log Error
            Mage::helper('connector')->logSyncProcess($e->getMessage(), Minematic_Connector_Model_Config::SYNC_TYPE_MINEMATIC_SIDE, "ERROR");

            //Set error response
            $this->_set_header_response('error', $e->getMessage(), 400);
        }

        return;
    }

    /**
     * Method used to pull events data manually through URL (i.e. http://www.storename.com/connector/minematic/events)
     * @return [type]
     * @author edudeleon
     * @date   2015-05-10
     */
    public function eventsAction(){
        //Call init method to verify module status and security parameters
        if(!$this->_init()){
            return;
        }

        //Get Post parameters
        $type    = $this->getRequest()->getPost('type');
        $since   = $this->getRequest()->getPost('since');
        $limit   = $this->getRequest()->getPost('limit');

        try {
            //Intiantate sync model
            $sync_model = Mage::getModel('connector/synchronization');

            //Sync events
            $sync_model->sync_events_data(Minematic_Connector_Model_Config::SYNC_TYPE_MINEMATIC_SIDE, $type, $since, $limit);

            //Get result sync messagae
            $result_sync_message            = $sync_model->get_result_sync_message();
            $result_sync_http_code_response = $sync_model->get_result_sync_http_code_response();
            
            //Set header response
            $this->_set_header_response('success', $result_sync_message, $result_sync_http_code_response);
            return;
            
        } catch (Exception $e) {
            //Log Error
            Mage::helper('connector')->logSyncProcess($e->getMessage(), Minematic_Connector_Model_Config::SYNC_TYPE_MINEMATIC_SIDE, "ERROR");

            //Set error response
            $this->_set_header_response('error', $e->getMessage(), 400);
        }

        return;
    }

   
}