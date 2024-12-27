<?php
namespace KugaApp\Controllers;
use Kuga\Core\Api\ApiService;
use Kuga\Core\Api\Request\JwtRequest as KugaRequest;
use Phalcon\Mvc\Controller;

class V4Controller extends Controller {
    /**
     * API服务网关地址，这种书写方式有利于nginx的rewrite分发
     * http://api.xxx.com/v4/gateway/console.user.login
     * @param string $method
     */
    public function gateway($method=''){
        $contentType = $this->request->getContentType();
        //post json格式时
        if(preg_match('/application\/json/',$contentType)){
            $requestData = file_get_contents('php://input');
            $requestData = json_decode($requestData,true);
            $requestData = \Qing\Lib\Utils::objectToArray($requestData);
        }else{
            //post 表单格式时
            $requestData = $this->request->getPost();
        }
        $headers = $this->request->getHeaders();
        //$locale = $this->request->get('locale','string','zh_CN');
        $locale = isset($headers['Locale'])?$headers['Locale']:$this->config->path('system.locale');
        if($locale=='zh'){
            $locale='zh_CN';
        }
        $this->getDI()->getShared('translator')->setLocale(LC_MESSAGES, $locale,$this->config->path('system.charset'));
        $requestObject = new KugaRequest($requestData);
        $requestObject->setOrigRequest($this->request);
        $requestObject->setMethod($method);
        $requestObject->setHeaders($headers);
        $requestObject->setDI($this->getDI());
        ApiService::setDi($this->getDI());
        ApiService::initApiJsonConfigFile(QING_ROOT_PATH.'/config/api/api.json');
        $result = ApiService::response($requestObject);
        switch($requestObject->getFormat()){
            case 'text':
                $this->response->setContent($result);
                break;
            case 'json':
            default:
                $this->setJsonResponse($result);
        }
    }
    private function setJsonResponse($data){
        $this->response->setHeader('Content-Type', 'application/json; charset=utf8');
        $data = json_encode($data);
        $this->response->setContent($data)->send();
    }
}