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
		"slidechange #light-slider" : function () { this.model.set("light_level", this.$("#light-slider").slider("value")); },
		"slidechange #motion-slider" : function () { this.model.set("motion_level", this.$("#motion-slider").slider("value")); },
	
		// Checkboxes clicked
		"click #light-enabled" : function (event) { this.model.set("light_detector", event.target.checked); },
		"click #motion-enabled" : function (event) { this.model.set("motion_detector", event.target.checked); },
	},

	initialize: function() {
			this.model.bind("change:errors", function() { console.log( "asd",this.model.get("errors") );this.drawErrors() }, this );
	    this.model.bind ("remove", this.remove, this);
	    this.model.bind ("change:name", this.render, this);
	    this.render();
	    
	    // Used for showing error messages in the right place
	    this.model.set("cid", this.model.cid);
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
	
	template: _.template("<div class='programError' id='programsErrorLevel'></div>\
	<input id='level-item-remove' type='button' value='Poista ryhmä'>\
	Käytä valosensoria<input id='light-enabled' type='checkbox'>\
		Käytä liiketunnistinta<input id='motion-enabled' type='checkbox'>\
		<div class='program-slider' id='light-slider' />\
		<div class='program-slider' id='motion-slider' />"),
	
	render: function() {
	    this.$el.html (this.template() );
	    this.$("#light-slider").slider({ orientation: "vertical", value: this.model.get("light_level") });
 	    this.$("#motion-slider").slider({ orientation: "vertical", value: this.model.get("motion_level") });

 	    this.$("#light-enabled").attr("checked", this.model.get("light_detector")!=0?true:false);
 	    this.$("#motion-enabled").attr("checked", this.model.get("motion_detector")!=0?true:false);
	    return this;
	},
	
  })
    
}).call(this);
