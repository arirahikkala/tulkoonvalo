(function() {
    var root = this;
    var Slider = {};

    // export the name Slider, either using CommonJS modules or just assigning
    // it to the root object directly; copied from underscore.js
    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = Slider;
	}
	exports.Slider = Slider;
    } else {
	root['Slider'] = Slider;
    }
    
 

    // attributes: name (string), brightness (float)
    Slider.Slider = Backbone.Model.extend({
	defaults: function() {
	    return {
		name: "unnamed",
		brightness: 0
	    };
	}

	
    });

    Slider.SliderView = Backbone.View.extend({
	tagName: "div",

	className: "slider",

	widget: {},
	$widget: {},

	template: _.template("<div class='slider'>\
<%= name %>\
<div class='slider-widget' id='<%= id %>' />\
</div>"),

	events: {
	    "slide .slider-widget" : "updateFromUI",
	},

	initialize: function() {
	    this.render();
	},

	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name'), 
					   id: this.options.idAttr}));
	    $widget = $("#"+this.options.idAttr)
	    widget = $widget.slider();
	    return this;
	},
	
	updateFromUI: function() {
	    this.model.set("brightness", $widget.slider("option", "value"));
	}
    });
}).call(this);
