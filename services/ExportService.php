<?php
namespace Craft;

class ExportService extends BaseApplicationComponent
{

    private $_service;
    public $delimiter = ExportModel::DelimiterComma;

    public function saveMap($settings, $map)
    {

        // Set criteria
        $criteria = new \CDbCriteria;
        $criteria->condition = 'settings = :settings';
        $criteria->params = array(
            ':settings' => JsonHelper::encode($settings)
        );

        // Check if we have a map already
        $mapRecord = Export_MapRecord::model()->find($criteria);

        if(!count($mapRecord) || $mapRecord->settings != $settings) {

            // Save settings and map to database
            $mapRecord           = new Export_MapRecord();
            $mapRecord->settings = $settings;

        }

        // Save new map to db
        $mapRecord->map = $map;
        $mapRecord->save(false);

    }

    public function download($settings)
    {

        // Get max power
        craft()->config->maxPowerCaptain();

        // Check what service we're gonna need
        $this->_service = 'export_' . strtolower($settings['type']);

        // Create the export template
        $export = "";

        // Get data
        $data = $this->getData($settings);
        $this->setDelimiter($settings);

        // If there is data, process
        if(count($data)) {

            // Count rows
            $rows = 0;

            // Loop trough data
            foreach($data as $element) {

                $row = "";

                // Get fields
                $fields = $this->parseFields($settings, $element);

                // Put down columns
                if(!$rows) {
                    $row .= $this->parseColumns($settings, $element, $fields);
                }

                // Loop trough the fields
                foreach($fields as $handle => $data) {

                    // Parse element data
                    $data = $this->parseElementData($handle, $data);

                    // Parse field data
                    $data = $this->parseFieldData($handle, $data);
                    if(is_numeric($data))
                    {
                        $dotPosition = strrpos($data,'.');
                        $decimals = 0;
                        if($dotPosition > -1)
                        {
                            $decimals = strlen(substr($data, $dotPosition)) -1;
                        }
                        $data = number_format($data,$decimals,',','.');
                    }

                    // Put in quotes and escape
                    $row .= '"'.addcslashes($data, '"').'"'.$this->delimiter;

                }
                if($this->delimiter == ExportModel::DelimiterTab)
                {
                    $row = substr($row, 0, -2);

                }
                else {
                    // Remove last delimiter
                    $row = substr($row, 0, -1);
                }

                // Encode row
                $row = StringHelper::convertToUTF8($row);
                if($this->delimiter == ExportModel::DelimiterTab)
                {
                    $row = str_replace("\\t", "\t", $row);
                }

                // And start a new line
                $row = $row . "\r\n";

                // Append to data
                $export .= $row;

                // Count rows
                $rows++;

            }
        }

        // Return the data to controller
        return $export;

    }

    private function setDelimiter($settings)
    {
        if (isset($settings['elementvars']['delimiter']) && $settings['elementvars']['delimiter'] != '' )
        {
            $delimiter = $settings['elementvars']['delimiter'];
            switch ($delimiter)
            {
                case 'semicolon':
                    $this->delimiter = ExportModel::DelimiterSemicolon;
                    break;
               case 'comma':
                    $this->delimiter = ExportModel::DelimiterComma;
                    break;
                case 'pipe':
                    $this->delimiter = ExportModel::DelimiterPipe;
                    break;
                case 'tab':
                    $this->delimiter = ExportModel::DelimiterTab;
                    break;
                default:
                    break;
            }
        }
    }

    protected function getData($settings)
    {

        // Get other sources
        $sources = craft()->plugins->call('registerExportSource', array($settings));

        // Loop through sources, see if we can get any data
        $data = array();
        foreach($sources as $plugin) {
            if(is_array($plugin)) {
                foreach($plugin as $source) {
                    $data[] = $source;
                }
            }
        }

        // If no data from source, get data by ourselves
        if(!count($data)) {

            // Find data
            $service = $this->_service;
            $criteria = craft()->$service->setCriteria($settings);
            if (isset($settings['elementvars']['filterRelatedCategory']) && $settings['elementvars']['filterRelatedCategory'] != '' )
            {
                $relatedTo = array('or');
                $relatedTo[] = array('targetElement' =>$settings['elementvars']['filterRelatedCategory'], 'field'=> null);
                $criteria->relatedTo = $relatedTo;
            }
            if (isset($settings['elementvars']['filterStatus']) && $settings['elementvars']['filterStatus'] != '' )
            {
                if($settings['elementvars']['filterStatus'] == 'onlyEnabled')
                {
                    $criteria->status = EntryModel::LIVE;
                }
                if($settings['elementvars']['filterStatus'] == 'onlyDisabled')
                {
                    $criteria->status = BaseElementModel::DISABLED;
                }
            }
            // Gather data
            $data = $criteria->find();
        }

        return $data;

    }

    // Parse fields
    protected function parseFields($settings, $element)
    {

        $fields = array();

        // Only get element attributes and content attributes
        if($element instanceof BaseElementModel) {

            // Get service
            $service = $this->_service;
            $attributes = craft()->$service->getAttributes($settings['map'], $element);

        } else {

            // No element, i.e. from export source
            $attributes = $element;

        }

        // Loop through the map
        foreach($settings['map'] as $handle => $data) {

            // Only get checked fields
            if($data['checked'] == '1' && (array_key_exists($handle, $attributes) || array_key_exists(substr($handle, 0, 5), $attributes))) {

                // Fill them with data
                $fields[$handle] = $attributes[$handle];

            }

        }

        return $fields;

    }

    // Parse column names
    protected function parseColumns($settings, $element, $fields)
    {

        $columns = "";

        // Loop trough map
        foreach($settings['map'] as $handle => $data) {

            // If checked
            if($data['checked'] == 1) {

                // Add column
                $columns .= '"'.addcslashes($data['label'], '"').'"'.$this->delimiter;

            }

        }

        if($this->delimiter == ExportModel::DelimiterTab)
        {
            $columns = substr($columns, 0, -2);
        }
        else {
            // Remove last delimiter
            $columns = substr($columns, 0, -1);
        }

        // Encode columns
        $columns = StringHelper::convertToUTF8($columns);

        // And start a new line
        $columns = $columns . "\r\n";

        return $columns;

    }

    // Parse reserved element values
    protected function parseElementData($handle, $data)
    {

        switch($handle) {

            case ExportModel::HandleAuthor:

                // Get username of author
                $data = craft()->users->getUserById($data)->username;

                break;

            case ExportModel::HandleEnabled:

                // Make data human readable
                switch($data) {

                    case "0":
                        $data = Craft::t("No");
                        break;

                    case "1":
                        $data = Craft::t("Yes");
                        break;

                }

                break;

        }

        return $data;

    }

    // Parse field values
    protected function parseFieldData($handle, $data)
    {

        // Do we have any data at all
        if(!is_null($data)) {

            // Get field info
            $field = craft()->fields->getFieldByHandle($handle);

            // If it's a field ofcourse
            if(!is_null($field)) {

                // For some fieldtypes the're special rules
                switch($field->type) {

                    case ExportModel::FieldTypeEntries:
                    case ExportModel::FieldTypeCategories:
                    case ExportModel::FieldTypeAssets:
                    case ExportModel::FieldTypeUsers:

                        // Show names
                        $data = $data instanceof ElementCriteriaModel ? implode(', ', $data->find()) : $data;

                        break;

                    case ExportModel::FieldTypeLightswitch:

                        // Make data human readable
                        switch($data) {

                            case "0":
                                $data = Craft::t("No");
                                break;

                            case "1":
                                $data = Craft::t("Yes");
                                break;

                        }

                        break;

                }

            }

            // Get other operations
            craft()->plugins->call('registerExportOperation', array(&$data, $handle));

        } else {

            // Don't return null, return empty
            $data = "";

        }

        // If it's an array, make it a string
        if(is_array($data)) {
            $data = StringHelper::arrayToString($data);
        }

        // If it's an object, make it a string
        if(is_object($data)) {
            $data = StringHelper::arrayToString(get_object_vars($data));
        }

        return $data;

    }

}