<?php

use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.core.js',            array('version' => 'auto', 'relative' => false));
// HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.tiqGraphQL.js',      array('version' => 'auto', 'relative' => false));
HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.components.min.js',  array('version' => 'auto', 'relative' => false));
// HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.charts.min.js',      array('version' => 'auto', 'relative' => false));

require_once 'thinkiq_context.php';
$context = new Context();

use Joomla\CMS\Factory;
$user = Factory::getUser();

?>

<div id="app">

    <wait-indicator
        :display='showWaitIndicator'
        mode='Regular'
	></wait-indicator>

    <div class="row">            
        <div class="col-12">
            <h1 class="pb-2 pt-2" style="font-size:2.5rem; color:#126181;">
                {{pageTitle}}
                <a v-if="true" class="float-end btn btn-sm btn-link mt-2" style="font-size:1rem; color:#126181;" v-bind:href="`/index.php?option=com_modeleditor&view=script&id=${context.std_inputs.script_id}`" target="_blank">source</a>
            </h1>
            <hr style="border-color:#126181; border-width:medium;" />
        </div>   
    </div>

    <div class="row">
        <div class="col-4" v-for="aButton in buttons">
            <div class="card my-2" style="width: 30rem; height: 610px;" :style="aButton.events[0].i==1 ? 'background-color: aliceblue;' : ''" >
                <div class="card-body">
                    <h5 class="card-title">{{aButton.name}}</h5>
                    <p class="card-text">{{aButton.bdaddr}}</p>
                    <p class="card-text">s-d-h: {{aButton.events.filter(x=>x.isSingleClick).length}}-{{aButton.events.filter(x=>x.isDoubleClick).length}}-{{aButton.events.filter(x=>x.isHold).length}}</p>
                    <p class="card-text">last click: {{aButton.events[0].ts.format('lll')}} (top {{aButton.events[0].i}})
                    <div v-if="aButton.buttonMapping==null">
                        <button class="btn btn-secondary" @click="AddButtonAsync(aButton)"><i class="fa-regular fa-layer-plus me-2"></i>add mapping</button>
                    </div>
                    <div v-else>
                        <div v-for="aMapping in aButton.buttonMapping.mappings.sort((a,b)=> a.attributes.find(x=>x.displayName=='Click Event Type').enumerationName < b.attributes.find(x=>x.displayName=='Click Event Type').enumerationName ? 1 : -1)">
                            <div style="background-color:lightgray;" class="my-1 p-2">
                                <h5 class="card-title">
                                    {{aMapping.attributes.find(x=>x.displayName=='Click Event Type').enumerationName}}
                                    <button class="btn btn-sm float-end" @click="ResetMapping(aMapping)" style="transform: translateY(-5px);" data-toggle="tooltip" title="Reset mapping: clears mode, value, and target.">
                                        <i>reset</i>
                                    </button>
                                </h5>
                                <div class="my-2">
                                    <div class="input-group">
                                        <select class="form-select input-group-prepend" v-model="aMapping.attributes.find(x=>x.displayName=='Flic Mode').enumerationValue" data-toggle="tooltip" title="Select Flic mode.">
                                            <option disabled value="">Please select one</option>
                                            <option v-for="(option, i) in aMapping.attributes.find(x=>x.displayName=='Flic Mode').enumerationType.enumerationNames" :value="aMapping.attributes.find(x=>x.displayName=='Flic Mode').enumerationValues[i]">
                                                {{option}}
                                            </option>
                                        </select>
                                        <input type="text" class="form-control" placeholder="" v-model="aMapping.attributes.find(x=>x.displayName=='Argument').stringValue" data-toggle="tooltip" title="Value to apply.">
                                    </div>
                                </div>
                                <div class="my-2 row">
                                    <div class="col-1 pt-3">
                                        <span data-toggle="tooltip" title="Pick target attribute."><tree-picker class="my-2"
                                            :picker-name='`mapping_target_${aMapping.id}`'
                                            display-mode='instance'
                                            content='Select a target attribute'
                                            :height='500'
                                            default-expand-levels='0'
                                            :default-root-node-fqn='null'
                                            :default-root-node-id='null'
                                            :prune-branches='false'
                                            :branch-types='["organization","place,equipment","gateway","connector","opcua_object","object","material","person","attribute"]'
                                            :leaf-types='["attribute","tag"]'
                                            @on-select="(selectedNode)=>{selectedValue=selectedNode; OnTargetSelectAsync(aMapping);}"
                                        ></tree-picker></span>
                                    </div>
                                    <div class="col-10 px-4">
                                        Target
                                        <span v-if="aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute!=null">
                                            : <br/><i>"{{aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute.partOf.displayName}} / {{aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute.displayName}}"</i>
                                        </span>
                                    </div>
                                    <div class="col-1 pt-3">
                                        <button class="btn btn-sm btn-info float-end" @click="SaveMappingAsync(aMapping)" data-toggle="tooltip" title="Save setting configuration.">
                                            <i class="fa-regular fa-floppy-disk fa-lg" style="color:tiq-primary;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-secondary float-end" @click="RemoveButtonAsync(aButton)"><i class="fa-solid fa-trash-xmark me-2"></i>remove mapping</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>

<script>
    var WinDoc = window.document;
    
    var app = createApp({
        // el: "#app",
        data() {
            return {
                moment: moment,
                pageTitle: "Manage Flic Buttons and Events",
                context:<?php echo json_encode($context)?>,
                user:<?php echo json_encode($user)?>,
                showWaitIndicator: false,
                buttons: [],
                flicManagerId: 0,
                flicButtonTypeId: 0,
                selectedValue: null
            }
        },
        mounted: async function () {
            WinDoc.title = this.pageTitle;

            this.showWaitIndicator = true;
            
            await this.GetFlicEventsAsync();
            await this.GetFlicButtonsAsync();
            
            this.showWaitIndicator = false;
        },
        methods: {
            ResetMapping: function(aMapping){
                aMapping.attributes.find(x=>x.displayName=='Target').referencedNodeId = null;
                aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute = null;

                aMapping.attributes.find(x=>x.displayName=='Argument').stringValue = null;

                aMapping.attributes.find(x=>x.displayName=='Flic Mode').enumerationValue = null;
                aMapping.attributes.find(x=>x.displayName=='Flic Mode').enumerationName = null;
            },
            SaveMappingAsync: async function(aMapping){
                
                this.showWaitIndicator = true;
                
                let query = `
                    mutation m1 {
                        m1: updateAttribute(input: { id: "${aMapping.attributes.find(x=>x.displayName=='Target').id}", patch: { referencedNodeId: "${aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute.id}" } }) {
                            clientMutationId
                        }
                        m2: updateAttribute(input: { id: "${aMapping.attributes.find(x=>x.displayName=='Argument').id}", patch: { stringValue: "${aMapping.attributes.find(x=>x.displayName=='Argument').stringValue}" } }) {
                            clientMutationId
                        }
                        m3: updateAttribute(input: { id: "${aMapping.attributes.find(x=>x.displayName=='Flic Mode').id}", patch: { enumerationValue: "${aMapping.attributes.find(x=>x.displayName=='Flic Mode').enumerationValue}" } }) {
                            clientMutationId
                        }
                    }
                `;
                let aResponse = await tiqJSHelper.invokeGraphQLAsync(query);
                
                this.showWaitIndicator = false;
            },
            OnTargetSelectAsync: async function(aMapping){
                console.log(aMapping);
                console.log(this.selectedValue);
                // let query = `
                //     mutation m1 {
                //         m1: updateAttribute(input: { id: "${aMapping.attributes.find(x=>x.displayName=='Target').id}", patch: { referencedNodeId: "${this.selectedValue.id}" } }) {
                //             clientMutationId
                //         }
                //     }
                // `;
                // let aResponse = await tiqJSHelper.invokeGraphQLAsync(query);

                // await this.GetFlicButtonsAsync();
                if(aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute == null){
                    aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute = {};
                }
                aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute.id = this.selectedValue.id;
                aMapping.attributes.find(x=>x.displayName=='Target').referencedAttribute.displayName = this.selectedValue.display_name;
            },
            RemoveButtonAsync: async function(aButton){
                
                this.showWaitIndicator = true;

                let query = `
                    mutation m1 {
                        deleteObject(input:{ id:"${aButton.buttonMapping.id}"}) {
                            clientMutationId
                        }
                    }
                `;
                let aResponse = await tiqJSHelper.invokeGraphQLAsync(query);

                await this.GetFlicButtonsAsync();

                this.showWaitIndicator = false;

            },

            AddButtonAsync: async function(aButton){
                
                this.showWaitIndicator = true;

                if(this.flicButtonTypeId==0){
                    await this.GetFlicButtonTypeAsync();
                }
                
                let query = `
                    mutation m1 {
                        createObject(
                            input: {
                                object: {
                                    displayName: "${aButton.name} (${aButton.bdaddr})"
                                    typeId: "${this.flicButtonTypeId}"
                                    partOfId: "${this.flicManagerId}"
                                }
                            }
                        ) {
                            clientMutationId
                            object {
                                id
                            }
                        }
                    }
                `;
                let aResponse = await tiqJSHelper.invokeGraphQLAsync(query);

                query = `
                    query q1 {
                        buttons: objects(condition: {id: "${aResponse.data.createObject.object.id}"}){
                            id
                            displayName
                            attributes{
                                id
                                displayName
                            }
                            mappings: childObjects{
                                id
                                displayName
                                attributes{
                                    id
                                    displayName
                                }
                            }
                        }
                    }
                `;
                aResponse = await tiqJSHelper.invokeGraphQLAsync(query);

                query = `
                    mutation m1 {
                        mutate_mac: updateAttribute(input: { id: "${aResponse.data.buttons[0].attributes.find(x=>x.displayName=="mac address").id}", patch: { stringValue: "${aButton.bdaddr}" } }) {
                            clientMutationId
                        }
                        mutate_name: updateAttribute(input: { id: "${aResponse.data.buttons[0].attributes.find(x=>x.displayName=="Name").id}", patch: { stringValue: "${aButton.name}" } }) {
                            clientMutationId
                        }
                        mutate_enum1: updateAttribute(input: { id: "${aResponse.data.buttons[0].mappings.find(x=>x.displayName=="Single Click Settings").attributes.find(x=>x.displayName=="Click Event Type").id}", patch: { enumerationValue: "1" } }) {
                            clientMutationId
                        }
                        mutate_enum2: updateAttribute(input: { id: "${aResponse.data.buttons[0].mappings.find(x=>x.displayName=="Double Click Settings").attributes.find(x=>x.displayName=="Click Event Type").id}", patch: { enumerationValue: "2" } }) {
                            clientMutationId
                        }
                        mutate_enum3: updateAttribute(input: { id: "${aResponse.data.buttons[0].mappings.find(x=>x.displayName=="Click and Hold Settings").attributes.find(x=>x.displayName=="Click Event Type").id}", patch: { enumerationValue: "3" } }) {
                            clientMutationId
                        }
                        mutate_importance1: updateObject(input: { id: "${aResponse.data.buttons[0].mappings.find(x=>x.displayName=="Single Click Settings").id}", patch: { importance: 10 } }) {
                            clientMutationId
                        }
                        mutate_importance2: updateObject(input: { id: "${aResponse.data.buttons[0].mappings.find(x=>x.displayName=="Double Click Settings").id}", patch: { importance: 20 } }) {
                            clientMutationId
                        }
                        mutate_importance3: updateObject(input: { id: "${aResponse.data.buttons[0].mappings.find(x=>x.displayName=="Click and Hold Settings").id}", patch: { importance: 30 } }) {
                            clientMutationId
                        }
                    }
                `;
                aResponse = await tiqJSHelper.invokeGraphQLAsync(query);

                await this.GetFlicButtonsAsync();

                this.showWaitIndicator = false;

            },
            GetFlicButtonTypeAsync: async function(){
                let query = `
                    query q1 {
                        tiqTypes(condition: { displayName: "Flic Button" }) {
                            id
                        }
                    }
                `;
                let aResponse = await tiqJSHelper.invokeGraphQLAsync(query);
                this.flicButtonTypeId = aResponse.data.tiqTypes[0].id;
            },
            GetFlicButtonsAsync: async function(){
                let query = `
query q1 {
  tiqTypes(condition: { displayName: "Flic Receiver" }) {
    id
    objectsByTypeId {
      id
      displayName
      relativeName
      buttons: childObjects {
        id
        displayName
        relativeName
        attributes {
          id
          displayName
          relativeName
          stringValue
          currentValue {
            timestamp
            value
          }
        }
        mappings: childObjects {
          id
          displayName
          relativeName
          attributes {
            id
            displayName
            relativeName
            stringValue
            enumerationValue
            enumerationName
            enumerationValues
            enumerationType {
              enumerationNames
            }
            referencedAttribute {
              id
              displayName
              relativeName
              dataType
              enumerationValue
              enumerationName
              enumerationType {
                enumerationNames
              }
              currentValue {
                timestamp
                value
              }
              partOf{
                displayName
              }
            }
          }
        }
      }
    }
  }
}

                `;

                let aResponse = await tiqJSHelper.invokeGraphQLAsync(query);
                let buttons = aResponse.data.tiqTypes[0].objectsByTypeId[0].buttons;
                this.buttons.forEach(aButton=>{
                    aButton.buttonMapping=null;
                });
                for(let i=0; i<buttons.length; i++){
                    const aButton = buttons[i];
                    let aPairedButton = this.buttons.find(x=>x.bdaddr==aButton.attributes.find(x=>x.displayName=="mac address").stringValue);
                    if(aPairedButton){
                        // validate name has not changed
                        if(aPairedButton.name != aButton.attributes.find(x=>x.displayName=="Name").stringValue){
                            query = `
                                mutation m1 {
                                    mutate_displayName: updateObject(input: { id: "${aButton.id}", patch: { displayName: "${aPairedButton.name} (${aPairedButton.bdaddr})" } }) {
                                        clientMutationId
                                    }
                                    mutate_name: updateAttribute(input: { id: "${aButton.attributes.find(x=>x.displayName=="Name").id}", patch: { stringValue: "${aPairedButton.name}" } }) {
                                        clientMutationId
                                    }

                                }
                            `;
                            aResponse = await tiqJSHelper.invokeGraphQLAsync(query);

                        }
                        aPairedButton.buttonMapping = aButton;
                    }
                }
            },
            GetFlicEventsAsync: async function () {
                let startDate = moment().add(-30,'day');
                let endDate = moment();
                let query = `
                    query q1 {
                        tiqTypes(condition: { displayName: "Flic Receiver" }) {
                            id
                            objectsByTypeId {
                                id
                                displayName
                                attributes {
                                    id
                                    displayName
                                    getTimeSeries(startTime: "${startDate.toISOString()}", endTime: "${endDate.toISOString()}") {
                                        ts
                                        objectvalue
                                    }
                                }
                            }
                        }
                    }
                `;

                let aResponse = await tiqJSHelper.invokeGraphQLAsync(query);
                this.flicManagerId = aResponse.data.tiqTypes[0].objectsByTypeId[0].id;
                let events = aResponse.data.tiqTypes[0].objectsByTypeId[0].attributes[0].getTimeSeries.reverse();
                let buttons = [];
                events.forEach((aEvent,i) => {
                    let aFlicMessage = JSON.parse(aEvent.objectvalue);
                    if(!buttons.find(x=>x.bdaddr == aFlicMessage.bdaddr)){
                        aFlicMessage.button.events=[];
                        aFlicMessage.button.mapping = null;
                        buttons.push(aFlicMessage.button)
                    }
                    let aButton = buttons.find(x=>x.bdaddr==aFlicMessage.bdaddr);
                    let aButtonPressEvent = JSON.parse(aEvent.objectvalue);
                    aButtonPressEvent.ts = moment(aEvent.ts);
                    aButtonPressEvent.i = i+1;
                    delete aButtonPressEvent.button;
                    aButton.events.push(aButtonPressEvent);
                });
                this.buttons = buttons.sort((a,b)=>a.name>b.name?1:-1);
            }
        },
    })
    .mount('#app');
</script>
