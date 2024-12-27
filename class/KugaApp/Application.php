<?php
namespace KugaApp;
use KugaApp\Controllers\V3Controller;
use KugaApp\Controllers\V4Controller;
use KugaApp\Controllers\OauthController;
use Phalcon\Mvc\Micro\Collection as MicroCollection;

class Application{
    public static function initHandlerCollection(){

        $collection = new MicroCollection();
        $collection->setHandler(new V3Controller());
        $collection->setPrefix('/v3');
        $collection->get('/gateway','gateway');
        $collection->post('/gateway','gateway');
        $collection->post('/gateway/{method}','gateway');
        $collection->get('/clear-apilogs','clearApiLogs');
        $collection->get('/refresh-applist-cache','refreshApplistCache');

        $oauthCollection = new MicroCollection();
        $oauthCollection->setHandler(new OauthController());
        $oauthCollection->setPrefix('/oauth');
        $oauthCollection->get('/get-authorization','getAuthorization');
        $oauthCollection->get('/client','client');

        $v4Collection = new MicroCollection();
        $v4Collection->setHandler(new V4Controller());
        $v4Collection->setPrefix('/v4');
        $v4Collection->get('/gateway','gateway');
        $v4Collection->post('/gateway','gateway');
        $v4Collection->post('/gateway/{method}','gateway');


        return [$collection,$v4Collection,$oauthCollection];
    }
}