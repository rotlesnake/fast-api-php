<?php
namespace FastApiPHP;


class Response
{

    public $APP;
    public $code = 200;
    public $headers = ["Content-Type"=>"application/json"];
    public $body = null;


    public function __construct($APP=null)
    {
        $this->APP = $APP;
    }

    public function setResponseCode($code)
    {
        $this->code = $code;
    }
    
    public function setContentType($type)
    {
        if ($type=="json") $type="application/json";
        if ($type=="pdf") $type="application/pdf";
        if ($type=="html") $type="text/html";
        if ($type=="xml") $type="application/xml";
        if ($type=="file") $type="application/octet-stream";
        
        $this->headers['Content-Type'] = $type;
    }
 
    public function setBody($body)
    {
        $this->body = $body;
    }

    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }


    public function send()
    {
       http_response_code($this->code);
       foreach ($this->headers as $k=>$v) {
          header("$k: $v");
       }
       
       if (gettype($this->body)=="object" || gettype($this->body)=="array") {
           $this->body = json_encode($this->body);
       }

       echo $this->body;
       die();
    }


    public function redirect($url){
        if (substr($url,0,1)=="/") $url = substr($url,1);

        $APP = \FastApiPHP\App::getInstance();
        header("Location: ".$APP->ROOT_URL.$url);
        die();
    }


    public function sendSuccess($body=[], $responseCode=200)
    {
        $this->sendResult($body, $responseCode, null);
    }

    public function sendError($body=[], $responseCode=500, $errorObject=["code"=>1, "message"=>"unknown"])
    {
        $this->sendResult($body, $responseCode, $errorObject);
    }

    public function sendResult($body=[], $responseCode=200, $errorObject=null)
    {
        $this->code = $responseCode;
       
        $this->body = [];
        $this->body["ok"] = $errorObject == null && $responseCode >= 200 && $responseCode <= 299;
        if ($errorObject) {
            $this->body["error"] = $errorObject;
            if ($body) $this->body["errorData"] = $body;
        } else {
            $this->body["result"] = $body;
        }

        $this->APP->duration->finish("app_run");
        if ($this->APP && $this->APP->duration->get()) $this->body["times"] = $this->APP->duration->get();

        $this->send();
    }


}
