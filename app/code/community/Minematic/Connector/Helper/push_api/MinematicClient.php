<?php
  class MinematicClient {

      private $api_key;
      private $end_point = 'https://www.minematic.com/mdata/v1/';

      /**
       * Constructor
       * @param  [type]     $api_key
       * @param  [type]     $ep
       * @author edudeleon
       * @date   2015-05-14
       */
      public function __construct($api_key, $ep = null) {
          $this->api_key = $api_key;

          if (!empty($ep))
          {
              $this->end_point = $ep;
          }
      }

      /**
       * Push data to Minematic server
       * @param  [type]     $name
       * @param  [type]     $data
       * @return [type]
       */
      public function pushData($name, $data) 
      {
          if (!empty($name) && !empty($data))
          {
              $endpoint =  $this->end_point . $name;

              $request_data = array(
                  "key" => $this->api_key,
                  $name => $data,
              );

              return $this->push($endpoint, $request_data);
          }
      }

      /**
       * Curh push method
       * @param  [type]     $endpoint
       * @param  [type]     $request_data
       * @param  string     $request_type
       * @return [type]
       */
      private function push($endpoint, $request_data, $request_type = 'post') {
          $request_data = json_encode($request_data);

          $headers  = array('Accept: application/json', 'Content-Type: application/json');

          $handle = curl_init();

          curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($handle, CURLOPT_URL, $endpoint);
          curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
          curl_setopt($handle, CURLOPT_POST, true);
          curl_setopt($handle, CURLOPT_POSTFIELDS, $request_data);

          $result = curl_exec($handle);

          $code   = curl_getinfo($handle, CURLINFO_HTTP_CODE);

          curl_close($handle);

          if ($code > 200)
          {
              Mage::helper('connector')->logSyncProcess('[API response] ' . $code . ' ' . $result, "API " . $endpoint, "POST");
          }

          return $code;
      }
  }