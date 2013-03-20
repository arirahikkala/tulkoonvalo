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
	  }
	},

	});

  ProgramSlider.ProgramSliderView = Backbone.View.extend ({
	tagName: "div",
	className: "programslider-item",

	events: {
		// Slider values changed
		"slidechange #light-slider" : function () { this.model.set("light_level", this.$("#light-slider").slider("value")); },
		"slidechange #motion-slider" : function () { this.model.set("motion_level", this.$("#motion-slider").slider("value")); },
	
		// Checkboxes clicked
		"click #light-enabled" : function (event) { this.model.set("light_detector", event.target.checked); },
		"click #motion-enabled" : function (event) { this.model.set("motion_detector", event.target.checked); },
	},

	initialize: function() {
	    this.model.bind ("remove", this.remove, this);
	    this.model.bind ("change:name", this.render, this);
	    this.render();
	    
	    // Used for showing error messages in the right place
	    this.model.set("cid", this.model.cid);
	},
	
	template: _.template("Käytä valosensoria<input id='light-enabled' type='checkbox'>\
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
