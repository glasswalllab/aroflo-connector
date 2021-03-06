<?php

namespace glasswalllab\arofloconnector;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use glasswalllab\arofloconnector\Models\ApiLog;

class ArofloConnector
{
    const CONTENT_TYPE = 'application/x-www-form-urlencoded';
    const ACCEPT_TYPE = 'text/json';
    const LIMIT = 500;

    private $page = 2; //Set to 2 as the first call is already completed
    private $authoisation;
    private $afdatetimeutc;
    private $responses;

    public function getPostfields($zone,array $joins, array $wheres,$page,$postxml) {
        $this->setAfdatetimeutc();

        $zone = urlencode($zone);

        $joins_string = '';
        if(sizeof($joins) > 0){
            $joins_string = '&joins='.implode("",$joins);
        }

        $wheres_string = '';
        if(sizeof($wheres) > 0){
            foreach($wheres as $index => $where)
            {
                if($index === array_key_last($wheres)){
                    $wheres_string = $wheres_string.'&where='.urlencode($where);
                } else {
                    $wheres_string = $wheres_string.'&where='.urlencode($where).',';
                }
            }
        }

        $page_string = '';
        if(!is_null($page) || $page === '') {
            $page_string = '&page='.$page;
        }

        $postxml_string = '';
        if(!is_null($postxml) || $postxml === '') {
            $postxml_string = '&postxml='.$postxml;
        }

        return 'zone='.$zone.$joins_string.$wheres_string.$page_string.$postxml_string;
    }

    public function generateHMAC($method,$postfields) {

        $accept = self::ACCEPT_TYPE;
        $urlPath = '';
        
        $payload = array();
        array_push($payload,$method);
        array_push($payload,$urlPath);
        array_push($payload,$accept);
        array_push($payload,$this->authorisation);
        array_push($payload,$this->afdatetimeutc);
        array_push($payload,$postfields);
        $payloadString = implode('+',$payload);
        
        return hash_hmac('sha512',$payloadString,config('ArofloConnector.secret'));
    }

    public function setAuthorisation() {
        $this->authorisation = $authorisation = "uencoded=".urlencode(config('ArofloConnector.uencode'))."&pencoded=".urlencode(config('ArofloConnector.applicationkey'))."&orgEncoded=".urlencode(config('ArofloConnector.orgencode'));
    }

    public function setAfdatetimeutc() {
        date_default_timezone_set('UTC');
        $this->afdatetimeutc = date("Y-m-d\TH:i:s.u\Z", time()); 
    }

    public function getHeader($hmac) {
        return [
            'Authentication' => 'HMAC '.$hmac,
            'Authorization' => $this->authorisation,
            'Accept' => self::ACCEPT_TYPE,
            'afdatetimeutc' => $this->afdatetimeutc,
            'Content-Type' => self::CONTENT_TYPE
        ];
    }

    public function getUrl() {
        return config('ArofloConnector.baseUrl');
    }

    public function CallAroflo($zone, $joins, $wheres, $postxml, $method, $page)
    {  
        $this->setAuthorisation();
 
        
        
        try
        {
            //POST or PUT request - contains parameter data, no pagination required - return array
            if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {

                $postfields = $this->getPostfields($zone,$joins,$wheres,$page,$postxml);
                $hmac = $this->generateHMAC($method,$postfields);
                $header = $this->getHeader($hmac);

                $log = ApiLog::create([
                    'service' => 'aroflo',
                    'resource' => $this->getUrl(),
                    'method' => $method,
                    'request' => json_encode($postfields),
                ]);

                $baseCall = Http::withHeaders($header)->retry(3, 500);

                switch($method) {
                    case 'POST':
                        $this->responses = $baseCall->withBody($postfields,self::CONTENT_TYPE)->post($this->getUrl())->body();
                    break;

                    case 'PUT':
                        $this->responses = $baseCall->withBody($postfields,self::CONTENT_TYPE)->put($this->getUrl())->body();
                    break;

                    case 'PATCH':
                        $this->responses = $baseCall->withBody($postfields,self::CONTENT_TYPE)->patch($this->getUrl())->body();
                    break;

                    case 'DELETE':
                        $this->responses = $baseCall->withBody($postfields,self::CONTENT_TYPE)->delete($this->getUrl())->body();
                    break;
                }

                $status_code = json_decode($this->responses)->status;
                $log->code = $status_code;
                $log->response = $this->responses;
                $log->save();

                //prevent more than 2 calls per second
                usleep(500000);

                //6 = Too many requests
                if($status_code == 6) {
                    sleep(5);
                    $this->CallAroflo($zone, $joins, $wheres, $postxml, $method, $page);   
                }

            //GET request - check for pagination - return array    
            } elseif($method === 'GET') {

                $postfields = $this->getPostfields($zone,$joins,$wheres,$page,'');
                $hmac = $this->generateHMAC($method,$postfields);
                $header = $this->getHeader($hmac);
                
                $log_first_call = ApiLog::create([
                    'service' => 'aroflo',
                    'resource' => $this->getUrl(),
                    'method' => $method,
                    'request' => $postfields,
                ]);
              
                $response = Http::withHeaders($header)->retry(3, 500)->get($this->getUrl(),$postfields)->body();
                $log_first_call->response = $response;
                
                $log_first_call->code = json_decode($response)->status;
                $log_first_call->save();

                $json = json_decode($response);

                //Check total items, returned with first call
                if(!is_null($json)) {
                    $total = $json->zoneresponse->currentpageresults;

                    if(isset($total)) {
                        $this->responses[] = $response;
                        if($total > self::LIMIT)
                        {   
                            //Start for loop at 2, as page 1 has already been retrieved - ceil = rounds up to nearest whole number             
                            for($i=$this->page;$i<=(ceil($total/self::LIMIT)); $i++)
                            {
                                $postfields = $this->getPostfields($zone,$joins,$wheres,$this->page,'');
                                $hmac = $this->generateHMAC($method,$postfields);
                                $header = $this->getHeader($hmac);

                                $this->page = $i;
                                
                                $log_additional_call = ApiLog::create([
                                    'service' => 'aroflo',
                                    'resource' => $this->getUrl(),
                                    'method' => $method,
                                    'request' => $postfields,
                                ]);

                                $response = Http::withHeaders($header)->retry(3, 500)->get($this->getUrl(),$postfields)->body();

                                $status_code = json_decode($response)->status;
                                $log_additional_call->response = $response;
                                $log_additional_call->code = $status_code;
                                $log_additional_call->save();


                                //prevent more than 2 calls per second
                                usleep(500000);

                                //6 = Too many requests
                                if($status_code == 6) {
                                    sleep(5);
                                    $this->CallAroflo($zone, $joins, $wheres, $postxml, $method, $page);
                                } else {
                                    $this->responses[] = $response;
                                }
                            }
                        }
                    }
                }
            }
            
            //return results as an array
            return $this->responses;

        } catch (RequestException $e) {
            if ($e->getCode() === 503 || $e->getCode() === 429) {
                // API limit exceeded
                sleep(5);
                return $this->CallAroflo($endpoint,$method,$body);
            }
            return $e;
        }
    }
}