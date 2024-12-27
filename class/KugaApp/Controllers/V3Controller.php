<?php
namespace KugaApp\Controllers;
use Kuga\Core\Api\ApiService;
use Kuga\Core\Api\Request\BaseRequest as KugaRequest;
use Kuga\Core\Service\ApiAccessLogService;
use Kuga\Module\Acc\Model\AppModel;
use Phalcon\Mvc\Controller;

class V3Controller extends Controller {
    public  function gateway($method=''){
        $requestMethod = $this->request->getMethod();
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
//        if(!$method && isset($requestData['method']) && $requestData['method']){
//            $method = $requestData['method'];
//        }

        $headers = $this->request->getHeaders();
        $locale = isset($headers['Locale'])?$headers['Locale']:$this->config->path('system.locale');
        if($locale=='zh'){
            $locale='zh_CN';
        }
        $this->getDI()->get('translator')->setLocale(\LC_MESSAGES, $locale,$this->config->path('system.charset'));
        $requestObject = new KugaRequest($requestData);
        $requestObject->setOrigRequest($this->request);
        $requestObject->setMethod($method);
        $requestObject->setHeaders($headers);
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
    /**
     * 刷新app list cache
     */
    public function refreshApplistCache(){
        $appModel = new AppModel();
        $appModel->freshCache();
    }
    /**
     * 清api logs 缓存
     */
    public function clearApiLogs(){
        $logService = new ApiAccessLogService($this->getDI());
        $logService->flush();
    }
}