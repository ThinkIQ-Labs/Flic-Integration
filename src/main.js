const authenticator = {
	"graphQlEndpoint": "https://xxx.xxx.thinkiq.net/graphql",
	"clientId": "ThinkIQ.GraphQL.xxx",
	"clientSecret": "xxx-xxx-xxx-xxx-xxx",
	"role": "xxx_ro_group",
	"userName": "ThinkIQ.GraphQL.xxx",
};

var buttonManager = require("buttons");
var http = require("http");
var token = "";
var messagesAttrId = 0;
var mappingObject = null;
var expEpoch = 0;

const authRequestQuery = 'mutation authRequest {authenticationRequest(input: {authenticator: "' + authenticator.clientId + 
						'", role: "' + authenticator.role + '", userName: "' + authenticator.userName + '"}) {jwtRequest {challenge message}}}';
//console.log(authRequestQuery);

function getAuthValidationQuery(challenge, authenticator) {
	var query = 'mutation authValidation {authenticationValidation(input: {authenticator: "' + authenticator.clientId + 
			'", signedChallenge: "' + challenge + '|' + authenticator.clientSecret + '"}) {jwtClaim}}';
	return query;
}

const getReceiverQuery = 'query q1 {tiqTypes(condition: { displayName: "Flic Receiver" }) { id objectsByTypeId { id displayName attributes { id displayName }}}}';

function getTargetAttrIdQuery(obj) {
	var objName = obj.isSingleClick ? 'Single Click Settings' : obj.isDoubleClick ? 'Double Click Settings' : 'Click and Hold Settings';
	//console.log(attrName);
	// query = 'query q2 { attributes( filter: { displayName: { equalTo: "mac address" } and: { stringValue: { equalTo: "' + obj.bdaddr + 
	// 	'" } } } ) { onObject { attributes(condition: { displayName: "' + attrName + '" }) { referencedAttribute { id }}}}}';

	var query = " \
query q2 {     \
  attributes(     \
    filter: {     																\
	  displayName: { equalTo: \"mac address\" }     							\
      and: { stringValue: { equalTo: \"" + obj.bdaddr + "\" } }     			\
    }     																		\
  ) {     																		\
    onObject {     																\
      displayName     															\
		childObjects(condition: { displayName: \"" + objName + "\" }) {   \
        displayName																\
        attributes {															\
          displayName															\
          stringValue															\
          enumerationName														\
          referencedAttribute {													\
            id																	\
            dataType															\
            enumerationValue													\
            enumerationValues													\
            currentValue {														\
              value																\
            }																	\
          }																		\
        }																		\
      }																			\
    }																			\
  }																				\
}																				\
	";

	//console.log(query);
	return query;
}

function getSendMessageQuery(aPayload) {

	var query = 'mutation m1 { replaceTimeSeriesRange( input: { attributeOrTagId: "' + messagesAttrId + 
			'" entries: [{ value: ' + JSON.stringify(JSON.stringify(aPayload)) + ', timestamp: "' + (new Date()).toISOString() + 
			'", status: "0" }]}) {clientMutationId json}}';
	if (mappingObject == null) {
		// use simple query
	} else {
		// deal with nature of mapping object

		var targetAttrId = null;
		var targetAttrDataType = null;
		var targetAttrEnumValue = null;
		var targetAttrEnumValues = [];
		var targetAttrCurrentValue = null;
		var flicMode = null;
		var argument = null;
		for(var i=0; i<data.attributes[0].onObject.childObjects[0].attributes.length; i++){
			const attr = data.attributes[0].onObject.childObjects[0].attributes[i];
			switch (attr.displayName){
				case "Target":
					if (attr.referencedAttribute != null) {
						// there is a referenced object
						targetAttrId = attr.referencedAttribute.id;
						console.log("Target ID: " + targetAttrId);
						targetAttrDataType = attr.referencedAttribute.dataType;
						console.log("Target Data Type: " + targetAttrDataType);
						targetAttrEnumValue = attr.referencedAttribute.enumerationValue;
						console.log("Target Enum Value: " + targetAttrEnumValue);
						targetAttrEnumValues = attr.referencedAttribute.enumerationValues;
						console.log("Target Enum Values: " + JSON.stringify(targetAttrEnumValues));
						if(attr.referencedAttribute.currentValue != null){
							targetAttrCurrentValue = attr.referencedAttribute.currentValue.value;
							console.log("Target Current Value: " + targetAttrCurrentValue);
						}
					}
					break;
				case "Flic Mode":
					if (attr.enumerationName != null) {
						// there is a referenced object
						flicMode = attr.enumerationName;
						console.log("Flic Mode: " + flicMode);
					}
					break;
				case "Argument":
					if (attr.stringValue != null) {
						// there is a referenced object
						argument = attr.stringValue;
						console.log("Argument: " + argument);
					}
					break;
				default:
					break;
			}
			
		}
		
		if(targetAttrId != null){
			switch (flicMode){
				case null:
					// use simple query
					break;

				case "Write Value":
					if(targetAttrDataType=='ENUMERATION'){
						var newValue = targetAttrEnumValues[0];
						if(targetAttrEnumValue!=null){
							newValue = targetAttrEnumValue;
						}
						console.log("New Value: " + newValue);
						query = 'mutation m1 { m1: replaceTimeSeriesRange( input: { attributeOrTagId: "' + messagesAttrId + 
							'" entries: [{ value: ' + JSON.stringify(JSON.stringify(aPayload)) + ', timestamp: "' + (new Date()).toISOString() + 
							'", status: "0" }]}) {clientMutationId json} m2: replaceTimeSeriesRange( input: { attributeOrTagId: "' + targetAttrId + 
							'" entries: [{ value: "' + newValue + '", timestamp: "' + (new Date()).toISOString() + '", status: "0" }]}) {clientMutationId json}}';
					} else {
						var newValue = argument;
						console.log("New Value: " + newValue);
						query = 'mutation m1 { m1: replaceTimeSeriesRange( input: { attributeOrTagId: "' + messagesAttrId + 
							'" entries: [{ value: ' + JSON.stringify(JSON.stringify(aPayload)) + ', timestamp: "' + (new Date()).toISOString() + 
							'", status: "0" }]}) {clientMutationId json} m2: replaceTimeSeriesRange( input: { attributeOrTagId: "' + targetAttrId + 
							'" entries: [{ value: "' + newValue + '", timestamp: "' + (new Date()).toISOString() + '", status: "0" }]}) {clientMutationId json}}';
					}
					break;
					
				case "Add Value":
					if(targetAttrDataType=='ENUMERATION'){
						var newValue = targetAttrEnumValues[0];
						if(targetAttrEnumValue!=null){
							var arrayIndex = 0;
							while(targetAttrEnumValues[arrayIndex]!=targetAttrEnumValue && arrayIndex<10){
								arrayIndex++;
							}
							//newValue = targetAttrEnumValues[(arrayIndex + Number(argument)) % targetAttrEnumValues.length];
							newValue = targetAttrEnumValues[(arrayIndex + Number(argument) + targetAttrEnumValues.length) % targetAttrEnumValues.length];
						}
						console.log("New Value: " + newValue);
						query = 'mutation m1 { m1: replaceTimeSeriesRange( input: { attributeOrTagId: "' + messagesAttrId + 
							'" entries: [{ value: ' + JSON.stringify(JSON.stringify(aPayload)) + ', timestamp: "' + (new Date()).toISOString() + 
							'", status: "0" }]}) {clientMutationId json} m2: updateAttribute(input: { id: "' + targetAttrId + 
							'", patch: { enumerationValue: "' + newValue + '" } }) {clientMutationId attribute { enumerationValue }}}';
						//console.log(query);
					} else {
						// this only works if the target and arguments are numeric
						var newValue = Number(targetAttrCurrentValue) + Number(argument);
						console.log("New Value: " + newValue);
						query = 'mutation m1 { m1: replaceTimeSeriesRange( input: { attributeOrTagId: "' + messagesAttrId + 
							'" entries: [{ value: ' + JSON.stringify(JSON.stringify(aPayload)) + ', timestamp: "' + (new Date()).toISOString() + 
							'", status: "0" }]}) {clientMutationId json} m2: replaceTimeSeriesRange( input: { attributeOrTagId: "' + targetAttrId + 
							'" entries: [{ value: "' + newValue + '", timestamp: "' + (new Date()).toISOString() + '", status: "0" }]}) {clientMutationId json}}';
					}
					break;
					
				case "Negate Value":
					// assuming the target is a boolean
					var newValue = true;
					if(Boolean(targetAttrCurrentValue)){
						newValue = !JSON.parse(targetAttrCurrentValue);
					}
					console.log("New Value: " + newValue);
					query = 'mutation m1 { m1: replaceTimeSeriesRange( input: { attributeOrTagId: "' + messagesAttrId + 
						'" entries: [{ value: ' + JSON.stringify(JSON.stringify(aPayload)) + ', timestamp: "' + (new Date()).toISOString() + 
						'", status: "0" }]}) {clientMutationId json} m2: replaceTimeSeriesRange( input: { attributeOrTagId: "' + targetAttrId + 
						'" entries: [{ value: "' + newValue + '", timestamp: "' + (new Date()).toISOString() + '", status: "0" }]}) {clientMutationId json}}';
					break;
					
				default:
					break;
					
			}
		}

	}
	//console.log(query);
	return query;
}

function isTokenValid() {
	//console.log("isTokenExpired");
	if (token == "") return false;

	//console.log(Math.floor(new Date().getTime() / 1000), expEpoch);
	return Math.floor(new Date().getTime() / 1000) < expEpoch;
}

function withValidToken(callback) {
	//console.log("withValidToken");
	if (isTokenValid(token)) {
		callback()
	} else {
		http.makeRequest({
			url: authenticator.graphQlEndpoint,
			method: "POST",
			headers: { "Content-Type": "application/json" },
			content: JSON.stringify({ query: authRequestQuery }),
		}, function (err, res) {
			var content = JSON.parse(res.content);
			var challenge = content.data.authenticationRequest.jwtRequest.challenge;
			//console.log(challenge);

			http.makeRequest({
				url: authenticator.graphQlEndpoint,
				method: "POST",
				headers: { "Content-Type": "application/json" },
				content: JSON.stringify({ query: getAuthValidationQuery(challenge, authenticator) }),
			}, function (err, res) {
				var content = JSON.parse(res.content);
				token = content.data.authenticationValidation.jwtClaim;
				const arrayToken = token.split('.');
				expEpoch = JSON.parse(new TextDecoder().decode(Duktape.dec('base64', arrayToken[1]))).exp;

				callback()
			})
		})
	}
}

function withAttributeID(callback) {
	//console.log("withAttributeID");
	if (messagesAttrId != 0) {
		callback()
	} else {
		withValidToken(function () {
			http.makeRequest({
				url: authenticator.graphQlEndpoint,
				method: "POST",
				headers: { "Content-Type": "application/json", "Authorization": "Bearer " + token },
				content: JSON.stringify({ query: getReceiverQuery }),
			}, function (err, res) {
				var content = JSON.parse(res.content);
				messagesAttrId = content.data.tiqTypes[0].objectsByTypeId[0].attributes[0].id;
				//console.log(JSON.stringify(messagesAttrId));

				callback()
			})
		})
	}
}

function withTokenAndAttributeID(callback) {
	//console.log("withTokenAndAttributeID");
	withValidToken(function () {
		withAttributeID(callback)
	})
}

function postMessage(obj) {
	//console.log("postMessage");
	withTokenAndAttributeID(function () {
		http.makeRequest({
			url: authenticator.graphQlEndpoint,
			method: "POST",
			headers: { "Content-Type": "application/json", "Authorization": "Bearer " + token },
			content: JSON.stringify({ query: getTargetAttrIdQuery(obj) }),
		}, function (err, res) {
			var content = JSON.parse(res.content);
			data = content.data;
			//console.log(JSON.stringify(data));
			mappingObject = null;
			//console.log(JSON.stringify(data));
			if (data.attributes.length > 0) {
				// there is a mapping for this button
				if (data.attributes[0].onObject.childObjects.length > 0) {
					// there is a mapping for this click event
					mappingObject = data.attributes[0].onObject.childObjects[0];
				}
			}
			//console.log(JSON.stringify(obj));
			http.makeRequest({
				url: authenticator.graphQlEndpoint,
				method: "POST",
				headers: { "Content-Type": "application/json", "Authorization": "Bearer " + token },
				content: JSON.stringify({ query: getSendMessageQuery(obj) }),
			}, function (err, res) {
				var content = JSON.parse(res.content);
				data = content.data;
				//console.log(JSON.stringify(data));
			})
		})
	})
}

buttonManager.on("buttonSingleOrDoubleClickOrHold", function (obj) {
	//console.log(JSON.stringify(obj));
	var button = buttonManager.getButton(obj.bdaddr);
	obj.button = button;
	//message = obj;
	//console.log(JSON.stringify(button));
	var clickType = obj.isSingleClick ? "click" : obj.isDoubleClick ? "double_click" : "hold";

	postMessage(obj);

});