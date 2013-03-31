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
	  	target_id: null,
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
			if (! this.model.get("new_level"))  {
				var choice = confirm("Haluatko varmasti poistaa ryhmän?");
				if (choice) {
					this.model.set("allow_delete", true)			
					this.remove();
				}
			}
			else { this.model.destroy(); this.remove(); }
		},
		
		// Slider values changed
		"change #levelGroupInput" : function() { this.model.set("target_id", this.$("#levelGroupInput").val() ); },
		"slidechange #light-slider" : function () { this.setSliderValues(); },
		"slidechange #motion-slider" : function () { this.setSliderValues(); },
	
		// Checkboxes clicked
		"click #light-enabled" : function (event) { this.model.set("light_detector", event.target.checked); console.log(this.model.get("light_detector"));},
		"click #motion-enabled" : function(event) {
			// Set slider 0 if checkbox not ticked
			this.$("#motion-slider").slider({disabled: ! event.target.checked, value: event.target.checked?this.model.get("motion_level"):0 });
			this.model.set("motion_detector", event.target.checked);
		},
		
		// Functionality for the tree popup
		"click #levelGroupInput" : function () {
	    $("#levelGroup #groupsPopup").hide();
			this.$("#groupsPopup").show();
		},
		
		// Click popup close button
		"click #groupsPopupClose" : function() { this.$("#groupsPopup").hide(); },
		
		// Get selected treee node ID and put the group name in the input
		"click #levelLightGroups a" : function() {
			this.model.set("target_id", this.$("#levelLightGroups").jstree('get_selected').attr('id'));
			this.$("#levelGroupInput").attr({"value": $('.jstree-clicked').text().substr(1)});
			this.$("#groupsPopup").hide();
		},
	},

	initialize: function() {
   		this.model.bind("remove", function() { this.remove(); }, this);
			this.model.bind("change:errors", function() { this.drawErrors(); }, this);
	    this.render();
	    
	    // Used for showing error messages in the right place
	    this.model.set("cid", this.model.cid);
	    
      this.tree = this.$("#levelLightGroups").jstree ({
	  		"json_data": {
            "ajax": {
                "url": "../server2/groupsTree/1",
            }
	  		},
	  		// TODO: Group icons and others here too
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
		Ryhmä:<input id='levelGroupInput' readonly='readonly'><br/>\
		<div id='groupsPopup'>\
			<input id='groupsPopupClose' type='button' value='Sulje'>\
			<div id='levelLightGroups'></div>\
		</div>\
	</div>\
	<table id='levelSettings'>\
		<tr>\
			<td><div class='program-slider' id='light-slider' /></td>\
			<td><div class='program-slider' id='motion-slider' /></td>\
			<td>\
				<input id='light-enabled' type='checkbox'>Käytä valosensoria<br />\
				<input id='motion-enabled' type='checkbox'>Käytä liiketunnistinta<br />\
			</td>\
		</tr>\
	</table><br/><br/><br/><br/>"),
	
	render: function() {
	    this.$el.html(this.template());
	    
	    this.$("#light-slider").slider({ orientation: "vertical", value: this.model.get("light_level") });
 	    this.$("#motion-slider").slider({ orientation: "vertical", value: this.model.get("motion_level") });
			
			// TODO: See license on jquery.ui.touch-punch.min.js library
			this.$("#light-slider").draggable();
			this.$("#motion-slider").draggable();
			this.$("#motion-slider").slider({disabled: this.model.get("motion_detector")==0?true:false});

 	    this.$("#light-enabled").attr("checked", this.model.get("light_detector")==0?false:true);
 	    this.$("#motion-enabled").attr("checked", this.model.get("motion_detector")==0?false:true);
	    
	    this.$("#light-slider .ui-slider-handle").append("<div id='ui-slider-handle-value'></div>");
	    this.$("#motion-slider .ui-slider-handle").append("<div id='ui-slider-handle-value'></div>");
  
  		this.setSliderValues();
	    
	    return this;
	},
	
  })
    
}).call(this);
