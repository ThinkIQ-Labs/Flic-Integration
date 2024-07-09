<?php
use TiqUtilities\Model\Node;
use TiqUtilities\Model\Attribute;
use TiqUtilities\GraphQL\GraphQL;

class FlicReceiver extends Node
{

    public $graphQlClient;

    public $mappings;
    public function GetMappings(){
        if($this->mappings==null){
            $aResponse = $this->graphQlClient->MakeRequest($this->MappingsQuery);
            $this->mappings = $aResponse;
        }
        return $this->mappings;
    }

    public $mappingsCompact;
    public function GetMappingsCompact(){
        if($this->mappingsCompact==null){
            $buttonMappings = $this->GetMappings()->data->tiqTypes[0]->objectsByTypeId[0]->childObjects;
            $mappingsCompact = new stdClass();
            foreach($buttonMappings as $i => $aButtonMapping){
                $attributeDisplayNames = array_column($aButtonMapping->attributes, 'displayName');
                
                $includeMapping = false;
                $aMappingCompact = new stdClass();
                $aMappingCompact->singleClickMapping = null;
                $aMappingCompact->doubleClickMapping = null;
                $aMappingCompact->clickHoldMapping = null;

                $aSingleClickMapping = $aButtonMapping->attributes[array_search('Single Click Target', $attributeDisplayNames)]->referencedAttribute;
                if($aSingleClickMapping!=null){
                    $includeMapping = true;
                    $aMappingCompact->singleClickMapping = $aSingleClickMapping->id;
                }

                $aDoubleClickMapping = $aButtonMapping->attributes[array_search('Double Click Target', $attributeDisplayNames)]->referencedAttribute;
                if($aDoubleClickMapping!=null){
                    $includeMapping = true;
                    $aMappingCompact->doubleClickMapping = $aDoubleClickMapping->id;
                }

                $aClickHoldMapping = $aButtonMapping->attributes[array_search('Click Hold Target', $attributeDisplayNames)]->referencedAttribute;
                if($aClickHoldMapping!=null){
                    $includeMapping = true;
                    $aMappingCompact->clickHoldMapping = $aClickHoldMapping->id;
                }

                $aMappingCompact->batteryStatusMapping = $aButtonMapping->attributes[array_search('Battery Status', $attributeDisplayNames)]->id;

                if($includeMapping){
                    $aMacAddress = $aButtonMapping->attributes[array_search('mac address', $attributeDisplayNames)]->stringValue;
                    $mappingsCompact->$aMacAddress = $aMappingCompact;
                }
            }
            $this->mappingsCompact = $mappingsCompact;
        }
        return $this->mappingsCompact;
    }

    public function GetMessagesRaw($start_time = null, $end_time = null){
        $data = $this->attributes['messages']->getTimeseries($start_time, $end_time);
        return $data;
    }

    public function GetMessagesBySourceAndEventType($start_time = null, $end_time = null){
        
        $startTime = new DateTime($start_time);
        $endTime = new DateTime($end_time);

        $messages = $this->GetMessagesRaw($start_time, $end_time);

        $messagesBySourceAndEventType = [];
        foreach($messages['values'] as $i => $aMessage){
            $aTimeStamp = new DateTime($messages['timestamps'][$i]);
            if($aTimeStamp >= $startTime && $aTimeStamp <= $endTime){
                $aMessageObj = json_decode($aMessage);
                if(!array_key_exists($aMessageObj->bdaddr, $messagesBySourceAndEventType)){
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]=[];
                    
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['single'] = [];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['single']['timestamps'] = [];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['single']['values'] = [];
                    
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['double'] = [];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['double']['timestamps'] = [];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['double']['values'] = [];

                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['hold'] = [];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['hold']['timestamps'] = [];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['hold']['values'] = [];

                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['battery'] = [];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['battery']['timestamps'] = [];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['battery']['values'] = [];

                }
                if($aMessageObj->isSingleClick){
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['single']['timestamps'][] = $messages['timestamps'][$i];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['single']['values'][] = 1;
                }
                if($aMessageObj->isDoubleClick){
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['double']['timestamps'][] = $messages['timestamps'][$i];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['double']['values'][] = 1;
                }
                if($aMessageObj->isHold){
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['hold']['timestamps'][] = $messages['timestamps'][$i];
                    $messagesBySourceAndEventType[$aMessageObj->bdaddr]['hold']['values'][] = 1;
                }

                $messagesBySourceAndEventType[$aMessageObj->bdaddr]['battery']['timestamps'][] = $messages['timestamps'][$i];
                $messagesBySourceAndEventType[$aMessageObj->bdaddr]['battery']['values'][] = $aMessageObj->button->batteryStatus;
            }
        }

        return $messagesBySourceAndEventType;

    }

    public function __construct($identifier = null, array $config = [])
	{
		parent::__construct($identifier, $config);
		$this->getAttributes(lazy_load:true);
        $this->graphQlClient = new GraphQL();
	}

    public $MappingsQuery = '
        query q1 {
            tiqTypes(condition: { displayName: "FlicReceiver" }) {
                id
                objectsByTypeId {
                    id
                    displayName
                    relativeName
                    childObjects {
                        id
                        displayName
                        relativeName
                        attributes {
                            id
                            displayName
                            relativeName
                            stringValue
                            referencedAttribute {
                                id
                                displayName
                                currentValue{
                                    timestamp
                                    value
                                }
                            }
                        }
                    }
                }
            }
        }
    ';


    public function updateData($start_time = null, $end_time = null)
	{
        $messagesBySourceAndEventType = $this->GetMessagesBySourceAndEventType($start_time, $end_time);

        if(count($messagesBySourceAndEventType) == 0) return;

        $mappingsCompact = $this->GetMappingsCompact();
        foreach($mappingsCompact as $aBdaddr => $aButtonMapping) {

            if($aButtonMapping->singleClickMapping!=null && array_key_exists($aBdaddr, $messagesBySourceAndEventType)){
                if(count($messagesBySourceAndEventType[$aBdaddr]['single']['values'])>0){
                    $aTargetAttribute = new Attribute($aButtonMapping->singleClickMapping);
                    // $aTargetAttribute->insertTimeseries($messagesBySourceAndEventType[$aBdaddr]['single']['values'], $messagesBySourceAndEventType[$aBdaddr]['single']['timestamps']);
                }
            }

            if($aButtonMapping->doubleClickMapping!=null && array_key_exists($aBdaddr, $messagesBySourceAndEventType)){ 
                if(count($messagesBySourceAndEventType[$aBdaddr]['double']['values'])>0){
                    $aTargetAttribute = new Attribute($aButtonMapping->doubleClickMapping);
                    // $aTargetAttribute->insertTimeseries($messagesBySourceAndEventType[$aBdaddr]['double']['values'], $messagesBySourceAndEventType[$aBdaddr]['double']['timestamps']);
                }
            }

            if($aButtonMapping->clickHoldMapping!=null && array_key_exists($aBdaddr, $messagesBySourceAndEventType)){ 
                if(count($messagesBySourceAndEventType[$aBdaddr]['hold']['values'])>0){
                    $aTargetAttribute = new Attribute($aButtonMapping->clickHoldMapping);
                    // $aTargetAttribute->insertTimeseries($messagesBySourceAndEventType[$aBdaddr]['hold']['values'], $messagesBySourceAndEventType[$aBdaddr]['hold']['timestamps']);
                }
            }

            if(array_key_exists($aBdaddr, $messagesBySourceAndEventType)){ 
                if(count($messagesBySourceAndEventType[$aBdaddr]['battery']['values'])>0){
                    $aBatteryStatusAttribute = new Attribute($aButtonMapping->batteryStatusMapping);
                    $aBatteryStatusAttribute->insertTimeseries($messagesBySourceAndEventType[$aBdaddr]['battery']['values'], $messagesBySourceAndEventType[$aBdaddr]['battery']['timestamps']);
                }
            }
            
        }

    }
}