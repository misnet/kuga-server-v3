<?php
namespace KugaApp\Controllers;
use Phalcon\Mvc\Controller;
use Kuga\Core\File\FileRequire;
use Kuga\Module\Acc\Model\Oauth2Model;
use Kuga\Module\Acc\Model\UserBindAppModel;
use Kuga\Module\Acc\Model\UserModel;
use League\OAuth2\Client\Provider\Google;
use NomisCZ\OAuth2\Client\Provider\WeChat;
class OauthController extends Controller {
    private function getOauthConfig($client){
        $config = $this->getDI()->getShared('config');
        $oauthFile = $config['oauth'];
        if(!file_exists($oauthFile)){
            throw new \Exception($this->translator->_('OAuth认证配置文件不存在'));
        }
        $content = file_get_contents($oauthFile);

        $options = json_decode($content,true);

        if(!$options[$client]||!$options[$client]['redirect']){
            throw new \Exception($this->getDI()->getShared('translator')->_('OAuth配置不正确，必须要有redirect配置项'));
        }
        return $options[$client];
    }

    /**
     * 根据code取得授权信息
     */
    public function getAuthorization(){
        $code = $this->request->get('code');
        $storage = $this->getDI()->getShared('simpleStorage');
        $result  = $storage->get($code);
        if(!$result){
            $this->setJsonResponse([
                'status'=>99999,
                'data'=>false
            ]);
        }else{
            $this->setJsonResponse([
                'status'=>0,
                'data'=>$result
            ]);
        }
    }
    private function verifyState($state){
        $query = $state;
        parse_str($query,$c);
        if(!$c['url'] ||!$c['security_token']||!$c['appkey']){
            $this->showMsg('state值无效');
        }
        return $c;
    }
    private function getRedirectUrl($url,$params){
        if(stripos($url,'?')!==false){
            return $url.'&'.http_build_query($params);
        }else{
            return $url.'?'.http_build_query($params);
        }
    }
    public function testStateAction(){
        $params['url'] = 'http://localhost:8001';
        $params['security_token'] = md5($params['url']);
        $params['appkey'] = 1001;
        $query= urlencode(http_build_query($params));
        echo 'original:'.$query;
        echo "<br/>\n\n";
        if(version_compare(PHP_VERSION,'8.0')>=0){
            $c = parse_str($query);
        }else{
            parse_str($query,$c);
        }
        print_r($c);
        exit;
    }
    /**
     * 登陆完成后，生成code和对应的用户信息
     * 第三方的登陆参见流程：https://processon.com/diagraming/60dc7c620e3e745b089e8a75
     * 使用的第三方插件：https://github.com/thephpleague/
     * oauth client处理
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function client(){
        $client = $this->request->get('client');
        $config = $this->getOauthConfig($client);
        //state的值是一个urlencode后的字串，包含url和security_token两个字段，url是验证成功后要返回的地址
        //example:state=url%3Dhttp%253A%252F%252Flocalhost%253A8001%26security_token%3Ded134db40b552845ba0e7980b7765dbf
        //要将url和security_token拼接起来，再urlencode
        $state = $this->request->get('state');
        $stateInfo  = $this->verifyState($state);
        $config['state'] = $state;
        $adapter = $this->_createAdapter($config,$client);
        $code = $this->request->get('code');
        if(empty($code)){
            //未授权，要跳转
            $authUrl = $adapter->getAuthorizationUrl(['state'=>urlencode($state)]);
            header('Location: ' . $authUrl);
            exit;
        }else{
            //拿到code

            if(empty($state)){
                //不会发生，前面verifyState已处理
            }else{
                $token = $adapter->getAccessToken('authorization_code',['code'=>$code]);

                try{
                    $ownerDetails = $adapter->getResourceOwner($token);
                    $data = $this->_formatResponse($client,$ownerDetails,$stateInfo);
//                    $data['oauthApp']= $client;
//                    $data['oauthId'] = $ownerDetails->getId();
//                    $data['name'] = $ownerDetails->getName();
//                    $data['email'] = $ownerDetails->getEmail();
//                    $data['avatar'] = $ownerDetails->getAvatar();
//                    $data['username'] = $client.'_'.$data['oauthId'];
//                    $data['appkey'] = $stateInfo['appkey'];
                    $storage = $this->getDI()->getShared('simpleStorage');
                    $row = Oauth2Model::findFirst([
                        'oauthApp=:app: and oauthId=:aid:',
                        'bind'=>['app'=>$client,'aid'=>$data['oauthId']]
                    ]);
                    $bindUrl = $this->getRedirectUrl($stateInfo['url'],[
                        'avatar'=>$data['avatar'],
//                        'client'=>$data['oauthApp'],
                        'oauthId'=>$data['oauthId'],
                        'name'=>$data['name'],
                        'security_token'=>$stateInfo['security_token']
                    ]);
                    if(!$row){
                        //创建 oauth 记录，跳到绑定界面
                        $oauth = new Oauth2Model();
                        $oauth->initData($data);
                        $oauth->lastLoginTime = time();
                        $oauth->avatarUrl = $data['avatar'];
                        $oauth->userId = 0;
                        $content = file_get_contents($oauth->avatarUrl);
                        $fr= new FileRequire();
                        $fr->newFilename = 'acc/avatar/'.uniqid();
                        $fr->maxFilesize = 10*1024*1024;

                        $file = QING_TMP_PATH.'/'.uniqid();
                        file_put_contents($file,$content);
                        $mime = mime_content_type($file);
                        switch($mime){
                            case 'image/jpeg':
                                $fr->newFilename.='.jpg';
                                break;
                            case 'image/png':
                                $fr->newFilename.='.png';
                                break;
                            case 'image/gif':
                                $fr->newFilename.='.gif';
                                break;
                        }
                        $uploader = $this->getDI()->getShared('fileStorage');

                        $oauth->avatarUrl = $uploader->upload($file,$fr);
                        $result = $oauth->create();
                        header('Location:'.$bindUrl);
                        exit;
                    }else{
                        if($row->userId > 0){
                            //已绑定
                            $key = bin2hex(random_bytes(32 / 2));
                            $lifetime = 120;
                            $storage->set($key,$row->userId);
                            $storage->expired($key,$lifetime);
                            header('Location:'.$this->getRedirectUrl($stateInfo['url'],['code'=>$key,'security_token'=>$stateInfo['security_token']]));
                            exit;
                        }else{
                            //未绑定
                            header('Location:'.$bindUrl);
                            exit;
                        }
                    }
                }catch(\Exception $e){
                    $this->showMsg('Something went wrong: ' . $e->getMessage());
                }
            }
        }
    }
    private function _getBindUrl($bindUrl, $oauthInfo){
        if(stripos($bindUrl,'?')!==-1){
            $w = '?';
        }else{
            $w = '&';
        }
        return $bindUrl.$w.http_build_query($oauthInfo);
    }
    private function _formatResponse($client,$resourceOwner,$stateInfo){
        $data = [];
        $data['oauthApp']= $client;
        $data['username'] = $client.'_'.$data['oauthId'];
        $data['appkey'] = $stateInfo['appkey'];
        $data['mobile'] = '';
        $data['email']  = '';
        switch($client){
            case 'google':
                $data['oauthId'] = $resourceOwner->getId();
                $data['name'] = $resourceOwner->getName();
                $data['email'] = $resourceOwner->getEmail();
                $data['avatar'] = $resourceOwner->getAvatar();
                break;
            case 'wechat':
                $data['oauthId'] = $resourceOwner->getUnionId();//getId
                $data['name'] = $resourceOwner->getNickname();
                $data['avatar'] = $resourceOwner->getHeadImgUrl();
        }
        return $data;
    }
    /**
     * 创建用户，建立绑定
     * @param $data
     * @return UserModel
     */
    private function _createUser($data)
    {
        $model = new UserModel();
        $tx = $this->getDI()->getShared('transactions');
        $transaction = $tx->get();
        $model->accBeforeSave(false);
        $model->username = $data['username'];
        $model->memo = '';
        $model->fullname = $data['name'];
        $model->password = uniqid();
        $model->setTransaction($transaction);
        if($data['mobile']){
            $model->mobile = $data['mobile'];
            $model->mobileVerified=1;
        }
        if ($data['email']){
            $model->email = $data['email'];
            $model->emailVerified = 1;
        }
        $model->createTime    = time();
        $model->lastVisitIp   = \Qing\Lib\Utils::getClientIp();
        $model->lastVisitTime = $model->createTime;
        $result               = $model->create();
        if($result){
            $bindModel = new UserBindAppModel();
            $bindModel->appId = $data['appkey'];
            $bindModel->setTransaction($transaction);
            $bindModel->uid   = $model->uid;
            $result3 = $bindModel->create();
            if(!$result3){
                $transaction->rollback('用户绑定应用失败');
            }
            $oauth = new Oauth2Model();
            $oauth->setTransaction($transaction);
            $oauth->userId = $model->uid;
            $oauth->oauthId= $data['oauthId'];
            $oauth->oauthApp =$data['oauthApp'];
            $oauth->name  = $data['name'];
            $oauth->email = $data['email'];
            $oauth->mobile= $data['mobile'];
            $oauth->avatarUrl     = $data['avatar'];
            $oauth->lastLoginTime = time();
            $result2 = $oauth->create();
            if(!$result2){
                $transaction->rollback($this->htmlWrapMsg('用户创建失败'));
            }else{
                $transaction->commit();
            }
        }else{
            $transaction->rollback($this->htmlWrapMsg('用户创建失败'));
        }
        return $model;
    }
    private function _createAdapter($config,$client){
        switch($client){
            case 'google':
                return new Google([
                    'clientId'=>$config['clientId'],
                    'clientSecret'=>$config['clientSecret'],
                    'redirectUri'=>$config['redirect']
                    //'accessType'=>'offline'
                ]);
                break;
            case 'wechat':
                return new WeChat([
                    'appid' => $config['clientId'],
                    'secret' => $config['clientSecret'],
                    'redirect_uri' => $config['redirect']
                ]);
                break;
            default:
                $this->showMsg('该第三方登陆尚未接入');
        }
    }
    private function htmlWrapMsg($msg){
        return '<html><meta charset="utf-8"/><body>'.$msg.'</body></html>';
    }
    private function showMsg($msg){
        die($this->htmlWrapMsg($msg));
    }
}