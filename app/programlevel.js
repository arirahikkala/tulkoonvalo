(function() {
    var root = this;
    var ProgramSlider = {};

    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = ProgramSlider;
	}
	exports.ProgramSlider = ProgramSlider;
    } else {
	root['ProgramSlider'] = ProgramSlider;
    }

    ProgramSlider.ProgramSlider = Backbone.RelationalModel.extend ({

	defaults: function() {
	  return {
	  	id: null,
	  	target_id: 1, // TODO: Get this from tree
	  	group: 1,
	  	light_detector: false,
	  	motion_detector: false,
	  	light_level: 0,
	  	motion_level: 0,
	  	new_level: true,
	  	allow_delete: false,
	  }
	},

	});

  ProgramSlider.ProgramSliderView = Backbone.View.extend ({
	tagName: "div",
	className: "programslider-item",

	events: {
		"click #level-item-remove" : function() {
			$("#levelGroup #groupsPopup").hide();
			console.log("BFE#F", $("#lightGroups"));
			this.$("#levelLightGroups").empty();
			console.log("AFTER", $("#lightGroups"));
			if (! this.model.get("new_level"))  {
				var choice = confirm("Haluatko varmasti poistaa ajan?");
				if (choice) {
					this.model.set("allow_delete", true)			
					this.remove();
				}
			}
			else this.model.destroy();
		},
		
		// Slider values changed
		"change #levelGroupInput" : function() { this.model.set("target_id", this.$("#levelGroupInput").val() ); },
		"slidechange #light-slider" : function () { this.setSliderValues(); },
		"slidechange #motion-slider" : function () { this.setSliderValues(); },
	
		// Checkboxes clicked
		"click #light-enabled" : function (event) { this.model.set("light_detector", event.target.checked); },
		"click #motion-enabled" : function (event) { this.model.set("motion_detector", event.target.checked); },
	
		"click #levelGroupInput" : function () {
	    $("#levelGroup #groupsPopup").hide();
			this.$("#groupsPopup").show();

			/*			
			var tree =jQuery.jstree._instance(1, $("levelLightGroupsContainer"));
			console.log( tree);
	    this.$("#levelLightGroupsContainer").append($("#lightGroups"));

	    //$.get("../server2/lightsTree/");
	    //var ref = $.jstree._reference(this.$("#levelLightGroups"));
	    this.$("#lightGroups").show();
	    //this.$("#levelLightGroupsContainer").append(this.$("#levelLightGroups"));
		*/
		},
		
		"click #groupsPopupClose" : function() { this.$("#groupsPopup").hide(); },
		"click #motion-enabled" : function(event) { this.$("#motion-slider").slider({disabled: ! event.target.checked}); },
		
		// TODO: Check that it's a group (here and server)
		// TODO: Maybe show only groups here? (Own tree?)
		// Get selected node ID and put the group name in
		"click #levelLightGroups a" : function() {
			this.model.set("target_id", this.$("#levelLightGroups").jstree('get_selected').attr('id'));
			this.$("#levelGroupInput").attr({"value": $('.jstree-clicked').text().substr(1)});
			this.$("#groupsPopup").hide();
		},
	},

	initialize: function() {
			this.model.bind("change:errors", function() { console.log( "asd",this.model.get("errors") ); this.drawErrors(); }, this );
	    this.model.bind ("remove", this.remove, this);
	    this.model.bind ("change:name", this.render, this);
	    this.render();
	    
	    // Used for showing error messages in the right place
	    this.model.set("cid", this.model.cid);
	    
			var testTree = 
				[{
					"data" : "Yritys", "attr" : { id : 6 }, "children" : 
	        	[{ "data" : "Aula", "attr" : { id : 3 }, "children" :
	        		[{"data" : "Aula Etu", "attr" : { id : 1 }},
	        		 {"data" : "Aula Taka", "attr" : { id : 2 }},
	        		]
	        	},
						{"data" : "Eteinen", "attr" : { id : 4 }, "children":
	        		[{"data" : "Ulkovalo", "attr" : { id : 5 }}
	        		]}

	        	]
	      },]
	    
      this.tree = this.$("#levelLightGroups").jstree ({
	  		"json_data": {
	      		// the url from which jstree loads its information
	      		// (note that updating to the server is done "out of view" from jstree, in callbacks below)
	      		"url": { "url": "../server2/lightsTree" },
	      		"data": function(n) {console.log(n);
	      			return { id : n.attr ? n.attr("id") : 0 };
	      		},
	      		
						"data"  : [testTree],
	      	
	  		},
	  		// allow selecting groups and their members independently
	  		"checkbox": {
  	    	"two_state": "false",
  	    },
	  		// note T4, T5, T6; this is currently a no-op
	  		"types": {
	      	"light": { max_children: 0 }
	  		},
	  		"plugins": ["themes", "json_data", "ui", "dnd", "crrm", "types"]
	  	})
	},
	
	drawErrors: function() {
		var errors = this.model.get("errors");
		if (errors) {
			for (var i in errors)
				this.$("#programsErrorLevel").append(this.model.collection.getErrorMessage(errors[i])+"<br />");
		}
		else
			this.$("#programsErrorLevel").html("");
	},
	
	setSliderValues: function() {
		this.model.set("light_level", this.$("#light-slider").slider("value"));
		this.model.set("motion_level", this.$("#motion-slider").slider("value"));
		this.$("#light-slider #ui-slider-handle-value").html(this.model.get("light_level"));
		this.$("#motion-slider #ui-slider-handle-value").html(this.model.get("motion_level"));
	},
	
	template: _.template("<div class='programError' id='programsErrorLevel'></div>\
	<input id='level-item-remove' type='button' value='Poista ryhmä'>\
	<div id='levelGroup'>\
		Ryhmä:<input id='levelGroupInput'><br/>\
		<div id='groupsPopup'>\
			<input id='groupsPopupClose' type='button' value='Sulje'>\
			<div id='levelLightGroups'></div>\
			<div id='levelLightGroupsContainer'></div>\
		</div>\
	</div>\
	<table id='levelSettings'>\
		<tr>\
			<td><div class='program-slider' id='light-slider' /></td>\
			<td><div class='program-slider' id='motion-slider' /></td>\
			<td>\
				<input id='light-enabled' type='checkbox'>Käytä valosensoria<br />\
				<input id='motion-enabled' type='checkbox'>Käytä liiketunnistinta\
			</td>\
		</tr>\
	</table><br/><br/><br/><br/>"),
	
	render: function() {
	    this.$el.html(this.template());

	    this.$("#light-slider").slider({ orientation: "vertical" });
 	    this.$("#motion-slider").slider({ orientation: "vertical" });
			
			// TODO: See license on jquery.ui.touch-punch.min.js library
			this.$("#light-slider").draggable();
			this.$("#motion-slider").draggable();
			
			// This didn't work above
			this.$("#motion-slider").slider({disabled: true});

 	    this.$("#light-enabled").attr("checked", this.model.get("light_detector")==0?false:true);
 	    this.$("#motion-enabled").attr("checked", this.model.get("motion_detector")==0?false:true);
	    
	    this.$("#light-slider .ui-slider-handle").append("<div id='ui-slider-handle-value'></div>");
	    this.$("#motion-slider .ui-slider-handle").append("<div id='ui-slider-handle-value'></div>");
  
  		this.setSliderValues();
	    
	    return this;
	},
	
  })
    
}).call(this);
