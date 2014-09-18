<?php
namespace Craft;

class ExportController extends BaseController
{

    public $allowAnonymous = array('actionDownload');

    public function actionGetEntryTypes() 
    {
    
        // Only ajax post requests
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        
        // Get section
        $section = craft()->request->getPost('section');
        $section = craft()->sections->getSectionById($section);
        
        // Get entry types
        $entrytypes = $section->getEntryTypes();
        
        // Return JSON
        $this->returnJson($entrytypes);
    
    }

    // Process input for mapping
    public function actionMap() 
    {
    
        // Get import post
        $export = craft()->request->getRequiredPost('export');
        
        // Create token
        $token = craft()->tokens->createToken(array('action' => 'export/download'));
            
        // Send variables to template and display
        $this->renderTemplate('export/_map', array(
            'exportToken' => $token,
            'export' => $export
        ));
    
    }
    
    // Download export
    public function actionDownload() 
    {
    
        // We need a token for this
        $this->requireToken();
        
        // Get export post
        $settings = craft()->request->getRequiredPost('export');
        
        // Get mapping fields
        $map = craft()->request->getParam('fields');
        
        // Set more settings
        $settings = array_merge(array(
            'map' => $map
        ), $settings);
        
        // Get data
        $data = craft()->export->download($settings);
        
        // Download the csv
        craft()->request->sendFile('export.csv', $data, array('forceDownload' => true, 'mimeType' => 'text/csv'));
    
    }
    
}